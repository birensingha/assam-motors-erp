<?php
/** AM231 Staging READ ONLY: compile actual recipient-specific voucher view,
 * exercise generic vs Expense->Pay renders, inspect Vendor/Staff/Customer lists.
 */
declare(strict_types=1);
if (!is_file(getcwd().'/artisan')) {echo "AM231_QA_APP_ROOT_MISSING\n";exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
function fail231(string $e):never { echo "AM231_QA_FAIL_".$e."\n";exit(3);}
foreach(['staff','customer_master','workshop_payment_vouchers'] as $table){
 if(!Schema::hasTable($table))fail231('MISSING_TABLE_'.$table);
}
$view=file_get_contents('resources/views/payments/create.blade.php');
$controller=file_get_contents('app/Http/Controllers/Web/AdminExpensePaymentPageController.php');
if(substr_count($view,'AM231_PAYMENT_TO_ACCOUNTING_CONTEXT')!==1)fail231('UI_MARKER_MISSING');
if(!str_contains($controller,"'am231Staff'")||!str_contains($controller,"'am231Customers'"))fail231('MASTER_GET_BINDING_MISSING');
if(!str_contains($controller,'AM230 no Staff/Customer ledger key'))fail231('ORIGINAL_BLOCKING_GUARD_MISSING');
try{
 $staff=DB::table('staff')->orderBy('name')->limit(2)->get(['id','staff_code','name']);
 $customers=DB::table('customer_master')->orderBy('name')->limit(2)->get(['id','customer_code','name']);
 $render=function($url) use ($app,$staff,$customers) {
  $r=Request::create($url,'GET');
  $r->setLaravelSession($app->make('session')->driver());
  $app->instance('request',$r);
  \Illuminate\Support\Facades\Request::swap($r);
  return view('payments.create',[
    'sources'=>[], 'jobs'=>[],
    'vendors'=>[['id'=>1,'vendor_code'=>'TEST','vendor_name'=>'Vendor Test']],
    'am231Staff'=>$staff,'am231Customers'=>$customers,
    'errors'=>new \Illuminate\Support\ViewErrorBag(),
  ])->render();
 };
 $generic=$render('/erp/payments/create');
 $expense=$render('/erp/payments/create?source=EXPENSE_VOUCHER&id=1');
}catch(\Throwable $e){
 echo 'AM231_QA_RENDER_EX_CLASS='.get_class($e)."\n";
 echo 'AM231_QA_RENDER_EX_MESSAGE='.substr($e->getMessage(),0,210)."\n";
 fail231('RENDER_500');
}
foreach([
 'Payment To', 'am231-staff-select','am231-customer-select',
 'am231-payment-purpose','am231-reference-choices',
 'Against Reference','Advance','On Account','am231-accounting-script',
 'am231-non-vendor','id="payment-vendor"','name="vendor_id"',
 'POST PAYMENT VOUCHER'
] as $needle){
 if(!str_contains($generic,$needle)){
  fail231('MISSING_'.preg_replace('/[^A-Za-z0-9]/','_',$needle));
 }
}
if(substr_count($generic,'name="vendor_id"')!==1)fail231('DUPLICATE_VENDOR_POST_FIELD');
if(substr_count($generic,'name="source_type"')!==1)fail231('DUPLICATE_SOURCE_TYPE');
if(substr_count($generic,'name="amount"')!==1)fail231('DUPLICATE_AMOUNT');
if(preg_match('/<option\\s+value="EXPENSE_VOUCHER"/',$generic))fail231('EXPENSE_VISIBLE_IN_GENERIC');
if(!preg_match('/<option\\s+value="EXPENSE_VOUCHER"/',$expense))fail231('EXPENSE_DEEPLINK_BROKEN');
echo "AM231_GENERIC_AND_EXPENSE_PAYMENT_RENDER_PASS\n";
echo "AM231_VENDOR_STAFF_CUSTOMER_MASTER_FIELDS_RENDER_PASS\n";
echo "AM231_SINGLE_FINANCIAL_POST_FIELDS_PASS\n";
echo "AM231_ORIGINAL_FINANCIAL_GUARD_PRESENT\n";
echo "AM231_RUNTIME_QA_COMPLETE_NO_WRITES\n";
