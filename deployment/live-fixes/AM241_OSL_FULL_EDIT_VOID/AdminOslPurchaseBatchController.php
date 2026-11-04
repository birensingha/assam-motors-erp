<?php
/* AM241_OSL_BATCH_EDIT_VOID — supplier purchase document editor.
   Every mutation is audited, paid/closed documents are locked, and customer JC
   billing lines are deliberately NOT silently rewritten from supplier costing. */
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\OslPurchaseTax;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

final class AdminOslPurchaseBatchController extends Controller
{
    private function admin(Request $request): string
    {
        $user=$request->user();
        abort_unless($user && $user->active && $user->isAdmin(),403,'Admin permission required.');
        return mb_substr(trim((string)($user->email ?: $user->name ?: 'ADMIN')),0,180);
    }

    private function document(int $id, bool $lock=false): array
    {
        $query=DB::table('workshop_purchases')->where('id',$id)->where('purchase_type','OSL');
        if($lock)$query->lockForUpdate();
        $anchor=$query->first();
        abort_unless($anchor,404,'OSL Purchase not found.');
        abort_if(strtoupper((string)$anchor->record_status)==='DELETED',404,'Purchase was voided.');
        $batchNo=trim((string)($anchor->purchase_batch_no??''));
        $linesQuery=DB::table('workshop_purchases')->where('purchase_type','OSL')
          ->whereRaw("UPPER(COALESCE(record_status,'ACTIVE')) <> 'DELETED'");
        if($batchNo!=='')$linesQuery->where('purchase_batch_no',$batchNo);
        else $linesQuery->where('id',$id);
        if($lock)$linesQuery->lockForUpdate();
        $lines=$linesQuery->orderBy('purchase_line_no')->orderBy('id')->get()->all();
        if(!$lines)throw ValidationException::withMessages(['purchase'=>'There are no active OSL lines.']);
        $header=null;
        if($batchNo!==''){
          $q=DB::table('workshop_purchase_batches')->where('batch_no',$batchNo)->where('purchase_type','OSL');
          if($lock)$q->lockForUpdate();
          $header=$q->first();
          if(!$header)throw ValidationException::withMessages(['purchase'=>'OSL supplier batch header missing. Editing blocked.']);
        }
        return [$anchor,$header,$lines,$batchNo];
    }

    private function paymentRefs(array $lines,string $batchNo): array
    {
        return array_values(array_unique(array_filter(array_merge([$batchNo],
          array_map(static fn($p)=>(string)$p->purchase_no,$lines)))));
    }

    private function assertSafe(array $lines,string $batchNo): void
    {
        $ids=array_map(static fn($p)=>(int)$p->id,$lines);
        $refs=$this->paymentRefs($lines,$batchNo);
        // Block vendor document revision if a linked voucher, allocation,
        // or directly recorded OSL payment exists.
        if(Schema::hasTable('workshop_payment_vouchers')){
          $paid=DB::table('workshop_payment_vouchers')
            ->whereRaw("UPPER(COALESCE(record_status,'ACTIVE')) NOT IN ('VOID','DELETED','CANCELLED')")
            ->where(function($q)use($ids,$refs){
              $q->whereIn('source_reference',$refs)
                ->orWhere(function($w)use($ids){$w->where('source_type','OSL_JOB')->whereIn('source_id',$ids);});
            })->exists();
          if($paid)throw ValidationException::withMessages(['purchase'=>'Payment Voucher exists against this OSL supplier document. Void/reallocate that voucher through Payment before purchase revision.']);
        }
        if(Schema::hasTable('workshop_payment_allocations') && $refs){
          $paid=DB::table('workshop_payment_allocations as a')
            ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
            ->whereRaw("UPPER(COALESCE(v.record_status,'ACTIVE')) NOT IN ('VOID','DELETED','CANCELLED')")
            ->whereIn('a.purchase_ref',$refs)->exists();
          if($paid)throw ValidationException::withMessages(['purchase'=>'This supplier bill has active payment allocations. Reverse allocations before editing or voiding.']);
        }
        $jobIds=array_values(array_unique(array_filter(array_map(static fn($p)=>(int)$p->job_card_id,$lines))));
        if(!$jobIds)throw ValidationException::withMessages(['purchase'=>'OSL Purchase is not linked to a Job Card. Editing blocked.']);
        $jobs=DB::table('job_cards')->whereIn('id',$jobIds)->lockForUpdate()->get()->keyBy('id');
        foreach($jobIds as $id){
          $job=$jobs->get($id);
          if(!$job)throw ValidationException::withMessages(['purchase'=>'Linked Job Card does not exist.']);
          $status=strtolower(trim((string)($job->status??'')));
          if(in_array($status,['cancelled','closed','invoiced'],true)||!empty($job->invoice_id)){
            throw ValidationException::withMessages(['purchase'=>'Linked Job Card '.$job->job_no.' is Closed, Invoiced or Cancelled. First use the audited JC/Credit Note financial correction workflow.']);
          }
        }
        if(Schema::hasTable('invoices') && Schema::hasColumn('invoices','job_card_id')){
          $query=DB::table('invoices')->whereIn('job_card_id',$jobIds);
          if(Schema::hasColumn('invoices','status')){
            $query->whereRaw("UPPER(COALESCE(status,'ACTIVE')) NOT IN ('CANCELLED','VOID','CREDIT_NOTED')");
          }
          if($query->exists())throw ValidationException::withMessages(['purchase'=>'An invoice exists against a linked Job Card. Supplier purchase is locked until authorised financial reversal.']);
        }
    }

    public function edit(Request $request,int $purchase): View
    {
        $this->admin($request);
        [$anchor,$header,$lines,$batchNo]=$this->document($purchase);
        $vendors=DB::table('workshop_vendors')->where(function($q)use($anchor){
          $q->where('active',1)->orWhere('id',$anchor->vendor_id);
        })->orderBy('vendor_name')->get();
        $masters=DB::table('workshop_osl_master')->where('active',1)->orderBy('osl_name')->get();
        $jobs=DB::table('job_cards')->whereNotIn('status',['cancelled'])
            ->orderByDesc('id')->limit(200)->get(['id','job_no','customer_name','vehicle_reg_no']);
        $jobIds=collect($lines)->pluck('job_card_id')->merge($jobs->pluck('id'))->filter()->unique()->all();
        $allJobs=DB::table('job_cards')->whereIn('id',$jobIds)->get(['id','job_no','customer_name','vehicle_reg_no'])
            ->keyBy('id');
        $labour=DB::table('job_card_labour')->whereIn('job_card_id',$jobIds)
            ->whereRaw("UPPER(COALESCE(mechanic_name,''))='OSL'")
            ->where('billing_included',1)
            ->get(['id','job_card_id','description','labour_rate','gst_percent','standard_hours','total']);
        $blocked=null;
        try { $this->assertSafe($lines,$batchNo); }
        catch(ValidationException $e){$blocked=collect($e->errors())->flatten()->first();}
        $oldTotals=round(array_sum(array_map(static fn($p)=>(float)$p->total_amount,$lines)),2);
        return view('purchases.osl-batch-edit',compact('anchor','header','lines','batchNo','vendors','masters','allJobs','labour','blocked','oldTotals'));
    }

    private function audit(object $row,string $action,array $before,array $after,string $actor,string $reason): void
    {
        DB::table('purchase_audit_logs')->insert([
          'purchase_id'=>$row->id,'purchase_no'=>$row->purchase_no,
          'batch_no'=>$row->purchase_batch_no,'action'=>$action,'actor'=>$actor,
          'reason'=>$reason,'before_json'=>json_encode($before,JSON_UNESCAPED_UNICODE),
          'after_json'=>json_encode($after,JSON_UNESCAPED_UNICODE),'created_at'=>now(),
        ]);
    }

    private function expected(array $lines,float $total): void
    {
        $actual=round(array_sum(array_map(static fn($p)=>(float)$p->total_amount,$lines)),2);
        if(abs($actual-$total)>0.009)throw ValidationException::withMessages([
          'purchase'=>'Purchase amounts changed in another session. Refresh and review before saving.'
        ]);
    }

    private function recalcHeader(?object $header,array $data,array $lines): void
    {
        if(!$header)return; // Legacy one-line OSL purchase without batch.
        $active=array_values(array_filter($lines,static fn($r)=>strtoupper((string)$r->record_status)!=='DELETED'));
        $quantity=0;$gross=0;$discount=0;$taxable=0;$gst=0;$pre=0;
        foreach($active as $line){
          $quantity+=(float)$line->qty;
          $gross+=round((float)$line->qty*(float)$line->unit_cost,2);
          $discount+=(float)$line->discount_amount;
          $taxable+=(float)$line->taxable_amount;
          $gst+=(float)$line->gst_amount;
          $pre+=(float)$line->total_amount;
        }
        $pre=round($pre,2);$grand=round($pre,0,PHP_ROUND_HALF_UP);
        DB::table('workshop_purchase_batches')->where('id',$header->id)->update([
          'vendor_id'=>(int)$data['vendor_id'],'supplier_name'=>$data['vendor_name'],
          'purchase_date'=>$data['purchase_date'],'supplier_invoice'=>$data['supplier_invoice'],
          'notes'=>$data['notes'],'item_count'=>count($active),'total_qty'=>round($quantity,3),
          'gross_amount'=>round($gross,2),'discount_amount'=>round($discount,2),
          'taxable_amount'=>round($taxable,2),'gst_amount'=>round($gst,2),
          'pre_round_total'=>$pre,'round_off'=>round($grand-$pre,2),
          'grand_total'=>$grand,'record_status'=>count($active)?'ACTIVE':'DELETED',
          'updated_at'=>now()
        ]);
    }

    public function update(Request $request,int $purchase): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate([
          'vendor_id'=>['required','integer','min:1'],
          'purchase_date'=>['required','date'],
          'supplier_invoice'=>['nullable','string','max:100'],
          'notes'=>['nullable','string','max:800'],
          'reason'=>['required','string','min:6','max:1000'],
          'expected_total'=>['required','numeric'],
          'lines'=>['required','array','min:1','max:50'],
          'lines.*.purchase_id'=>['nullable','integer','min:1'],
          'lines.*.job_card_id'=>['required','integer','min:1'],
          'lines.*.labour_line_id'=>['required','integer','min:1'],
          'lines.*.osl_master_id'=>['nullable','integer','min:1'],
          'lines.*.description'=>['required','string','max:255'],
          'lines.*.qty'=>['required','numeric','gt:0','lte:10000'],
          'lines.*.unit_cost'=>['required','numeric','min:0','lte:10000000'],
          'lines.*.discount_percent'=>['required','numeric','between:0,100'],
          'lines.*.gst_mode'=>['required','in:EXTRA,INCLUDED,NONE'],
          'lines.*.gst_percent'=>['required','numeric','between:0,100'],
        ]);
        $result=DB::transaction(function()use($purchase,$data,$actor){
          [$anchor,$header,$old,$batchNo]=$this->document($purchase,true);
          $this->expected($old,(float)$data['expected_total']);
          $this->assertSafe($old,$batchNo);
          $vendor=DB::table('workshop_vendors')->where('id',$data['vendor_id'])->where('active',1)->lockForUpdate()->first();
          if(!$vendor)throw ValidationException::withMessages(['vendor_id'=>'Select an active Vendor from Vendor Database.']);
          $oldById=collect($old)->keyBy('id');
          $seen=[];$used=[];$newIds=[];
          $details=[
            'vendor_id'=>(int)$vendor->id,'vendor_name'=>$vendor->vendor_name,
            'purchase_date'=>$data['purchase_date'],
            'supplier_invoice'=>trim((string)($data['supplier_invoice']??''))?:null,
            'notes'=>trim((string)($data['notes']??''))?:null,
          ];
          foreach(array_values($data['lines']) as $i=>$line){
            $num=$i+1;
            $id=(int)($line['purchase_id']??0);
            if($id>0){
              if(!isset($oldById[$id])||isset($used[$id]))throw ValidationException::withMessages(['lines'=>"Line {$num}: Invalid or duplicate Purchase ID."]);
              $oldRow=$oldById[$id];$used[$id]=true;
            } else {
              if($batchNo==='')throw ValidationException::withMessages(['lines'=>'Legacy unbatched purchase cannot add lines. Create a separate OSL Purchase.']);
              $oldRow=null;
            }
            $jid=(int)$line['job_card_id'];$labourId=(int)$line['labour_line_id'];
            if($oldRow && ($jid!==(int)$oldRow->job_card_id||$labourId!==(int)$oldRow->labour_line_id)){
              throw ValidationException::withMessages(['lines'=>"Line {$num}: Changing the linked JC/billing line on an existing purchase needs JC-side audited revision."]);
            }
            $job=DB::table('job_cards')->where('id',$jid)->lockForUpdate()->first();
            if(!$job||in_array(strtolower((string)$job->status),['closed','invoiced','cancelled'],true)||!empty($job->invoice_id)){
              throw ValidationException::withMessages(['lines'=>"Line {$num}: Linked JC missing or financially locked."]);
            }
            $billing=DB::table('job_card_labour')->where('id',$labourId)->where('job_card_id',$jid)
              ->whereRaw("UPPER(COALESCE(mechanic_name,''))='OSL'")->where('billing_included',1)->lockForUpdate()->first();
            if(!$billing)throw ValidationException::withMessages(['lines'=>"Line {$num}: Select a valid existing JC OSL work line."]);
            $key=$jid.':'.$labourId;
            if(isset($seen[$key]))throw ValidationException::withMessages(['lines'=>"Line {$num}: This JC OSL billing line is duplicated in the bill."]);
            $seen[$key]=true;
            if(!$oldRow && DB::table('workshop_purchases')->where('purchase_type','OSL')
              ->where('job_card_id',$jid)->where('labour_line_id',$labourId)->exists()){
              throw ValidationException::withMessages(['lines'=>"Line {$num}: This JC OSL line was already purchased (including historical/voided purchase). Add another OSL work to JC first."]);
            }
            $masterId=(int)($line['osl_master_id']??0);
            if($masterId>0 && !DB::table('workshop_osl_master')->where('id',$masterId)->where('active',1)->exists()){
              throw ValidationException::withMessages(['lines'=>"Line {$num}: OSL Master missing or inactive."]);
            }
            $quote=OslPurchaseTax::quote((float)$line['qty'],(float)$line['unit_cost'],(float)$line['discount_percent'],(float)$line['gst_percent'],(string)$line['gst_mode']);
            $payload=[
              'vendor_id'=>$details['vendor_id'],'supplier_name'=>$details['vendor_name'],
              'purchase_date'=>$details['purchase_date'],'supplier_invoice'=>$details['supplier_invoice'],
              'notes'=>$details['notes'],'purchase_line_no'=>$num,
              'job_card_id'=>$jid,'labour_line_id'=>$labourId,
              'osl_master_id'=>$masterId?:null,'description'=>mb_substr(trim($line['description']),0,255),
              'qty'=>(float)$line['qty'],'unit_cost'=>(float)$line['unit_cost'],
              'discount_percent'=>(float)$line['discount_percent'],'discount_amount'=>$quote['discount'],
              'gst_mode'=>$quote['mode'],'gst_percent'=>(float)$line['gst_percent'],
              'taxable_amount'=>$quote['taxable'],'gst_amount'=>$quote['gst'],'total_amount'=>$quote['total'],
              'customer_rate'=>(float)$billing->labour_rate,
              'customer_gst_percent'=>(float)$billing->gst_percent,
              'customer_amount'=>(float)$billing->total,
              'updated_at'=>now()
            ];
            if($oldRow){
              $before=(array)$oldRow;
              DB::table('workshop_purchases')->where('id',$id)->update($payload+['edit_count'=>((int)$oldRow->edit_count)+1]);
              $after=(array)DB::table('workshop_purchases')->where('id',$id)->first();
              $this->audit($oldRow,'EDIT_OSL_PURCHASE_LINE',$before,$after,$actor,$data['reason']);
              $newIds[]=$id;
            }else{
              $no='OSL-'.now('Asia/Kolkata')->format('ymdHis').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
              $insert=$payload+[
                'purchase_no'=>$no,'purchase_batch_no'=>$batchNo,'purchase_type'=>'OSL',
                'billing_line_created'=>0,'customer_billing_status'=>'OPEN_LINKED',
                'record_status'=>'ACTIVE','created_by'=>$actor,'created_at'=>now()
              ];
              $newId=DB::table('workshop_purchases')->insertGetId($insert);
              $newRow=DB::table('workshop_purchases')->where('id',$newId)->first();
              $this->audit($newRow,'ADD_OSL_PURCHASE_LINE',[],(array)$newRow,$actor,$data['reason']);
              $newIds[]=$newId;
            }
          }
          foreach($old as $line){
            if(isset($used[$line->id]))continue;
            $before=(array)$line;
            // Removing a purchase DOES NOT remove already planned JC OSL billing.
            // This becomes a reconciliation pending item for finance.
            DB::table('workshop_purchases')->where('id',$line->id)->update([
              'record_status'=>'DELETED','deleted_at'=>now(),'updated_at'=>now(),
              'edit_count'=>((int)$line->edit_count)+1
            ]);
            $after=(array)DB::table('workshop_purchases')->where('id',$line->id)->first();
            $this->audit($line,'REMOVE_OSL_PURCHASE_LINE',$before,$after,$actor,$data['reason']);
          }
          $active=DB::table('workshop_purchases')->where('purchase_type','OSL')
            ->whereRaw("UPPER(COALESCE(record_status,'ACTIVE')) <> 'DELETED'");
          if($batchNo!=='')$active->where('purchase_batch_no',$batchNo);
          else $active->where('id',$anchor->id);
          $this->recalcHeader($header,$details,$active->get()->all());
          return (int)($newIds[0]??$anchor->id);
        },3);
        return redirect()->route('erp.purchases.show',$result)
          ->with('success','OSL supplier bill updated with line-wise audit. Customer billing remains under linked Job Card control.');
    }

    public function void(Request $request,int $purchase): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate([
          'void_reason'=>['required','string','min:8','max:1000'],
          'expected_total'=>['required','numeric']
        ]);
        DB::transaction(function()use($purchase,$data,$actor){
          [$anchor,$header,$lines,$batchNo]=$this->document($purchase,true);
          $this->expected($lines,(float)$data['expected_total']);
          $this->assertSafe($lines,$batchNo);
          foreach($lines as $line){
            $before=(array)$line;
            DB::table('workshop_purchases')->where('id',$line->id)->update([
              'record_status'=>'DELETED','deleted_at'=>now(),'updated_at'=>now(),
              'edit_count'=>((int)$line->edit_count)+1
            ]);
            $after=(array)DB::table('workshop_purchases')->where('id',$line->id)->first();
            $this->audit($line,'VOID_OSL_SUPPLIER_BILL',$before,$after,$actor,$data['void_reason']);
          }
          if($header)DB::table('workshop_purchase_batches')->where('id',$header->id)
            ->update(['record_status'=>'DELETED','item_count'=>0,'total_qty'=>0,'gross_amount'=>0,
              'discount_amount'=>0,'taxable_amount'=>0,'gst_amount'=>0,'pre_round_total'=>0,
              'round_off'=>0,'grand_total'=>0,'updated_at'=>now()]);
        },3);
        return redirect()->route('erp.purchases.index')
          ->with('success','OSL supplier purchase VOID completed with audited history. JC billing lines were retained for reconciliation.');
    }
}
