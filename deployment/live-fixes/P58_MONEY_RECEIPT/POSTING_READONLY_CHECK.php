<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');if(!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
function p(string $n,mixed $v){echo "P58C_$n=".json_encode($v,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";}
p('MODE','READONLY_POSTING_SAFETY_NO_CREATE_OR_DB_WRITES');
$items=DB::table('invoices')->whereIn('status',['issued','part_paid'])->whereNotNull('customer_id')->get(['id','invoice_no','customer_id','status','grand_total','balance_due','balance_total']);
$counts=['eligible'=>0,'mismatches'=>0,'zeroDue'=>0,'customerMissing'=>0];
foreach($items as $inv){
 $allocated=(float)DB::table('customer_payment_allocations')->where('invoice_id',$inv->id)->where('status','posted')->whereNull('reversed_at')->sum('amount');
 $due=round((float)$inv->grand_total-$allocated,2);
 $stored=(float)$inv->balance_due;
 $stored2=(float)$inv->balance_total;
 $exists=DB::table('am_customer_master')->where('id',$inv->customer_id)->exists();
 if(!$exists){$counts['customerMissing']++;continue;}
 if($due<=0){$counts['zeroDue']++;continue;}
 if(abs($due-$stored)>.02||abs($due-$stored2)>.02){$counts['mismatches']++;continue;}
 $counts['eligible']++;
}
p('CANDIDATE_COUNTS',$counts);
foreach(['customer_payments','customer_ledger','customer_payment_allocations','invoices','am_customer_master'] as $t){
 $eng=DB::selectOne("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME=?",[$t]);
 p('ENGINE',[$t,$eng->ENGINE??'UNKNOWN']);
}
if(method_exists(Schema::getFacadeRoot(),'getIndexes')){
 foreach(['customer_payments','customer_payment_allocations'] as $table){
  $indexes=Schema::getIndexes($table);
  p('INDEXES',[$table,array_map(fn($r)=>['name'=>$r['name']??'','columns'=>$r['columns']??[],'unique'=>$r['unique']??false],$indexes)]);
 }
}
$receipt=DB::table('customer_payments')->selectRaw('COUNT(*) as cnt, COUNT(DISTINCT receipt_no) as receipt_codes, COUNT(DISTINCT request_key) as keys')->first();
p('RECEIPT_UNIQUENESS_COUNTS',$receipt);
echo "P58C_CHECK_COMPLETE_READONLY\n";