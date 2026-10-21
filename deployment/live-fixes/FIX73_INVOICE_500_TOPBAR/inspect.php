<?php
/** FIX73 READ ONLY, JC34 operational state, posted invoices, CN and linked ledger totals. */
declare(strict_types=1);
$root=(string)($argv[1]??'');if(!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$conn=DB::connection();$conn->statement('START TRANSACTION READ ONLY');
try {
 $j=DB::table('job_cards')->where('id',34)->first();
 if(!$j || $j->job_no!=='JC-20261014-00034')throw new RuntimeException('JC34 identity missing');
 $invs=DB::table('invoices')->where('job_card_id',34)->orderBy('id')->get();
 echo 'FIX73_JOB '.json_encode(['job_no'=>$j->job_no,'status'=>$j->status,'invoice_id'=>$j->invoice_id,'grand_total'=>$j->grand_total,'gst_total'=>$j->gst_total])."\n";
 echo 'FIX73_INVOICE_COUNT '.$invs->count()."\n";
 foreach($invs as $i){
  echo 'FIX73_INVOICE '.json_encode(['id'=>$i->id,'number'=>$i->invoice_no,'status'=>$i->status,
   'date'=>$i->invoice_date,'gross'=>$i->grand_total,'tax'=>$i->gst_total,
   'paid'=>$i->paid_total,'balance'=>$i->balance_due])."\n";
  $l=DB::table('customer_ledger')->where('invoice_id',$i->id)->get();
  echo 'FIX73_INVOICE_LEDGER '.json_encode(['invoice_id'=>$i->id,'count'=>$l->count(),
   'debits'=>round($l->sum('debit'),2),'credits'=>round($l->sum('credit'),2)])."\n";
 }
 $notes=DB::table('invoice_credit_notes')->where('job_card_id',34)->get();
 foreach($notes as $n){echo 'FIX73_CREDIT_NOTE '.json_encode(['number'=>$n->credit_note_no,'invoice_id'=>$n->invoice_id,
    'status'=>$n->status,'credit'=>$n->credit_amount,'gst'=>$n->gst_amount])."\n";}
 $tax=\App\Services\JobCardTaxTotals::forJob(34);
 echo 'FIX73_JC_CURRENT_LINES '.json_encode($tax)."\n";
 echo 'FIX73_READONLY_DB_COMPLETE'."\n";
}finally{$conn->rollBack();}
