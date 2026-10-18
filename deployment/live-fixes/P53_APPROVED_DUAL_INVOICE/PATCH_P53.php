<?php
declare(strict_types=1);
/**
 * P53: Print-only dual documents from ONE saved invoice.
 * Keep invoice ID, accounting, GST amounts, ledger, Job Card state unchanged.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
$package=__DIR__;
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"P53_USAGE check|apply STAGING_ROOT\n");exit(2);
}
$print=$root.'/resources/views/invoices/print.blade.php';
$show=$root.'/resources/views/invoices/show.blade.php';
$template=$package.'/print.blade.php';
foreach([$print,$show,$template] as $file)if(!is_file($file)||is_link($file)){
 fwrite(STDERR,"P53_NO_GO_MISSING_FILE ".basename($file)."\n");exit(3);
}
$beforePrint=(string)file_get_contents($print);
$beforeShow=(string)file_get_contents($show);
$prepared=(string)file_get_contents($template);
$marker='P53_APPROVED_DUAL_INVOICE_PRINT';
if(str_contains($beforePrint,$marker)){
 if(str_contains($beforeShow,'P53_DUAL_INVOICE_BUTTONS')){
    echo "P53_ALREADY_INSTALLED\n";exit(0);
 }
 fwrite(STDERR,"P53_NO_GO_PARTIAL_INSTALL\n");exit(4);
}
if(!str_contains($beforePrint,'Workshop Tax Invoice')||
   !str_contains($beforeShow,'🖨 Print / Save PDF')){
 fwrite(STDERR,"P53_NO_GO_UNEXPECTED_INVOICE_BASELINE\n");exit(4);
}
if(substr_count($beforeShow,'🖨 Print / Save PDF')!==1)
 {fwrite(STDERR,"P53_NO_GO_BUTTON_ANCHOR_NOT_UNIQUE\n");exit(4);}
$originalButton=<<<'BLADE'
    <a class="btn primary" target="_blank" href="{{ route('erp.invoices.print',$inv->id) }}">🖨 Print / Save PDF</a>
BLADE;
$dual=<<<'BLADE'
    {{-- P53_DUAL_INVOICE_BUTTONS: ONE issued Invoice, TWO print formats, NO ledger reposting. --}}
    <a class="btn primary" target="_blank" rel="noopener" href="{{ route('erp.invoices.print',$inv->id) }}?format=tax">🧾 Print Tax / Service Invoice</a>
    <a class="btn" target="_blank" rel="noopener" href="{{ route('erp.invoices.print',$inv->id) }}?format=summary">📋 Print Customer Cost Summary (No GST breakup)</a>
BLADE;
if(substr_count($beforeShow,$originalButton)!==1){
 fwrite(STDERR,"P53_NO_GO_FULL_BUTTON_SOURCE_MISMATCH\n");exit(4);
}
$newShow=str_replace($originalButton,$dual,$beforeShow);
$newPrint="{{-- $marker: two approved print choices, same saved financial invoice --}}\n".$prepared;
$expected=[
 'Tax / Service Invoice',
 'Customer Cost Summary',
 'NOT A TAX INVOICE',
 'Parts / Materials',
 'Labour / ROT Charges',
 'OSL Jobs / Outside Services',
 'Bank Details',
 'Terms and Conditions',
 'Round Off',
 "request()->query('format')==='summary'",
];
foreach($expected as $needle){
 if(!str_contains($newPrint,$needle)){fwrite(STDERR,"P53_NO_GO_TEMPLATE_COMPONENT_MISSING ".$needle."\n");exit(4);}
}
try{
 chdir($root);
 require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 if(!\Illuminate\Support\Facades\Route::has('erp.invoices.print') ||
    !\Illuminate\Support\Facades\Route::has('erp.invoices.show'))
   throw new RuntimeException('Required authenticated Laravel invoice route absent');
 foreach(['new print'=>$newPrint,'new invoice show'=>$newShow] as $label=>$source){
   $compiled=app('blade.compiler')->compileString($source);
   token_get_all($compiled,TOKEN_PARSE);
   echo 'P53_COMPILE_OK_'.strtoupper(str_replace(' ','_',$label))."\n";
 }
 echo "P53_VIEW_COMPILE_TWO_BUTTONS_ONE_INVOICE_PASS\n";
} catch(Throwable $e){
 fwrite(STDERR,"P53_NO_GO_BLADE_VALIDATION ".get_class($e).' '.$e->getMessage()."\n");exit(5);
}
if($mode==='check'){
 echo "P53_READ_ONLY_PREFLIGHT_PASS_NO_DATA_WRITES\n";exit(0);
}
$backup=$root.'/storage/app/p53_invoice_print_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){
 fwrite(STDERR,"P53_NO_GO_BACKUP_DIR\n");exit(6);
}
if(!copy($print,$backup.'/print.blade.php')||!copy($show,$backup.'/show.blade.php')||
 hash_file('sha256',$backup.'/print.blade.php')!==hash('sha256',$beforePrint)||
 hash_file('sha256',$backup.'/show.blade.php')!==hash('sha256',$beforeShow)){
 fwrite(STDERR,"P53_NO_GO_BACKUP_CHECKSUM\n");exit(6);
}
if(hash_file('sha256',$print)!==hash('sha256',$beforePrint)||
   hash_file('sha256',$show)!==hash('sha256',$beforeShow)){
 fwrite(STDERR,"P53_NO_GO_CONCURRENT_CHANGE\n");exit(6);
}
function atomic(string $file,string $data):void {
 $tmp=tempnam(dirname($file),'.p53_');
 if(!$tmp||file_put_contents($tmp,$data,LOCK_EX)!==strlen($data)
   ||!chmod($tmp,fileperms($file)&0777)||!rename($tmp,$file)) {
   if($tmp)@unlink($tmp);
   throw new RuntimeException('File write failed');
 }
}
try{
 atomic($print,$newPrint);
 atomic($show,$newShow);
 if(hash_file('sha256',$print)!==hash('sha256',$newPrint)||
    hash_file('sha256',$show)!==hash('sha256',$newShow))
      throw new RuntimeException('Installed content hash mismatch');
}catch(Throwable $e){
 copy($backup.'/print.blade.php',$print);
 copy($backup.'/show.blade.php',$show);
 fwrite(STDERR,"P53_SOURCE_ROLLBACK ".$e->getMessage()."\n");exit(7);
}
echo "P53_INSTALLED_DUAL_INVOICE_PRINT_BACKUP=$backup\n";
echo "P53_NO_INVOICE_OR_LEDGER_DATABASE_CHANGES\n";
