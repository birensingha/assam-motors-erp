<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
function out(string $name,mixed $v):void{echo "P58_".$name.'='.json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n";}
out('MODE','READONLY_SCHEMA_SOURCE_COUNTS_NO_PERSONAL_NAMES');
foreach(['customers','vehicles','job_cards','customer_payments','customer_ledger','invoices','erp_auth_users','job_card_change_audits','payment_allocations','invoice_payment_allocations','customer_payment_allocations','money_receipt_audits'] as $t)if(Schema::hasTable($t)){
 out('TABLE',[$t,Schema::getColumnListing($t),DB::table($t)->count()]);
}
foreach(['customer_payments','customer_ledger','invoices'] as $t){
 if(Schema::hasTable($t)){
   $fields=Schema::getColumnListing($t);
   foreach(['payment_status','entry_type','status','reference_type','reference_kind'] as $c)
     if(in_array($c,$fields,true))out('VALUES',[$t,$c,DB::table($t)->select($c)->selectRaw('COUNT(*) as cnt')->groupBy($c)->get()]);
 }
}
foreach(Route::getRoutes() as $r){
 if(preg_match('/erp\/(customers|invoices|payments|job-cards)/',$r->uri()))
    out('ROUTE',[$r->uri(),$r->getName(),$r->methods(),$r->getActionName(),$r->gatherMiddleware()]);
}
$range=[
 ['resources/views/layouts/erp.blade.php',61,125],
 ['routes/web.php',107,230],
 ['app/Http/Controllers/Web/AdminCustomerPageController.php',1,245],
 ['app/Http/Controllers/Web/AdminExpensePaymentPageController.php',1,130],
 ['app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',310,545],
 ['resources/views/invoices/show.blade.php',1,75],
 ['resources/views/customers/index.blade.php',1,85],
];
foreach($range as [$f,$from,$to]){
 $path="$root/$f";
 if(!is_file($path)){out('MISSING',$f);continue;}
 $lines=file($path);$rows=[];
 for($i=$from-1;$i<min($to,count($lines));$i++)$rows[]=($i+1).':'.rtrim($lines[$i]);
 out('SOURCE',[$f,$from,$to,$rows]);
}
echo "P58_READONLY_AUDIT_COMPLETE\n";