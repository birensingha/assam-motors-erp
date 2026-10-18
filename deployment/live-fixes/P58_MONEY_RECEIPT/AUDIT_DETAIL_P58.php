<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;
function out(string $n,mixed $v):void{echo 'P58D_'.$n.'='.json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";}
foreach(Schema::getTables() as $t){$name=$t['name']??$t['table']??'';if(preg_match('/customer|receipt|payment|allocat|vehicle|ledger/i',$name)){out('TABLE_NAME',$name);if(Schema::hasTable($name))out('SCHEMA',[$name,Schema::getColumnListing($name)]);}}
foreach(['customer_payments','customer_payment_allocations','customer_ledger','invoices'] as $t){
 if(!Schema::hasTable($t))continue;
 if($t==='customer_payments'){
   $items=DB::table($t)->orderByDesc('id')->limit(6)->get(['id','receipt_no','reference_type','reference_kind','reference_id','invoice_id','customer_id','cash_amount','online_amount','amount','total_amount','allocated_amount','payment_status','receipt_date','created_at']);
   out('PAYMENTS_SHAPES',$items);
 }elseif($t==='customer_payment_allocations'){
   out('ALLOCATIONS',DB::table($t)->select('status')->selectRaw('count(*) as cnt')->groupBy('status')->get());
 }elseif($t==='invoices'){
   out('INVOICE_TOTAL_COMPARISON',DB::table('invoices')->select('status')->selectRaw('count(*) as cnt,sum(grand_total) as total,sum(paid_total) as paid,sum(balance_total) as balance')->groupBy('status')->get());
 }
}
foreach(['app/Http/Controllers/Web/AdminNativeModuleController.php','resources/views/dashboard/customers.blade.php','resources/views/layouts/erp.blade.php','app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php'] as $path){
 $fn="$root/$path";if(!is_file($fn))continue;
 $lines=file($fn);$hits=[];
 foreach($lines as $i=>$line)if(preg_match('/function customers|function showCustomer|function createCustomer|workshop_customers|customer_payments|customer_ledger|customer_id|receipt_no|money.receipt|payment.?alloc/i',$line)){
    $hits[]=[$i+1,substr(trim($line),0,300)];
    if(count($hits)>=70)break;
 }
 out('SOURCE_MATCH',[$path,$hits]);
}
echo "P58D_READONLY_COMPLETE\n";