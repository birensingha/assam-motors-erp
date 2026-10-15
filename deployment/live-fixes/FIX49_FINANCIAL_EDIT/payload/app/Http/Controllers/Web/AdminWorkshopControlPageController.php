<?php

declare(strict_types=1);
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * FIX48: admin-only workshop finance editing + Vendor Database.
 * No mutation of issued invoice, posted customer ledger or stock quantities.
 * Locks the relevant rows and audits every edit.
 */
final class AdminWorkshopControlPageController extends Controller
{
    private function admin(Request $request): ErpAuthUser
    {
        $user = $request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(), 403, 'Admin permission required.');
        return $user;
    }

    private function cols(string $table): array
    {
        return Schema::hasTable($table) ? Schema::getColumnListing($table) : [];
    }

    private function filtered(string $table, array $values): array
    {
        $cols = $this->cols($table);
        return array_intersect_key($values, array_flip($cols));
    }

    private function value(object|array|null $obj, array $keys, mixed $default = null): mixed
    {
        $row = (array) ($obj ?? []);
        foreach ($keys as $key) if (array_key_exists($key, $row) && $row[$key] !== null) return $row[$key];
        return $default;
    }

    private function auditJob(int $job, string $entity, int $id, array $before, array $after, string $reason, ErpAuthUser $user): void
    {
        abort_unless(Schema::hasTable('job_card_change_audits'), 409, 'Job Card audit table missing. No changes saved.');
        $row = $this->filtered('job_card_change_audits', [
            'job_card_id'=>$job,'entity_type'=>$entity,'entity_id'=>$id,'action'=>'FIX48_ADMIN_AMOUNT_EDIT',
            'before_json'=>json_encode($before, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'after_json'=>json_encode($after, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'reason'=>$reason,'actor'=>$user->email ?: $user->name ?: 'Admin',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('job_card_change_audits')->insert($row);
    }

    private function quoteFromLine(float $qty, float $rate, float $discount, float $gst): array
    {
        $gross = round($qty * $rate, 2);
        $discountAmount = round($gross * $discount / 100, 2);
        $taxable = round($gross - $discountAmount, 2);
        $gstAmount = round($taxable * $gst / 100, 2);
        return compact('gross','discountAmount','taxable','gstAmount') + ['final'=>round($taxable + $gstAmount, 2)];
    }

    /**
     * No invoice mutation, no alternate billing engine. Recalculate from stored
     * discounted TAXABLE line values and add GST exactly once in the JC header.
     */
    private function recalculateJob(int $job): array
    {
        $parts = DB::table('job_card_parts')->where('job_card_id',$job)->get();
        $labour = DB::table('job_card_labour')->where('job_card_id',$job)->get();
        $partsTotal = round((float)$parts->sum('total'),2);
        $labourTotal = round((float)$labour->sum('total'),2);
        $gst = 0.0;
        foreach ($parts->concat($labour) as $line) {
            $gst += round(((float)($line->total ?? 0)) * ((float)($line->gst_percent ?? 0)) / 100, 2);
        }
        $gst = round($gst,2);
        $preRound = round($partsTotal + $labourTotal + $gst,2);
        $grand = round($preRound,0);
        $roundOff = round($grand - $preRound,2);
        $header = $this->filtered('job_cards',[
            'parts_total'=>$partsTotal,'labour_total'=>$labourTotal,'gst_total'=>$gst,
            'pre_round_total'=>$preRound,'round_off'=>$roundOff,'grand_total'=>$grand,
            'updated_at'=>now(),
        ]);
        DB::table('job_cards')->where('id',$job)->update($header);
        return compact('partsTotal','labourTotal','gst','preRound','grand','roundOff');
    }

    private function activeInvoice(int $job): bool
    {
        return Schema::hasTable('invoices') && DB::table('invoices')->where('job_card_id',$job)
            ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists();
    }

    public function jobAmounts(Request $request, int $job): View
    {
        $this->admin($request);
        abort_unless(Schema::hasTable('job_cards') && Schema::hasTable('job_card_parts') && Schema::hasTable('job_card_labour'), 503);
        $card = DB::table('job_cards')->where('id',$job)->first();
        abort_unless($card,404);
        $invoiced = $this->activeInvoice($job) || !empty($card->invoice_id) || strtolower((string)$card->status)==='invoiced';
        $parts=DB::table('job_card_parts')->where('job_card_id',$job)->orderBy('id')->get();
        $labour=DB::table('job_card_labour')->where('job_card_id',$job)->orderBy('id')->get();
        $taxBasisUnverified=(float)($card->gst_total??0)<0.005
            && $parts->concat($labour)->contains(fn($ln)=>(float)($ln->total??0)>0
                && (float)($ln->gst_percent??0)>0);
        return view('workshop-controls.job-amounts',[
            'card'=>$card,'invoiced'=>$invoiced,
            'taxBasisUnverified'=>$taxBasisUnverified,
            'parts'=>$parts,'labour'=>$labour,
        ]);
    }

    public function updateJobLine(Request $request, int $job, string $kind, int $line): RedirectResponse
    {
        $user = $this->admin($request);
        $data = $request->validate([
            'quantity'=>['required','numeric','gt:0','lte:10000'],
            'rate'=>['required','numeric','min:0','lte:10000000'],
            'discount_percent'=>['required','numeric','between:0,100'],
            'gst_percent'=>['required','numeric','between:0,100'],
            'expected_old_total'=>['required','numeric'],
            'reason'=>['required','string','min:5','max:1000'],
        ]);
        abort_unless(in_array($kind,['parts','labour'],true),404);
        $table=$kind==='parts'?'job_card_parts':'job_card_labour';
        $qtyCol=$kind==='parts'?'qty':'standard_hours';
        $rateCol=$kind==='parts'?'rate':'labour_rate';
        $required=[$qtyCol,$rateCol,'discount_percent','discount_amount','gst_percent','total'];
        if(array_diff($required,$this->cols($table))){
            throw ValidationException::withMessages(['rate'=>'Live schema differs: correction paused until field mapping is confirmed.']);
        }
        DB::transaction(function()use($job,$kind,$line,$table,$qtyCol,$rateCol,$data,$user){
            $card=DB::table('job_cards')->where('id',$job)->lockForUpdate()->first();
            abort_unless($card,404);
            if($this->activeInvoice($job) || !empty($card->invoice_id) || in_array(strtolower((string)$card->status),['invoiced','cancelled'],true)){
                throw ValidationException::withMessages(['reason'=>'Issued Invoice linked: original billing cannot be overwritten. Use audited invoice revision workflow.']);
            }
            $row=DB::table($table)->where('job_card_id',$job)->where('id',$line)->lockForUpdate()->first();
            abort_unless($row,404);
            if(abs((float)$row->total-(float)$data['expected_old_total'])>0.009){
                throw ValidationException::withMessages(['rate'=>'This line changed on another device. Refresh before editing.']);
            }
            // Billing edits must never silently change inventory quantity or
            // technician ROT standard hours. Edit quantity via the corresponding
            // stock issue/reversal or ROT workflow, not this finance form.
            $oldQty=(float)$row->{$qtyCol};
            if(abs($oldQty-(float)$data['quantity'])>0.0001){
                $label=$kind==='parts'?'Part Qty is stock-linked':'ROT Standard Hours affect technician performance';
                throw ValidationException::withMessages(['quantity'=>$label.'. Use the dedicated stock/ROT editing workflow; this screen edits prices only.']);
            }
            // Historical GST ambiguity guard: invoices and older Job Cards may
            // store gross line totals with GST already embedded. A previous
            // zero header GST plus non-zero line GST does NOT prove exclusive
            // rates. Do not reapply 18% to untouched historical lines.
            $allLines=DB::table('job_card_parts')->where('job_card_id',$job)->get(['total','gst_percent'])
                ->concat(DB::table('job_card_labour')->where('job_card_id',$job)->get(['total','gst_percent']));
            $taxableLines=$allLines->filter(fn($ln)=>(float)($ln->total??0)>0
                && (float)($ln->gst_percent??0)>0);
            if((float)($card->gst_total??0)<0.005 && $taxableLines->isNotEmpty()){
                throw ValidationException::withMessages(['rate'=>'Historical Job Card GST basis is ambiguous: existing amount may ALREADY INCLUDE GST. Do not add GST again. Tax-basis reconciliation must be completed before changing this Job Card. No values saved.']);
            }
            // JC pricing amount edit does not touch purchase settlements or ROT session clocks.
            // Purchase/OSL payment guards belong only in updateOsl(), not this method.
            $q=$this->quoteFromLine((float)$data['quantity'],(float)$data['rate'],(float)$data['discount_percent'],(float)$data['gst_percent']);
            $fields=$this->filtered($table,[
                $qtyCol=>(float)$data['quantity'],$rateCol=>(float)$data['rate'],
                'discount_percent'=>(float)$data['discount_percent'],
                'discount_amount'=>$q['discountAmount'],
                'gst_percent'=>(float)$data['gst_percent'],
                'total'=>$q['taxable'], // taxable line total; GST stored at JC header
                'updated_at'=>now(),
            ]);
            DB::table($table)->where('id',$line)->update($fields);
            $newLine=(array)DB::table($table)->where('id',$line)->first();
            $totals=$this->recalculateJob($job);
            $this->auditJob($job,$kind==='parts'?'JOB_CARD_PART':'JOB_CARD_LABOUR',$line,
                (array)$row,$newLine,$data['reason'].' | JC FINAL '.number_format($totals['grand'],2),$user);
        }, 3);
        return redirect()->route('erp.fix48.job-amounts',$job)->with('success','Amount recalculated; GST separately posted to Job Card total.');
    }

    private function oslRow(int $id): object
    {
        abort_unless(Schema::hasTable('workshop_purchases'),503);
        $row=DB::table('workshop_purchases')->where('id',$id)->first();
        abort_unless($row,404);
        $type=strtoupper((string)$this->value($row,['purchase_type','type'],''));
        abort_unless(str_contains($type,'OSL')||str_contains($type,'OUTSIDE'),404);
        return $row;
    }

    public function oslIndex(Request $request): View
    {
        $this->admin($request);
        abort_unless(Schema::hasTable('workshop_purchases'),503);
        $typeColumn=in_array('purchase_type',$this->cols('workshop_purchases'),true)?'purchase_type':(in_array('type',$this->cols('workshop_purchases'),true)?'type':null);
        abort_unless($typeColumn!==null,503,'OSL purchase type field missing.');
        $rows=DB::table('workshop_purchases')->where(function($q)use($typeColumn){
            $q->whereRaw('UPPER('.$typeColumn.") LIKE '%OSL%'")
              ->orWhereRaw('UPPER('.$typeColumn.") LIKE '%OUTSIDE%'");
        })->orderByDesc('id')->limit(300)->get();
        $term=mb_strtolower(trim((string)$request->query('q','')));
        if($term!=='') $rows=$rows->filter(function($p)use($term){
            return str_contains(mb_strtolower(implode(' ',array_map(fn($x)=>(string)$x,(array)$p))),$term);
        });
        return view('workshop-controls.osl-index',['rows'=>$rows,'term'=>$term]);
    }

    public function editOsl(Request $request,int $purchase): View
    {
        $this->admin($request);
        $row=$this->oslRow($purchase);
        $jobId=(int)($row->job_card_id ?? 0);
        $job=$jobId && Schema::hasTable('job_cards') ? DB::table('job_cards')->where('id',$jobId)->first() : null;
        $blocked=$job && ($this->activeInvoice($jobId)||!empty($job->invoice_id)||in_array(strtolower((string)$job->status),['closed','invoiced','cancelled'],true));
        return view('workshop-controls.osl-edit',['row'=>$row,'job'=>$job,'blocked'=>$blocked]);
    }

    public function updateOsl(Request $request,int $purchase): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'quantity'=>['required','numeric','gt:0','lte:10000'],
            'vendor_rate'=>['required','numeric','min:0','lte:10000000'],
            'discount_percent'=>['required','numeric','between:0,100'],
            'gst_percent'=>['required','numeric','between:0,100'],
            'reason'=>['required','string','min:5','max:1000'],
            'expected_old_total'=>['required','numeric'],
        ]);
        DB::transaction(function()use($purchase,$data,$user){
            $old=$this->oslRow($purchase);
            $row=DB::table('workshop_purchases')->where('id',$purchase)->lockForUpdate()->first();
            $jobId=(int)($row->job_card_id??0);
            if($jobId){
                $job=DB::table('job_cards')->where('id',$jobId)->lockForUpdate()->first();
                if($job && ($this->activeInvoice($jobId)||!empty($job->invoice_id)||in_array(strtolower((string)$job->status),['closed','invoiced','cancelled'],true))){
                    throw ValidationException::withMessages(['reason'=>'Linked Job Card is closed/invoiced. Apply OSL correction through posted-bill revision, not by overwriting supplier bill.']);
                }
            }
            if(abs((float)$this->value($row,['total_amount','total','grand_total'],0)-(float)$data['expected_old_total'])>0.009){
                throw ValidationException::withMessages(['reason'=>'Supplier bill changed elsewhere. Reload before editing.']);
            }
            $cols=$this->cols('workshop_purchases');
            $rateCol=in_array('vendor_rate',$cols,true)?'vendor_rate':'rate';
            $qtyCol=in_array('qty',$cols,true)?'qty':'quantity';
            foreach([$qtyCol,$rateCol,'discount_percent','gst_percent'] as $col){
                if(!in_array($col,$cols,true)) throw ValidationException::withMessages(['reason'=>'Purchase schema mapping incomplete: '.$col]);
            }
            // Do not allow editing supplier-bill costing when posted, paid, or allocated;
            // requires the existing PurchaseService reversal/update hooks.
            if(in_array(strtolower((string)$this->value($row,['record_status','billing_status','status'],'')),['paid','posted','closed','issued','billed','locked'],true)){
                throw ValidationException::withMessages(['reason'=>'Posted/paid OSL requires authorised Purchase revision; this edit did not change it.']);
            }
            if(!in_array('total_amount',$cols,true) && !in_array('total',$cols,true)){
                throw ValidationException::withMessages(['reason'=>'Purchase row does not have a verified payable total column.']);
            }
            if(Schema::hasTable('workshop_payment_vouchers')){
                $refs=array_filter([(string)($row->purchase_batch_no??''),(string)($row->purchase_no??'')]);
                if($refs && DB::table('workshop_payment_vouchers')->where('record_status','ACTIVE')
                    ->where('source_type','OSL_JOB')->whereIn('source_reference',$refs)->exists()){
                    throw ValidationException::withMessages(['reason'=>'OSL source already has payment allocation. Reverse/reissue before changing supplier cost.']);
                }
            }
            $q=$this->quoteFromLine((float)$data['quantity'],(float)$data['vendor_rate'],(float)$data['discount_percent'],(float)$data['gst_percent']);
            $new=$this->filtered('workshop_purchases',[
                $qtyCol=>(float)$data['quantity'],$rateCol=>(float)$data['vendor_rate'],
                'discount_percent'=>(float)$data['discount_percent'],
                'discount_amount'=>$q['discountAmount'],
                'gst_percent'=>(float)$data['gst_percent'],
                'taxable_amount'=>$q['taxable'],'tax_amount'=>$q['gstAmount'],
                'gst_amount'=>$q['gstAmount'],'total'=>$q['final'],'total_amount'=>$q['final'],
                'grand_total'=>$q['final'],'updated_at'=>now(),
            ]);
            // OSL rows are Job Card linked in the existing multi-line flow.
            // Use the established Job Card before/after audit rather than
            // guessing incompatible PurchaseAuditLog column names.
            if($jobId<=0 || !Schema::hasTable('job_card_change_audits')){
                throw ValidationException::withMessages(['reason'=>'OSL is not linked to a valid Job Card audit trail. Edit cancelled.']);
            }
            // A supplier purchase batch is also a financial record. Update its
            // totals in the same transaction or refuse rather than leaving stale
            // batch/ledger sums. Any payments against this bill require review.
            $batchNo=trim((string)($row->purchase_batch_no??''));
            $batch=null;
            if($batchNo!=='' && Schema::hasTable('workshop_purchase_batches')){
                $bc=$this->cols('workshop_purchase_batches');
                if(!in_array('batch_no',$bc,true) && !in_array('purchase_batch_no',$bc,true)){
                    throw ValidationException::withMessages(['reason'=>'OSL batch schema is incompatible; edit not saved.']);
                }
                $batchKey=in_array('batch_no',$bc,true)?'batch_no':'purchase_batch_no';
                $batch=DB::table('workshop_purchase_batches')->where($batchKey,$batchNo)->lockForUpdate()->first();
                if(!$batch) throw ValidationException::withMessages(['reason'=>'OSL batch header missing. Edit not saved.']);
                if(in_array(strtolower((string)$this->value($batch,['status','payment_status'],'open')),['posted','paid','settled','closed','locked'],true)){
                    throw ValidationException::withMessages(['reason'=>'Supplier batch is posted/paid: use finance revision.']);
                }
            }
            DB::table('workshop_purchases')->where('id',$purchase)->update($new);
            if($batch){
                $batchLines=DB::table('workshop_purchases')->where('purchase_batch_no',$batchNo)->get();
                $batchCols=$this->cols('workshop_purchase_batches');
                $sumTaxable=0.0;$sumTax=0.0;$sumTotal=0.0;
                foreach($batchLines as $ln){
                    $sumTotal+=round((float)$this->value($ln,['total_amount','total','grand_total'],0),2);
                    $sumTaxable+=round((float)$this->value($ln,['taxable_amount','net_amount'],0),2);
                    $sumTax+=round((float)$this->value($ln,['tax_amount','gst_amount'],0),2);
                }
                // Only update financial headers with recognized semantics.
                $fields=$this->filtered('workshop_purchase_batches',[
                    'subtotal'=>round($sumTaxable,2),
                    'taxable_total'=>round($sumTaxable,2),
                    'gst_total'=>round($sumTax,2),
                    'tax_total'=>round($sumTax,2),
                    'grand_total'=>round($sumTotal+(float)($batch->round_off??0),2),
                    'total_amount'=>round($sumTotal+(float)($batch->round_off??0),2),
                    'updated_at'=>now(),
                ]);
                $availableTotals=array_intersect(['grand_total','total_amount'],array_keys($fields));
                if(!$availableTotals) throw ValidationException::withMessages(['reason'=>'OSL batch financial columns unknown; edit rolled back.']);
                DB::table('workshop_purchase_batches')->where('id',$batch->id)->update($fields);
            }
            $after=(array)DB::table('workshop_purchases')->where('id',$purchase)->first();
            $this->auditJob($jobId,'OSL_PURCHASE',$purchase,(array)$old,$after,$data['reason'],$user);
            // Avoid silently changing the Job Card labour billing rate or vendor
            // payable until linked services can be reconciled safely.
        },3);
        return redirect()->route('erp.fix48.osl-edit',$purchase)->with('success','OSL cost changed; please verify linked JC and Vendor Ledger.');
    }

    public function vendorDatabase(Request $request): View
    {
        $this->admin($request);
        abort_unless(Schema::hasTable('workshop_vendors'),503);
        $query=DB::table('workshop_vendors')->orderByDesc('id');
        $q=trim((string)$request->query('q',''));
        if($q!==''){
            $cols=$this->cols('workshop_vendors');
            $search=array_values(array_intersect(['name','vendor_name','business_name','mobile','phone','gstin','vendor_code','email'],$cols));
            if($search) $query->where(function($w)use($search,$q){foreach($search as $col)$w->orWhere($col,'like','%'.$q.'%');});
        }
        $vendors=$query->paginate(24)->withQueryString();
        $vendorIds=$vendors->pluck('id')->all();
        $purchaseTotals=[];$paymentTotals=[];
        if($vendorIds && Schema::hasTable('workshop_purchases') && in_array('vendor_id',$this->cols('workshop_purchases'),true)){
            $cols=$this->cols('workshop_purchases');
            $amountCol=in_array('total_amount',$cols,true)?'total_amount':(in_array('total',$cols,true)?'total':(in_array('grand_total',$cols,true)?'grand_total':null));
            if($amountCol) $purchaseTotals=DB::table('workshop_purchases')->whereIn('vendor_id',$vendorIds)
                ->selectRaw('vendor_id, SUM('.$amountCol.') as amount')->groupBy('vendor_id')->pluck('amount','vendor_id')->all();
        }
        if($vendorIds && Schema::hasTable('workshop_vendor_payments') && in_array('vendor_id',$this->cols('workshop_vendor_payments'),true)){
            $cols=$this->cols('workshop_vendor_payments');
            $amountCol=in_array('amount',$cols,true)?'amount':(in_array('payment_amount',$cols,true)?'payment_amount':null);
            if($amountCol) $paymentTotals=DB::table('workshop_vendor_payments')->whereIn('vendor_id',$vendorIds)
                ->selectRaw('vendor_id, SUM('.$amountCol.') as amount')->groupBy('vendor_id')->pluck('amount','vendor_id')->all();
        }
        return view('workshop-controls.vendor-database',compact('vendors','purchaseTotals','paymentTotals','q'));
    }
}