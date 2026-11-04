<?php
namespace App\Services;

use App\Models\ErpAuthUser;
use App\Models\WorkshopPaymentVoucher;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/**
 * AM245 — one Staff Payment Voucher for multiple already-posted Expense Vouchers.
 *
 * Uses existing workshop_payment_allocations as immutable per-reference shares:
 * purchase_ref = "EXPENSE_VOUCHER::<id>".
 * This deliberately does not re-create an Expense or a Staff salary journal.
 */
final class StaffMultiExpensePaymentService
{
    private const REF = 'STAFF_EXPENSE_MULTI';

    private function reject(string $field,string $reason): never
    {
        throw ValidationException::withMessages([$field=>$reason]);
    }

    private function admin(Request $request): string
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403);
        return (string)($user->email?:$user->name?:'ADMIN');
    }

    private function cents($value,string $field): int
    {
        $s=trim((string)$value);
        if(!preg_match('/^\\d{1,10}(?:\\.\\d{1,2})?$/',$s))
            $this->reject($field,'Enter a valid amount with up to two decimal places.');
        [$whole,$part]=array_pad(explode('.',$s,2),2,'0');
        $n=(int)$whole*100+(int)str_pad($part,2,'0');
        if($n<=0)$this->reject($field,'Payment amount must be greater than zero.');
        return $n;
    }

    public function outstandingRows(?int $staffId=null): array
    {
        $expenses=DB::table('workshop_expenses as e')
            ->leftJoin('job_cards as jc','jc.id','=','e.job_card_id')
            ->where('e.record_status','ACTIVE')->whereNotNull('e.staff_id')
            ->when($staffId,fn($q)=>$q->where('e.staff_id',$staffId))
            ->select(['e.id','e.staff_id','e.expense_no','e.expense_date','e.expense_category',
                'e.total_amount','e.description','e.payee_name','jc.job_no','jc.vehicle_reg_no'])
            ->orderBy('e.expense_date')->orderBy('e.id')->limit(2000)->get();
        if($expenses->isEmpty())return [];
        $ids=$expenses->pluck('id')->all();
        $direct=DB::table('workshop_payment_vouchers')
            ->where('record_status','ACTIVE')->where('source_type','EXPENSE_VOUCHER')
            ->whereIn('source_id',$ids)
            ->selectRaw('source_id, SUM(amount) AS paid')
            ->groupBy('source_id')->pluck('paid','source_id');
        $shares=DB::table('workshop_payment_allocations as a')
            ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
            ->where('v.record_status','ACTIVE')
            ->whereIn('a.purchase_ref',array_map(fn($id)=>'EXPENSE_VOUCHER::'.$id,$ids))
            ->selectRaw('a.purchase_ref, SUM(a.amount) AS paid')->groupBy('a.purchase_ref')
            ->pluck('paid','purchase_ref');
        $rows=[];
        foreach($expenses as $e){
            $paid=round((float)($direct[$e->id]??0)+(float)($shares['EXPENSE_VOUCHER::'.$e->id]??0),2);
            $due=round(max(0,(float)$e->total_amount-$paid),2);
            if($due<=0.009)continue;
            $rows[]=[
                'id'=>(int)$e->id,'staff_id'=>(int)$e->staff_id,'expense_no'=>$e->expense_no,
                'date'=>$e->expense_date,'category'=>$e->expense_category,
                'job_no'=>$e->job_no,'vehicle'=>$e->vehicle_reg_no,
                'description'=>mb_substr((string)$e->description,0,180),
                'total'=>round((float)$e->total_amount,2),'paid'=>$paid,'outstanding'=>$due
            ];
        }
        return $rows;
    }

    private function allocatedCents(int $expenseId): int
    {
        $direct=(float)DB::table('workshop_payment_vouchers')->where('record_status','ACTIVE')
            ->where('source_type','EXPENSE_VOUCHER')->where('source_id',$expenseId)->sum('amount');
        $multi=(float)DB::table('workshop_payment_allocations as a')
            ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
            ->where('v.record_status','ACTIVE')
            ->where('a.purchase_ref','EXPENSE_VOUCHER::'.$expenseId)->sum('a.amount');
        return (int)round(($direct+$multi)*100);
    }

    private function refresh(int $expenseId): void
    {
        $e=DB::table('workshop_expenses')->where('id',$expenseId)->lockForUpdate()->first();
        if(!$e)return;
        $paid=min((int)round((float)$e->total_amount*100),$this->allocatedCents($expenseId));
        $status=$paid<=0?'UNPAID':($paid+1 >= (int)round((float)$e->total_amount*100)?'PAID':'PARTIAL');
        DB::table('workshop_expenses')->where('id',$expenseId)
            ->update(['paid_amount'=>round($paid/100,2),'payment_status'=>$status]);
    }

    public function post(Request $request): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate([
            'payee_category'=>['required','in:STAFF'],
            'staff_multi_expense'=>['required','in:1'],
            'staff_id'=>['required','integer','min:1'],
            'staff_payment_kind'=>['required','in:EXPENSE_REIMBURSEMENT'],
            'staff_payment_against'=>['required','in:AGAINST'],
            'payment_date'=>['required','date_format:Y-m-d','before_or_equal:today'],
            'amount'=>['required','numeric','gt:0','max:99999999.99','decimal:0,2'],
            'payment_mode'=>['required','in:Cash,UPI,Bank Transfer,Cheque,Card'],
            'account_name'=>['required','string','min:2','max:120'],
            'reference_no'=>['nullable','string','max:140'],
            'notes'=>['required','string','min:6','max:600'],
            'request_key'=>['required','uuid'],
            'allocations'=>['required','array','min:1','max:100'],
            'allocations.*.expense_id'=>['required','integer','min:1'],
            'allocations.*.amount'=>['required','numeric','gt:0','decimal:0,2'],
        ]);
        $staffId=(int)$data['staff_id'];
        $requestKey=(string)$data['request_key'];
        $totalCents=$this->cents($request->input('amount'),'amount');
        $normalized=[];
        foreach($data['allocations'] as $index=>$row){
            $id=(int)$row['expense_id'];
            if(isset($normalized[$id]))$this->reject('allocations','The same Expense Voucher cannot be selected twice.');
            $normalized[$id]=$this->cents($row['amount'],'allocations.'.$index.'.amount');
        }
        ksort($normalized,SORT_NUMERIC);
        if(array_sum($normalized)!==$totalCents)
            $this->reject('allocations','Total allocations must exactly equal Payment Amount. No unallocated remainder is allowed.');
        return DB::transaction(function()use($data,$staffId,$requestKey,$totalCents,$normalized,$actor){
            $staff=DB::table('staff')->where('id',$staffId)->lockForUpdate()->first();
            if(!$staff||strtolower((string)$staff->status)!=='active')
                $this->reject('staff_id','Select an active Staff member from Staff Master.');
            $existing=WorkshopPaymentVoucher::query()->where('request_key',$requestKey)->lockForUpdate()->first();
            if($existing){
                if($existing->source_type!=='EXPENSE_VOUCHER'||$existing->source_reference!==self::REF||
                    (int)$existing->staff_id!==$staffId ||
                    (int)round((float)$existing->amount*100)!==$totalCents)
                    $this->reject('request_key','This request key belongs to another payment. No duplicate posted.');
                $current=DB::table('workshop_payment_allocations')
                    ->where('payment_voucher_id',$existing->id)->get();
                $actual=[];
                foreach($current as $a){
                    if(preg_match('/^EXPENSE_VOUCHER::(\\d+)$/',(string)$a->purchase_ref,$m))
                        $actual[(int)$m[1]]=(int)round((float)$a->amount*100);
                }
                ksort($actual,SORT_NUMERIC);
                if($actual!==$normalized)$this->reject('request_key','Existing request allocations differ. No second payment posted.');
                return redirect()->route('erp.payments.show',$existing->id);
            }
            $expenses=DB::table('workshop_expenses')
                ->whereIn('id',array_keys($normalized))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach($normalized as $id=>$cents){
                $e=$expenses->get($id);
                if(!$e||(string)$e->record_status!=='ACTIVE'||(int)$e->staff_id!==$staffId)
                    $this->reject('allocations','Selected expense '.$id.' is no longer an active payable for this Staff.');
                $due=(int)round((float)$e->total_amount*100)-$this->allocatedCents($id);
                if($due<=0||$cents>$due)
                    $this->reject('allocations','Expense '.$e->expense_no.' has insufficient outstanding balance. Refresh the unpaid reference list.');
            }
            $voucher=WorkshopPaymentVoucher::query()->create([
                'payment_date'=>$data['payment_date'],'source_type'=>'EXPENSE_VOUCHER',
                'source_id'=>null,'source_reference'=>self::REF,
                'job_card_id'=>null,'payee_type'=>'STAFF','staff_id'=>$staffId,
                'vendor_id'=>null,'payee_name'=>(string)$staff->name,
                'amount'=>round($totalCents/100,2),'payment_mode'=>$data['payment_mode'],
                'account_name'=>$data['account_name']??null,
                'reference_no'=>$data['reference_no']??null,'notes'=>$data['notes'],
                'record_status'=>'ACTIVE','request_key'=>$requestKey,'created_by'=>$actor
            ]);
            $voucher->voucher_no='PAY-'.now('Asia/Kolkata')->format('Ymd').'-'.
                str_pad((string)$voucher->id,6,'0',STR_PAD_LEFT);
            $voucher->save();
            foreach($normalized as $id=>$cents){
                DB::table('workshop_payment_allocations')->insert([
                    'payment_voucher_id'=>$voucher->id,'purchase_ref'=>'EXPENSE_VOUCHER::'.$id,
                    'amount'=>round($cents/100,2),'request_key'=>(string)Str::uuid(),
                    'created_at'=>now(),'updated_at'=>now()
                ]);
            }
            foreach(array_keys($normalized) as $id)$this->refresh($id);
            return redirect()->route('erp.payments.show',$voucher->id)
                ->with('success','One Staff Payment Voucher posted against '.count($normalized).' Expense references. All allocations audited.');
        },3);
    }

    public function void(Request $request,int $payment): RedirectResponse
    {
        $actor=$this->admin($request);
        $data=$request->validate(['reason'=>['required','string','min:8','max:300']]);
        return DB::transaction(function()use($payment,$data,$actor){
            $voucher=WorkshopPaymentVoucher::query()->where('id',$payment)->lockForUpdate()->first();
            if(!$voucher||$voucher->record_status!=='ACTIVE'||
               $voucher->source_type!=='EXPENSE_VOUCHER'||$voucher->source_reference!==self::REF||
               $voucher->payee_type!=='STAFF')
                $this->reject('reason','Not an active multi-expense Staff Payment Voucher.');
            $allocations=DB::table('workshop_payment_allocations')
                ->where('payment_voucher_id',$payment)->lockForUpdate()->get();
            if($allocations->isEmpty())$this->reject('reason','Missing allocation history. Financial reversal blocked.');
            $ids=[];
            foreach($allocations as $item){
                if(!preg_match('/^EXPENSE_VOUCHER::([1-9]\\d*)$/',(string)$item->purchase_ref,$m))
                    $this->reject('reason','Unexpected reference in payment allocation. Cannot void.');
                $ids[(int)$m[1]]=true;
            }
            foreach(array_keys($ids) as $id)DB::table('workshop_expenses')->where('id',$id)->lockForUpdate()->first();
            $voucher->record_status='VOID';
            $voucher->void_reason=$data['reason'];
            $voucher->voided_by=$actor;
            $voucher->voided_at=now();
            $voucher->save();
            foreach(array_keys($ids) as $id)$this->refresh($id);
            return redirect()->route('erp.payments.show',$voucher->id)
              ->with('success','Staff Multi-Expense Payment Voucher voided; each expense outstanding restored according to active payments.');
        },3);
    }
}
