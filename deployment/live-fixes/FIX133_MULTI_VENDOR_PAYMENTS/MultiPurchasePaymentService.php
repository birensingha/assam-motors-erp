<?php
namespace App\Services;

use App\Models\WorkshopPaymentVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * FIX133: one payment voucher -> multiple PURCHASE_INVOICE allocations.
 * All bills must be from one vendor and have an existing positive outstanding.
 * Entire save is a single DB transaction, with deterministic bill locks and
 * idempotent request_key. Voiding the parent removes active allocation impact.
 */
final class MultiPurchasePaymentService
{
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['allocations'=>$message]);
    }

    public function save(array $data,string $actor): WorkshopPaymentVoucher
    {
        if (!Schema::hasTable('workshop_payment_allocations')) {
            $this->reject('Multi-bill allocation table missing. No payment saved.');
        }
        if (strtoupper((string)($data['source_type']??''))!=='PURCHASE_INVOICE') {
            $this->reject('Multi-bill payment is supported only for Purchase Bills.');
        }
        $key=(string)($data['request_key']??'');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',$key)) {
            $this->reject('Invalid payment request key. Reload form.');
        }
        $items=$data['allocations']??[];
        if (!is_array($items) || count($items)<2 || count($items)>30) {
            $this->reject('Choose between 2 and 30 Purchase Bills.');
        }
        $alloc=[];
        foreach($items as $item) {
            if(!is_array($item))$this->reject('Invalid bill allocation.');
            $ref=trim((string)($item['ref']??''));
            $amount=round((float)($item['amount']??0),2);
            if ($ref==='' || strlen($ref)>180 || $amount<=0 || !is_finite($amount)) {
                $this->reject('Select valid bills and positive allocations.');
            }
            if(isset($alloc[$ref]))$this->reject('Duplicate Purchase Bill '.$ref.' selected.');
            $alloc[$ref]=$amount;
        }
        ksort($alloc,SORT_STRING);
        $total=round(array_sum($alloc),2);
        if ($total<=0 || abs($total-(float)($data['amount']??0))>0.01) {
            $this->reject('Payment Amount must equal sum of bill allocations.');
        }
        $paymentMode=trim((string)($data['payment_mode']??'Cash'));
        if(!in_array($paymentMode,['Cash','UPI','Bank Transfer','Cheque','Card'],true)) {
            $this->reject('Choose valid Payment Mode.');
        }
        $result=DB::transaction(function()use($key,$alloc,$total,$data,$actor,$paymentMode){
            $prior=(int)DB::table('workshop_payment_allocations')
                ->where('request_key',$key)->value('payment_voucher_id');
            if($prior>0) {
                $existing=WorkshopPaymentVoucher::query()->find($prior);
                if($existing && $existing->record_status==='ACTIVE' &&
                   abs((float)$existing->amount-$total)<0.01) return $existing;
                $this->reject('This payment request key was already used. Reload New Payment Voucher.');
            }
            $vendorId=null;$allJobIds=[];$allAssigned=true;
            foreach($alloc as $ref=>$amount) {
                $rows=DB::table('workshop_purchases')
                    ->whereRaw("COALESCE(NULLIF(purchase_batch_no,''),purchase_no)=?",[$ref])
                    ->where('purchase_type','PART')
                    ->whereRaw("COALESCE(record_status,'ACTIVE') <> 'DELETED'")
                    ->orderBy('id')->lockForUpdate()->get();
                if($rows->isEmpty())$this->reject('Purchase Bill '.$ref.' no longer active.');
                $vendorIds=$rows->pluck('vendor_id')->map(fn($id)=>(int)$id)->unique();
                if($vendorIds->count()!==1 || (int)$vendorIds->first()<=0) {
                    $this->reject('Purchase Bill '.$ref.' vendor data is inconsistent.');
                }
                $billVendor=(int)$vendorIds->first();
                if($vendorId===null)$vendorId=$billVendor;
                if($billVendor!==$vendorId)$this->reject('All selected bills must belong to ONE vendor.');
                $batch=DB::table('workshop_purchase_batches')
                    ->where('batch_no',$ref)->where('purchase_type','PART')
                    ->where('record_status','ACTIVE')->lockForUpdate()->first();
                $dueTotal=$batch ? (float)$batch->grand_total : (float)$rows->sum('total_amount');
                $single=(float)DB::table('workshop_payment_vouchers')
                    ->where('source_type','PURCHASE_INVOICE')->where('source_reference',$ref)
                    ->where('record_status','ACTIVE')->sum('amount');
                $multi=(float)DB::table('workshop_payment_allocations as a')
                    ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                    ->where('a.purchase_ref',$ref)->where('v.record_status','ACTIVE')->sum('a.amount');
                $available=round($dueTotal-$single-$multi,2);
                if($available<0.009 || $amount>$available+0.009){
                    $this->reject('Purchase Bill '.$ref.' outstanding ₹'.number_format(max(0,$available),2).
                       ', requested ₹'.number_format($amount,2).'. No payment saved.');
                }
                $ids=$rows->pluck('job_card_id')->map(fn($v)=>(int)$v);
                if($ids->contains(0))$allAssigned=false;
                foreach($ids as $id)if($id>0)$allJobIds[$id]=true;
            }
            $vendor=DB::table('workshop_vendors')->where('id',$vendorId)->lockForUpdate()->first();
            if(!$vendor)$this->reject('Vendor no longer exists.');
            $singleJob=$allAssigned && count($allJobIds)===1 ? (int)array_key_first($allJobIds) : null;
            $payment=WorkshopPaymentVoucher::query()->create([
                'payment_date'=>$data['payment_date']??now('Asia/Kolkata')->toDateString(),
                'source_type'=>'PURCHASE_INVOICE',
                'source_id'=>null,
                'source_reference'=>null,
                'job_card_id'=>$singleJob,
                'payee_type'=>'VENDOR','vendor_id'=>$vendorId,
                'payee_name'=>mb_substr((string)$vendor->vendor_name,0,180),
                'amount'=>$total,'payment_mode'=>$paymentMode,
                'account_name'=>mb_substr(trim((string)($data['account_name']??'')),0,120) ?: null,
                'reference_no'=>mb_substr(trim((string)($data['reference_no']??'')),0,150) ?: null,
                'notes'=>mb_substr(trim((string)($data['notes']??'')),0,2000) ?: null,
                'record_status'=>'ACTIVE','created_by'=>$actor,
            ]);
            $payment->voucher_no='PAY-'.now('Asia/Kolkata')->format('Ymd').'-'.str_pad((string)$payment->id,6,'0',STR_PAD_LEFT);
            $payment->source_reference='MULTI-PURCHASE-'.$payment->id;
            $payment->save();
            foreach($alloc as $ref=>$amount){
                DB::table('workshop_payment_allocations')->insert([
                    'payment_voucher_id'=>$payment->id,
                    'purchase_ref'=>$ref,
                    'amount'=>$amount,
                    'request_key'=>$key,
                    'created_at'=>now(),'updated_at'=>now()
                ]);
            }
            return $payment;
        },3);
        return $result;
    }
}
