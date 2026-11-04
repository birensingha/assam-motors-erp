<?php
declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * AM235 — Read-only Parts Purchase reconciliation.
 * Aggregates every active PART purchase by JC + Part / STOCK + Part.
 * No database writes, no invoices or stock mutations.
 */
final class AdminPurchaseReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active && $actor->isAdmin(),403);

        $filters=$request->validate([
            'q'=>['nullable','string','max:100'],
            'against'=>['nullable','in:ALL,JOB,STOCK'],
            'state'=>['nullable','in:ALL,LEDGER_REVIEW,PENDING_ISSUE,PARTIAL_ISSUE,DUE_BILLING,FITTING_REVIEW,INVOICE_LINKED,STOCK_MATCHED'],
            'from'=>['nullable','date'],
            'to'=>['nullable','date'],
            'page'=>['nullable','integer','min:1'],
        ]);
        $q=trim((string)($filters['q']??''));
        $against=$filters['against']??'ALL';
        $state=$filters['state']??'ALL';
        $from=(string)($filters['from']??'');
        $to=(string)($filters['to']??'');

        $base=DB::table('workshop_purchases as p')
            ->leftJoin('job_cards as j','j.id','=','p.job_card_id')
            ->leftJoin('parts_master as pm','pm.id','=','p.part_master_id')
            ->where('p.purchase_type','PART')
            ->whereRaw("UPPER(COALESCE(p.record_status,'ACTIVE')) <> 'DELETED'");
        if ($against==='JOB') $base->whereNotNull('p.job_card_id');
        if ($against==='STOCK') $base->whereNull('p.job_card_id');
        if ($from!=='') $base->whereDate('p.purchase_date','>=',$from);
        if ($to!=='') $base->whereDate('p.purchase_date','<=',$to);
        if ($q!=='') {
            $like='%'.$q.'%';
            $base->where(function($w)use($like) {
                $w->where('p.purchase_no','like',$like)
                  ->orWhere('p.purchase_batch_no','like',$like)
                  ->orWhere('p.supplier_invoice','like',$like)
                  ->orWhere('j.job_no','like',$like)
                  ->orWhere('j.vehicle_reg_no','like',$like)
                  ->orWhere('j.customer_name','like',$like)
                  ->orWhere('pm.part_code','like',$like)
                  ->orWhere('pm.part_name','like',$like);
            });
        }
        // Read-only safety cap; independently aggregating all-time activity for chosen keys.
        $limit=10000;
        $source=$base
            ->select(['p.id','p.purchase_no','p.purchase_batch_no','p.purchase_date',
                'p.job_card_id','p.part_master_id','p.qty','p.total_amount',
                'p.supplier_name','p.supplier_invoice','j.job_no','j.vehicle_reg_no',
                'j.customer_name','pm.part_name','pm.part_code','pm.stock_qty'])
            ->orderByDesc('p.purchase_date')->orderByDesc('p.id')
            ->limit($limit)->get();

        $rows=[];
        $partIds=[];
        $jobIds=[];
        foreach($source as $p) {
            $partId=(int)$p->part_master_id;
            if ($partId<=0) continue;
            $jobId=(int)($p->job_card_id??0);
            $key=$jobId.':'.$partId;
            if (!isset($rows[$key])) {
                $rows[$key]=[
                    'key'=>$key,'job_id'=>$jobId,'part_id'=>$partId,
                    'part_code'=>(string)($p->part_code??''),
                    'part_name'=>(string)($p->part_name??''),
                    'job_no'=>(string)($p->job_no??''),
                    'vehicle_reg_no'=>(string)($p->vehicle_reg_no??''),
                    'customer_name'=>(string)($p->customer_name??''),
                    'stock_qty'=>(float)($p->stock_qty??0),
                    'latest_purchase'=>(string)$p->purchase_date,
                    'purchase_anchor'=>(int)$p->id,
                    'batch_no'=>(string)($p->purchase_batch_no??$p->purchase_no),
                    'latest_vendor'=>(string)($p->supplier_name??''),
                    'count'=>0,'selected_qty'=>0.0,'selected_amount'=>0.0
                ];
            }
            $rows[$key]['count']++;
            $rows[$key]['selected_qty']+=(float)$p->qty;
            $rows[$key]['selected_amount']+=(float)$p->total_amount;
            $partIds[$partId]=$partId;
            if ($jobId>0) $jobIds[$jobId]=$jobId;
        }
        $keys=array_keys($rows);
        $parts=array_values($partIds);
        $jobs=array_values($jobIds);
        $totals=[];
        $receipts=[];
        $issues=[];
        $fitted=[];
        $invoices=[];

        if ($parts) {
            $purchases=DB::table('workshop_purchases')
                ->where('purchase_type','PART')
                ->whereRaw("UPPER(COALESCE(record_status,'ACTIVE')) <> 'DELETED'")
                ->whereIn('part_master_id',$parts)
                ->where(function($w)use($jobs){
                    $w->whereNull('job_card_id');
                    if($jobs) $w->orWhereIn('job_card_id',$jobs);
                })
                ->selectRaw('part_master_id,job_card_id,SUM(qty) AS total_qty')
                ->groupBy('part_master_id','job_card_id')->get();
            foreach($purchases as $p) {
                $totals[(int)($p->job_card_id??0).':'.(int)$p->part_master_id]=(float)$p->total_qty;
            }
            $movements=DB::table('inventory_movements')
                ->whereIn('part_master_id',$parts)
                ->where('movement_type','PURCHASE_IN')
                ->where(function($w)use($jobs){
                    $w->whereNull('job_card_id');
                    if($jobs)$w->orWhereIn('job_card_id',$jobs);
                })
                ->selectRaw('part_master_id,job_card_id,SUM(qty_in) AS total_qty')
                ->groupBy('part_master_id','job_card_id')->get();
            foreach($movements as $m) {
                $receipts[(int)($m->job_card_id??0).':'.(int)$m->part_master_id]=(float)$m->total_qty;
            }
        }
        if($parts && $jobs) {
            $outs=DB::table('inventory_movements')
                ->whereIn('job_card_id',$jobs)->whereIn('part_master_id',$parts)
                ->where('movement_type','JOB_ISSUE')
                ->selectRaw('job_card_id,part_master_id,SUM(qty_out) AS issued_qty')
                ->groupBy('job_card_id','part_master_id')->get();
            foreach($outs as $m) $issues[(int)$m->job_card_id.':'.(int)$m->part_master_id]=(float)$m->issued_qty;

            $jcParts=DB::table('job_card_parts')
                ->whereIn('job_card_id',$jobs)->whereIn('part_master_id',$parts)
                ->selectRaw("job_card_id,part_master_id,SUM(CASE WHEN UPPER(COALESCE(issue_status,''))='FITTED' THEN qty ELSE 0 END) AS fitted_qty")
                ->groupBy('job_card_id','part_master_id')->get();
            foreach($jcParts as $p)$fitted[(int)$p->job_card_id.':'.(int)$p->part_master_id]=(float)$p->fitted_qty;
            $inv=DB::table('invoices')->whereIn('job_card_id',$jobs)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")
                ->select(['id','invoice_no','job_card_id','status'])
                ->orderByDesc('id')->get();
            foreach($inv as $i) {
                if (!isset($invoices[(int)$i->job_card_id])) $invoices[(int)$i->job_card_id]=$i;
            }
        }

        $counts=[
            'LEDGER_REVIEW'=>0,'PENDING_ISSUE'=>0,'PARTIAL_ISSUE'=>0,
            'DUE_BILLING'=>0,'FITTING_REVIEW'=>0,'INVOICE_LINKED'=>0,'STOCK_MATCHED'=>0
        ];
        foreach($rows as &$r) {
            $key=$r['key'];
            $qty=round((float)($totals[$key]??0),3);
            $in=round((float)($receipts[$key]??0),3);
            $issue=round((float)($issues[$key]??0),3);
            $fit=round((float)($fitted[$key]??0),3);
            $invoice=$invoices[$r['job_id']]??null;
            $r['purchase_qty']=$qty;
            $r['received_qty']=$in;
            $r['issued_qty']=$issue;
            $r['fitted_qty']=$fit;
            $r['pending_qty']=max(0,round($qty-$issue,3));
            $r['invoice_no']=$invoice? (string)$invoice->invoice_no : '';
            $r['invoice_id']=$invoice? (int)$invoice->id : 0;
            $r['ledger_difference']=round($qty-$in,3);

            if(abs($qty-$in)>0.001)$status='LEDGER_REVIEW';
            elseif(!$r['job_id'])$status='STOCK_MATCHED';
            elseif($issue<=0.0005)$status='PENDING_ISSUE';
            elseif($issue+0.001<$qty)$status='PARTIAL_ISSUE';
            elseif($invoice===null)$status='DUE_BILLING';
            elseif($fit+0.001<$qty)$status='FITTING_REVIEW';
            else $status='INVOICE_LINKED';
            $r['state']=$status;
            $counts[$status]++;
        }
        unset($r);
        $allCount=count($rows);
        if($state!=='ALL')$rows=array_filter($rows,fn($r)=>$r['state']===$state);
        $rows=array_values($rows);
        $page=max(1,(int)($filters['page']??1));
        $perPage=40;
        $pager=new LengthAwarePaginator(array_slice($rows,($page-1)*$perPage,$perPage),
            count($rows),$perPage,$page,['path'=>$request->url(),'query'=>$request->except('page')]);

        return view('purchases.reconciliation',[
            'entries'=>$pager,
            'filters'=>compact('q','against','state','from','to'),
            'counts'=>$counts,
            'group_count'=>$allCount,
            'limited'=>$source->count()===$limit,
        ]);
    }
}
