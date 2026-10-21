<?php
/**
 * JC-20261014-00034 UAT read-only diagnostic.
 * No mutation, no secret, PII, customer identity, vendor or vehicle output.
 */
declare(strict_types=1);
$root=(string)($argv[1]??'');
if(!is_file($root.'/artisan')){fwrite(STDERR,"INVALID_LARAVEL_ROOT\n");exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::class;
$sc=\Illuminate\Support\Facades\Schema::class;
$conn=$db::connection();
$conn->statement('START TRANSACTION READ ONLY');
try{
  $job=$db::table('job_cards')->where('id',34)->first();
  if(!$job){echo "JC34_NOT_FOUND\n";exit(3);}
  $jobNo=(string)($job->job_no??'');
  if($jobNo!=='JC-20261014-00034'){
     echo "JC34_NUMBER_MISMATCH_EXPECTED_JC-20261014-00034_GOT_".preg_replace('/[^A-Za-z0-9-]/','',(string)$jobNo)."\n";
     exit(4);
  }
  $jobInfo=['id'=>34,'number'=>$jobNo,'status'=>$job->status??null,'invoice_id'=>$job->invoice_id??null];
  foreach(['grand_total','total_amount','taxable_amount','taxable_total','gst_amount','gst_total','round_off'] as $name){
     if(property_exists($job,$name))$jobInfo[$name]=$job->$name;
  }
  echo "JC34_JOB ".json_encode($jobInfo,JSON_UNESCAPED_SLASHES)."\n";
  $invs=$sc::hasTable('invoices')?$db::table('invoices')->where('job_card_id',34)->orderByDesc('id')->get():collect();
  echo "JC34_INVOICE_COUNT ".$invs->count()."\n";
  foreach($invs as $inv){
    $safe=['id'=>$inv->id,'invoice_no'=>$inv->invoice_no??null,'status'=>$inv->status??null];
    foreach(['grand_total','taxable_amount','taxable_total','gst_amount','gst_total','paid_amount','paid_total','balance_due','round_off'] as $name){
       if(property_exists($inv,$name))$safe[$name]=$inv->$name;
    }
    echo "JC34_INVOICE ".json_encode($safe,JSON_UNESCAPED_SLASHES)."\n";
  }
  $active=$invs->first(fn($i)=>strtolower(trim((string)($i->status??'')))!=='reversed');
  $invoiceId=$active?(int)$active->id:0;
  $paymentCount=0;$allocationCount=0;
  foreach(['customer_payments','customer_payment_allocations','customer_ledger','invoice_credit_notes','customer_advance_transfers'] as $table){
    if(!$sc::hasTable($table)){
      echo "JC34_TABLE_ABSENT ".$table."\n";continue;
    }
    $cols=$sc::getColumnListing($table);
    $filters=in_array('invoice_id',$cols,true)?['invoice_id',$invoiceId]:(
      in_array('job_card_id',$cols,true)?['job_card_id',34]:null);
    if(!$filters || (!$invoiceId && $filters[0]==='invoice_id')){
      echo "JC34_TABLE_NOT_DIRECTLY_LINKABLE ".$table."\n";continue;
    }
    $rows=$db::table($table)->where($filters[0],$filters[1])->get();
    echo "JC34_LINKED_COUNT ".json_encode(['table'=>$table,'count'=>$rows->count()])."\n";
    if($table==='customer_payments')$paymentCount=$rows->count();
    if($table==='customer_payment_allocations')$allocationCount=$rows->count();
    $sumFields=['amount','allocated_amount','credit_amount','debit','credit'];
    foreach($sumFields as $name){
      if(!in_array($name,$cols,true))continue;
      $total=$rows->sum(fn($r)=>(float)($r->$name??0));
      echo "JC34_LINKED_TOTAL ".json_encode(['table'=>$table,'field'=>$name,'sum'=>round($total,2)])."\n";
    }
    $sampleFields=['status','reversed_at','voided_at','allocation_type','type','entry_type'];
    foreach($sampleFields as $name){
      if(!in_array($name,$cols,true))continue;
      $vals=$rows->groupBy(fn($r)=>(string)($r->$name??'NULL'))->map(fn($r)=>$r->count())->all();
      if(count($vals)>15)$vals=['various'=>'more than 15 distinct'];
      echo "JC34_LINKED_STATUS ".json_encode(['table'=>$table,'column'=>$name,'values'=>$vals])."\n";
    }
  }
  foreach(['job_card_parts','job_card_labour','rot_sessions'] as $table){
    if(!$sc::hasTable($table))continue;
    $cols=$sc::getColumnListing($table);
    if(!in_array('job_card_id',$cols,true))continue;
    $rows=$db::table($table)->where('job_card_id',34)->get();
    $summary=['table'=>$table,'count'=>$rows->count()];
    foreach(['total','line_total','amount','taxable_amount','gst_amount','gst_total'] as $field){
      if(in_array($field,$cols,true))$summary['sum_'.$field]=round($rows->sum(fn($r)=>(float)($r->$field??0)),2);
    }
    echo "JC34_LINE_SUMMARY ".json_encode($summary)."\n";
  }
  $paid=$active && ((float)($active->paid_amount??0)>0.009||(float)($active->paid_total??0)>0.009);
  $issued=$active && strtolower((string)($active->status??''))==='issued';
  $isJCInvoice=$active && (int)($job->invoice_id??0)===$invoiceId;
  $hasCn=$sc::hasTable('invoice_credit_notes') && $invoiceId && $db::table('invoice_credit_notes')->where('invoice_id',$invoiceId)->exists();
  $unpaidEligible=($job->status??null)==='invoiced' && $issued && $isJCInvoice && !$paid && !$paymentCount && !$allocationCount && !$hasCn;
  echo "JC34_UNPAID_RETURN_GATE ".json_encode(['eligible'=>(bool)$unpaidEligible,'issued'=>(bool)$issued,'job_invoice_match'=>(bool)$isJCInvoice,'field_paid'=>(bool)$paid,'payment_rows'=>$paymentCount,'allocation_rows'=>$allocationCount,'credit_note_exists'=>(bool)$hasCn])."\n";
  echo "JC34_READ_ONLY_PREFLIGHT_COMPLETE_NO_FINANCIAL_WRITES\n";
} finally {
  $conn->rollBack();
}
