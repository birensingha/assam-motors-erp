<?php
declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Models\PartMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * AM260: Goods received now / supplier bill later.
 * GRN updates stock precisely ONCE at physical receipt; bill matching
 * records a normal PART purchase batch but NEVER changes stock again.
 * PO has no stock or ledger impact.
 *
 * Existing invoicing, payments, purchase and job issue are untouched.
 */
final class AdminProcurementGrnController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $u=$request->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin()
            && in_array(strtolower(trim((string)$u->role)),['admin','owner'],true),
            403,'Owner/Admin permission required for Vendor Challan Procurement.');
        foreach(['am260_purchase_orders','am260_po_lines','am260_grns','am260_grn_lines',
          'am260_grn_bills','am260_grn_bill_allocations'] as $t)
            abort_unless(Schema::hasTable($t),503,'Procurement schema missing: '.$t);
        return $u;
    }

    private function vendor(int $id): object
    {
        $v=DB::table('workshop_vendors')->where('id',$id)->where('active',1)->first();
        if(!$v)throw ValidationException::withMessages(['vendor_id'=>'Choose an active vendor from Vendor Master.']);
        return $v;
    }

    private function job(int $id): ?object
    {
        if(!$id)return null;
        $j=DB::table('job_cards')->where('id',$id)->first();
        if(!$j || strtolower((string)$j->status)==='cancelled')
            throw ValidationException::withMessages(['job_card_id'=>'Select an existing non-cancelled Job Card.']);
        return $j;
    }

    private function selections(): array
    {
        return [
          'vendors'=>DB::table('workshop_vendors')->where('active',1)
              ->orderBy('vendor_name')->get(['id','vendor_name','vendor_code']),
          'jobs'=>DB::table('job_cards')->whereRaw("LOWER(COALESCE(status,''))<>'cancelled'")
              ->orderByDesc('id')->limit(250)->get(['id','job_no','vehicle_reg_no']),
          'parts'=>DB::table('parts_master')->where('active',1)->orderBy('part_name')
              ->get(['id','part_code','part_name','unit','stock_qty','purchase_price']),
        ];
    }

    private function num(string $prefix): string
    {
        return $prefix.'-'.now('Asia/Kolkata')->format('ymdHis').'-'.Str::upper(Str::random(7));
    }

    private function effectiveLines(array $lines,string $mode='po'): array
    {
        if(count($lines)<1 || count($lines)>100)
            throw ValidationException::withMessages(['lines'=>'Enter 1–100 Part lines.']);
        $found=[];
        foreach($lines as $line){
            if(!is_array($line))continue;
            $part=(int)($line['part_master_id']??0);
            $qty=round((float)($line['qty']??0),3);
            $rate=round((float)($line['unit_cost']??0),2);
            // Partial PO delivery can legitimately skip ordered lines (qty=0).
            if($qty<=0 && $mode==='receipt')continue;
            if($part<=0 && $qty<=0)continue;
            if($part<=0 || $qty<=0 || $qty>999999 || $rate<0 || $rate>100000000)
                throw ValidationException::withMessages(['lines'=>'Select a Part, positive received/order quantity and valid cost.']);
            if(isset($found[$part]))
                throw ValidationException::withMessages(['lines'=>'The same Part is entered twice. Combine into one row.']);
            $found[$part]=['part_master_id'=>$part,'qty'=>$qty,'unit_cost'=>$rate,
                'po_line_id'=>(int)($line['po_line_id']??0)];
        }
        if(!$found)throw ValidationException::withMessages(['lines'=>'Add at least one Part with quantity.']);
        return array_values($found);
    }

    public function index(Request $request): View
    {
        $this->admin($request);
        $orders=DB::table('am260_purchase_orders as o')
            ->join('workshop_vendors as v','v.id','=','o.vendor_id')
            ->leftJoin('job_cards as j','j.id','=','o.job_card_id')
            ->orderByDesc('o.id')->limit(80)
            ->get(['o.*','v.vendor_name','j.job_no','j.vehicle_reg_no']);
        $receipts=DB::table('am260_grns as g')
            ->join('workshop_vendors as v','v.id','=','g.vendor_id')
            ->leftJoin('am260_purchase_orders as p','p.id','=','g.po_id')
            ->leftJoin('job_cards as j','j.id','=','g.job_card_id')
            ->orderByDesc('g.id')->limit(80)
            ->get(['g.*','v.vendor_name','p.po_no','j.job_no','j.vehicle_reg_no']);
        $bills=DB::table('am260_grn_bills as b')
            ->join('workshop_vendors as v','v.id','=','b.vendor_id')
            ->orderByDesc('b.id')->limit(80)->get(['b.*','v.vendor_name']);
        $pending=DB::table('am260_grn_lines as l')
            ->join('am260_grns as g','g.id','=','l.grn_id')
            ->join('workshop_vendors as v','v.id','=','g.vendor_id')
            ->join('parts_master as p','p.id','=','l.part_master_id')
            ->leftJoin('am260_purchase_orders as po','po.id','=','g.po_id')
            ->whereRaw('(l.qty_received-l.qty_billed)>0.0005')
            ->select(['l.*','g.grn_no','g.challan_no','g.vendor_id','g.receipt_date',
              'v.vendor_name','p.part_code','p.part_name','p.unit','po.po_no'])
            ->orderByDesc('g.id')->limit(200)->get();
        return view('procurement.index',compact('orders','receipts','bills','pending'));
    }

    public function newPo(Request $request): View
    {
        $this->admin($request);
        return view('procurement.po-create',$this->selections()+[
            'selectedJob'=max(0,(int)$request->query('job_card_id',0)),
            'selectedVendor'=max(0,(int)$request->query('vendor_id',0)),
        ]);
    }

    public function savePo(Request $request): RedirectResponse
    {
        $u=$this->admin($request);
        $d=$request->validate([
           'vendor_id'=>['required','integer','min:1'],
           'job_card_id'=>['nullable','integer','min:1'],
           'order_date'=>['required','date_format:Y-m-d'],
           'notes'=>['nullable','string','max:1200'],
           'lines'=>['required','array','min:1','max:100'],
           'lines.*.part_master_id'=>['nullable','integer','min:1'],
           'lines.*.qty'=>['nullable','numeric','min:0'],
           'lines.*.unit_cost'=>['nullable','numeric','min:0'],
        ]);
        $lines=$this->effectiveLines($d['lines']);
        $id=DB::transaction(function()use($d,$lines,$u){
            $vendor=$this->vendor((int)$d['vendor_id']);
            $job=$this->job((int)($d['job_card_id']??0));
            $po=DB::table('am260_purchase_orders')->insertGetId([
                'po_no'=>$this->num('PO'),'vendor_id'=>$vendor->id,
                'job_card_id'=>$job->id??null,
                'order_date'=>$d['order_date'],'notes'=>trim((string)($d['notes']??''))?:null,
                'status'=>'OPEN','created_by'=>(string)($u->name??$u->id),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            foreach($lines as $line){
                $p=DB::table('parts_master')->where('id',$line['part_master_id'])->where('active',1)->first();
                if(!$p)throw ValidationException::withMessages(['lines'=>'One Part is missing or inactive.']);
                DB::table('am260_po_lines')->insert([
                    'po_id'=>$po,'part_master_id'=>$p->id,
                    'qty_ordered'=>$line['qty'],'qty_received'=>0,
                    'expected_unit_cost'=>$line['unit_cost'],
                    'created_at'=>now(),'updated_at'=>now()
                ]);
            }
            return $po;
        },3);
        return redirect()->route('erp.procurement.receive',['po_id'=>$id])
            ->with('success','Purchase Order created. No stock or supplier payable posted yet. Record actual physical challan receipt now.');
    }

    public function newReceipt(Request $request): View
    {
        $this->admin($request);
        $poId=max(0,(int)$request->query('po_id',0));
        $order=null;$orderLines=collect();
        if($poId){
            $order=DB::table('am260_purchase_orders as o')
              ->join('workshop_vendors as v','v.id','=','o.vendor_id')
              ->where('o.id',$poId)->select(['o.*','v.vendor_name'])->first();
            abort_unless($order,404);
            $orderLines=DB::table('am260_po_lines as l')
                ->join('parts_master as p','p.id','=','l.part_master_id')
                ->where('l.po_id',$poId)
                ->whereRaw('(l.qty_ordered-l.qty_received)>0.0005')
                ->get(['l.*','p.part_code','p.part_name','p.stock_qty','p.unit']);
        }
        $all=$this->selections();
        $all['order']=$order;
        $all['orderLines']=$orderLines;
        $all['poOptions']=DB::table('am260_purchase_orders as o')
           ->join('workshop_vendors as v','v.id','=','o.vendor_id')
           ->where('o.status','OPEN')->orderByDesc('o.id')->limit(100)
           ->get(['o.id','o.po_no','v.vendor_name']);
        $all['selectedJob']=max(0,(int)$request->query('job_card_id',0));
        $all['selectedVendor']=max(0,(int)$request->query('vendor_id',0));
        return view('procurement.receive',$all);
    }

    public function postReceipt(Request $request): RedirectResponse
    {
        $u=$this->admin($request);
        $d=$request->validate([
          'po_id'=>['nullable','integer','min:1'],
          'vendor_id'=>['required','integer','min:1'],
          'job_card_id'=>['nullable','integer','min:1'],
          'challan_no'=>['required','string','min:1','max:100'],
          'challan_date'=>['required','date_format:Y-m-d'],
          'receipt_date'=>['required','date_format:Y-m-d'],
          'notes'=>['nullable','string','max:1200'],
          'lines'=>['required','array','min:1','max:100'],
          'lines.*.part_master_id'=>['nullable','integer','min:1'],
          'lines.*.po_line_id'=>['nullable','integer','min:1'],
          'lines.*.qty'=>['nullable','numeric','min:0'],
          'lines.*.unit_cost'=>['nullable','numeric','min:0'],
        ]);
        $lines=$this->effectiveLines($d['lines'],'receipt');
        $receipt=DB::transaction(function()use($d,$lines,$u){
            $orderId=(int)($d['po_id']??0);
            $order=$orderId?DB::table('am260_purchase_orders')->where('id',$orderId)->lockForUpdate()->first():null;
            if($orderId && !$order)
                throw ValidationException::withMessages(['po_id'=>'Selected PO does not exist.']);
            if($order && $order->status!=='OPEN')
                throw ValidationException::withMessages(['po_id'=>'PO is closed; cannot receive additional items.']);
            $vendor=$this->vendor((int)$d['vendor_id']);
            if($order && (int)$order->vendor_id!==(int)$vendor->id)
                throw ValidationException::withMessages(['vendor_id'=>'Challan vendor must match Purchase Order vendor.']);
            $jobId=$order?(int)($order->job_card_id??0):(int)($d['job_card_id']??0);
            $this->job($jobId);
            $challan=trim($d['challan_no']);
            if(DB::table('am260_grns')->where('vendor_id',$vendor->id)
                   ->where('challan_no',$challan)->exists())
                throw ValidationException::withMessages(['challan_no'=>'This vendor challan was already received. Duplicate stock-in blocked.']);

            $poLines=collect();
            if($order) {
                $poLines=DB::table('am260_po_lines')->where('po_id',$order->id)
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            }
            $partIds=array_column($lines,'part_master_id');
            sort($partIds,SORT_NUMERIC);
            $parts=PartMaster::query()->whereIn('id',$partIds)->orderBy('id')
                ->lockForUpdate()->get()->keyBy('id');
            foreach($lines as $l){
                $part=$parts->get($l['part_master_id']);
                if(!$part || !$part->active)
                    throw ValidationException::withMessages(['lines'=>'Received Part missing or inactive.']);
                if($part->stock_qty===null)
                    throw ValidationException::withMessages(['lines'=>$part->part_name.' has no recorded opening stock. Reconcile opening stock before receiving challan; do not silently convert unknown to zero.']);
                if($order){
                    $pl=$poLines->get($l['po_line_id']);
                    if(!$pl || (int)$pl->part_master_id!==(int)$part->id)
                       throw ValidationException::withMessages(['lines'=>'Part does not match selected PO line.']);
                    if((float)$pl->qty_ordered-(float)$pl->qty_received+0.0005<$l['qty'])
                       throw ValidationException::withMessages(['lines'=>$part->part_name.' exceeds outstanding PO quantity.']);
                }
            }
            $grnNo=$this->num('GRN');
            $grnId=DB::table('am260_grns')->insertGetId([
                'grn_no'=>$grnNo,'vendor_id'=>$vendor->id,
                'po_id'=>$order->id??null,'job_card_id'=>$jobId?:null,
                'challan_no'=>$challan,'challan_date'=>$d['challan_date'],
                'receipt_date'=>$d['receipt_date'],'status'=>'RECEIVED',
                'notes'=>trim((string)($d['notes']??''))?:null,
                'created_by'=>(string)($u->name??$u->id),
                'created_at'=>now(),'updated_at'=>now()
            ]);

            foreach($lines as $l){
                $part=$parts->get($l['part_master_id']);
                $before=(float)$part->stock_qty;
                $after=round($before+$l['qty'],3);
                DB::table('am260_grn_lines')->insert([
                   'grn_id'=>$grnId,
                   'po_line_id'=>$order?$l['po_line_id']:null,
                   'part_master_id'=>$part->id,'job_card_id'=>$jobId?:null,
                   'qty_received'=>$l['qty'],'qty_billed'=>0,
                   'provisional_unit_cost'=>$l['unit_cost'],
                   'created_at'=>now(),'updated_at'=>now()
                ]);
                $part->stock_qty=$after;
                $part->save();
                if($order){
                    DB::table('am260_po_lines')->where('id',$l['po_line_id'])
                      ->update(['qty_received'=>round((float)$poLines->get($l['po_line_id'])->qty_received+$l['qty'],3),'updated_at'=>now()]);
                }
                DB::table('inventory_movements')->insert([
                  'part_master_id'=>$part->id,'job_card_id'=>$jobId?:null,
                  'movement_type'=>'GRN_RECEIPT_IN',
                  'qty_in'=>$l['qty'],'qty_out'=>0,'balance_after'=>$after,
                  'reference_no'=>$grnNo,
                  'note'=>'AM260 PHYSICAL CHALLAN RECEIVED · '.$vendor->vendor_name.
                     ' · Vendor Challan '.$challan.($order?' · PO '.$order->po_no:' · Urgent receipt without PO').
                     ' · BILL PENDING · NO PURCHASE BILL POSTED',
                  'created_at'=>now()
                ]);
            }
            if($order){
                $remaining=DB::table('am260_po_lines')->where('po_id',$order->id)
                  ->whereRaw('qty_received+0.0005<qty_ordered')->exists();
                if(!$remaining)DB::table('am260_purchase_orders')->where('id',$order->id)
                    ->update(['status'=>'RECEIVED','updated_at'=>now()]);
            }
            return $grnNo;
        },3);
        return redirect()->route('erp.procurement.index')
             ->with('success','GRN '.$receipt.' posted. Physical received stock increased ONCE. Vendor Purchase Bill remains pending; Job Card Parts issue is a separate action.');
    }

    private function pendingRows(int $vendorId): \Illuminate\Support\Collection
    {
        if(!$vendorId)return collect();
        return DB::table('am260_grn_lines as l')
           ->join('am260_grns as g','g.id','=','l.grn_id')
           ->join('parts_master as p','p.id','=','l.part_master_id')
           ->leftJoin('am260_purchase_orders as po','po.id','=','g.po_id')
           ->leftJoin('job_cards as jc','jc.id','=','l.job_card_id')
           ->where('g.vendor_id',$vendorId)->whereRaw('(l.qty_received-l.qty_billed)>0.0005')
           ->orderBy('g.receipt_date')->orderBy('l.id')
           ->get(['l.*','g.grn_no','g.challan_no','p.part_name','p.part_code','p.unit',
               'po.po_no','jc.job_no','jc.vehicle_reg_no']);
    }

    public function newBill(Request $request): View
    {
        $this->admin($request);
        $vendorId=max(0,(int)$request->query('vendor_id',0));
        $all=$this->selections();
        $all['selectedVendor']=$vendorId;
        $all['pending']=$this->pendingRows($vendorId);
        return view('procurement.match-bill',$all);
    }

    private function price(float $qty,float $rate,float $discountPercent,float $gstPercent): array
    {
        $gross=round($qty*$rate,2);
        $discount=round($gross*$discountPercent/100,2);
        $taxable=round($gross-$discount,2);
        $gst=round($taxable*$gstPercent/100,2);
        $cgst=round($gst/2,2);
        return ['gross'=>$gross,'discount'=>$discount,'taxable'=>$taxable,'gst'=>$gst,
            'cgst_percent'=>$gstPercent/2,'cgst'=>$cgst,'sgst_percent'=>$gstPercent/2,
            'sgst'=>round($gst-$cgst,2),'total'=>round($taxable+$gst,2)];
    }

    public function postBill(Request $request): RedirectResponse
    {
        $u=$this->admin($request);
        $d=$request->validate([
          'vendor_id'=>['required','integer','min:1'],
          'supplier_invoice'=>['required','string','max:100'],
          'purchase_date'=>['required','date_format:Y-m-d'],
          'notes'=>['nullable','string','max:1000'],
          'lines'=>['required','array','min:1','max:100'],
          'lines.*.selected'=>['nullable','in:1'],
          'lines.*.qty'=>['nullable','numeric','min:0'],
          'lines.*.unit_cost'=>['nullable','numeric','min:0'],
          'lines.*.discount_percent'=>['nullable','numeric','between:0,100'],
          'lines.*.gst_percent'=>['nullable','numeric','between:0,100'],
        ]);
        $prepared=[];
        foreach($d['lines'] as $lineId=>$row){
            if(!is_array($row)||($row['selected']??'')!=='1')continue;
            $lineId=(int)$lineId;
            $qty=round((float)($row['qty']??0),3);
            $rate=round((float)($row['unit_cost']??0),2);
            $discount=round((float)($row['discount_percent']??0),2);
            $gst=round((float)($row['gst_percent']??0),2);
            if($lineId<=0||$qty<=0||$rate<0||$rate>100000000 || $discount<0||$discount>100||$gst<0||$gst>100)
                throw ValidationException::withMessages(['lines'=>'Selected GRN lines need valid positive quantities, rates, discounts and GST.']);
            $prepared[$lineId]=['qty'=>$qty,'rate'=>$rate,'discount'=>$discount,'gst'=>$gst];
        }
        if(!$prepared)throw ValidationException::withMessages(['lines'=>'Select at least one pending GRN item.']);
        if(count($prepared)>100)throw ValidationException::withMessages(['lines'=>'Maximum 100 GRN lines per supplier bill.']);

        $batch=DB::transaction(function()use($d,$prepared,$u){
            $vendor=$this->vendor((int)$d['vendor_id']);
            $invoice=trim($d['supplier_invoice']);
            if(DB::table('am260_grn_bills')->where('vendor_id',$vendor->id)
                ->where('supplier_invoice',$invoice)->exists() ||
               DB::table('workshop_purchases')->where('vendor_id',$vendor->id)
                ->where('supplier_invoice',$invoice)
                ->whereRaw("COALESCE(record_status,'ACTIVE')<>'DELETED'")->exists())
                throw ValidationException::withMessages(['supplier_invoice'=>'Supplier invoice already booked for this vendor. Duplicate Purchase Bill blocked.']);

            $grnLines=DB::table('am260_grn_lines as l')
                ->join('am260_grns as g','g.id','=','l.grn_id')
                ->whereIn('l.id',array_keys($prepared))->orderBy('l.id')
                ->select(['l.*','g.vendor_id','g.grn_no','g.challan_no','g.po_id'])
                ->lockForUpdate()->get()->keyBy('id');
            $matched=[];
            $parts=[];
            $totals=['qty'=>0.0,'gross'=>0.0,'discount'=>0.0,'taxable'=>0.0,'gst'=>0.0,'total'=>0.0];
            foreach($prepared as $lineId=>$input){
                $grn=$grnLines->get($lineId);
                if(!$grn || (int)$grn->vendor_id!==(int)$vendor->id)
                    throw ValidationException::withMessages(['lines'=>'One GRN line is not pending for the selected vendor.']);
                $pending=round((float)$grn->qty_received-(float)$grn->qty_billed,3);
                if($input['qty']>$pending+0.0005)
                    throw ValidationException::withMessages(['lines'=>'GRN '.$grn->grn_no.' pending quantity is only '.$pending.'.']);
                $part=DB::table('parts_master')->where('id',$grn->part_master_id)->first();
                if(!$part)throw ValidationException::withMessages(['lines'=>'GRN-linked Part Master missing.']);
                $money=$this->price($input['qty'],$input['rate'],$input['discount'],$input['gst']);
                foreach($totals as $key=>$x)$totals[$key]+=$key==='qty'?$input['qty']:$money[$key];
                $matched[]=['grn'=>$grn,'part'=>$part,'input'=>$input,'money'=>$money];
            }

            $batchNo=$this->num('PB');
            $billId=DB::table('am260_grn_bills')->insertGetId([
                'vendor_id'=>$vendor->id,'supplier_invoice'=>$invoice,
                'purchase_date'=>$d['purchase_date'],'batch_no'=>$batchNo,
                'created_by'=>(string)($u->name??$u->id),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            $links=[];
            foreach($matched as $n=>$line){
                $grn=$line['grn'];$part=$line['part'];$input=$line['input'];$money=$line['money'];
                $po=$grn->po_id?DB::table('am260_purchase_orders')->where('id',$grn->po_id)->first():null;
                $job=$grn->job_card_id?DB::table('job_cards')->where('id',$grn->job_card_id)->first():null;
                $reference='AGAINST '.($job?($job->job_no.' / '.$job->vehicle_reg_no):'STOCK')
                    .' + '.($po?$po->po_no:'NO PO').' + '.$grn->grn_no.' + CHALLAN '.$grn->challan_no;
                $lineNo=$n+1;
                $purchaseNo=$batchNo.'-L'.str_pad((string)$lineNo,3,'0',STR_PAD_LEFT);
                $id=DB::table('workshop_purchases')->insertGetId([
                    'purchase_no'=>$purchaseNo,'purchase_batch_no'=>$batchNo,
                    'purchase_line_no'=>$lineNo,'purchase_type'=>'PART',
                    'purchase_date'=>$d['purchase_date'],'vendor_id'=>$vendor->id,
                    'supplier_name'=>$vendor->vendor_name,'supplier_invoice'=>$invoice,
                    'job_card_id'=>$grn->job_card_id?:null,
                    'part_master_id'=>$part->id,'description'=>$part->part_name,
                    'hsn_code'=>$part->hsn_code?:null,'unit'=>$part->unit?:'PCS',
                    'qty'=>$input['qty'],'unit_cost'=>$input['rate'],
                    'discount_percent'=>$input['discount'],'discount_amount'=>$money['discount'],
                    'gst_percent'=>$input['gst'],'taxable_amount'=>$money['taxable'],
                    'gst_amount'=>$money['gst'],'cgst_percent'=>$money['cgst_percent'],
                    'cgst_amount'=>$money['cgst'],'sgst_percent'=>$money['sgst_percent'],
                    'sgst_amount'=>$money['sgst'],'total_amount'=>$money['total'],
                    'notes'=>Str::limit($reference,780),
                    'record_status'=>'ACTIVE',
                    'created_by'=>(string)($u->name??$u->id),
                    'created_at'=>now(),'updated_at'=>now()
                ]);
                DB::table('am260_grn_bill_allocations')->insert([
                    'bill_id'=>$billId,'grn_line_id'=>$grn->id,'purchase_id'=>$id,
                    'matched_qty'=>$input['qty'],'created_at'=>now(),'updated_at'=>now()
                ]);
                DB::table('am260_grn_lines')->where('id',$grn->id)
                   ->update(['qty_billed'=>round((float)$grn->qty_billed+$input['qty'],3),'updated_at'=>now()]);
                DB::table('purchase_audit_logs')->insert([
                    'purchase_id'=>$id,'purchase_no'=>$purchaseNo,'batch_no'=>$batchNo,
                    'action'=>'AM260_GRN_MATCHED_NO_STOCK_IN',
                    'actor'=>(string)($u->name??$u->id),
                    'reason'=>$reference,
                    'before_json'=>null,
                    'after_json'=>json_encode(['grn_line_id'=>$grn->id,'qty'=>$input['qty'],
                       'stock_movement'=>'NONE','invoice'=>$invoice],JSON_UNESCAPED_UNICODE),
                    'created_at'=>now()
                ]);
                $links[]=$reference;
            }
            $pre=round($totals['total'],2);
            $grand=round($pre,0,PHP_ROUND_HALF_UP);
            DB::table('workshop_purchase_batches')->insert([
                'batch_no'=>$batchNo,'purchase_type'=>'PART',
                'purchase_date'=>$d['purchase_date'],'vendor_id'=>$vendor->id,
                'supplier_name'=>$vendor->vendor_name,'supplier_invoice'=>$invoice,
                'item_count'=>count($matched),'total_qty'=>round($totals['qty'],3),
                'gross_amount'=>round($totals['gross'],2),
                'discount_amount'=>round($totals['discount'],2),
                'taxable_amount'=>round($totals['taxable'],2),
                'gst_amount'=>round($totals['gst'],2),
                'pre_round_total'=>$pre,'round_off'=>round($grand-$pre,2),
                'grand_total'=>$grand,'notes'=>'AM260 GRN MATCHED · '.Str::limit(implode(' ; ',array_unique($links)),600),
                'record_status'=>'ACTIVE',
                'created_by'=>(string)($u->name??$u->id),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            return $batchNo;
        },3);
        return redirect()->route('erp.procurement.index')
           ->with('success','Purchase Bill '.$batch.' posted and linked to GRN/PO/Job Card. No additional stock received (0 stock movements). Vendor bill now appears in Purchase Register.');
    }
}
