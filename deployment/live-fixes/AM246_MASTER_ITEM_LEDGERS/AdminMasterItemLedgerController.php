<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

/**
 * AM246_MASTER_ITEM_LEDGERS
 * Read-only transaction explorer. Parts stock drawn ONLY from inventory_movements;
 * Purchase / JC billing are evidence rows, never counted as stock movements twice.
 */
final class AdminMasterItemLedgerController extends Controller
{
    private const MASTERS=[
        'PART'=>['table'=>'parts_master','code'=>'part_code','name'=>'part_name'],
        'LABOUR'=>['table'=>'labour_master','code'=>'rot_code','name'=>'rot_description'],
        'OSL'=>['table'=>'workshop_osl_master','code'=>'osl_code','name'=>'osl_name']
    ];

    private function authorizeUser(Request $request): void
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),
            403,'Admin permission required to view master transaction history.');
    }

    private function search(string $type,string $term): array
    {
        $info=self::MASTERS[$type];
        $q=DB::table($info['table']);
        $term=trim(mb_substr($term,0,120));
        if($term!==''){
            $q->where(function($w)use($term,$info){
                $like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$term).'%';
                $w->where($info['code'],'like',$like)->orWhere($info['name'],'like',$like);
            });
        }
        return $q->orderBy($info['name'])->orderBy('id')->limit(50)
            ->get(['id',$info['code'].' as code',$info['name'].' as name'])
            ->map(fn($r)=>['id'=>(int)$r->id,'code'=>(string)$r->code,
                'name'=>(string)$r->name,'label'=>trim((string)$r->code).' — '.trim((string)$r->name)])
            ->all();
    }

    private static function dateOf($value): string
    {
        return $value ? substr((string)$value,0,10) : '';
    }

    private static function event(string $date,string $order,int $rowId,string $type,
        string $reference,string $detail,string $party,string $vehicle,
        $inQty,$outQty,$amount,?string $url,?string $status='',?string $notes=''): array
    {
        return [
            'date'=>self::dateOf($date),'sort_time'=>$order,'sort_id'=>$rowId,
            'type'=>$type,'reference'=>$reference,'detail'=>$detail,
            'party'=>$party,'vehicle'=>$vehicle,'in'=>$inQty,'out'=>$outQty,
            'amount'=>$amount,'url'=>$url,'status'=>$status,'notes'=>$notes,
        ];
    }

    private function partReport(object $master,?string $from,?string $to): array
    {
        $id=(int)$master->id;
        $filter=function($q,$column)use($from,$to){
            if($from)$q->whereDate($column,'>=',$from);
            if($to)$q->whereDate($column,'<=',$to);
            return $q;
        };
        $timeline=[];
        $allMovement=DB::table('inventory_movements')->where('part_master_id',$id);
        $first=(clone $allMovement)->orderBy('created_at')->orderBy('id')->first();
        $initial=$first
            ? round((float)$first->balance_after-(float)$first->qty_in+(float)$first->qty_out,3)
            : round((float)$master->stock_qty,3);
        $netAll=(float)(clone $allMovement)->selectRaw('COALESCE(SUM(qty_in-qty_out),0) as n')->value('n');
        $before=$from ? (float)(clone $allMovement)->whereDate('created_at','<',$from)
            ->selectRaw('COALESCE(SUM(qty_in-qty_out),0) as n')->value('n') : 0.0;
        $opening=round($initial+$before,3);
        $running=$opening;
        $movements=$filter(DB::table('inventory_movements as m')
                ->leftJoin('job_cards as jc','jc.id','=','m.job_card_id')
                ->where('m.part_master_id',$id),'m.created_at')
            ->orderBy('m.created_at')->orderBy('m.id')
            ->get(['m.*','jc.job_no','jc.customer_name','jc.vehicle_reg_no']);
        foreach($movements as $m){
            $in=round((float)$m->qty_in,3);$out=round((float)$m->qty_out,3);
            $running=round($running+$in-$out,3);
            $reference=(string)($m->reference_no ?: ($m->job_no ?: 'MOV-'.$m->id));
            $url=$m->job_card_id ? url('/erp/job-cards/'.$m->job_card_id) : null;
            $timeline[]=self::event((string)$m->created_at,(string)$m->created_at,
                (int)$m->id,'STOCK · '.(string)$m->movement_type,$reference,
                'Inventory movement; recorded balance '.number_format((float)$m->balance_after,3),
                (string)($m->customer_name?:''),(string)($m->vehicle_reg_no?:''),
                $in,$out,null,$url,'STOCK POSTED',(string)($m->note?:''));
        }

        $purchaseQuery=DB::table('workshop_purchases as p')
            ->leftJoin('workshop_vendors as v','v.id','=','p.vendor_id')
            ->leftJoin('job_cards as jc','jc.id','=','p.job_card_id')
            ->where('p.purchase_type','PART')->where('p.part_master_id',$id);
        $purchases=$filter($purchaseQuery,'p.purchase_date')
            ->orderBy('p.purchase_date')->orderBy('p.id')
            ->get(['p.id','p.purchase_no','p.purchase_batch_no','p.purchase_date','p.supplier_invoice',
                'p.description','p.qty','p.unit_cost','p.total_amount','p.record_status',
                'p.job_card_id','p.vendor_id','p.supplier_name','v.vendor_name','jc.job_no','jc.vehicle_reg_no']);
        foreach($purchases as $p){
            $deleted=strtoupper((string)$p->record_status)==='DELETED';
            $timeline[]=self::event((string)$p->purchase_date,((string)$p->purchase_date).' 12:00:00',
                (int)$p->id,'PURCHASE · BILL',
                (string)($p->purchase_batch_no?:$p->purchase_no),
                'Qty '.number_format((float)$p->qty,3).' · Supplier invoice '.($p->supplier_invoice?:'—').
                ' · Unit cost ₹'.number_format((float)$p->unit_cost,2).
                ($p->job_no?' · Against '.$p->job_no:' · STOCK'),
                (string)($p->vendor_name?:$p->supplier_name),
                (string)($p->vehicle_reg_no?:''),
                null,null,round((float)$p->total_amount,2),
                url('/erp/purchases/'.$p->id),$deleted?'VOID/DELETED':'PURCHASE SAVED',
                'Supplier document; stock addition is shown separately from inventory_movements.');
        }

        // Historical supplier purchases may originate from the legacy purchase_items table.
        $legacy=$filter(DB::table('purchase_items as pi')
                ->join('purchases as p','p.id','=','pi.purchase_id')
                ->where('pi.part_master_id',$id),'p.purchase_date')
            ->orderBy('p.purchase_date')->orderBy('pi.id')
            ->get(['pi.id','pi.qty','pi.unit_cost','pi.total','p.purchase_no',
                'p.invoice_no','p.purchase_date','p.supplier_name']);
        foreach($legacy as $p){
            $timeline[]=self::event((string)$p->purchase_date,((string)$p->purchase_date).' 12:00:00',
                (int)$p->id,'PURCHASE · LEGACY',(string)$p->purchase_no,
                'Legacy purchase item · Invoice '.$p->invoice_no.' · Qty '.number_format((float)$p->qty,3),
                (string)$p->supplier_name,'',null,null,round((float)$p->total,2),
                null,'HISTORICAL','Not counted again as stock. Confirm against inventory movements.');
        }

        $jcParts=$filter(DB::table('job_card_parts as jp')
                ->join('job_cards as jc','jc.id','=','jp.job_card_id')
                ->leftJoin('invoices as iv','iv.id','=','jc.invoice_id')
                ->where(function($q)use($id,$master){
                    $q->where('jp.part_master_id',$id)
                      ->orWhere(function($w)use($master){
                          $w->whereNull('jp.part_master_id')->where('jp.part_code',$master->part_code);
                      });
                }),'jp.created_at')
            ->orderBy('jp.created_at')->orderBy('jp.id')
            ->get(['jp.id','jp.qty','jp.total','jp.rate','jp.issue_status',
                'jp.created_at','jp.job_card_id','jc.job_no','jc.customer_name','jc.vehicle_reg_no',
                'jc.status as job_status','iv.invoice_no','iv.status as invoice_status']);
        foreach($jcParts as $p){
            $timeline[]=self::event((string)$p->created_at,(string)$p->created_at,
                (int)$p->id,'JC · PART LINE',(string)$p->job_no,
                'Qty '.number_format((float)$p->qty,3).
                ' · Issue '.($p->issue_status?:'Not recorded').
                ' · JC status '.$p->job_status.
                ($p->invoice_no?' · JC linked invoice '.$p->invoice_no:''),
                (string)$p->customer_name,(string)$p->vehicle_reg_no,
                null,null,round((float)$p->total,2),
                url('/erp/job-cards/'.$p->job_card_id),
                $p->invoice_no?'JC INVOICED (line snapshot unverified)':'JC LINE',
                'Part line is not stock-out. Actual stock issue comes from inventory movement.');
        }

        $direct=$filter(DB::table('direct_invoice_lines as dl')
                ->join('invoices as i','i.id','=','dl.invoice_id')
                ->where('dl.part_master_id',$id),'i.invoice_date')
            ->orderBy('i.invoice_date')->orderBy('dl.id')
            ->get(['dl.id','dl.qty','dl.total_amount','i.id as invoice_id',
                'i.invoice_no','i.invoice_date','i.customer_name','i.status']);
        foreach($direct as $p){
            $timeline[]=self::event((string)$p->invoice_date,((string)$p->invoice_date).' 14:00:00',
                (int)$p->id,'COUNTER · INVOICE',(string)$p->invoice_no,
                'Qty '.number_format((float)$p->qty,3).' · Direct Parts Invoice',
                (string)$p->customer_name,'',null,null,round((float)$p->total_amount,2),
                url('/erp/counter-invoices/'.$p->invoice_id),
                (string)($p->status?:'INVOICED'),
                'Direct invoice item. Counter stock-out shown separately from inventory movements.');
        }

        $reconciled=round($initial+$netAll,3);
        $current=round((float)$master->stock_qty,3);
        return [
            'events'=>$timeline,'opening'=>$opening,'closing'=>$running,'current_stock'=>$current,
            'reconstructed_current'=>$reconciled,
            'variance'=>round($current-$reconciled,3),
            'opening_kind'=>$first?'Derived from first recorded balance':'Current stock only (no recorded movements)',
            'purchase_qty'=>(float)$purchases->where('record_status','!=','DELETED')->sum('qty'),
            'issued_qty'=>(float)$movements->sum('qty_out'),
            'purchase_amount'=>(float)$purchases->where('record_status','!=','DELETED')->sum('total_amount'),
        ];
    }

    private function labourReport(object $master,?string $from,?string $to): array
    {
        $rows=DB::table('job_card_labour as jl')
            ->join('job_cards as jc','jc.id','=','jl.job_card_id')
            ->leftJoin('invoices as i','i.id','=','jc.invoice_id')
            ->where(function($q)use($master){
                $q->where('jl.rot_master_id',(int)$master->id)
                  ->orWhere(function($w)use($master){
                      $w->whereNull('jl.rot_master_id')->where('jl.rot_code',(string)$master->rot_code);
                  });
            });
        if($from)$rows->whereDate('jl.created_at','>=',$from);
        if($to)$rows->whereDate('jl.created_at','<=',$to);
        $data=$rows->get(['jl.id','jl.job_card_id','jl.rot_code','jl.description','jl.created_at',
            'jl.standard_hours','jl.labour_rate','jl.total','jl.mechanic_name',
            'jl.execution_status','jl.billing_included','jc.job_no','jc.customer_name','jc.vehicle_reg_no',
            'jc.status as job_status','i.invoice_no']);
        $events=[];
        foreach($data as $r){
            $events[]=self::event((string)$r->created_at,(string)$r->created_at,(int)$r->id,
                'JC · LABOUR', (string)$r->job_no,
                'ROT '.$r->rot_code.' · STD '.number_format((float)$r->standard_hours,2).
                'h · Mechanic '.($r->mechanic_name?:'Unassigned').
                ' · '.$r->execution_status.
                ($r->invoice_no?' · Linked invoice '.$r->invoice_no:''),
                (string)$r->customer_name,(string)$r->vehicle_reg_no,
                null,null,round((float)$r->total,2),url('/erp/job-cards/'.$r->job_card_id),
                $r->billing_included?'BILLABLE JC LINE':'NON-BILLABLE',
                'Customer billing depends on saved invoice; item line is not an invoice snapshot.');
        }
        $sessions=DB::table('rot_sessions as rs')
            ->leftJoin('job_cards as jc','jc.id','=','rs.job_card_id')
            ->where(function($q)use($master){
                $q->where('rs.rot_code',(string)$master->rot_code)
                  ->orWhereIn('rs.labour_line_id',function($sub)use($master){
                      $sub->select('id')->from('job_card_labour')->where('rot_master_id',$master->id);
                  });
            });
        if($from)$sessions->whereDate('rs.created_at','>=',$from);
        if($to)$sessions->whereDate('rs.created_at','<=',$to);
        foreach($sessions->get(['rs.id','rs.created_at','rs.rot_code','rs.status','rs.staff_name',
            'rs.total_seconds','rs.amount','rs.job_card_id','rs.labour_line_id',
            'jc.job_no','jc.customer_name','jc.vehicle_reg_no']) as $r){
            $events[]=self::event((string)$r->created_at,(string)$r->created_at,(int)$r->id,
                'ROT · EXECUTION',$r->job_no?:('ROT SESSION #'.$r->id),
                'Mechanic '.($r->staff_name?:'—').' · Duration '.
                    number_format((float)$r->total_seconds/3600,2).'h · '.$r->status,
                (string)($r->customer_name?:''),(string)($r->vehicle_reg_no?:''),
                null,null,null,$r->job_card_id?url('/erp/job-cards/'.$r->job_card_id):null,
                $r->status,'Execution log, not an additional customer billing amount.');
        }
        return ['events'=>$events,'opening'=>null,'closing'=>null,'current_stock'=>null,'variance'=>null,
            'opening_kind'=>'Services do not carry stock quantities.',
            'purchase_qty'=>null,'issued_qty'=>null,'purchase_amount'=>null];
    }

    private function oslReport(object $master,?string $from,?string $to): array
    {
        $purchase=DB::table('workshop_purchases as p')
            ->leftJoin('workshop_vendors as v','v.id','=','p.vendor_id')
            ->leftJoin('job_cards as jc','jc.id','=','p.job_card_id')
            ->where('p.purchase_type','OSL')
            ->where('p.osl_master_id',(int)$master->id);
        if($from)$purchase->whereDate('p.purchase_date','>=',$from);
        if($to)$purchase->whereDate('p.purchase_date','<=',$to);
        $data=$purchase->get(['p.id','p.purchase_date','p.purchase_no','p.purchase_batch_no',
            'p.supplier_invoice','p.qty','p.unit_cost','p.total_amount','p.customer_rate',
            'p.customer_amount','p.customer_billing_status','p.record_status',
            'p.labour_line_id','p.job_card_id','v.vendor_name','p.supplier_name',
            'jc.job_no','jc.customer_name','jc.vehicle_reg_no']);
        $events=[];$ids=[];
        foreach($data as $p){
            if($p->labour_line_id)$ids[]=(int)$p->labour_line_id;
            $events[]=self::event((string)$p->purchase_date,((string)$p->purchase_date).' 12:00:00',
                (int)$p->id,'OSL · PURCHASE',
                (string)($p->purchase_batch_no?:$p->purchase_no),
                'Vendor cost ₹'.number_format((float)$p->total_amount,2).
                ' · Qty '.number_format((float)$p->qty,3).
                ' · Supplier invoice '.($p->supplier_invoice?:'—').
                ' · Customer saved amount ₹'.number_format((float)$p->customer_amount,2).
                ' · '.$p->customer_billing_status,
                (string)($p->vendor_name?:$p->supplier_name),
                (string)($p->vehicle_reg_no?:''),null,null,round((float)$p->total_amount,2),
                url('/erp/purchases/'.$p->id),
                strtoupper((string)$p->record_status)==='DELETED'?'VOID/DELETED':'PURCHASE SAVED',
                'Supplier cost shown here. Linked JC billing is a separate event.');
        }
        $bill=DB::table('job_card_labour as jl')
            ->join('job_cards as jc','jc.id','=','jl.job_card_id')
            ->leftJoin('invoices as inv','inv.id','=','jc.invoice_id')
            ->whereRaw("UPPER(TRIM(COALESCE(jl.mechanic_name,''))) = 'OSL'")
            ->where(function($w)use($master,$ids){
                $w->where('jl.rot_code',(string)$master->osl_code);
                if($ids)$w->orWhereIn('jl.id',array_unique($ids));
            });
        if($from)$bill->whereDate('jl.created_at','>=',$from);
        if($to)$bill->whereDate('jl.created_at','<=',$to);
        foreach($bill->get(['jl.id','jl.created_at','jl.total','jl.labour_rate',
            'jl.billing_included','jl.description','jl.job_card_id',
            'jc.job_no','jc.customer_name','jc.vehicle_reg_no','jc.status as job_status','inv.invoice_no']) as $r){
            $events[]=self::event((string)$r->created_at,(string)$r->created_at,(int)$r->id,
                'OSL · JC BILLING',(string)$r->job_no,
                $r->description.' · JC selling amount ₹'.number_format((float)$r->total,2).
                ($r->invoice_no?' · Linked invoice '.$r->invoice_no:''),
                (string)$r->customer_name,(string)$r->vehicle_reg_no,
                null,null,round((float)$r->total,2),url('/erp/job-cards/'.$r->job_card_id),
                $r->billing_included?'BILLABLE':'NON-BILLABLE',
                'Billing entry is distinct from supplier purchase. Invoice item snapshot may differ.');
        }
        return ['events'=>$events,'opening'=>null,'closing'=>null,'current_stock'=>null,'variance'=>null,
            'opening_kind'=>'OSL is a service, not physical inventory.',
            'purchase_qty'=>null,'issued_qty'=>null,'purchase_amount'=>null];
    }

    public function suggest(Request $request): JsonResponse
    {
        $this->authorizeUser($request);
        $type=strtoupper(trim((string)$request->query('type','PART')));
        abort_unless(isset(self::MASTERS[$type]),422,'Invalid master type.');
        $q=mb_substr(trim((string)$request->query('q','')),0,120);
        return response()->json(['items'=>$this->search($type,$q)]);
    }

    public function index(Request $request): View
    {
        $this->authorizeUser($request);
        $type=strtoupper((string)$request->query('type','PART'));
        if(!isset(self::MASTERS[$type]))$type='PART';
        $q=trim(mb_substr((string)$request->query('q',''),0,120));
        $id=(int)$request->query('id',0);
        $input=$request->validate([
            'from'=>['nullable','date_format:Y-m-d'],'to'=>['nullable','date_format:Y-m-d']
        ]);
        $from=$input['from']??null;$to=$input['to']??null;
        if($from && $to && $from>$to)
            throw ValidationException::withMessages(['from'=>'From Date must be before To Date.']);
        $master=$id?DB::table(self::MASTERS[$type]['table'])->where('id',$id)->first():null;
        if($id&&!$master)abort(404,'Selected master item not found.');
        $choices=$this->search($type,$q);
        $report=['events'=>[],'opening'=>null,'closing'=>null,'current_stock'=>null,
            'reconstructed_current'=>null,'variance'=>null,'purchase_qty'=>null,
            'issued_qty'=>null,'purchase_amount'=>null,'opening_kind'=>''];
        if($master){
            $report=match($type){
                'PART'=>$this->partReport($master,$from,$to),
                'LABOUR'=>$this->labourReport($master,$from,$to),
                'OSL'=>$this->oslReport($master,$from,$to),
            };
            usort($report['events'],static fn($a,$b)=>
                [($a['sort_time']?:$a['date']),(int)$a['sort_id'],$a['type']] <=>
                [($b['sort_time']?:$b['date']),(int)$b['sort_id'],$b['type']]);
        }
        $totalEvents=count($report['events']);
        if($totalEvents>3000){
            // Full totals not fabricated from the truncated event table.
            $report['events']=array_slice($report['events'],-3000);
        }
        return view('admin.native.item-ledger',compact('type','q','id','master','choices',
            'report','totalEvents','from','to'));
    }
}
