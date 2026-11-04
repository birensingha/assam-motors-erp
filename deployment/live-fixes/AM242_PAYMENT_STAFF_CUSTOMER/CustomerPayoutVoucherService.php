<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ErpAuthUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * AM242_CUSTOMER_PAYOUT_LEDGER
 * CUSTOMER payouts are never routed through Vendor / Staff service.
 *
 * AGAINST => refund ONLY a positive verified customer credit balance.
 * ADVANCE => advance paid to customer, creates an accountable customer receivable.
 * Each outgoing Payment Voucher posts exactly one Customer Ledger DEBIT.
 * On Void, write a new CREDIT reversal; never erase financial history.
 */
final class CustomerPayoutVoucherService
{
    private function fail(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field=>$message]);
    }

    private function admin(Request $request): string
    {
        $u=$request->user();
        abort_unless($u instanceof ErpAuthUser && $u->active && $u->isAdmin(),403,
            'Admin permission required for outgoing Customer Payment.');
        return mb_substr(trim((string)($u->email?:$u->name?:'ADMIN')),0,180);
    }

    private function tables(): void
    {
        foreach(['am_customer_master','customer_ledger','workshop_payment_vouchers'] as $table) {
            abort_unless(Schema::hasTable($table),503,'Customer accounting table unavailable: '.$table);
        }
        abort_unless(Schema::hasColumn('workshop_payment_vouchers','customer_id'),503,
            'Customer payout identity schema not installed.');
    }

    public function post(Request $request): RedirectResponse
    {
        $actor=$this->admin($request);
        $this->tables();
        $v=$request->validate([
            'payee_category'=>['required','in:CUSTOMER'],
            'customer_id'=>['required','integer','min:1','exists:am_customer_master,id'],
            'customer_payment_against'=>['required','in:AGAINST,ADVANCE'],
            'amount'=>['required','numeric','gt:0','max:99999999.99','decimal:0,2'],
            'payment_date'=>['required','date_format:Y-m-d'],
            'payment_mode'=>['required','in:Cash,UPI,Bank Transfer,Cheque,Card'],
            'account_name'=>['required','string','min:2','max:120'],
            'reference_no'=>['nullable','string','max:150'],
            'notes'=>['required','string','min:6','max:2000'],
            'request_key'=>['required','uuid'],
        ]);
        $now=now('Asia/Kolkata')->toDateString();
        if($v['payment_date']>$now)
            $this->fail('payment_date','Future-dated customer payout is not allowed.');
        $amount=round((float)$v['amount'],2);
        $cid=(int)$v['customer_id'];
        $mode=(string)$v['customer_payment_against'];
        $type=$mode==='AGAINST'?'CUSTOMER_REFUND':'CUSTOMER_ADVANCE';
        $key=(string)$v['request_key'];

        $id=DB::transaction(function()use($v,$actor,$cid,$amount,$type,$mode,$key){
            // Serializes with Money Receipt's customer-row lock to prevent concurrent
            // credit refunds, and makes the request idempotent.
            $customer=DB::table('am_customer_master')->where('id',$cid)->lockForUpdate()->first();
            if(!$customer)$this->fail('customer_id','Select a valid customer from Customer Master.');
            $existing=DB::table('workshop_payment_vouchers')
                ->where('request_key',$key)->lockForUpdate()->first();
            if($existing){
                if((string)$existing->payee_type!=='CUSTOMER' || (int)$existing->customer_id!==$cid ||
                   (string)$existing->source_type!==$type ||
                   abs((float)$existing->amount-$amount)>0.009 ||
                   (string)$existing->record_status!=='ACTIVE')
                    $this->fail('request_key','Request key already belongs to another or voided voucher.');
                return (int)$existing->id;
            }
            if($mode==='AGAINST'){
                // Only refund a provable credit balance, not an invoice outstanding
                // or a fabricated customer payable.
                $available=DB::table('customer_ledger')->where('customer_id',$cid)
                    ->selectRaw('COALESCE(SUM(COALESCE(credit,0)-COALESCE(debit,0)),0) AS credit_balance')
                    ->first();
                $credit=(float)($available->credit_balance??0);
                $credit=round($credit,2);
                if($credit+0.009<$amount)
                    $this->fail('amount','Refund exceeds Customer Ledger net credit. Verified available credit ₹'.number_format(max(0,$credit),2).'. Use Advance for a separate receivable payout.');
            }
            $prefix=$mode==='AGAINST'?'CRF-':'CADV-';
            $reference=$prefix.str_replace('-','',$v['payment_date']).'-'.strtoupper(substr(str_replace('-','',$key),0,12));
            $temporary='TMP-PAY-'.strtoupper(substr(str_replace('-','',$key),0,28));
            $id=(int)DB::table('workshop_payment_vouchers')->insertGetId([
                'voucher_no'=>$temporary,
                'payment_date'=>$v['payment_date'],
                'source_type'=>$type,'source_id'=>$cid,'source_reference'=>$reference,
                'job_card_id'=>null,'payee_type'=>'CUSTOMER','customer_id'=>$cid,
                'staff_id'=>null,'vendor_id'=>null,
                'payee_name'=>(string)$customer->name,'amount'=>$amount,
                'payment_mode'=>$v['payment_mode'],'account_name'=>trim($v['account_name']),
                'reference_no'=>trim((string)($v['reference_no']??'')) ?: null,
                'notes'=>trim((string)$v['notes']),
                'record_status'=>'ACTIVE','created_by'=>$actor,'created_at'=>now(),
                'request_key'=>$key
            ]);
            $voucherNo='PAY-'.now('Asia/Kolkata')->format('Ymd').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
            DB::table('workshop_payment_vouchers')->where('id',$id)->update(['voucher_no'=>$voucherNo]);
            $note=($mode==='AGAINST'?'Customer Credit Refund':'Advance Paid to Customer').
                 ' · '.$reference.' · '.$v['notes'];
            DB::table('customer_ledger')->insert([
                'customer_id'=>$cid,
                'customer_name'=>(string)$customer->name,
                'customer_email'=>$customer->email ?: null,
                'invoice_id'=>null,'job_card_id'=>null,'vehicle_id'=>null,'payment_id'=>null,
                'entry_type'=>'DEBIT','amount'=>$amount,'debit'=>$amount,'credit'=>0,
                'reference_no'=>$voucherNo,'reference_type'=>'PAYMENT_VOUCHER',
                'reference_id'=>$id,'note'=>mb_substr($note,0,2000),
                'narration'=>mb_substr($note,0,2000),
                'entry_date'=>$v['payment_date'],'created_at'=>now()
            ]);
            return $id;
        },3);

        return redirect()->route('erp.payments.show',$id)->with('success',
            $mode==='AGAINST'
                ? 'Customer Refund Payment Voucher posted with Customer Ledger debit.'
                : 'Customer Advance Payment Voucher posted as Customer Receivable debit.');
    }

    public function void(Request $request,int $payment): RedirectResponse
    {
        $actor=$this->admin($request);
        $this->tables();
        $data=$request->validate(['reason'=>['required','string','min:8','max:300']]);
        DB::transaction(function()use($payment,$data,$actor){
            $voucher=DB::table('workshop_payment_vouchers')->where('id',$payment)->lockForUpdate()->first();
            if(!$voucher || (string)$voucher->record_status!=='ACTIVE' ||
               (string)$voucher->payee_type!=='CUSTOMER' ||
               !in_array((string)$voucher->source_type,['CUSTOMER_REFUND','CUSTOMER_ADVANCE'],true))
                $this->fail('payment','Not an active Customer Payment Voucher.');
            $customer=DB::table('am_customer_master')->where('id',$voucher->customer_id)->lockForUpdate()->first();
            if(!$customer)$this->fail('payment','Customer Master record missing. Financial reversal locked.');
            $ledger=DB::table('customer_ledger')->where('customer_id',$voucher->customer_id)
                ->where('reference_type','PAYMENT_VOUCHER')->where('reference_id',$payment)
                ->lockForUpdate()->get();
            if($ledger->count()!==1 ||
               abs((float)$ledger->first()->debit-(float)$voucher->amount)>0.009 ||
               abs((float)$ledger->first()->credit)>0.009)
                $this->fail('payment','Original Customer Ledger debit is missing or inconsistent. Reversal locked.');
            if(DB::table('customer_ledger')->where('customer_id',$voucher->customer_id)
                ->where('reference_type','PAYMENT_VOUCHER_VOID')->where('reference_id',$payment)->exists())
                $this->fail('payment','This Customer Voucher reversal was already recorded.');
            $amount=round((float)$voucher->amount,2);
            $note='AUDITED VOID of Customer Payment '.$voucher->voucher_no.' · '.$data['reason'];
            DB::table('customer_ledger')->insert([
                'customer_id'=>(int)$voucher->customer_id,
                'customer_name'=>(string)$customer->name,
                'customer_email'=>$customer->email ?: null,
                'invoice_id'=>null,'job_card_id'=>null,'vehicle_id'=>null,'payment_id'=>null,
                'entry_type'=>'CREDIT','amount'=>$amount,'debit'=>0,'credit'=>$amount,
                'reference_no'=>'VOID-'.$voucher->voucher_no,
                'reference_type'=>'PAYMENT_VOUCHER_VOID','reference_id'=>$payment,
                'note'=>mb_substr($note,0,2000),'narration'=>mb_substr($note,0,2000),
                'entry_date'=>now('Asia/Kolkata')->toDateString(),'created_at'=>now()
            ]);
            DB::table('workshop_payment_vouchers')->where('id',$payment)->update([
                'record_status'=>'VOID','void_reason'=>$data['reason'],
                'voided_by'=>$actor,'voided_at'=>now()
            ]);
        },3);
        return redirect()->route('erp.payments.show',$payment)->with('success',
            'Customer Payment Voucher VOID posted with separate Customer Ledger reversal and audit reason.');
    }
}
