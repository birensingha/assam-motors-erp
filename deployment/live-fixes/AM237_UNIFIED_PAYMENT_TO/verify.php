<?php
declare(strict_types=1);
/** AM237 read-only Laravel payment form rendering smoke test, NO financial POST */
if(!is_file(getcwd().'/artisan')){echo "AM237_STOP_INVALID_STAGING_ROOT\n";exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
function verifyFail(string $message): never{echo "AM237_QA_FAIL_".$message."\n";exit(3);}
foreach(['workshop_payment_vouchers','workshop_vendors','staff','customer_master','workshop_purchases'] as $table)
 if(!Schema::hasTable($table))verifyFail('SCHEMA_'.$table);
$vendor=DB::table('workshop_vendors')->select('id','vendor_code','vendor_name')->limit(2)->get()->map(fn($v)=>(array)$v)->all();
$staff=DB::table('staff')->select('id','staff_code','name')->limit(2)->get();
$customers=DB::table('customer_master')->select('id','customer_code','name')->limit(2)->get();
$osl=DB::table('workshop_purchases')->where('purchase_type','OSL')->whereNotNull('vendor_id')
  ->distinct()->limit(3)->pluck('vendor_id')->all();
$base=['sources'=>[],'vendors'=>$vendor,'jobs'=>[],'am231Staff'=>$staff,
 'am231Customers'=>$customers,'am237OslVendorIds'=>$osl,
 'errors'=>new \Illuminate\Support\ViewErrorBag()];
function renderFor(string $path,array $vars): string{
 $r=Request::create($path,'GET');
 $r->setLaravelSession(app('session')->driver());
 app()->instance('request',$r);
 \Illuminate\Support\Facades\Request::swap($r);
 return view('payments.create',$vars)->render();
}
try{
 $normal=renderFor('/erp/payments/create',$base);
 $expense=renderFor('/erp/payments/create?source=EXPENSE_VOUCHER&id=1',$base);
}catch(\Throwable $e){
 echo "AM237_RENDER_ERROR=".get_class($e).' '.substr($e->getMessage(),0,220)."\n";
 verifyFail('RENDER_EXCEPTION');
}
foreach(['id="am237-payee-search"','id="am237-mode"','id="am237-amount-slot"',
 'id="am237-bills-slot"','id="am237-unified-script"','name="vendor_id"',
 'name="payee_category"','name="source_type"','name="request_key"',
 'name="amount"','id="p133-multi-area"'] as $needle)
 if(!str_contains($normal,$needle))verifyFail('NORMAL_MISSING_'.md5($needle));
if(str_contains($expense,'id="am237-payee-search"'))verifyFail('EXPENSE_DEEPLINK_NOT_PRESERVED');
if(!str_contains($expense,'value="EXPENSE_VOUCHER"'))verifyFail('EXPENSE_SOURCE_OPTION_MISSING');
if(!str_contains(file_get_contents('app/Http/Controllers/Web/AdminExpensePaymentPageController.php'),
 'AM230 no Staff/Customer ledger key'))verifyFail('UNSUPPORTED_LEDGER_GUARD_MISSING');
echo "AM237_NORMAL_PAYMENT_VIEW_RENDER_PASS\n";
echo "AM237_EXPENSE_LINK_VIEW_RENDER_PASS\n";
echo "AM237_VENDOR_AND_STAFF_CUSTOMER_MASTER_LOOKUP_RENDER_PASS\n";
echo "AM237_FINANCIAL_SOURCE_FIELDS_PRESERVED\n";
echo "AM237_READONLY_LARAVEL_RENDER_QA_PASS\n";
