#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo '=== LIVE ROUTES MATCHING ATTENDANCE / ROT REPORT / DASHBOARD ==='
php artisan route:list --json | python3 -c 'import sys,json; a=json.load(sys.stdin); [print(x.get("method"),x.get("uri"),x.get("action"),x.get("name","")) for x in a if any(k in x.get("uri","").lower() for k in ["attendance","checkout","check-out","rot-performance","productivity","rot/performance","dashboard","rot/session","rot/"])][:200]' | head -190
echo '=== TARGETED CODE CLASS FINDER ==='
python3 - <<'PY'
from pathlib import Path
import re
targets=[
 'class RotWorkflowService','function checkOut','function checkout','function checkIn','function checkin',
 'ROT Performance','Rot Performance','rot-performance','Staff Productivity','staff-performance',
 'function dashboard','function attendance','pending_rot','pending ROT','function pause','rot_session_events'
]
roots=[Path('app/Http/Controllers'),Path('app/Services'),Path('resources/views'),Path('routes')]
for root in roots:
 for p in root.rglob('*.php'):
  try: s=p.read_text(errors='replace')
  except: continue
  hits=[(i+1,x.strip()[:210]) for i,x in enumerate(s.splitlines()) if any(t.lower() in x.lower() for t in targets)]
  if hits:
   print('FILE',p,'LINES',len(s.splitlines()),'HITS',len(hits))
   for line,t in hits[:30]:print('  ',line,t)
print('=== MODELS ===')
for p in Path('app/Models').glob('*Attendance*'): print(p)
PY
echo '=== DB TABLES & FIELDS ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['staff_attendance','staff_attendances','attendances','attendance_logs','staff','staff_members','rot_sessions','rot_session_events','rot_mechanic_segments','job_cards','job_card_labour'] as $table){
 if(!Schema::hasTable($table))continue;
 echo 'TABLE='.$table.'='.json_encode(array_map(fn($x)=>['f'=>$x->Field,'type'=>$x->Type],DB::select('SHOW COLUMNS FROM '.$table))).PHP_EOL;
}
PHP
echo '=== SOURCE FOCUSED FULL ==='
python3 - <<'PY'
from pathlib import Path
targets=[
 ('app/Services/AttendanceService.php',1,185),
 ('app/Http/Controllers/Api/V1/AttendanceController.php',1,110),
 ('app/Http/Controllers/Api/V1/OwnerSelfAttendanceInspectionController.php',1,135),
 ('app/Http/Controllers/Web/AdminSelfAttendanceController.php',1,160),
 ('app/Http/Controllers/Web/StaffPortalController.php',1,185),
 ('app/Http/Controllers/Web/AdminDashboardPageController.php',1,145),
 ('resources/views/dashboard/rot-performance.blade.php',1,130),
 ('resources/views/dashboard/workshop.blade.php',1,135),
 ('app/Services/RotWorkflowService.php',35,170),
 ('app/Services/RotWorkflowService.php',190,300),
 ('routes/web.php',60,140),
]
for p,start,end in targets:
 f=Path(p)
 if not f.exists():continue
 a=f.read_text(errors='replace').splitlines()
 print('===SRC',p,'START',start,'END',end,'===')
 for i in range(start-1,min(end,len(a))):
  line=a[i]
  if 'password' in line.lower() or 'private_key' in line.lower() or 'secret' in line.lower():continue
  print(f'{i+1}: {line[:240]}')
PY
echo '=== ATTENDANCE SOURCE SCHEMA ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(DB::select('SHOW TABLES') as $v){
 $t=(string)array_values((array)$v)[0];
 if(preg_match('/attend|check.?in|work.?log|daily|staff.*log/i',$t)){
  echo 'ATTEND_TABLE='.$t.' '.json_encode(array_map(fn($c)=>['f'=>$c->Field,'t'=>$c->Type],DB::select('SHOW COLUMNS FROM '.$t))).PHP_EOL;
 }
}
PHP
echo 'V648_READONLY=COMPLETE'
