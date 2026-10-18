<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file("$root/artisan")){echo "RECEIPT_AUDIT_INVALID_STAGING_ROOT\n";exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
function result(string $what,mixed $data):void{
  echo 'RECEIPT_AUDIT_'.$what.'='.json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
}
result('MODE','READ_ONLY_ROUTE_MENU_SOURCE');
foreach(\Illuminate\Support\Facades\Route::getRoutes() as $r){
 $uri=$r->uri();
 $name=(string)$r->getName();
 $action=$r->getActionName();
 if(preg_match('~receipt|customer.?payment|payment|collection|cash.?receive|advance|allocat~i',$uri.' '.$name.' '.$action)){
   result('ROUTE',[$uri,$name,$r->methods(),$action]);
 }
}
$paths=[
 'resources/views/layouts/erp.blade.php',
 'routes/web.php',
 'routes/api.php',
 'resources/views/payments/index.blade.php',
 'resources/views/customers/show.blade.php',
 'app/Http/Controllers/Web/AdminCustomerPageController.php',
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
];
foreach($paths as $path){
 $file="$root/$path";
 if(!is_file($file))continue;
 $lines=file($file);$matches=[];
 foreach($lines as $i=>$line)if(preg_match('/money.?receipt|payment.?receipt|customer.?payment|receive.?payment|payment.?voucher|erp.payments|cash.?receipt|receipt.?register|legacy.*receipt/i',$line)){
   $matches[]=['line'=>$i+1,'text'=>trim(substr($line,0,300))];
 }
 result('FILE',[$path,$matches]);
}
foreach(['customer_payments','customer_ledger','invoices'] as $t)
 if(\Illuminate\Support\Facades\Schema::hasTable($t))
   result('TABLE',[$t,\Illuminate\Support\Facades\Schema::getColumnListing($t)]);
// Legacy filename discovery only: never read personal customer records.
$legacyCandidates=[
 dirname($root).'/public_html/legacy/workshop',
 dirname($root).'/public_html/workshop',
 dirname($root).'/public_html/legacy',
 dirname($root).'/public_html',
 dirname($root).'/legacy/workshop',
];
foreach($legacyCandidates as $directory){
 if(!is_dir($directory))continue;
 $found=[];
 $walk=function(string $base,int $depth) use (&$walk,&$found):void{
   if($depth>2||count($found)>=35)return;
   $entries=@scandir($base);if(!$entries)return;
   foreach($entries as $entry){
     if($entry==='.'||$entry==='..'||str_starts_with($entry,'.'))continue;
     $full=$base.'/'.$entry;
     if(is_link($full))continue;
     if(is_file($full)&&preg_match('/(?:receipt|collection|payment|voucher)/i',$entry)){
        $found[]=substr($full,0,300);
     }elseif(is_dir($full)&&preg_match('/^(?:workshop|legacy|receipt|payment|finance|account|admin|pages)$/i',$entry)){
        $walk($full,$depth+1);
     }
   }
 };
 $walk($directory,0);
 result('LEGACY_FILENAME_MATCHES',[$directory,$found]);
}
echo "RECEIPT_AUDIT_COMPLETE_NO_WRITES\n";