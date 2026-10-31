<?php
/** AM233 strictly read-only acceptance: real Staging Expense form/register/detail
 * rendering, linked Staff Master and new coupon/payment schema. No POSTs.
 */
declare(strict_types=1);
if(!is_file(getcwd().'/artisan')){echo "AM233_INVALID_STAGING_ROOT\n";exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use App\Services\ExpensePaymentService;
function am233Fail(string $x):never{echo 'AM233_QA_FAIL_'.$x."\n";exit(3);}
foreach(['workshop_expenses'=>['staff_id','coupon_company','coupon_no'],
         'workshop_payment_vouchers'=>['staff_id','request_key']] as $table=>$cols){
 foreach($cols as $column)if(!Schema::hasColumn($table,$column))am233Fail('MISSING_SCHEMA_'.$column);
}
$service=$app->make(ExpensePaymentService::class);
if(!in_array('LUBRICASH_COUPON',$service->expenseCategories(),true))am233Fail('CATEGORY_NOT_ACTIVE');
$controller=file_get_contents('app/Http/Controllers/Web/AdminExpensePaymentPageController.php');
$impl=file_get_contents('app/Services/ExpensePaymentService.php');
$routes=file_get_contents('routes/web.php');
if(!str_contains($impl,'AM233_STAFF_LUBRICASH_AUDIT'))am233Fail('SERVICE_PATCH_MISSING');
if(!str_contains($impl,'lockForUpdate()')||!str_contains($impl,'where(\'request_key\',$requestKey)'))am233Fail('PAYMENT_LOCK_IDEMPOTENCY_MISSING');
if(!str_contains($controller,'payLubricashExpense('))am233Fail('STAFF_PAYOUT_POST_ACTION_MISSING');
if(!str_contains($routes,'erp.expenses.lubricash-payment'))am233Fail('LINKED_EXPENSE_PAY_ROUTE_MISSING');
try{
 $staff=DB::table('staff')->orderBy('name')->get(['id','staff_code','name']);
 $app->instance('request',$request=Request::create('/erp/expenses/create','GET'));
 \Illuminate\Support\Facades\Request::swap($request);
 $request->setLaravelSession($app->make('session')->driver());
 $create=view('expenses.create',[
    'jobs'=>$service->jobs(),'vendors'=>$service->vendors(),'categories'=>$service->expenseCategories(),
    'presetJobId'=>0,'am233Staff'=>$staff,'errors'=>new \Illuminate\Support\ViewErrorBag(),
 ])->render();
 $rows=$service->searchExpenses(['status'=>'UNPAID']);
 $index=view('expenses.index',[
    'expenses'=>$rows,'summary'=>$service->expenseSummary(),'categories'=>$service->expenseCategories(),
    'filters'=>['status'=>'UNPAID'],'am233Staff'=>$staff,'errors'=>new \Illuminate\Support\ViewErrorBag(),
 ])->render();
 $first=DB::table('workshop_expenses')->orderByDesc('id')->value('id');
 if($first){
  $expense=$service->showExpense((int)$first);
  $detail=view('expenses.show',$expense+['errors'=>new \Illuminate\Support\ViewErrorBag()])->render();
 }else{$detail='NO_EXPENSES_TO_RENDER';}
}catch(\Throwable $e){
 echo 'AM233_QA_RENDER_ERROR_CLASS='.get_class($e)."\n";
 echo 'AM233_QA_RENDER_ERROR_MESSAGE='.mb_substr($e->getMessage(),0,240)."\n";
 am233Fail('EXPENSE_VIEW_RENDER');
}
foreach(['name="staff_id"','name="coupon_company"','name="coupon_no"',
 'am233-jc-search','am233-lubricash-js','name="pay_now"','LUBRICASH COUPON'] as $s){
 if(!str_contains($create,$s))am233Fail('CREATE_FIELD_'.preg_replace('/[^A-Za-z0-9]/','_',$s));
}
if(!str_contains($index,'name="staff_id"'))am233Fail('STAFF_REGISTER_FILTER');
if(!str_contains($detail,'Payment History'))am233Fail('DETAIL_PAYMENT_HISTORY');
echo "AM233_EXPENSE_FORM_RENDER_PASS\n";
echo "AM233_EXPENSE_REGISTER_AND_STAFF_FILTER_PASS\n";
echo "AM233_EXPENSE_DETAIL_RENDER_PASS\n";
echo "AM233_COUPON_PAYMENT_FIELDS_AND_LOCKING_PASS\n";
echo "AM233_RUNTIME_READONLY_QA_PASS\n";
