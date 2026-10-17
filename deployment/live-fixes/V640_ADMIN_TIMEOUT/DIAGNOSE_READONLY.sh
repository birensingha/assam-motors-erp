#!/usr/bin/env bash
set -euo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
cd "$ROOT"
echo '=== LARAVEL ROUTES ==='
php artisan route:list --json | python3 -c 'import json,sys; a=json.load(sys.stdin); [print(x.get("method"),x.get("uri"),x.get("action")) for x in a if "admin/mobile/my-attendance" in x.get("uri","") or "admin/mobile/dashboard" in x.get("uri","")]'
echo '=== SOURCE CONTROLLERS ==='
python3 - <<'PY'
from pathlib import Path
import re
files=[
'app/Http/Controllers/Api/V1/OwnerSelfAttendanceInspectionController.php',
'app/Http/Controllers/Api/V1/AdminMobileController.php',
'app/Http/Controllers/Web/AdminSelfAttendanceController.php',
'app/Services/StaffAttendanceService.php',
'app/Services/AttendanceService.php',
]
for name in files:
 f=Path(name)
 if not f.is_file(): continue
 a=f.read_text(errors='replace').splitlines()
 print('===FILE',name,'LINES',len(a),'===')
 if len(a)<=360: indices=range(len(a))
 else:
  hits=[i for i,line in enumerate(a) if re.search(r'function (myAttendance|dashboard|selfAttendance|checkIn|checkOut|current|today)|attend|function admin\(',line,re.I)]
  selected=set()
  for i in hits[:35]:selected.update(range(max(0,i-4),min(len(a),i+15)))
  indices=sorted(selected)
 for i in indices:
  t=a[i]
  if re.search(r'password|api.secret|private.key|bearer |token_secret',t,re.I):continue
  print(f'{i+1}: {t[:245]}')
print('=== REFERENCE FILES ===')
roots=[Path('app/Services'),Path('app/Http/Controllers')]
for root in roots:
 for f in root.rglob('*.php'):
  if f.name in ['AdminMobileController.php','OwnerSelfAttendanceInspectionController.php']:continue
  try:t=f.read_text(errors='replace')
  except:continue
  if 'myAttendance' in t or 'dailyAttendance' in t or ('attendance' in f.name.lower() and ('dashboard' in t.lower() or 'staff_attendance' in t)):
   print(f)
PY
echo '=== ATTENDANCE SCHEMA / COUNTS ONLY ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$tables=['staff_attendance','staff_attendances','attendance','staff','rot_sessions','staff_location_logs','staff_location_points','hr_attendance_adjustments'];
foreach($tables as $t) {
 if(!Schema::hasTable($t))continue;
 $cols=DB::select('SHOW COLUMNS FROM '.$t);
 $indexes=DB::select('SHOW INDEX FROM '.$t);
 echo 'TABLE='.$t.' COLS='.implode(',',array_map(fn($x)=>$x->Field,$cols)).' ROWS='.DB::table($t)->count().PHP_EOL;
 echo 'INDEX='.$t.' '.implode(';',array_map(fn($x)=>$x->Key_name.':'.$x->Column_name,$indexes)).PHP_EOL;
}
echo 'SERVER_CLOCK='.$app['config']->get('app.timezone').'|'.date('c').PHP_EOL;
PHP
echo '=== SOURCE DIAGNOSIS READONLY COMPLETE ==='
