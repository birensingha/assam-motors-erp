<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
function p56(string $topic,mixed $v):void{echo "P56_".$topic."=".json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n";}
p56('MODE','READONLY_DDL_AND_SOURCE_NO_WRITES');
foreach(['invoices','invoice_lines','invoice_items','customer_ledger','customer_payments','customer_ledger_entries','money_receipts','workshop_payment_vouchers','job_card_change_audits','job_cards','rot_sessions','job_card_labour'] as $table){
 if(Schema::hasTable($table))p56('COLUMNS',[$table,Schema::getColumnListing($table)]);
}
$files=[
 'resources/views/workshop-controls/job-amounts.blade.php',
 'app/Http/Controllers/Web/AdminWorkshopControlPageController.php',
 'resources/views/job-cards/unwind.blade.php',
 'app/Http/Controllers/Web/AdminJobCardUnwindController.php',
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',
 'resources/views/job-cards/rot-alteration.blade.php',
];
foreach($files as $filename){
 $f="$root/$filename";
 if(!is_file($f)){p56('FILE_MISSING',$filename);continue;}
 $lines=file($f);$hits=[];
 foreach($lines as $i=>$line)if(preg_match('/function jobAmounts|function updateJobLine|function show|function cancel|function createInvoice|function reverse|function decideReversal|P16_|P55_|return view|amount editor|invoiced|Finance|review|Request /i',$line)){
   $from=max(0,$i-3);$until=min(count($lines),$i+7);
   $hits[]=['line'=>$i+1,'text'=>implode('',array_slice($lines,$from,$until-$from))];
   if(count($hits)>=28)break;
 }
 p56('SOURCE',[$filename,count($lines),$hits]);
}
foreach(Route::getRoutes() as $r){
 $s=$r->uri().' '.$r->getName().' '.$r->getActionName();
 if(preg_match('/invoice|receipt|credit.note|reversal|billing.edit/i',$s))
   p56('ROUTE',[$r->uri(),$r->getName(),$r->methods(),$r->getActionName()]);
}
$records=DB::table('job_cards')->whereIn('job_no',['JC-2026-0013','JC-2026-0021'])->get(['id','job_no','status','invoice_id','grand_total']);
p56('CARD_STATUSES',$records);
echo "P56_READONLY_AUDIT_COMPLETE\n";