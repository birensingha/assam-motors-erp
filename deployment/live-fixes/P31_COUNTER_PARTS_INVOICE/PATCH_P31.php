<?php
declare(strict_types=1);
/** P31_COUNTER_PARTS_INVOICE: guarded routes and direct-sale invoice views.
 * A separately reviewed additive migration is applied by the workflow after source backup.
 */
$mode=$argv[1]??'check';$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan'))exit(2);
$edit=[
 'routes/web.php',
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',
 'resources/views/purchases/index.blade.php',
];
$payload=[
 'app/Http/Controllers/Web/CounterPartsInvoiceController.php'=>__DIR__.'/payload/CounterPartsInvoiceController.php',
 'resources/views/counter-sales/index.blade.php'=>__DIR__.'/payload/counter-index.blade.php',
 'resources/views/counter-sales/create.blade.php'=>__DIR__.'/payload/counter-create.blade.php',
 'resources/views/invoices/direct-show.blade.php'=>__DIR__.'/payload/counter-show.blade.php',
 'resources/views/invoices/direct-print.blade.php'=>__DIR__.'/payload/counter-print.blade.php',
 'database/migrations/2026_10_14_170000_create_direct_invoice_parts.php'=>__DIR__.'/payload/2026_10_14_170000_create_direct_invoice_parts.php',
];
$original=[];
foreach($edit as $p){
 if(!is_file($root.'/'.$p)||is_link($root.'/'.$p)){fwrite(STDERR,"P31_NO_GO_MISSING ".$p."\n");exit(3);}
 $original[$p]=(string)file_get_contents($root.'/'.$p);
 if(str_contains($original[$p],'P31_COUNTER_PARTS_INVOICE')){fwrite(STDERR,"P31_NO_GO_INSTALLED_OR_PARTIAL\n");exit(4);}
}
foreach($payload as $p=>$f){
 if(!is_file($f)||is_link($f)||file_exists($root.'/'.$p)){fwrite(STDERR,"P31_NO_GO_DESTINATION_OR_PAYLOAD ".$p."\n");exit(4);}
}
function replace31($s,$needle,$new,$tag){
 $n=substr_count($s,$needle);
 if($n!==1)throw new RuntimeException('P31_NO_GO_ANCHOR_'.$tag.'_COUNT_'.$n);
 return str_replace($needle,$new,$s);
}
try{
 $routes=$original[$edit[0]];
 $regexp="/^([ \\t]*Route::post\\('\\/job-cards\\/\\{job\\}\\/invoice'[^\\n]+)$/m";
 if(preg_match_all($regexp,$routes,$matches)!==1 ||
   !str_contains($matches[1][0],"name('erp.job-cards.invoice.create')")){
   throw new RuntimeException('P31_NO_GO_INVOICE_ROUTE_ANCHOR');
 }
 $extra=<<<'PHP'

    // P31_COUNTER_PARTS_INVOICE: independent counter invoice, no Job Card.
    Route::get('/counter-sales', [\App\Http\Controllers\Web\CounterPartsInvoiceController::class,'index'])->name('erp.counter-sales.index');
    Route::get('/counter-sales/create', [\App\Http\Controllers\Web\CounterPartsInvoiceController::class,'create'])->name('erp.counter-sales.create');
    Route::post('/counter-sales', [\App\Http\Controllers\Web\CounterPartsInvoiceController::class,'store'])->name('erp.counter-sales.store');
PHP;
 $routes=replace31($routes,$matches[1][0],$matches[1][0].$extra,'counter_routes');
 $controller=$original[$edit[1]];
 $show="        return view('invoices.show',\$this->invoiceData(\$invoice));";
 $print="        return view('invoices.print',\$this->invoiceData(\$invoice));";
 $showDirect=<<<'PHP'
        // P31_COUNTER_PARTS_INVOICE: invoice without Job Card uses immutable sold-parts snapshots.
        if(Schema::hasTable('direct_invoice_requests') &&
           DB::table('direct_invoice_requests')->where('invoice_id',$invoice)->exists()){
            return app(\App\Http\Controllers\Web\CounterPartsInvoiceController::class)->show($request,$invoice);
        }
        return view('invoices.show',$this->invoiceData($invoice));
PHP;
 $printDirect=<<<'PHP'
        if(Schema::hasTable('direct_invoice_requests') &&
           DB::table('direct_invoice_requests')->where('invoice_id',$invoice)->exists()){
            return app(\App\Http\Controllers\Web\CounterPartsInvoiceController::class)->print($request,$invoice);
        }
        return view('invoices.print',$this->invoiceData($invoice));
PHP;
 $controller=replace31($controller,$show,$showDirect,'invoice_view');
 $controller=replace31($controller,$print,$printDirect,'invoice_print');
 $purchase=$original[$edit[2]];
 $quick=<<<'BLADE'

{{-- P31_COUNTER_PARTS_INVOICE: direct Parts Sale without any Job Card. --}}
<div style="display:flex;justify-content:flex-end;gap:9px;margin:10px 0 12px">
  <a href="{{ route('erp.counter-sales.create') }}" class="btn primary" style="background:#10835e;color:#fff;padding:11px 16px;border-radius:9px;font-weight:800">🧾 + Direct Parts Sale Invoice</a>
</div>
BLADE;
 $purchase=replace31($purchase,"@section('content')","@section('content')".$quick,'purchase_link');
 $out=[$edit[0]=>$routes,$edit[1]=>$controller,$edit[2]=>$purchase];
 chdir($root);require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $schema=\Illuminate\Support\Facades\Schema::class;
 if(!$schema::hasTable('invoices')||!$schema::hasTable('am_customer_master')||
    !$schema::hasTable('parts_master')||!$schema::hasTable('inventory_movements')||
    !$schema::hasTable('customer_ledger'))throw new RuntimeException('P31 required schema missing');
 if(!$schema::hasColumn('invoices','job_card_id'))throw new RuntimeException('P31 invoice job link missing');
 foreach($out as $p=>$content){
    if(str_ends_with($p,'.blade.php'))$content=app('blade.compiler')->compileString($content);
    token_get_all($content,TOKEN_PARSE);
 }
 foreach($payload as $p=>$src){
    $content=(string)file_get_contents($src);
    if(str_ends_with($p,'.blade.php'))$content=app('blade.compiler')->compileString($content);
    token_get_all($content,TOKEN_PARSE);
 }
 echo "P31_ALL_CONTROLLER_ROUTE_PHP_PARSE_PASS\n";
 echo "P31_COUNTER_ENTRY_SHOW_PRINT_BLADE_COMPILE_PASS\n";
 echo "P31_LEDGER_AND_INVENTORY_SCHEMA_PRESENT_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P31_NO_GO_PREFLIGHT ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P31_DRY_RUN_NO_FINANCIAL_STOCK_OR_SOURCE_CHANGES\n";exit(0);}
$bak=$root.'/storage/app/p31_counter_sale_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($bak,0700,true))exit(6);
foreach($edit as $p){
 $copy=$bak.'/'.str_replace('/','__',$p);
 if(!copy($root.'/'.$p,$copy)||hash_file('sha256',$copy)!==hash('sha256',$original[$p]))exit(6);
}
$done=[];
try {
 foreach($edit as $p){
   $dest=$root.'/'.$p;
   if(hash_file('sha256',$dest)!==hash('sha256',$original[$p]))throw new RuntimeException('Concurrent update '.$p);
   $tmp=tempnam(dirname($dest),'.p31_');
   if(!$tmp||file_put_contents($tmp,$out[$p],LOCK_EX)!==strlen($out[$p]))throw new RuntimeException('Temp write '.$p);
   chmod($tmp,fileperms($dest)&0777);
   if(!rename($tmp,$dest))throw new RuntimeException('Atomic replace '.$p);
   $done[]=$p;
 }
 foreach($payload as $p=>$source){
   $dest=$root.'/'.$p;
   if(!is_dir(dirname($dest))&&!mkdir(dirname($dest),0755,true))throw new RuntimeException('Dir '.$p);
   if(file_exists($dest)||!copy($source,$dest))throw new RuntimeException('Payload '.$p);
   $done[]=$p;
 }
 echo 'P31_APPLIED_BACKUP='.$bak."\n";
 echo "P31_SOURCE_ONLY_NO_CUSTOMER_STOCK_INVOICE_OR_LEDGER_ROWS_CREATED\n";
}catch(Throwable $e){
 foreach(array_reverse($done) as $p){
   if(isset($payload[$p]))@unlink($root.'/'.$p);
   else copy($bak.'/'.str_replace('/','__',$p),$root.'/'.$p);
 }
 fwrite(STDERR,'P31_INSTALL_ROLLBACK '.$e->getMessage()."\n");exit(7);
}
