<?php
/** AM240 staging DB rollback-only test. No lasting financial data or real payout. */
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\ErpAuthUser;
use App\Services\StaffPaymentVoucherService;
use App\Services\ExpensePaymentService;
use App\Http\Controllers\Web\AdminStaffFinanceLedgerController;

function check240(bool $condition,string $message): void {
 if(!$condition)throw new RuntimeException('AM240_QA_FAIL_'.$message);
}
$person=DB::table('staff')->where('status','Active')->first(['id','name']);
if(!$person)$person=DB::table('staff')->first(['id','name']);
if(!$person)throw new RuntimeException('AM240_QA_FAIL_NO_STAFF_MASTER_FOR_TRANSACTION_TEST');
// Resolve a real authorized admin model from the ERP auth table, without reading credentials.
// This identity is used ONLY for a fully rolled-back financial QA transaction.
$fakeUser=ErpAuthUser::query()->whereRaw('LOWER(role) = ?', ['admin'])->where('active',1)->first();
check240($fakeUser instanceof ErpAuthUser && $fakeUser->isAdmin() && $fakeUser->active,
 'AUTHENTIC_ADMIN_MODEL_MISSING');
echo "AM240_QA_ADMIN_USER_MODEL_READONLY_PASS\n";
function makeRequest(array $data,ErpAuthUser $user): Request {
 $request=Request::create('/erp/payments','POST',$data);
 $request->setLaravelSession(app('session')->driver());
 app()->instance('request',$request);
 \Illuminate\Support\Facades\Request::swap($request);
 // Container re-binding can install the default auth resolver; override AFTER swap.
 $request->setUserResolver(static fn()=>$user);
 return $request;
}
$month='2010-01';$date=now('Asia/Kolkata')->toDateString();
$uuid0=(string)Str::uuid();$uuidA=(string)Str::uuid();$uuidS=(string)Str::uuid();
$id=(int)$person->id;
$service=app(StaffPaymentVoucherService::class);
DB::beginTransaction();
try {
 $provision=makeRequest([
  'staff_id'=>$id,'entry_type'=>'SALARY_PROVISION','entry_date'=>$date,
  'salary_month'=>$month,'amount'=>'75.00',
  'narration'=>'AM240 rollback staff provision only','request_key'=>$uuid0
 ],$fakeUser);
 check240($provision->user() instanceof ErpAuthUser && $provision->user()->isAdmin() &&
   strtolower((string)$provision->user()->role)==='admin','REQUEST_USER_RESOLVER_MISMATCH');
 echo "AM240_QA_REQUEST_USER_AUTHORIZATION_PASS\n";
 app(AdminStaffFinanceLedgerController::class)->store($provision);
 check240(DB::table('workshop_staff_finance_journals')->where('request_key',$uuid0)->exists(),'PROVISION_NOT_POSTED_IN_ROLLBACK');

 $base=[
  'payee_category'=>'STAFF','staff_id'=>$id,'payment_date'=>$date,'salary_month'=>$month,
  'payment_mode'=>'Cash','account_name'=>'Cash','reference_no'=>'QA-ROLLBACK',
  'notes'=>'AM240 rollback accounting integration test'
 ];
 $advance=makeRequest($base+[
  'staff_payment_against'=>'ADVANCE','staff_payment_kind'=>'SALARY_ADVANCE',
  'amount'=>'25.00','request_key'=>$uuidA,
 ],$fakeUser);
 $result=$service->post($advance);
 check240($result->getStatusCode()===302,'ADVANCE_NO_REDIRECT');
 $vAdvance=DB::table('workshop_payment_vouchers')->where('request_key',$uuidA)->first();
 $jAdvance=DB::table('workshop_staff_finance_journals')->where('request_key',$uuidA)->first();
 check240($vAdvance && $jAdvance,'ADVANCE_VOUCHER_JOURNAL_MISSING');
 check240($vAdvance->source_type==='STAFF_SALARY_ADVANCE' &&
  $vAdvance->payee_type==='STAFF' && (int)$vAdvance->staff_id===$id &&
  (int)$vAdvance->source_id===(int)$jAdvance->id,'ADVANCE_STAFF_ID_OR_LINK_MISMATCH');
 check240((int)DB::table('workshop_staff_finance_lines')->where('journal_id',$jAdvance->id)->count()===2,
  'ADVANCE_JOURNAL_NOT_TWO_LINES');
 $advanceCash=DB::table('workshop_staff_finance_lines')->where('journal_id',$jAdvance->id)
   ->selectRaw('SUM(debit-credit) as total')->first();
 check240(abs((float)$advanceCash->total)<0.009,'ADVANCE_UNBALANCED');
 echo "AM240_QA_STAFF_ADVANCE_VOUCHER_LEDGER_DOUBLE_ENTRY_BALANCED_PASS\n";

 $salary=makeRequest($base+[
  'staff_payment_against'=>'AGAINST','staff_payment_kind'=>'SALARY_PAYMENT',
  'amount'=>'75.00','request_key'=>$uuidS,
 ],$fakeUser);
 $service->post($salary);
 $vSalary=DB::table('workshop_payment_vouchers')->where('request_key',$uuidS)->first();
 $jSalary=DB::table('workshop_staff_finance_journals')->where('request_key',$uuidS)->first();
 check240($vSalary && $jSalary,'SALARY_VOUCHER_JOURNAL_MISSING');
 check240($vSalary->source_type==='STAFF_SALARY_PAYMENT' &&
  (int)$vSalary->source_id===(int)$jSalary->id,'SALARY_VOUCHER_SOURCE_MISMATCH');
 $salaryDue=(float)DB::table('workshop_staff_finance_lines as l')
   ->join('workshop_staff_finance_journals as j','j.id','=','l.journal_id')
   ->where('l.staff_id',$id)->where('l.account_code','SALARY_PAYABLE')
   ->where('j.salary_month',$month)->sum('l.credit');
 $salaryDeb=(float)DB::table('workshop_staff_finance_lines as l')
   ->join('workshop_staff_finance_journals as j','j.id','=','l.journal_id')
   ->where('l.staff_id',$id)->where('l.account_code','SALARY_PAYABLE')
   ->where('j.salary_month',$month)->sum('l.debit');
 check240(abs($salaryDue-$salaryDeb)<0.009,'SALARY_PAYABLE_NOT_SETTLED');
 echo "AM240_QA_SALARY_PAYABLE_VOUCHER_LINK_AND_MONTHLY_LIMIT_PASS\n";

 try{
  app(ExpensePaymentService::class)->voidPayment((int)$vAdvance->id,
     'AM240 rollback check','AM240 QA');
  throw new RuntimeException('AM240_QA_FAIL_GENERIC_VOID_WAS_ALLOWED');
 }catch(ValidationException $e){}
 echo "AM240_QA_GENERIC_VOID_CANNOT_ORPHAN_SALARY_LEDGER_PASS\n";

 $voidSalary=makeRequest(['reason'=>'AM240 audit rollback verified salary','request_key'=>(string)Str::uuid()],$fakeUser);
 $service->void($voidSalary,(int)$vSalary->id);
 $voidAdvance=makeRequest(['reason'=>'AM240 audit rollback verified advance','request_key'=>(string)Str::uuid()],$fakeUser);
 $service->void($voidAdvance,(int)$vAdvance->id);
 check240(DB::table('workshop_payment_vouchers')->whereIn('request_key',[$uuidA,$uuidS])
  ->where('record_status','VOID')->count()===2,'VOUCHER_VOID_NOT_LINKED');
 check240(DB::table('workshop_staff_finance_journals')
  ->whereIn('reversal_of',[$jAdvance->id,$jSalary->id])->count()===2,
  'SALARY_ADVANCE_REVERSAL_NOT_PAIRED');
 echo "AM240_QA_STAFF_VOUCHER_VOID_AND_SALARY_LEDGER_REVERSAL_PASS\n";

 try {
  $direct=makeRequest([
   'staff_id'=>$id,'entry_type'=>'SALARY_ADVANCE','entry_date'=>$date,'salary_month'=>$month,
   'amount'=>'15.00','payment_mode'=>'Cash','account_name'=>'Cash',
   'narration'=>'AM240 blocked direct salary cash','request_key'=>(string)Str::uuid()
  ],$fakeUser);
  app(AdminStaffFinanceLedgerController::class)->store($direct);
  throw new RuntimeException('AM240_QA_FAIL_DIRECT_LEDGER_PAYMENT_NOT_BLOCKED');
 } catch(ValidationException $e){}
 echo "AM240_QA_STAFF_LEDGER_DIRECT_SALARY_CASH_POSTING_BLOCKED_PASS\n";
} catch(\Throwable $e) {
 echo 'AM240_QA_ERROR='.get_class($e).' '.substr($e->getMessage(),0,240)."\n";
 throw $e;
} finally {
 DB::rollBack();
 echo "AM240_QA_TRANSACTION_ROLLED_BACK\n";
}
check240(!DB::table('workshop_payment_vouchers')->whereIn('request_key',[$uuidA,$uuidS])->exists(),
 'PERSISTENT_PAYMENT_TEST_ROWS');
check240(!DB::table('workshop_staff_finance_journals')->whereIn('request_key',[$uuid0,$uuidA,$uuidS])->exists(),
 'PERSISTENT_SALARY_TEST_ROWS');
echo "AM240_QA_NO_PERSISTENT_FINANCIAL_RECORDS_PASS\n";
