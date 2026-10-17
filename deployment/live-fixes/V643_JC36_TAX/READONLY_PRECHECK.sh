#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo "== CONTROLLER GST CALCULATION, AUDIT, BILLING =="
python3 - <<'PY'
from pathlib import Path
import re
files={
 'app/Http/Controllers/Web/AdminWorkshopControlPageController.php':['quoteFromLine','recalculateJob','auditJob','activeInvoice'],
 'app/Http/Controllers/Web/AdminJobCardPageController.php':['recalculate','audit','updatePart'],
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php':['markWip','markWorkComplete','qcResult']
}
for p,names in files.items():
 f=Path(p)
 if not f.is_file():continue
 lines=f.read_text(errors='replace').splitlines()
 for name in names:
  hits=[i for i,x in enumerate(lines) if re.search(r'function\s+'+re.escape(name)+r'\s*\(',x)]
  for i in hits[:1]:
   end=next((k for k in range(i+1,len(lines)) if re.search(r'\b(?:public|private|protected)\s+function\s+\w+\(',lines[k])),len(lines))
   print('SOURCE',p,'METHOD',name)
   for k in range(i,min(i+92,end)):print(f'{k+1}: {lines[k][:250]}')
PY
echo "== READ-ONLY JOB CARD #36 TAX / RECEIPT / INVOICE AUDIT =="
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$job=DB::table('job_cards')->where('job_no','JC-20261016-00036')->first();
if(!$job || (int)$job->id!==36)throw new RuntimeException('Target job mismatch');
$jobColumns=['id','job_no','status','parts_total','labour_total','gst_total','grand_total','round_off','invoice_id','estimate_id','customer_id','updated_at'];
$j=[];foreach($jobColumns as $c)if(property_exists($job,$c))$j[$c]=$c==='customer_id'?'PRESENT':$job->$c;
echo 'JC36_HEADER='.json_encode($j,JSON_UNESCAPED_SLASHES).PHP_EOL;
foreach(['job_card_parts','job_card_labour','rot_sessions'] as $t){
 if(!Schema::hasTable($t))continue;
 $rows=DB::table($t)->where('job_card_id',36)->get();
 $selected=[];
 foreach($rows as $row){
   $out=[];foreach(['id','rot_code','description','standard_hours','labour_rate','discount_percent','discount_amount','billing_included','gst_percent','total','status','total_seconds'] as $key)if(property_exists($row,$key))$out[$key]=$row->$key;
   $selected[]=$out;
 }
 echo 'JC36_LINES_'.$t.'='.json_encode($selected,JSON_UNESCAPED_SLASHES).PHP_EOL;
}
$schema=DB::select('SHOW TABLES');
$tables=[];foreach($schema as $r)foreach((array)$r as $t)$tables[]=$t;
$matches=array_filter($tables,fn($t)=>preg_match('/invoice|receipt|payment|voucher|ledger|journal|audit|history|credit|tax|allocation|advance|settle/i',$t));
foreach($matches as $t){
  $columns=Schema::getColumnListing($t);
  $referenceFields=array_values(array_intersect(['job_card_id','job_id','invoice_id','jc_id','reference_id','against_id','voucher_no','against_reference','reference_no','job_no','created_at','customer_id'], $columns));
  $refCols=array_values(array_intersect(['job_card_id','job_id','jc_id','job_no'], $columns));
  $count=0;
  try{
    foreach($refCols as $c){
      $val=$c==='job_no'?'JC-20261016-00036':36;
      $count+=DB::table($t)->where($c,$val)->count();
    }
  }catch(Throwable $e){$count=-1;}
  echo 'LINK_TABLE='.json_encode(['table'=>$t,'candidate_columns'=>$referenceFields,'matched_by_job'=>$count]).PHP_EOL;
}
foreach(['invoices','invoice_items','money_receipts','receipts','payment_vouchers','customer_ledger','customer_ledger_entries'] as $t){
 if(!Schema::hasTable($t))continue;
 $cols=Schema::getColumnListing($t);
 $fields=array_values(array_intersect(['id','job_card_id','job_id','invoice_id','status','amount','total','grand_total','round_off','reference','against_reference','created_at'], $cols));
 echo 'TABLE_COLS='.json_encode(['table'=>$t,'fields'=>$fields]).PHP_EOL;
 if(in_array('job_card_id',$cols)){
  $rows=DB::table($t)->where('job_card_id',36)->get($fields);
  echo 'JC36_FINANCIAL_LINK_'.$t.'='.json_encode($rows).PHP_EOL;
 }
}
PHP
echo '== ACCOUNTING REFERENCE + AUDIT SCHEMA PRECHECK (NO WRITES) =='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$j=DB::table('job_cards')->where('id',36)->first();
foreach(['pre_round_total','round_off','gst_total','updated_at','status'] as $key)echo 'HEADER_'.$key.'='.($j->$key??'NULL').PHP_EOL;
$a=DB::select('SHOW COLUMNS FROM job_card_change_audits');
echo 'AUDIT_SCHEMA='.json_encode(array_map(fn($row)=>['field'=>$row->Field,'type'=>$row->Type,'null'=>$row->Null,'default'=>$row->Default],$a)).PHP_EOL;
$last=DB::table('job_card_change_audits')->where('job_card_id',36)->orderByDesc('id')->limit(7)->get(['id','entity_type','entity_id','action','reason','created_at']);
echo 'AUDIT_TRAIL='.json_encode($last,JSON_UNESCAPED_SLASHES).PHP_EOL;
foreach(['customer_payments','customer_ledger','workshop_payment_vouchers','am_payments','invoices','direct_invoice_requests'] as $t){
 if(!Schema::hasTable($t))continue;
 $cols=Schema::getColumnListing($t);$refs=array_values(array_intersect(['reference_no','against_reference','voucher_no','reference','job_no','narration','description','notes','invoice_no'], $cols));
 $rows=[];
 foreach($refs as $col){
   try{$n=DB::table($t)->where($col,'like','%JC-20261016-00036%')->count();if($n)$rows[$col]=$n;}
   catch(Throwable $e){$rows[$col]='ERROR';}
 }
 echo 'ACCOUNT_REFERENCE='.json_encode(['table'=>$t,'matches'=>$rows]).PHP_EOL;
}
PHP
echo 'V643_GST_READONLY_PRECHECK=COMPLETE'
