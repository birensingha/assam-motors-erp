<?php
/** FIX72 read-only JC34 original-vs-current audit and historical tax condition. */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$conn=DB::connection();$conn->statement('START TRANSACTION READ ONLY');
try {
 $j=DB::table('job_cards')->where('id',34)->first();$i=DB::table('invoices')->where('id',20)->first();
 if(!$j||!$i){echo "FIX72_NOT_FOUND\n";exit(3);}
 $job=['id'=>$j->id,'job_no'=>$j->job_no,'status'=>$j->status,'invoice_id'=>$j->invoice_id];
 $invoice=['id'=>$i->id,'invoice_no'=>$i->invoice_no,'job_card_id'=>$i->job_card_id,'status'=>$i->status];
 foreach(['parts_total','labour_total','subtotal','gst_total','round_off','pre_round_total','grand_total','paid_amount','paid_total','balance_due','balance_total'] as $col) {
  if(property_exists($j,$col))$job[$col]=$j->$col;
  if(property_exists($i,$col))$invoice[$col]=$i->$col;
 }
 echo 'FIX72_JC '.json_encode($job)."\n";
 echo 'FIX72_INVOICE '.json_encode($invoice)."\n";
 $tax=\App\Services\JobCardTaxTotals::forJob(34);
 echo 'FIX72_RECALC '.json_encode($tax)."\n";
 foreach(['customer_payments','customer_payment_allocations','invoice_credit_notes'] as $tab){
  echo 'FIX72_REFS '.json_encode(['table'=>$tab,'count'=>DB::table($tab)->where('invoice_id',20)->count()])."\n";
 }
 $ledgers=DB::table('customer_ledger')->where('invoice_id',20)->get();
 echo 'FIX72_LEDGER '.json_encode([
   'count'=>$ledgers->count(),
   'sum_debit'=>$ledgers->sum('debit'),
   'sum_credit'=>$ledgers->sum('credit'),
   'sole_debit_valid'=>$ledgers->count()===1
      &&strtoupper((string)$ledgers[0]->entry_type)==='DEBIT'
      &&(int)$ledgers[0]->customer_id===(int)$i->customer_id
 ])."\n";
 $cols=Schema::getColumnListing('invoices');$jcols=Schema::getColumnListing('job_cards');
 echo 'FIX72_INVOICE_COLUMN_CONTRACT '.json_encode(array_values(array_intersect(
 ['id','job_card_id','status','subtotal','parts_total','labour_total','gst_total','round_off','grand_total','invoice_date','customer_id','paid_amount','paid_total','balance_due','balance_total'], $cols)))."\n";
 echo "FIX72_PRECHECK_READONLY_COMPLETE\n";
}finally{$conn->rollBack();}
