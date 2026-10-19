<?php
/** FIX51: single-ROT invoice correction; Rs341 inclusive discount, ending at Rs3500, staging only. */
declare(strict_types=1);
$root=(string)($argv[2]??'');
$mode=(string)($argv[1]??'');
if(!in_array($mode,['--check','--apply'],true)||!is_file("$root/artisan"))exit(2);
if($mode==='--apply'&&getenv('ASSAM_FIX51_CONFIRM')!=='APPLY_INVOICE23_ROT75_TO_3500')exit(3);
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
function eqm($v,$e):bool{return abs((float)$v-$e)<0.011;}
DB::transaction(function()use($mode){
 $job=DB::table('job_cards')->where('id',39)->lockForUpdate()->first();
 $inv=DB::table('invoices')->where('id',23)->lockForUpdate()->first();
 $rot=DB::table('job_card_labour')->where('id',75)->lockForUpdate()->first();
 $entries=DB::table('customer_ledger')->where('invoice_id',23)->lockForUpdate()->get();
 $oldAudit=DB::table('job_card_change_audits')->where('job_card_id',39)
   ->where('action','INVOICE_ROT_DISCOUNT_TO_3500_FIX51')->exists();
 if(!$job||!$inv||!$rot||$entries->count()!==1||$oldAudit)throw new RuntimeException('Changed target or previously adjusted.');
 $led=$entries->first();
 if($job->job_no!=='JC-20261019-00039'||$inv->invoice_no!=='INV-2026-00023'
    ||(int)$job->invoice_id!==23||(int)$inv->job_card_id!==39
    ||(int)$rot->job_card_id!==39||(string)$rot->rot_code!=='ROT-FUE-006'
    ||strtolower((string)$job->status)!=='invoiced'
    ||strtolower((string)$inv->status)!=='issued'
    ||!eqm($inv->paid_amount,0)||!eqm($inv->paid_total,0)
    ||!eqm($inv->grand_total,3841)||!eqm($job->grand_total,3841)
    ||!eqm($led->debit,3841)||!eqm($led->amount,3841)||!eqm($led->credit,0)
    ||!eqm($rot->total,423.72)||!eqm($rot->discount_amount,0)
    ||!eqm($rot->discount_percent,0)||!eqm($rot->gst_percent,18)
    ||(int)$rot->billing_included!==1)throw new RuntimeException('Financial safety precondition failed.');
 if(!in_array(strtoupper((string)$led->entry_type),['DEBIT'],true)
     || strtoupper((string)$led->reference_type)!=='INVOICE'
     || (int)$led->customer_id !== (int)$inv->customer_id
     || !eqm($inv->balance_due,3841)||!eqm($inv->balance_total,3841))
   throw new RuntimeException('Ledger and balance safety guard failed.');
 if(DB::table('job_card_change_audits')->where('job_card_id',39)
     ->where('action','INVOICE_ROT_DISCOUNT_FIX50')->exists())
   throw new RuntimeException('Existing older ROT correction audit detected.');
 foreach(DB::select('SHOW TABLES') as $row){
   $values=array_values((array)$row);$table=(string)($values[0]??'');
   if(!preg_match('/^[A-Za-z0-9_]+$/D',$table))continue;
   if(!Schema::hasColumn($table,'invoice_id'))continue;
   $count=DB::table($table)->where('invoice_id',23)->count();
   if($count && !in_array($table,['job_cards','customer_ledger'],true))
     throw new RuntimeException('External invoice reference exists in '.$table);
 }
 $current=\App\Services\JobCardTaxTotals::forJob(39);
 if(!eqm($current['grand_total'],3841)||!eqm($current['gst_total'],585.84))
   throw new RuntimeException('Current tax basis changed.');
 // Rs341 discount at invoice level (inclusive of 18% GST) applied to ROT75 ONLY.
 // Taxable reduction=Rs288.98; GST reduction=Rs52.02; total reduction=Rs341.00.
 $discount=288.98;
 $newRot=round((float)$rot->total-$discount,2);
 $newPercent=round($discount/(float)$rot->total*100,2);
 if(!eqm($newRot,134.74)||!eqm($newPercent,68.20))throw new RuntimeException('ROT fixture failed.');
 $labour=DB::table('job_card_labour')->where('job_card_id',39)
   ->when(Schema::hasColumn('job_card_labour','billing_included'),
     fn($q)=>$q->where('billing_included',1))->orderBy('id')->get();
 $parts=DB::table('job_card_parts')->where('job_card_id',39)->get();
 $revisedLabour=$labour->map(function($item)use($newRot,$newPercent,$discount){
   if((int)$item->id===75){
     $item->total=$newRot;
     $item->discount_amount=$discount;
     $item->discount_percent=$newPercent;
   }
   return $item;
 });
 $new=\App\Services\JobCardTaxTotals::fromLines($parts,$revisedLabour);
 foreach(['parts_total'=>1941.14,'labour_total'=>1024.57,'gst_total'=>533.82,
          'pre_round_total'=>3499.53,'round_off'=>0.47,'grand_total'=>3500] as $field=>$expected)
   if(!eqm($new[$field],$expected))throw new RuntimeException('New tax fixture mismatch: '.$field);
 echo 'FIX51_CHECK=PASS ROT75 INCLUSIVE_DISCOUNT=341.00 TAXABLE_DISCOUNT=288.98 NEW_TOTAL=3500.00'.PHP_EOL;
 if($mode==='--check'){echo "READ_ONLY_CHECK_PASS\n";return;}
 $before=['rot'=>['total'=>$rot->total,'discount_amount'=>$rot->discount_amount,
   'discount_percent'=>$rot->discount_percent],
   'job'=>['grand_total'=>$job->grand_total,'gst_total'=>$job->gst_total],
   'invoice'=>['grand_total'=>$inv->grand_total,'gst_total'=>$inv->gst_total,
     'balance_due'=>$inv->balance_due],
   'ledger'=>['debit'=>$led->debit,'amount'=>$led->amount]];
 DB::table('job_card_labour')->where('id',75)->update([
   'total'=>$newRot,'discount_amount'=>$discount,'discount_percent'=>$newPercent
 ]);
 DB::table('job_cards')->where('id',39)->update([
   'labour_total'=>$new['labour_total'],'gst_total'=>$new['gst_total'],
   'pre_round_total'=>$new['pre_round_total'],'round_off'=>$new['round_off'],
   'grand_total'=>$new['grand_total']
 ]);
 DB::table('invoices')->where('id',23)->update([
   'labour_total'=>$new['labour_total'],'subtotal'=>$new['subtotal'],
   'tax_total'=>$new['gst_total'],'gst_total'=>$new['gst_total'],
   'pre_round_total'=>$new['pre_round_total'],'round_off'=>$new['round_off'],
   'grand_total'=>$new['grand_total'],'balance_due'=>$new['grand_total'],
   'balance_total'=>$new['grand_total']
 ]);
 DB::table('customer_ledger')->where('id',$led->id)->update([
   'debit'=>$new['grand_total'],'amount'=>$new['grand_total']
 ]);
 DB::table('job_card_change_audits')->insert([
   'job_card_id'=>39,'entity_type'=>'JOB_CARD_LABOUR','entity_id'=>75,
   'action'=>'INVOICE_ROT_DISCOUNT_TO_3500_FIX51',
   'before_json'=>json_encode($before,JSON_UNESCAPED_SLASHES),
   'after_json'=>json_encode(['rot'=>['total'=>134.74,'discount_amount'=>288.98,
     'discount_percent'=>$newPercent],'new_totals'=>$new,'ledger_debit'=>3500],JSON_UNESCAPED_SLASHES),
   'reason'=>'User-approved Rs341 discount inclusive of GST, allocated only to ROT-FUE-006 as Rs288.98 taxable reduction plus Rs52.02 tax benefit, invoice total Rs3500; existing invoice number retained.',
   'actor_id'=>null,'actor_name'=>'SYSTEM:USER_AUTHORIZED_FIX51',
   'created_at'=>now('Asia/Kolkata'),'updated_at'=>now('Asia/Kolkata')
 ]);
 $after=DB::table('invoices')->where('id',23)->first();
 $audit=DB::table('job_card_change_audits')->where('job_card_id',39)->where('action','INVOICE_ROT_DISCOUNT_TO_3500_FIX51')->count();
 if(!eqm($after->grand_total,3500)||!eqm($after->gst_total,533.82)||$audit!==1
    ||!eqm(DB::table('customer_ledger')->where('id',$led->id)->value('debit'),3500))
   throw new RuntimeException('Post-commit validation failed; rolling back transaction.');
 echo "FIX51_AUDITED_CORRECTION_APPLIED GRAND=3500.00 GST=533.82 DEBIT=3500.00\n";
},3);
