<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * AM262_STAFF_FINANCE_LEDGER
 * Append-only, balanced STAFF SUBLEDGER.
 * This deliberately does not silently post to any unrelated legacy GL or vendor account.
 */
final class AdminStaffFinanceLedgerController extends Controller
{
    private const LEGS = [
        'SALARY_PROVISION' => [['SALARY_EXPENSE','INDIRECT_EXPENSES'],['SALARY_PAYABLE','CURRENT_LIABILITIES']],
        'SALARY_ADVANCE'   => [['STAFF_ADVANCE','LOANS_AND_ADVANCES_ASSET'],['CASH_BANK','CASH_AND_BANK']],
        'SALARY_PAYMENT'   => [['SALARY_PAYABLE','CURRENT_LIABILITIES'],['CASH_BANK','CASH_AND_BANK']],
        'ADVANCE_ADJUST'   => [['SALARY_PAYABLE','CURRENT_LIABILITIES'],['STAFF_ADVANCE','LOANS_AND_ADVANCES_ASSET']],
    ];

    private function actor(Request $request): ErpAuthUser
    {
        $actor=$request->user();
        abort_unless($actor instanceof ErpAuthUser && $actor->active
            && strtolower(trim((string)$actor->role))==='admin',403,'Admin authorization required.');
        return $actor;
    }

    private function balance(int $staff, string $code, ?string $month=null): float
    {
        $q=DB::table('workshop_staff_finance_lines as l')
            ->join('workshop_staff_finance_journals as j','j.id','=','l.journal_id')
            ->where('l.staff_id',$staff)->where('l.account_code',$code);
        if($month!==null)$q->where('j.salary_month',$month);
        $value=(float)$q->selectRaw('COALESCE(SUM(l.debit-l.credit),0) as balance')->value('balance');
        return round($value,2);
    }

    private function state(int $staff, ?string $month=null): array
    {
        return [
            'salary_payable'=>round(max(0,-$this->balance($staff,'SALARY_PAYABLE',$month)),2),
            'salary_advance'=>round(max(0,$this->balance($staff,'STAFF_ADVANCE')),2),
            'salary_expense'=>round($this->balance($staff,'SALARY_EXPENSE'),2),
        ];
    }

    public function index(Request $request): View
    {
        $this->actor($request);
        $people=DB::table('staff')->orderBy('name')->get(['id','staff_code','name','department','status']);
        $staffId=(int)$request->query('staff_id',0);
        $selected=$staffId ? $people->firstWhere('id',$staffId) : null;
        abort_if($staffId && !$selected,404,'Staff Master record not found.');
        $month=(string)$request->query('month',now('Asia/Kolkata')->format('Y-m'));
        if(!preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/',$month))$month=now('Asia/Kolkata')->format('Y-m');
        $state=['salary_payable'=>0,'salary_advance'=>0,'salary_expense'=>0];
        $expenseDue=0; $expensePaid=0; $expenseTotal=0;
        $rows=collect(); $monthPayable=0;
        if($selected){
            $state=$this->state($staffId);
            $monthPayable=$this->state($staffId,$month)['salary_payable'];
            $journals=DB::table('workshop_staff_finance_journals')
                ->where('staff_id',$staffId)->orderBy('entry_date')->orderBy('id')->limit(1000)->get();
            $expenses=DB::table('workshop_expenses')->where('staff_id',$staffId)
                ->where('record_status','<>','VOID')->orderBy('id')->limit(800)->get();
            // Existing expense payment vouchers retain their own source, never repost them.
            $payments=DB::table('workshop_payment_vouchers')
                ->where('staff_id',$staffId)->where('source_type','EXPENSE_VOUCHER')
                ->where('record_status','ACTIVE')->orderBy('id')->limit(800)->get();
            $paidByExpense=$payments->groupBy('source_id')->map(fn($x)=>round($x->sum('amount'),2));
            foreach($journals as $j){
                $rows->push((object)[
                    'date'=>$j->entry_date,'id'=>'J'.$j->id,'ref'=>$j->voucher_no,
                    'type'=>$j->entry_type,'description'=>$j->narration,
                    'amount'=>(float)$j->amount,
                    'month'=>$j->salary_month,
                    'reversal_of'=>$j->reversal_of,'source'=>'STAFF_JOURNAL',
                    'journal_id'=>$j->id,'can_reverse'=>!$j->reversal_of,
                ]);
            }
            $expenseIds=$expenses->pluck('id')->all();
            foreach($expenses as $expense){
                $sum=round((float)$expense->total_amount,2);
                $paid=round((float)($paidByExpense->get($expense->id,0)),2);
                $expenseTotal+=$sum; $expensePaid+=$paid;
                $rows->push((object)[
                    'date'=>$expense->expense_date,'id'=>'E'.$expense->id,
                    'ref'=>$expense->expense_no,'type'=>'STAFF_EXPENSE',
                    'description'=>$expense->description,'amount'=>$sum,
                    'month'=>'','reversal_of'=>null,
                    'source'=>'EXISTING_EXPENSE','journal_id'=>null,'can_reverse'=>false,
                ]);
            }
            foreach($payments as $payment){
                if(!in_array((int)$payment->source_id,$expenseIds,true))continue;
                $rows->push((object)[
                    'date'=>$payment->payment_date,'id'=>'P'.$payment->id,
                    'ref'=>$payment->voucher_no,'type'=>'EXPENSE_PAYMENT',
                    'description'=>$payment->notes ?: 'Expense reimbursement','amount'=>(float)$payment->amount,
                    'month'=>'','reversal_of'=>null,
                    'source'=>'EXISTING_PAYMENT','journal_id'=>null,'can_reverse'=>false,
                ]);
            }
            $expenseDue=round(max(0,$expenseTotal-$expensePaid),2);
            $rows=$rows->sort(fn($a,$b)=>strcmp($b->date.'-'.$b->id,$a->date.'-'.$a->id))->values()->take(800);
        }
        $reversed=DB::table('workshop_staff_finance_journals')
            ->whereNotNull('reversal_of')->pluck('reversal_of')->map(fn($x)=>(int)$x)->all();
        $reversedIds=array_fill_keys($reversed,true);
        $rows=$rows->map(function($row)use($reversedIds){
            if($row->journal_id)$row->can_reverse=$row->can_reverse&&!isset($reversedIds[(int)$row->journal_id]);
            return $row;
        });
        return view('staff-finance.index',compact('people','selected','month','state','monthPayable',
            'expenseTotal','expensePaid','expenseDue','rows'));
    }

    public function store(Request $request): RedirectResponse
    {
        $actor=$this->actor($request);
        $data=$request->validate([
            'staff_id'=>['required','integer','exists:staff,id'],
            'entry_type'=>['required','in:SALARY_PROVISION,SALARY_ADVANCE,SALARY_PAYMENT,ADVANCE_ADJUST'],
            'entry_date'=>['required','date','before_or_equal:today'],
            'salary_month'=>['required','date_format:Y-m'],
            'amount'=>['required','numeric','gt:0','max:99999999.99','decimal:0,2'],
            'payment_mode'=>['nullable','in:Cash,UPI,Bank Transfer,Cheque,Card'],
            'account_name'=>['nullable','string','max:120'],
            'reference_no'=>['nullable','string','max:140'],
            'narration'=>['required','string','min:6','max:600'],
            'request_key'=>['required','uuid'],
        ]);
        $staffId=(int)$data['staff_id'];
        $type=$data['entry_type'];
        $amount=round((float)$data['amount'],2);
        $isCash=in_array($type,['SALARY_ADVANCE','SALARY_PAYMENT'],true);
        if($isCash && (empty($data['payment_mode'])||empty(trim((string)($data['account_name']??''))))){
            throw ValidationException::withMessages(['payment_mode'=>'Select payment mode and cash/bank account for staff payment.']);
        }
        $month=$data['salary_month'];
        $voucher=DB::transaction(function()use($data,$staffId,$type,$amount,$month,$actor,$isCash){
            $existing=DB::table('workshop_staff_finance_journals')
                ->where('request_key',$data['request_key'])->first();
            if($existing){
                if((int)$existing->staff_id!==$staffId||$existing->entry_type!==$type||
                   abs((float)$existing->amount-$amount)>0.001)
                    throw ValidationException::withMessages(['request_key'=>'Request key already used for a different entry.']);
                return $existing->voucher_no;
            }
            // Serialize all staff financial operations so balances cannot be over-allocated.
            $person=DB::table('staff')->where('id',$staffId)->lockForUpdate()->first();
            if(!$person)throw ValidationException::withMessages(['staff_id'=>'Staff record missing.']);
            if($type==='SALARY_PROVISION'){
                $provisionIds=DB::table('workshop_staff_finance_journals')
                    ->where('staff_id',$staffId)->where('entry_type','SALARY_PROVISION')
                    ->where('salary_month',$month)->pluck('id')->all();
                if($provisionIds){
                    $reversed=DB::table('workshop_staff_finance_journals')
                      ->whereIn('reversal_of',$provisionIds)->whereNotNull('reversal_of')
                      ->pluck('reversal_of')->all();
                    if(count(array_diff($provisionIds,$reversed)))
                        throw ValidationException::withMessages(['salary_month'=>
                            'Salary provision for this staff and month already posted. Reverse it with audit reason before correction.']);
                }
            }
            $monthPayable=$this->state($staffId,$month)['salary_payable'];
            $advance=$this->state($staffId)['salary_advance'];
            if(in_array($type,['SALARY_PAYMENT','ADVANCE_ADJUST'],true) && $amount>$monthPayable+0.001)
                throw ValidationException::withMessages(['amount'=>
                    'Payment/adjustment exceeds selected month salary payable ₹'.number_format($monthPayable,2).'.']);
            if($type==='ADVANCE_ADJUST' && $amount>$advance+0.001)
                throw ValidationException::withMessages(['amount'=>
                    'Adjustment exceeds available salary advance ₹'.number_format($advance,2).'.']);
            [$debit,$credit]=self::LEGS[$type];
            $id=DB::table('workshop_staff_finance_journals')->insertGetId([
                'staff_id'=>$staffId,'voucher_no'=>'PENDING-'.str_replace('-','',$data['request_key']),
                'entry_date'=>$data['entry_date'],'salary_month'=>$month,'entry_type'=>$type,
                'amount'=>$amount,'payment_mode'=>$isCash?($data['payment_mode']??null):null,
                'account_name'=>$isCash?trim((string)($data['account_name']??'')):null,
                'reference_no'=>trim((string)($data['reference_no']??''))?:null,
                'narration'=>trim($data['narration']),'request_key'=>$data['request_key'],
                'created_by'=>(string)($actor->name ?? 'Admin'),
                'created_at'=>now(),'updated_at'=>now()
            ]);
            $number='SL-'.now('Asia/Kolkata')->format('ymd').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
            DB::table('workshop_staff_finance_journals')->where('id',$id)->update(['voucher_no'=>$number]);
            $now=now();
            DB::table('workshop_staff_finance_lines')->insert([
                ['journal_id'=>$id,'staff_id'=>$staffId,'account_code'=>$debit[0],
                 'account_group'=>$debit[1],'debit'=>$amount,'credit'=>0,'created_at'=>$now,'updated_at'=>$now],
                ['journal_id'=>$id,'staff_id'=>$staffId,'account_code'=>$credit[0],
                 'account_group'=>$credit[1],'debit'=>0,'credit'=>$amount,'created_at'=>$now,'updated_at'=>$now]
            ]);
            return $number;
        },3);
        return redirect()->route('erp.staff-finance.index',['staff_id'=>$staffId,'month'=>$month])
            ->with('success','Balanced Staff Ledger voucher '.$voucher.' posted.');
    }

    public function reverse(Request $request,int $journal): RedirectResponse
    {
        $actor=$this->actor($request);
        $data=$request->validate([
            'reason'=>['required','string','min:8','max:300'],
            'request_key'=>['required','uuid']
        ]);
        $staffId=DB::transaction(function()use($journal,$data,$actor){
            $target=DB::table('workshop_staff_finance_journals')->where('id',$journal)->first();
            abort_unless($target,404,'Journal not found.');
            $staffId=(int)$target->staff_id;
            $staff=DB::table('staff')->where('id',$staffId)->lockForUpdate()->first();
            abort_unless($staff,404,'Staff record missing.');
            $target=DB::table('workshop_staff_finance_journals')->where('id',$journal)->lockForUpdate()->first();
            if($target->reversal_of)
                throw ValidationException::withMessages(['reason'=>'A reversal journal cannot be reversed here.']);
            if(DB::table('workshop_staff_finance_journals')->where('reversal_of',$journal)->exists())
                throw ValidationException::withMessages(['reason'=>'Journal has already been reversed.']);
            $lines=DB::table('workshop_staff_finance_lines')->where('journal_id',$journal)->get();
            if($lines->count()!==2)throw new \RuntimeException('Staff Journal lines unbalanced; escalation required.');
            $payable=$this->state($staffId,$target->salary_month)['salary_payable'];
            $advance=$this->state($staffId)['salary_advance'];
            $payableOriginal=0;$advanceOriginal=0;
            foreach($lines as $line){
                if($line->account_code==='SALARY_PAYABLE')$payableOriginal+=((float)$line->credit-(float)$line->debit);
                if($line->account_code==='STAFF_ADVANCE')$advanceOriginal+=((float)$line->debit-(float)$line->credit);
            }
            if($payable-$payableOriginal < -0.001 || $advance-$advanceOriginal < -0.001)
                throw ValidationException::withMessages(['reason'=>
                    'Cannot reverse while dependent salary payment / advance adjustment remains. Reverse dependent entries first.']);
            $existing=DB::table('workshop_staff_finance_journals')->where('request_key',$data['request_key'])->exists();
            if($existing)throw ValidationException::withMessages(['request_key'=>'This reversal request was already posted.']);
            $id=DB::table('workshop_staff_finance_journals')->insertGetId([
                'staff_id'=>$staffId,'voucher_no'=>'PENDING-'.str_replace('-','',$data['request_key']),
                'entry_date'=>now('Asia/Kolkata')->toDateString(),'salary_month'=>$target->salary_month,
                'entry_type'=>'REVERSAL','amount'=>$target->amount,'request_key'=>$data['request_key'],
                'narration'=>'Reversal of '.$target->voucher_no.': '.$data['reason'],
                'reference_no'=>$target->voucher_no,'created_by'=>(string)($actor->name??'Admin'),
                'reversal_of'=>$journal,'reversal_reason'=>$data['reason'],
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('workshop_staff_finance_journals')->where('id',$id)
                ->update(['voucher_no'=>'SLR-'.now('Asia/Kolkata')->format('ymd').'-'.str_pad((string)$id,6,'0',STR_PAD_LEFT)]);
            foreach($lines as $line){
                DB::table('workshop_staff_finance_lines')->insert([
                    'journal_id'=>$id,'staff_id'=>$staffId,'account_code'=>$line->account_code,
                    'account_group'=>$line->account_group,'debit'=>$line->credit,'credit'=>$line->debit,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
            return $staffId;
        },3);
        return redirect()->route('erp.staff-finance.index',['staff_id'=>$staffId])
            ->with('success','Reversal journal posted with original voucher retained.');
    }
}
