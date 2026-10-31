<?php
declare(strict_types=1);
/** AM230 strict read-only staging runtime verify: render generic + Expenses->Pay
 * through actual Laravel Blade without posting any financial transaction.
 */
if (!is_file(getcwd().'/artisan')) {echo "AM230_INVALID_STAGING_ROOT\n";exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
function bad230(string $x): never {echo "AM230_QA_FAIL_".$x."\n";exit(3);}
$view=file_get_contents('resources/views/payments/create.blade.php');
$controller=file_get_contents('app/Http/Controllers/Web/AdminExpensePaymentPageController.php');
if(!str_contains($view,'AM230_PAYMENT_TO_CATEGORY_PERSISTENT')
 ||!str_contains($controller,'AM230 no Staff/Customer ledger key'))
 bad230('PATCH_MISSING');
if(!Schema::hasTable('workshop_payment_vouchers'))bad230('PAYMENT_TABLE_MISSING');
foreach(['payee_type','vendor_id','payee_name','source_type'] as $col) {
 if(!Schema::hasColumn('workshop_payment_vouchers',$col))bad230('SCHEMA_'.$col);
}
try{
 $render=function(string $url) use($app):string {
  $r=Request::create($url,'GET');
  $r->setLaravelSession($app->make('session')->driver());
  $app->instance('request',$r);
  \Illuminate\Support\Facades\Request::swap($r);
  return view('payments.create',[
    'sources'=>[], 'jobs'=>[], 'vendors'=>[['id'=>1,'vendor_code'=>'TEST','vendor_name'=>'Test Vendor']],
    'errors'=>new \Illuminate\Support\ViewErrorBag(),
  ])->render();
 };
 $generic=$render('/erp/payments/create');
 $expense=$render('/erp/payments/create?source=EXPENSE_VOUCHER&id=1');
}catch(\Throwable $t){
 echo 'AM230_QA_RENDER_ERROR='.get_class($t).' '.substr($t->getMessage(),0,220)."\n";
 bad230('RENDER_EXCEPTION');
}
foreach(['Payment To','Staff','Customer','am230-payee-type',
         'id="payment-vendor"','Search Vendor Master','am230-payee-script',
         'POST PAYMENT VOUCHER'] as $needle){
 if(!str_contains($generic,$needle))bad230('RENDER_MISSING_'.preg_replace('/[^a-z0-9]/i','_',$needle));
}
if(str_contains($generic,'value="EXPENSE_VOUCHER"'))bad230('EXPENSE_STILL_IN_GENERAL_DROPDOWN');
if(!str_contains($expense,'value="EXPENSE_VOUCHER"'))bad230('EXPENSE_DEEPLINK_BROKEN');
if(!str_contains($controller,"['STAFF','CUSTOMER']"))bad230('FINANCIAL_SERVER_GUARD_MISSING');
echo "AM230_GENERIC_FORM_RENDER_PASS\n";
echo "AM230_EXPENSE_PAYMENT_DEEPLINK_RENDER_PASS\n";
echo "AM230_VENDOR_IDENTITY_AND_UNSUPPORTED_PAYEE_GUARD_PASS\n";
echo "AM230_RUNTIME_QA_COMPLETE_NO_PAYMENT_POSTING\n";
