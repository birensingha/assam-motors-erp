<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\Web\AdminStaffFinanceLedgerController;
use App\Models\WorkshopPaymentVoucher;
use App\Models\WorkshopExpense;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/**
 * AM240: Staff payout begins in Universal Payment Voucher, posts exactly once.
 * Salary money is accounted by existing balanced Staff Finance journal.
 * Expense reimbursement settles existing approved workshop expense without
 * rebooking expense; the Staff Ledger already reads those original payments.
 */
final class StaffPaymentVoucherService
{
    private const JOURNAL_TYPES = ['SALARY_ADVANCE','SALARY_PAYMENT'];

    private function reject(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field=>$message]);
    }

    private function staff(Request $request): array
    {
        $data=$request->validate([
            'payee_category'=>['required','in:STAFF'],
            'staff_id'=>['required','integer','min:1','exists:staff,id'],
            'staff_payment_kind'=>['required','in:SALARY_ADVANCE,SALARY_PAYMENT,EXPENSE_REIMBURSEMENT'],
            'payment_date'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'salary_month'=>['nullable','date_format:Y-m'],
            'staff_expense_id'=>['nullable','integer','min:1'],
            'amount'=>['required','numeric','gt:0','max:99999999.99','decimal:0,2'],
            'payment_mode'=>['required','in:Cash,UPI,Bank Transfer,Cheque,Card'],
            'account_name'=>['required','string','min:2','max:120'],
            'reference_no'=>['nullable','string','max:140'],
            'notes'=>['required','string','min:6','max:600'],
            'request_key'=>['required','uuid'],
            'staff_payment_against'=>['required','in:AGAINST,ADVANCE'],
        ]);
        $kind=(string)$data['staff_payment_kind'];
        if($kind==='SALARY_ADVANCE' && $data['staff_payment_against']!=='ADVANCE')
            $this->reject('staff_payment_against','Staff salary advance must use Payment Against: Advance.');
        if($kind!=='SALARY_ADVANCE' && $data['staff_payment_against']!=='AGAINST')
            $this->reject('staff_payment_against','Salary/expense payable must use Against Reference.');
        if(in_array($kind,self::JOURNAL_TYPES,true) && empty($data['salary_month']))
            $this->reject('salary_month','Select Salary Month for staff salary payment/advance.');
        if($kind==='EXPENSE_REIMBURSEMENT' && empty($data['staff_expense_id']))
            $this->reject('staff_expense_id','Select an existing unpaid Staff Expense Voucher.');
        return $data;
    }

    public function post(Request $request): RedirectResponse
    {
        $user=$request->user();
        abort_unless($user instanceof \App\Models\ErpAuthUser && $user->active &&
            $user->isAdmin(),403,'Admin authorization required for Staff payment.');
        $data=$this->staff($request);
        $staffId=(int)$data['staff_id'];
        $staff=DB::table('staff')->where('id',$staffId)->first();
        if(!$staff)$this->reject('staff_id','Staff Master record not found.');
        $key=$data['request_key'];
        $kind=$data['staff_payment_kind'];
        $amount=(float)$data['amount'];

        if($kind==='EXPENSE_REIMBURSEMENT'){
            return DB::transaction(function()use($data,$request,$staff,$staffId,$amount,$key){
                // Serialize by staff ID and outstanding Expense source (ExpensePaymentService also locks it).
                DB::table('staff')->where('id',$staffId)->lockForUpdate()->first();
                $expense=WorkshopExpense::query()->where('record_status','ACTIVE')
                    ->lockForUpdate()->find((int)$data['staff_expense_id']);
                if(!$expense || (int)$expense->staff_id!==$staffId)
                    $this->reject('staff_expense_id','This Expense is not an active payable for selected Staff.');
                $sourceRef=(string)$expense->expense_no;
                $existing=WorkshopPaymentVoucher::query()->where('request_key',$key)->first();
                if($existing){
                    if($existing->payee_type!=='STAFF'||(int)$existing->staff_id!==$staffId||
                       $existing->source_type!=='EXPENSE_VOUCHER'||(int)$existing->source_id!==(int)$expense->id||
                       abs((float)$existing->amount-$amount)>0.009)
                        $this->reject('request_key','Voucher key belongs to a different payment.');
                    return redirect()->route('erp.payments.show',$existing->id);
                }
                $payment=app(ExpensePaymentService::class)->savePayment([
                    'payment_date'=>$data['payment_date'],
                    'source_type'=>'EXPENSE_VOUCHER',
                    'source_key'=>'EXPENSE_VOUCHER::'.(int)$expense->id,
                    'request_key'=>$key,
                    'amount'=>$amount,
                    'payment_mode'=>$data['payment_mode'],
                    'account_name'=>$data['account_name'],
                    'reference_no'=>$data['reference_no']??null,
                    'notes'=>$data['notes'],
                ],(string)($request->user()->email ?: $request->user()->name ?: 'ADMIN'));
                if($payment->payee_type!=='STAFF'||(int)$payment->staff_id!==$staffId)
                    throw new \RuntimeException('Staff Expense posting ledger identity mismatch; payment rolled back.');
                return redirect()->route('erp.payments.show',$payment->id)
                    ->with('success','Staff Expense payment posted against '.$sourceRef.'.');
            },3);
        }

        return DB::transaction(function() use($request,$data,$staff,$staffId,$amount,$kind,$key){
            // Journal and payment header MUST commit/roll back together.
            $person=DB::table('staff')->where('id',$staffId)->lockForUpdate()->first();
            if(!$person)$this->reject('staff_id','Staff Master missing.');
            $mirror=WorkshopPaymentVoucher::query()->where('request_key',$key)->lockForUpdate()->first();
            if($mirror){
                if($mirror->payee_type!=='STAFF'||(int)$mirror->staff_id!==$staffId||
                   $mirror->source_type!=='STAFF_'.$kind||
                   abs((float)$mirror->amount-$amount)>0.009)
                   $this->reject('request_key','Request key is already used for another Voucher.');
                return redirect()->route('erp.payments.show',$mirror->id);
            }
            if(DB::table('workshop_staff_finance_journals')->where('request_key',$key)->exists())
                $this->reject('request_key','Journal already exists without linked Payment Voucher. Administrator review required.');
            $request->attributes->set('am240_from_payment_voucher',true);
            $request->merge([
                'entry_type'=>$kind,'entry_date'=>$data['payment_date'],
                'salary_month'=>$data['salary_month'],'narration'=>$data['notes'],
                'request_key'=>$key
            ]);
            // Reuse the verified AM262 balanced debit/credit posting and salary checks.
            app(AdminStaffFinanceLedgerController::class)->store($request);
            $journal=DB::table('workshop_staff_finance_journals')
                ->where('request_key',$key)->lockForUpdate()->first();
            if(!$journal||(int)$journal->staff_id!==$staffId||$journal->entry_type!==$kind||
               abs((float)$journal->amount-$amount)>0.009)
                throw new \RuntimeException('Staff journal/money mismatch, transaction rolled back.');
            $voucher=WorkshopPaymentVoucher::query()->create([
                'payment_date'=>$data['payment_date'],
                'source_type'=>'STAFF_'.$kind,
                'source_id'=>(int)$journal->id,
                'source_reference'=>(string)$journal->voucher_no,
                'payee_type'=>'STAFF','staff_id'=>$staffId,'vendor_id'=>null,
                'payee_name'=>(string)$person->name,
                'amount'=>$amount,
                'payment_mode'=>$data['payment_mode'],
                'account_name'=>$data['account_name'],
                'reference_no'=>$data['reference_no']??null,
                'notes'=>$data['notes'],
                'request_key'=>$key,
                'record_status'=>'ACTIVE',
                'created_by'=>(string)($request->user()->email?:$request->user()->name?:'ADMIN')
            ]);
            $voucher->voucher_no='PAY-'.now('Asia/Kolkata')->format('Ymd').'-'.
                str_pad((string)$voucher->id,6,'0',STR_PAD_LEFT);
            $voucher->save();
            return redirect()->route('erp.payments.show',$voucher->id)
                ->with('success','Staff Payment Voucher posted; balanced Staff Ledger journal '.$journal->voucher_no.' linked.');
        },3);
    }

    public function void(Request $request,int $payment): RedirectResponse
    {
        $user=$request->user();
        abort_unless($user instanceof \App\Models\ErpAuthUser && $user->active &&
            $user->isAdmin(),403,'Admin authorization required.');
        $data=$request->validate(['reason'=>['required','string','min:8','max:300']]);
        return DB::transaction(function() use($request,$payment,$data){
            $voucher=WorkshopPaymentVoucher::query()->where('id',$payment)->lockForUpdate()->first();
            if(!$voucher||$voucher->record_status!=='ACTIVE' ||
                $voucher->payee_type!=='STAFF'||!in_array($voucher->source_type,
                    ['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'],true))
                $this->reject('reason','Not an active Staff Salary Payment Voucher.');
            $journal=DB::table('workshop_staff_finance_journals')
                ->where('id',(int)$voucher->source_id)->lockForUpdate()->first();
            if(!$journal||(string)$journal->request_key!==(string)$voucher->request_key ||
               (int)$journal->staff_id!==(int)$voucher->staff_id)
                throw new \RuntimeException('Staff journal/voucher link missing; no void posted.');
            $request->attributes->set('am240_from_payment_voucher',true);
            $request->merge(['reason'=>$data['reason'],'request_key'=>(string)Str::uuid()]);
            app(AdminStaffFinanceLedgerController::class)->reverse($request,(int)$journal->id);
            $voucher->record_status='VOID';
            $voucher->void_reason=$data['reason'];
            $voucher->voided_by=(string)($request->user()->email?:$request->user()->name?:'ADMIN');
            $voucher->voided_at=now('Asia/Kolkata');
            $voucher->save();
            return redirect()->route('erp.payments.show',$voucher->id)
                ->with('success','Staff Payment Voucher voided and matching Staff Ledger reversal posted.');
        },3);
    }
}
