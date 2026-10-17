#!/usr/bin/env bash
# Single JC exclusive GST correction explicitly approved by user, no other financial mutations.
set -euo pipefail
ROOT="$1"
test "$ROOT" = "/home/u956497103/domains/assammotors.com/assam-erp-staging"
test -f "$ROOT/artisan" && test -f "$ROOT/vendor/autoload.php"
cd "$ROOT"
php <<'PHP'
<?php
declare(strict_types=1);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$jc='JC-20261016-00036';
$reason='User-approved 17 Oct 2026: taxable JC Labour Rs 847.46 is EXCLUSIVE of 18% GST. Header GST 0 -> 152.54; pre-round 847.46 -> 1000; round-off -0.46 -> 0; grand total 847 -> 1000. No line, ROT, stock or unrelated ledger edits.';
$equals=fn($v,$n)=>abs((float)$v-(float)$n)<0.005;
if(!Schema::hasTable('job_card_change_audits'))throw new RuntimeException('Audit table missing');
$res=DB::transaction(function()use($jc,$reason,$equals){
 $card=DB::table('job_cards')->where('id',36)->lockForUpdate()->first();
 if(!$card || (string)$card->job_no!==$jc)throw new RuntimeException('JC identity mismatch');
 $audited=DB::table('job_card_change_audits')->where('job_card_id',36)->where('action','V643_GST_EXCLUSIVE_HEADER')->exists();
 if($audited){
  if(!$equals($card->gst_total,152.54)||!$equals($card->grand_total,1000)||!$equals($card->round_off,0))
   throw new RuntimeException('Previously audited but totals inconsistent');
  return ['already_applied'=>true,'grand_total'=>1000.00,'gst_total'=>152.54];
 }
 if(!in_array(strtolower(trim((string)$card->status)),['wip','in-progress','in progress','work in progress'],true) || !empty($card->invoice_id))
  throw new RuntimeException('Job is not unlocked WIP');
 foreach(['parts_total'=>0,'labour_total'=>847.46,'gst_total'=>0,'pre_round_total'=>847.46,'round_off'=>-0.46,'grand_total'=>847.00] as $field=>$amount)
  if(!property_exists($card,$field)||!$equals($card->$field,$amount))throw new RuntimeException('Header changed '.$field);
 if(DB::table('job_card_parts')->where('job_card_id',36)->exists())throw new RuntimeException('Parts added; re-review needed');
 $lines=DB::table('job_card_labour')->where('job_card_id',36)->orderBy('id')->lockForUpdate()->get();
 if(count($lines)!==2||(int)$lines[0]->id!==72||(int)$lines[1]->id!==73)throw new RuntimeException('Labour rows changed');
 $taxable=0;$gst=0;$snapshot=[];
 foreach($lines as $l){
  if(!$equals($l->total,423.73)||!$equals($l->gst_percent,18)||!$equals($l->discount_percent??0,0)||(int)($l->billing_included??1)!==1)
   throw new RuntimeException('Labour tax/discount basis changed');
  $taxable+=(float)$l->total;
  $gst+=round((float)$l->total*0.18,2);
  $snapshot[]=['id'=>$l->id,'rot_code'=>$l->rot_code??'','taxable'=>$l->total,'gst_percent'=>$l->gst_percent];
 }
 $taxable=round($taxable,2);$gst=round($gst,2);$preRound=round($taxable+$gst,2);
 $grand=round($preRound,0,PHP_ROUND_HALF_UP);$roundOff=round($grand-$preRound,2);
 if(!$equals($taxable,847.46)||!$equals($gst,152.54)||!$equals($preRound,1000)||!$equals($grand,1000)||!$equals($roundOff,0))
  throw new RuntimeException('Unexpected GST calculation');
 foreach(['invoices','customer_payments','customer_ledger','workshop_payment_vouchers','am_payments','direct_invoice_requests'] as $t){
  if(!Schema::hasTable($t))continue;
  $cols=Schema::getColumnListing($t);
  foreach(['job_card_id','job_id','jc_id'] as $col)
   if(in_array($col,$cols,true)&&DB::table($t)->where($col,36)->exists())throw new RuntimeException('Financial link '.$t.'.'.$col);
  foreach(['reference_no','against_reference','reference','job_no','voucher_no','narration','description','notes','invoice_no'] as $col)
   if(in_array($col,$cols,true)&&DB::table($t)->where($col,'like','%'.$jc.'%')->exists())throw new RuntimeException('Reference link '.$t.'.'.$col);
 }
 $old=[];foreach(['id','job_no','status','parts_total','labour_total','gst_total','pre_round_total','round_off','grand_total','invoice_id','updated_at'] as $f)
  $old[$f]=$card->$f??null;
 $new=$old;$new['gst_total']='152.54';$new['pre_round_total']='1000.00';$new['round_off']='0.00';$new['grand_total']='1000.00';
 // File backup remains outside public_html. DB transaction commits only after backup and audit.
 $backup=dirname(base_path()).'/BACKUP_V643_EXCLUSIVE_GST';
 if(!is_dir($backup)&&!mkdir($backup,0700,true)&&!is_dir($backup))throw new RuntimeException('Backup directory unavailable');
 chmod($backup,0700);
 $file=$backup.'/'.$jc.'-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(3)).'.json';
 $payload=json_encode(['reason'=>$reason,'before_header'=>$old,'intended_header'=>$new,'unchanged_labour'=>$snapshot],
                     JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 if(file_put_contents($file,$payload."\n",LOCK_EX)===false || !chmod($file,0600))
  throw new RuntimeException('Private backup failed');
 $rows=DB::table('job_cards')->where('id',36)->update(['gst_total'=>$gst,'pre_round_total'=>$preRound,
                 'round_off'=>$roundOff,'grand_total'=>$grand,'updated_at'=>now()]);
 if($rows!==1)throw new RuntimeException('JC header update failed');
 DB::table('job_card_change_audits')->insert([
  'job_card_id'=>36,'entity_type'=>'JOB_CARD','entity_id'=>36,
  'action'=>'V643_GST_EXCLUSIVE_HEADER','before_json'=>json_encode($old,JSON_THROW_ON_ERROR),
  'after_json'=>json_encode($new,JSON_THROW_ON_ERROR),'reason'=>$reason,
  'actor_id'=>null,'actor_name'=>'System-assisted, user-confirmed correction',
  'created_at'=>now(),'updated_at'=>now()
 ]);
 $check=DB::table('job_cards')->where('id',36)->first();
 if(!$equals($check->gst_total,$gst)||!$equals($check->grand_total,$grand)||!$equals($check->round_off,0))
  throw new RuntimeException('Verification in transaction failed');
 return ['already_applied'=>false,'taxable'=>$taxable,'gst_total'=>$gst,'grand_total'=>$grand,'round_off'=>$roundOff,
         'backup_file'=>basename($file)];
},1);
echo 'V643_JC36_GST_POSTING='.json_encode($res,JSON_UNESCAPED_SLASHES).PHP_EOL;
echo "V643_JC36_EXCLUSIVE_GST=SUCCESS\n";
PHP
