<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FIX57. Never reverse an original receipt. Accounting journal is append-only.
 * Full paid invoice: receipt allocation released, original invoice fully
 * credited, amount parked ON ACCOUNT for the same customer/job card.
 */
final class PaidInvoiceAdvanceService
{
    private static function fail(string $text): never
    {
        throw ValidationException::withMessages(['finance'=>$text]);
    }
    private static function money(mixed $v): int
    {
        return (int)round((float)$v*100);
    }
    private static function ledger(int $customerId,?int $invoiceId,int $jobId,?int $vehicleId,
        ?int $receiptId,string $type,string $referenceNo,?int $referenceId,int $debit,int $credit,
        string $reason,string $customerName,?string $email,string $date): int
    {
        return (int)DB::table('customer_ledger')->insertGetId([
            'customer_id'=>$customerId,'invoice_id'=>$invoiceId,
            'job_card_id'=>$jobId,'vehicle_id'=>$vehicleId,
            'payment_id'=>$receiptId,'entry_type'=>$debit>0?'DEBIT':'CREDIT',
            'amount'=>($debit?:$credit)/100,'reference_type'=>$type,
            'reference_id'=>$referenceId,'reference_no'=>$referenceNo,
            'debit'=>$debit/100,'credit'=>$credit/100,
            'note'=>$reason,'narration'=>$reason,'customer_name'=>$customerName,
            'customer_email'=>$email,'entry_date'=>$date,'created_at'=>now('Asia/Kolkata'),
        ]);
    }

    public static function transfer(int $jobId,int $invoiceId,string $reason,string $creditDate,
        int $actorId,string $actorName): array
    {
        $reason=trim($reason);
        if(mb_strlen($reason)<10)self::fail('Credit Note reason minimum 10 characters.');
        return DB::transaction(function()use($jobId,$invoiceId,$reason,$creditDate,$actorId,$actorName){
            $job=DB::table('job_cards')->where('id',$jobId)->lockForUpdate()->first();
            $inv=DB::table('invoices')->where('id',$invoiceId)->lockForUpdate()->first();
            if(!$job||!$inv||(int)$inv->job_card_id!==$jobId ||
                (int)$job->invoice_id!==$invoiceId ||
                strtolower(trim((string)$job->status))!=='invoiced' ||
                strtolower(trim((string)$inv->status))!=='paid' ||
                (int)$job->customer_id!==(int)$inv->customer_id)
                self::fail('Invoice and Job Card must be linked and fully paid before transfer.');
            $gross=self::money($inv->grand_total);
            if($gross<=0||self::money($inv->paid_amount)!==$gross ||
              self::money($inv->paid_total)!==$gross ||
              self::money($inv->balance_due)!==0 || self::money($inv->balance_total)!==0)
                self::fail('Paid totals / outstanding do not reconcile.');
            if(DB::table('invoice_credit_notes')->where('invoice_id',$invoiceId)->exists()||
               DB::table('customer_advance_transfers')->where('original_invoice_id',$invoiceId)->exists())
                self::fail('Invoice already credited or transferred; duplicate blocked.');
            $payments=DB::table('customer_payments')->where('invoice_id',$invoiceId)
                ->lockForUpdate()->get();
            $allocs=DB::table('customer_payment_allocations')->where('invoice_id',$invoiceId)
                ->lockForUpdate()->get();
            if($payments->count()!==1||$allocs->count()!==1)
                self::fail('Only one full Money Receipt allocation supported. Use Finance review for split/multiple receipts.');
            $receipt=$payments->first();$alloc=$allocs->first();
            if((int)$alloc->payment_id!==(int)$receipt->id ||
               (int)$receipt->customer_id!==(int)$inv->customer_id ||
               strtolower((string)$receipt->payment_status)!=='posted' ||
               !empty($receipt->reversed_at) ||
               (string)$receipt->reference_kind!=='invoice' ||
               self::money($receipt->total_amount)!==$gross ||
               self::money($receipt->allocated_amount)!==$gross ||
               self::money($alloc->amount)!==$gross ||
               strtolower((string)$alloc->status)!=='posted' ||
               !empty($alloc->reversed_at))
                self::fail('Receipt/allocation does not match original full payment.');
            $other=DB::table('customer_payment_allocations')->where('payment_id',$receipt->id)
                ->lockForUpdate()->get();
            if($other->count()!==1||DB::table('customer_advance_transfers')->where('receipt_id',$receipt->id)->exists())
                self::fail('Receipt has other allocations or was already transferred.');
            $invLedgers=DB::table('customer_ledger')->where('invoice_id',$invoiceId)
                ->lockForUpdate()->get();
            $paymentLedgers=DB::table('customer_ledger')->where('payment_id',$receipt->id)
                ->lockForUpdate()->get();
            if($invLedgers->count()!==2||$paymentLedgers->count()!==1)
                self::fail('Invoice customer ledger does not match a single debit and receipt credit.');
            $dr=$invLedgers->first(fn($r)=>$r->reference_type==='INVOICE');
            $cr=$paymentLedgers->first();
            if(!$dr||!$cr||(int)$cr->invoice_id!==$invoiceId ||
               strtoupper((string)$dr->entry_type)!=='DEBIT' ||
               strtoupper((string)$cr->entry_type)!=='CREDIT' ||
               self::money($dr->debit)!==$gross || self::money($cr->credit)!==$gross ||
               self::money($dr->credit)!==0 ||self::money($cr->debit)!==0 ||
               (int)$dr->customer_id!==(int)$inv->customer_id ||
               (int)$cr->customer_id!==(int)$inv->customer_id)
                self::fail('Original debit/credit do not balance; transfer refused.');
            $tax=JobCardTaxTotals::forJob($jobId);
            if(self::money($tax['grand_total'])!==$gross ||
               abs(self::money($tax['gst_total'])-self::money($inv->gst_total))>1)
                self::fail('Stored invoice differs from current JC tax lines; Finance review required.');
            $snapshot=[
              'invoice'=>(array)$inv,'job'=>(array)$job,
              'parts'=>DB::table('job_card_parts')->where('job_card_id',$jobId)->orderBy('id')->get()->toArray(),
              'labour'=>DB::table('job_card_labour')->where('job_card_id',$jobId)->orderBy('id')->get()->toArray(),
              'money_receipt'=>(array)$receipt,'original_allocation'=>(array)$alloc,
              'invoice_ledger'=>(array)$dr,'receipt_ledger'=>(array)$cr
            ];
            $now=now('Asia/Kolkata');$customerId=(int)$inv->customer_id;
            $customerName=(string)($inv->customer_name??'');
            $email=$inv->customer_email??null;
            $vehicleId=$inv->vehicle_id? (int)$inv->vehicle_id:null;
            $cnId=(int)DB::table('invoice_credit_notes')->insertGetId([
                'credit_note_no'=>'TMP-FIX57-'.$invoiceId,
                'invoice_id'=>$invoiceId,'job_card_id'=>$jobId,'customer_id'=>$customerId,
                'credit_date'=>$creditDate,'taxable_amount'=>$inv->subtotal,
                'gst_amount'=>$inv->gst_total,'credit_amount'=>$inv->grand_total,
                'status'=>'posted','reason'=>$reason,
                'original_snapshot'=>json_encode($snapshot,JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_UNICODE),
                'posted_by'=>$actorId,'created_at'=>$now,
            ]);
            $cnNo='CN-'.$now->format('Y').'-'.str_pad((string)$cnId,5,'0',STR_PAD_LEFT);
            DB::table('invoice_credit_notes')->where('id',$cnId)->update(['credit_note_no'=>$cnNo]);
            // Original receipt, its bank reference and original receipt ledger are NEVER deleted or rewritten.
            // Release allocation only; add an explicit debit reversal of that old allocation.
            DB::table('customer_payment_allocations')->where('id',$alloc->id)
                ->update(['status'=>'reversed','reversed_at'=>$now]);
            DB::table('customer_payments')->where('id',$receipt->id)->update(['allocated_amount'=>0]);
            $date=$now->toDateString();
            $releaseId=self::ledger($customerId,$invoiceId,$jobId,$vehicleId,(int)$receipt->id,
                'ADVANCE_TRANSFER_OUT',$receipt->receipt_no,(int)$receipt->id,$gross,0,
                'FIX57 release original invoice receipt allocation '.$receipt->receipt_no,
                $customerName,$email,$date);
            $cnLedgerId=self::ledger($customerId,$invoiceId,$jobId,$vehicleId,null,
                'CREDIT_NOTE',$cnNo,$cnId,0,$gross,
                'Credit Note '.$cnNo.' against '.$inv->invoice_no,$customerName,$email,$creditDate);
            $advanceId=(int)DB::table('customer_advance_transfers')->insertGetId([
                'receipt_id'=>$receipt->id,'original_invoice_id'=>$invoiceId,
                'job_card_id'=>$jobId,'customer_id'=>$customerId,
                'credit_note_id'=>$cnId,'original_allocation_id'=>$alloc->id,
                'original_receipt_ledger_id'=>$cr->id,
                'amount'=>$gross/100,'remaining_amount'=>$gross/100,
                'status'=>'available','reason'=>$reason,'credit_date'=>$creditDate,
                'posted_by'=>$actorId,'created_at'=>$now,'updated_at'=>$now,
            ]);
            $advanceRef='ADV-'.$now->format('Y').'-'.str_pad((string)$advanceId,5,'0',STR_PAD_LEFT);
            $advanceLedgerId=self::ledger($customerId,null,$jobId,$vehicleId,(int)$receipt->id,
                'CUSTOMER_ADVANCE',$advanceRef,$advanceId,0,$gross,
                'Original receipt '.$receipt->receipt_no.' transferred to customer advance',
                $customerName,$email,$date);
            DB::table('invoices')->where('id',$invoiceId)->update([
                'status'=>'reversed','returned_at'=>$now,'return_reason'=>$reason,
                'balance_due'=>0,'balance_total'=>0,
            ]);
            DB::table('job_cards')->where('id',$jobId)->update([
                'status'=>'closed','invoice_id'=>null,
                'customer_public_status'=>'Invoice credited — correction pending',
                'updated_at'=>$now,
            ]);
            DB::table('job_card_change_audits')->insert([
                'job_card_id'=>$jobId,'entity_type'=>'INVOICE','entity_id'=>$invoiceId,
                'action'=>'FIX57_PAID_CREDIT_NOTE_ADVANCE',
                'before_json'=>json_encode([
                   'invoice'=>$inv,'receipt'=>$receipt,'allocation'=>$alloc,'job'=>$job
                ],JSON_INVALID_UTF8_SUBSTITUTE),
                'after_json'=>json_encode([
                   'credit_note_id'=>$cnId,'credit_note_no'=>$cnNo,
                   'advance_transfer_id'=>$advanceId,'advance_reference'=>$advanceRef,
                   'receipt_id'=>$receipt->id,'released_allocation_id'=>$alloc->id,
                   'reallocation_debit_ledger_id'=>$releaseId,
                   'credit_note_credit_ledger_id'=>$cnLedgerId,
                   'customer_advance_ledger_id'=>$advanceLedgerId,
                   'advance_amount'=>$gross/100,'job_status'=>'closed'
                ],JSON_INVALID_UTF8_SUBSTITUTE),
                'reason'=>$reason,'actor_id'=>$actorId,'actor_name'=>$actorName,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            $linked=DB::table('customer_ledger')->where('invoice_id',$invoiceId)->get();
            $net=$linked->sum(fn($r)=>self::money($r->debit)-self::money($r->credit));
            if($net!==0 || DB::table('customer_advance_transfers')->where('id',$advanceId)
              ->where('remaining_amount',$gross/100)->count()!==1)
                throw new \RuntimeException('FIX57 post-journal imbalance; transaction rolled back.');
            return ['credit_note'=>$cnNo,'receipt'=>$receipt->receipt_no,
                'advance_id'=>$advanceId,'advance_amount'=>$gross/100];
        },3);
    }

    /** Automatically reallocate the original receipt to a corrected invoice ONLY
     * inside the same transaction which is creating that new Job Card invoice.
     */
    public static function applyToNewInvoice(int $jobId,int $invoiceId,int $actorId,string $actorName): float
    {
        $invoice=DB::table('invoices')->where('id',$invoiceId)->lockForUpdate()->first();
        if(!$invoice||(int)$invoice->job_card_id!==$jobId)
            throw new \RuntimeException('FIX57 invoice/new job mismatch.');
        $available=DB::table('customer_advance_transfers')
            ->where('job_card_id',$jobId)->where('customer_id',$invoice->customer_id)
            ->where('remaining_amount','>',0)->lockForUpdate()->get();
        if($available->isEmpty())return 0.0;
        if($available->count()!==1)self::fail('Multiple customer advances require Finance selection.');
        $advance=$available->first();
        $creditNote=DB::table('invoice_credit_notes')->where('id',$advance->credit_note_id)->first();
        if(!$creditNote||strtolower((string)$creditNote->status)!=='posted'||
           (int)$creditNote->invoice_id!==(int)$advance->original_invoice_id)
           self::fail('Original Credit Note missing; cannot auto-apply advance.');
        if(DB::table('customer_advance_applications')->where('advance_transfer_id',$advance->id)->exists())
            self::fail('Advance was already used in a different invoice; Finance review needed.');
        $receipt=DB::table('customer_payments')->where('id',$advance->receipt_id)->lockForUpdate()->first();
        if(!$receipt||!empty($receipt->reversed_at)||
           (int)$receipt->customer_id!==(int)$invoice->customer_id ||
           strtolower((string)$receipt->payment_status)!=='posted' ||
           self::money($receipt->allocated_amount)!==0)
           self::fail('Original Money Receipt advance amount changed; cannot apply twice.');
        if(DB::table('customer_payment_allocations')->where('payment_id',$receipt->id)
          ->where('status','posted')->whereNull('reversed_at')->exists())
          self::fail('Money Receipt already has active payment allocation.');
        $grand=self::money($invoice->grand_total);
        $remaining=self::money($advance->remaining_amount);
        $use=min($grand,$remaining);
        if($use<=0)return 0;
        $date=now('Asia/Kolkata')->toDateString();
        $allocId=(int)DB::table('customer_payment_allocations')->insertGetId([
          'payment_id'=>$receipt->id,'invoice_id'=>$invoiceId,'amount'=>$use/100,
          'status'=>'posted','created_at'=>now('Asia/Kolkata'),
        ]);
        $name=(string)($invoice->customer_name??'');
        $email=$invoice->customer_email??null;
        $vehicle=$invoice->vehicle_id?(int)$invoice->vehicle_id:null;
        $ref='ADV-APP-'.$invoice->invoice_no;
        // Remove advance credit and post matching credit against the NEW invoice.
        self::ledger((int)$invoice->customer_id,null,$jobId,$vehicle,(int)$receipt->id,
          'ADVANCE_APPLIED_OUT',$ref,(int)$advance->id,$use,0,
          'FIX57 consume customer advance for corrected invoice '.$invoice->invoice_no,
          $name,$email,$date);
        self::ledger((int)$invoice->customer_id,$invoiceId,$jobId,$vehicle,(int)$receipt->id,
          'ADVANCE_APPLIED_INVOICE',$receipt->receipt_no,$allocId,0,$use,
          'FIX57 original Money Receipt '.$receipt->receipt_no.' applied to corrected invoice '.$invoice->invoice_no,
          $name,$email,$date);
        DB::table('customer_payment_allocations')->where('id',$allocId)->first();
        DB::table('customer_payments')->where('id',$receipt->id)->update(['allocated_amount'=>$use/100]);
        DB::table('customer_advance_transfers')->where('id',$advance->id)->update([
           'remaining_amount'=>($remaining-$use)/100,
           'status'=>$remaining===$use?'applied':'partially_applied',
           'updated_at'=>now('Asia/Kolkata'),
        ]);
        DB::table('customer_advance_applications')->insert([
          'advance_transfer_id'=>$advance->id,'invoice_id'=>$invoiceId,
          'allocation_id'=>$allocId,'amount'=>$use/100,'created_at'=>now('Asia/Kolkata')
        ]);
        DB::table('invoices')->where('id',$invoiceId)->update([
          'paid_amount'=>$use/100,'paid_total'=>$use/100,
          'balance_due'=>($grand-$use)/100,'balance_total'=>($grand-$use)/100,
          'status'=>$grand===$use?'paid':'part_paid'
        ]);
        DB::table('job_card_change_audits')->insert([
          'job_card_id'=>$jobId,'entity_type'=>'INVOICE','entity_id'=>$invoiceId,
          'action'=>'FIX57_APPLY_ADVANCE_NEW_INVOICE',
          'before_json'=>json_encode(['new_invoice_id'=>$invoiceId,'advance_remaining'=>$remaining/100]),
          'after_json'=>json_encode(['receipt_no'=>$receipt->receipt_no,'allocated'=>$use/100,
            'remaining_advance'=>($remaining-$use)/100,'allocation_id'=>$allocId]),
          'reason'=>'Original preserved Money Receipt advance applied to corrected JC invoice',
          'actor_id'=>$actorId,'actor_name'=>$actorName,
          'created_at'=>now('Asia/Kolkata'),'updated_at'=>now('Asia/Kolkata')
        ]);
        return $use/100;
    }
}
