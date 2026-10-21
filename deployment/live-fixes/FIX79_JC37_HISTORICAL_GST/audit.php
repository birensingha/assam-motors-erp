<?php
/** FIX79 read-only JC37 GST-basis review. Never update any rows. */
declare(strict_types=1);
$root=$argv[1]??'';
if(!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$c=DB::connection();$c->statement('START TRANSACTION READ ONLY');
try {
 $j=DB::table('job_cards')->where('job_no','JC-20261018-00037')->first();
 if(!$j){echo "FIX79_JC37_NOT_FOUND\n";exit(3);}
 $jid=(int)$j->id;
 $fields=['id','job_no','status','invoice_id','parts_total','labour_total','subtotal','gst_total','pre_round_total','round_off','grand_total','created_at','updated_at'];
 $safe=[];
 foreach($fields as $field){if(property_exists($j,$field))$safe[$field]=$j->$field;}
 echo 'FIX79_JOB '.json_encode($safe)."\n";
 foreach(['job_card_parts','job_card_labour'] as $table) {
   $columns=Schema::getColumnListing($table);
   $fields=array_values(array_intersect(['id','job_card_id','rot_code','description','item_description','labour_description','quantity','qty','rate','unit_rate','discount_percent','gst_percent','total','billing_included','start_time','end_time','created_at','updated_at'],$columns));
   $list=DB::table($table)->where('job_card_id',$jid)->get($fields);
   echo 'FIX79_LINES '.json_encode(['table'=>$table,'count'=>$list->count(),'rows'=>$list->map(fn($r)=>(array)$r)->all()])."\n";
 }
 $tax=\App\Services\JobCardTaxTotals::forJob($jid);
 echo 'FIX79_CALCULATED_TAX '.json_encode($tax)."\n";
 $invoiceCols=Schema::getColumnListing('invoices');
 $invoices=DB::table('invoices')->where('job_card_id',$jid)->orderBy('id')->get();
 foreach($invoices as $inv) {
   $out=[];
   foreach(['id','invoice_no','status','subtotal','parts_total','labour_total','gst_total','round_off','grand_total','created_at'] as $k)
     if(property_exists($inv,$k))$out[$k]=$inv->$k;
   echo 'FIX79_INVOICE '.json_encode($out)."\n";
 }
 if(Schema::hasTable('estimates')) {
   $cols=Schema::getColumnListing('estimates');
   if(in_array('job_card_id',$cols,true)) {
    $est=DB::table('estimates')->where('job_card_id',$jid)->orderBy('id')->get();
    foreach($est as $e){
      $x=[];foreach(['id','estimate_no','status','taxable_total','gst_total','cgst_total','sgst_total','grand_total','approved_at'] as $k)if(property_exists($e,$k))$x[$k]=$e->$k;
      echo 'FIX79_ESTIMATE '.json_encode($x)."\n";
    }
   }
 }
 echo 'FIX79_JC37_READ_ONLY_SUCCESS'."\n";
}finally{$c->rollBack();}
