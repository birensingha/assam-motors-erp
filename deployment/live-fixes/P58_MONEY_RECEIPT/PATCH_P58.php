<?php
declare(strict_types=1);
/**
 * P58 safely exposes native Money Receipt and three reference types.
 * Does not change existing finance records or OLD ERP on installation.
 * Source preflight, checksum backup, Laravel Blade compile, rollback.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"P58_USAGE check|apply STAGING_APP_ROOT\n");exit(2);
}
$dir=__DIR__;
$existing=[
 'routes/web.php',
 'resources/views/layouts/erp.blade.php',
 'resources/views/invoices/show.blade.php',
];
$payload=[
 'app/Http/Controllers/Web/AdminMoneyReceiptController.php'=>'payload/AdminMoneyReceiptController.php',
 'resources/views/money-receipts/index.blade.php'=>'payload/index.blade.php',
 'resources/views/money-receipts/create.blade.php'=>'payload/create.blade.php',
 'resources/views/money-receipts/show.blade.php'=>'payload/show.blade.php',
 'resources/views/money-receipts/print.blade.php'=>'payload/print.blade.php',
];
$before=[];$updated=[];
foreach($existing as $f){
 $path="$root/$f";
 if(!is_file($path)||is_link($path)){fwrite(STDERR,"P58_NO_GO_MISSING_EXISTING ".$f."\n");exit(3);}
 $before[$f]=(string)file_get_contents($path);
 $updated[$f]=$before[$f];
}
if(str_contains($before[$existing[0]],'P58_NATIVE_MONEY_RECEIPTS')){
 if(str_contains($before[$existing[1]],'P58_RECEIPT_NAV_LINK')
   && is_file($root.'/app/Http/Controllers/Web/AdminMoneyReceiptController.php')){
   echo "P58_ALREADY_INSTALLED\n";exit(0);
 }
 fwrite(STDERR,"P58_NO_GO_PARTIAL_INSTALL\n");exit(4);
}
foreach($payload as $target=>$file){
 if(!is_file("$dir/$file")||file_exists("$root/$target")){
   fwrite(STDERR,"P58_NO_GO_PAYLOAD_CONFLICT ".$target."\n");exit(4);
 }
}
function uniqueSwap(string &$text,string $from,string $to,string $tag):void{
 $count=substr_count($text,$from);
 if($count!==1)throw new RuntimeException('P58_NO_GO_ANCHOR_'.$tag.' count='.$count);
 $text=str_replace($from,$to,$text);
}
try{
 $routes=&$updated[$existing[0]];
 // Use existing ERP group with auth:web and EnsureErpAdmin.
 $pat="/^([ \\t]*)Route::get\\('\\/invoices\\/\\{invoice\\}\\/print',.*\\n/m";
 if(!preg_match($pat,$routes,$found) || preg_match_all($pat,$routes)!==1){
  throw new RuntimeException('P58_NO_GO_INVOICE_PRINT_ROUTE_ANCHOR');
 }
 $indent=$found[1];
 $newRoutes=<<<'PHP'
    // P58_NATIVE_MONEY_RECEIPTS: Customer Collections / Against Invoice / New Reference / On Account.
    Route::get('/money-receipts', [\App\Http\Controllers\Web\AdminMoneyReceiptController::class,'index'])->name('erp.money-receipts.index');
    Route::get('/money-receipts/create', [\App\Http\Controllers\Web\AdminMoneyReceiptController::class,'create'])->name('erp.money-receipts.create');
    Route::post('/money-receipts', [\App\Http\Controllers\Web\AdminMoneyReceiptController::class,'store'])->name('erp.money-receipts.store');
    Route::get('/money-receipts/{receipt}', [\App\Http\Controllers\Web\AdminMoneyReceiptController::class,'show'])->whereNumber('receipt')->name('erp.money-receipts.show');
    Route::get('/money-receipts/{receipt}/print', [\App\Http\Controllers\Web\AdminMoneyReceiptController::class,'print'])->whereNumber('receipt')->name('erp.money-receipts.print');

PHP;
 $routes=str_replace($found[0],$newRoutes.$found[0],$routes);
 unset($routes);
 $layout=&$updated[$existing[1]];
 $navOld=<<<'BLADE'
<a href="{{ route('erp.payments.index') }}" class="{{ request()->routeIs('erp.payments.*')?'active':'' }}"><span>💳</span> Payment</a>
BLADE;
 $navNew=$navOld."\n".
   '        {{-- P58_RECEIPT_NAV_LINK: Incoming customer payments are separate from supplier vouchers. --}}'."\n".
   '        <a href="{{ route(\'erp.money-receipts.index\') }}" class="{{ request()->routeIs(\'erp.money-receipts.*\')?\'active\':\'\' }}"><span>💵</span> Money Receipt</a>'."\n";
 uniqueSwap($layout,$navOld,$navNew,'ERP_SIDEBAR');
 unset($layout);
 $invoice=&$updated[$existing[2]];
 $invoiceAnchor=<<<'BLADE'
    @if($job->customer_id)
      <a class="btn" href="{{ route('erp.customers.show',$job->customer_id) }}">Customer Ledger</a>
    @endif
BLADE;
 $newInvoice=<<<'BLADE'
    {{-- P58_RECEIVE_AGAINST_INVOICE: One-click collect with customer and invoice preselected. --}}
    @if($job->customer_id && !in_array(strtolower((string)$inv->status),['paid','reversed'],true))
      <a class="btn primary" href="{{ route('erp.money-receipts.create',['customer'=>$job->customer_id,'invoice'=>$inv->id]) }}">💵 Receive Payment</a>
    @endif
BLADE;
 uniqueSwap($invoice,$invoiceAnchor,$invoiceAnchor."\n".$newInvoice,'INVOICE_RECEIVE_BUTTON');
 unset($invoice);

 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach(['am_customer_master','customer_payments','customer_payment_allocations','customer_ledger','invoices','job_cards'] as $table)
   if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('P58_REQUIRED_TABLE_MISSING_'.$table);
 foreach($existing as $f){
   $compiled=str_ends_with($f,'.blade.php')?app('blade.compiler')->compileString($updated[$f]):$updated[$f];
   token_get_all($compiled,TOKEN_PARSE);
   echo "P58_BASE_COMPILED=".basename($f)."\n";
 }
 foreach($payload as $target=>$src){
   $bytes=(string)file_get_contents("$dir/$src");
   $compiled=str_ends_with($target,'.blade.php')?app('blade.compiler')->compileString($bytes):$bytes;
   token_get_all($compiled,TOKEN_PARSE);
   echo "P58_PAYLOAD_COMPILED=".basename($target)."\n";
 }
 foreach(['erp.invoices.show','erp.invoices.print','erp.payments.index'] as $route)
   if(!\Illuminate\Support\Facades\Route::has($route))throw new RuntimeException('P58_MISSING_ROUTE_'.$route);
 echo "P58_ALL_FIVE_NEW_VIEWS_AND_CONTROLLER_VERIFIED\n";
} catch(Throwable $e){
 fwrite(STDERR,"P58_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);
}
if($mode==='check'){echo "P58_READONLY_PREFLIGHT_PASS_NO_BUSINESS_WRITES\n";exit(0);}
$backup=$root.'/storage/app/p58_money_receipt_source_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){fwrite(STDERR,"P58_BACKUP_DIR_ERROR\n");exit(6);}
foreach($existing as $f){
 $target=$backup.'/'.str_replace('/','__',$f);
 if(!copy("$root/$f",$target)||hash_file('sha256',$target)!==hash('sha256',$before[$f])){
  fwrite(STDERR,"P58_BACKUP_CHECKSUM_ERROR\n");exit(6);
 }
}
foreach($existing as $f)
 if(hash_file('sha256',"$root/$f")!==hash('sha256',$before[$f])){
    fwrite(STDERR,"P58_CONCURRENT_EDIT_BLOCKED\n");exit(7);
 }
try{
 foreach($existing as $f){
   $file="$root/$f";
   $temp=tempnam(dirname($file),'.p58_');
   if(!$temp||file_put_contents($temp,$updated[$f],LOCK_EX)!==strlen($updated[$f])||
      !chmod($temp,fileperms($file)&0777)||!rename($temp,$file))throw new RuntimeException('P58_SAVE_FAILED_'.basename($f));
 }
 foreach($payload as $target=>$src){
   $folder=dirname("$root/$target");if(!is_dir($folder)&&!mkdir($folder,0755,true))throw new RuntimeException('P58_NEW_FOLDER');
   if(!copy("$dir/$src","$root/$target"))throw new RuntimeException('P58_PAYLOAD_COPY_'.basename($target));
 }
}catch(Throwable $e){
 foreach($existing as $f)copy($backup.'/'.str_replace('/','__',$f),"$root/$f");
 foreach($payload as $target=>$src)@unlink("$root/$target");
 fwrite(STDERR,"P58_PATCH_ROLLBACK ".$e->getMessage()."\n");exit(8);
}
echo "P58_NATIVE_RECEIPTS_INSTALLED_BACKUP=$backup\n";
echo "P58_NO_CUSTOMER_FINANCIAL_RECORDS_MODIFIED_ON_INSTALL\n";
