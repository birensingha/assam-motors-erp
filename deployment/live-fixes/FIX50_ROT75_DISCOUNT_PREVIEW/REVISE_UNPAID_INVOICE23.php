<?php
/** FIX50: one-time audited pre-delivery correction, staging only. */
declare(strict_types=1);
$root=(string)($argv[2]??'');
$mode=(string)($argv[1]??'');
if(!in_array($mode,['--check','--apply'],true)||!is_file("$root/artisan"))exit(2);
if($mode==='--apply'&&getenv('ASSAM_FIX50_CONFIRM')!=='APPLY_INVOICE23_ROT75')exit(3);
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
   ->where('action','INVOICE_ROT_DISCOUNT_FIX50')->exists();
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
 // Reconciliation fixture from stored taxable baseline, not display rate.
 $discount=341.00;
 $newRot=round((float)$rot->total-$discount,2);
 $newPercent=round($discount/(float)$rot->total*100,2);
 if(!eqm($newRot,82.72)||!eqm($newPercent,80.48))throw new RuntimeException('ROT fixture failed.');
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
 foreach(['parts_total'=>1941.14,'labour_total'=>972.55,'gst_total'=>524.46,
          'pre_round_total'=>3438.15,'round_off'=>-0.15,'grand_total'=>3438] as $field=>$expected)
   if(!eqm($new[$field],$expected))throw new RuntimeException('New tax fixture mismatch: '.$field);
 echo 'FIX50_CHECK=PASS ROT=75 DISCOUNT=341.00 NEW_TOTAL=3438.00'.PHP_EOL;
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
   'action'=>'INVOICE_ROT_DISCOUNT_FIX50',
   'before_json'=>json_encode($before,JSON_UNESCAPED_SLASHES),
   'after_json'=>json_encode(['rot'=>['total'=>82.72,'discount_amount'=>341,
     'discount_percent'=>$newPercent],'new_totals'=>$new,'ledger_debit'=>3438],JSON_UNESCAPED_SLASHES),
   'reason'=>'User-authorized Rs341 additional taxable ROT1 discount on unsent unpaid staging invoice; original snapshot audited; unchanged invoice number.',
   'actor_id'=>null,'actor_name'=>'SYSTEM:USER_AUTHORIZED_FIX50',
   'created_at'=>now('Asia/Kolkata'),'updated_at'=>now('Asia/Kolkata')
 ]);
 $after=DB::table('invoices')->where('id',23)->first();
 $audit=DB::table('job_card_change_audits')->where('job_card_id',39)->where('action','INVOICE_ROT_DISCOUNT_FIX50')->count();
 if(!eqm($after->grand_total,3438)||!eqm($after->gst_total,524.46)||$audit!==1
    ||!eqm(DB::table('customer_ledger')->where('id',$led->id)->value('debit'),3438))
   throw new RuntimeException('Post-commit validation failed; rolling back transaction.');
 echo "FIX50_AUDITED_CORRECTION_APPLIED GRAND=3438.00 GST=524.46 DEBIT=3438.00\n";
},3);
