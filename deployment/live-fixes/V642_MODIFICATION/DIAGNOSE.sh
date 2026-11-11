#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo '=== SEARCH EXACT MODIFICATION DENIAL ==='
grep -RInE --include='*.php' --include='*.blade.php' 'Modification [Nn]ot [Aa]llowed|Modification Allowed|MODIFICATION NOT ALLOWED|Modification not allowed|Modification not permitted|modification_allowed' app resources/views routes | head -70 || true
echo '=== EDIT AMOUNTS VIEW ==='
python3 - <<'PY'
from pathlib import Path
p=Path('resources/views/workshop-controls/job-amounts.blade.php')
a=p.read_text(errors='replace').splitlines()
for i,line in enumerate(a):
 if i>275:break
 print(f'{i+1}: {line[:225]}')
print('=== AMOUNTS CONTROLLER ===')
p=Path('app/Http/Controllers/Web/AdminWorkshopControlPageController.php')
a=p.read_text(errors='replace').splitlines()
for i,line in enumerate(a):
 if 'public function jobAmounts(' in line or 'public function updateJobLine(' in line or 'private function' in line and 'amount' in line.lower():
  print('METHOD',i+1,line)
  for k in range(i,min(i+160,len(a))):
   val=a[k]
   if any(word in val.lower() for word in ['password','bearer','secret','token']):continue
   print(f'{k+1}: {val[:250]}')
print('=== JOB CARD STATUS DISPLAY (VIEW/JS) ===')
for p in Path('resources/views').rglob('*.blade.php'):
 if any(v in p.name for v in ['reversal','lifecycle']):
  a=p.read_text(errors='replace').splitlines()
  for i,line in enumerate(a):
   if 'modification' in line.lower() or 'invoice locked' in line.lower():
    print(str(p),i+1,line[:200])
PY
echo '=== JC AND AMOUNTS SQL STATUS COUNTS (READ ONLY) ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
echo 'JC_STATUS_COUNTS='.json_encode(DB::table('job_cards')->select('status',DB::raw('COUNT(*) n'))->groupBy('status')->get()).PHP_EOL;
foreach(['JC-20261016-00036','JC-20261016-00035'] as $no) {
 $card=DB::table('job_cards')->where('job_no',$no)->first();
 if (!$card) continue;
 $i=DB::table('invoices')->where('job_card_id',$card->id)->get(['id','status']);
 echo 'JC_EXAMPLE='.json_encode(['job_no'=>$no,'id'=>$card->id,'status'=>$card->status,'invoice_id'=>$card->invoice_id??null,'invoices'=>$i]).PHP_EOL;
}
PHP
echo '=== INTEGRITY CHECK FOR RECENT JC BILLS (NO WRITES) ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
foreach([36,35] as $id){
 $card=DB::table('job_cards')->where('id',$id)->first();
 if(!$card)continue;
 $parts=DB::table('job_card_parts')->where('job_card_id',$id)->get();
 $labour=DB::table('job_card_labour')->where('job_card_id',$id)->get();
 $included=$labour->filter(fn($x)=>(int)($x->billing_included??1)===1);
 $rows=$parts->concat($included);
 $unverified=(float)($card->gst_total??0)<0.005 && $rows->contains(fn($x)=>(float)($x->total??0)>0 && (float)($x->gst_percent??0)>0);
 $calcTax=round($rows->sum(fn($x)=>round((float)($x->total??0)*(float)($x->gst_percent??0)/100,2)),2);
 $p=round($parts->sum('total'),2);$l=round($included->sum('total'),2);
 $expected=round($p+$l+$calcTax+(float)($card->round_off??0),2);
 echo 'BILLING_CHECK='.json_encode([
  'job_no'=>$card->job_no,'status'=>$card->status,'invoice_id'=>$card->invoice_id??null,
  'stored_gst'=>(float)($card->gst_total??0),'line_gst'=>$calcTax,
  'stored_parts'=>(float)($card->parts_total??0),'parts_sum'=>$p,
  'stored_labour'=>(float)($card->labour_total??0),'labour_sum'=>$l,
  'stored_grand'=>(float)($card->grand_total??0),'derived_grand'=>$expected,
  'invoice_count'=>DB::table('invoices')->where('job_card_id',$id)->count(),
  'tax_basis_unverified'=>$unverified,
  'view_amounts_disabled'=>$unverified || !in_array(strtolower(trim((string)$card->status)),['wip','in progress','in-progress','work in progress'],true) || !empty($card->invoice_id)
 ]).PHP_EOL;
}
PHP
echo 'V642_MODIFICATION_READONLY=COMPLETE'
