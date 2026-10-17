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
echo 'V642_MODIFICATION_READONLY=COMPLETE'
