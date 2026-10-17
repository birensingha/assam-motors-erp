#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$jc=DB::table('job_cards')->where('job_no','JC-20261016-00036')->firstOrFail();
$lines=DB::table('job_card_labour')->where('job_card_id',$jc->id)->orderBy('id')->get();
$parts=DB::table('job_card_parts')->where('job_card_id',$jc->id)->count();
$audits=DB::table('job_card_change_audits')->where('job_card_id',$jc->id)->where('action','V643_GST_EXCLUSIVE_HEADER')->count();
$sum=round($lines->sum('total'),2);
$gst=round($lines->sum(fn($l)=>round((float)$l->total*(float)$l->gst_percent/100,2)),2);
$invoices=DB::table('invoices')->where('job_card_id',$jc->id)->count();
$wip=in_array(strtolower(trim((string)$jc->status)),['wip','in-progress','in progress','work in progress'],true);
$ok=$wip && !$jc->invoice_id && !$invoices && !$parts &&
      count($lines)===2 && (int)$lines[0]->id===72 && (int)$lines[1]->id===73 &&
      abs($sum-847.46)<.005 && abs($gst-152.54)<.005 &&
      abs((float)$jc->gst_total-$gst)<.005 &&
      abs((float)$jc->pre_round_total-1000)<.005 &&
      abs((float)$jc->grand_total-1000)<.005 &&
      abs((float)$jc->round_off)<.005 && $audits===1;
echo 'V643_FRESH_HEADER='.json_encode(['status'=>$jc->status,'taxable'=>$sum,'gst'=>$jc->gst_total,
     'pre_round_total'=>$jc->pre_round_total,'grand_total'=>$jc->grand_total,
     'round_off'=>$jc->round_off,'invoice_count'=>$invoices,'audit_count'=>$audits,
     'parts_count'=>$parts,'labour_count'=>count($lines)]).PHP_EOL;
if(!$ok)throw new RuntimeException('Post-reconciliation sums/financial linkage/audit mismatch');
$taxBasisUnverified=(float)$jc->gst_total<0.005 && $lines->contains(fn($l)=>(float)$l->total>0 && (float)$l->gst_percent>0);
$editorUnlocked=$wip && !$invoices && !$jc->invoice_id && !$taxBasisUnverified;
if(!$editorUnlocked)throw new RuntimeException('Amount Editor still blocked for this WIP, non-invoiced JC');
echo "V643_AMOUNT_EDITOR_PREDICATE=ENABLED\n";
echo "V643_JC36_POSTCHECK=PASS\n";
PHP
