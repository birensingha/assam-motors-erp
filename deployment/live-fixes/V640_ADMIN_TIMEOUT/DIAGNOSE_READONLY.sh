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
echo '=== AUTHENTICATED READONLY LATENCY BENCHMARK ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$actor=\App\Models\ErpAuthUser::query()->where('role','admin')->where('staff_id',4)->where('active',true)->first();
if(!$actor){echo "AUTH_ACTOR_LINK=UNAVAILABLE\n";exit(0);}
$queries=0;$dbMs=0;
DB::listen(function($event)use(&$queries,&$dbMs){$queries++;$dbMs+=$event->time;});
$routes=[
 ['/admin/mobile/my-attendance',\App\Http\Controllers\Api\V1\OwnerSelfAttendanceInspectionController::class,'myAttendance'],
 ['/admin/mobile/dashboard',\App\Http\Controllers\Api\V1\AdminMobileController::class,'dashboard'],
];
foreach($routes as [$path,$class,$method]){
 for($i=0;$i<3;$i++){
  $request=\Illuminate\Http\Request::create('/api/v1'.$path,'GET');
  $request->setUserResolver(fn()=>$actor);
  $qBefore=$queries;$dbBefore=$dbMs;$start=microtime(true);
  try{
   $response=$app->make($class)->$method($request);
   $data=json_decode($response->getContent(),true);
   $ok=($data['ok']??false)===true;
   echo 'ENDPOINT='.$path.' PASS='.($ok?'YES':'NO').' REQUEST_MS='.round((microtime(true)-$start)*1000,1).' DB_MS='.round($dbMs-$dbBefore,1).' SQL_COUNT='.($queries-$qBefore).PHP_EOL;
  }catch(\Throwable $e){
   echo 'ENDPOINT='.$path.' EXCEPTION_CLASS='.get_class($e).' REQUEST_MS='.round((microtime(true)-$start)*1000,1).PHP_EOL;
  }
 }
}
PHP
echo '=== INTERNET HTTP ANONYMOUS RESPONSE TIMINGS ==='
for p in /api/v1/admin/mobile/my-attendance /api/v1/admin/mobile/dashboard; do
 curl --connect-timeout 8 --max-time 14 -sS -o /dev/null -w "HTTP_ROUTE=$p STATUS=%{http_code} TOTAL_SECONDS=%{time_total} CONNECT_SECONDS=%{time_connect}\n" "https://staging.assammotors.com$p" || true
done
echo '=== SOURCE DIAGNOSIS READONLY COMPLETE ==='
