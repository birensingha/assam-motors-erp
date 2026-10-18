#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo '=== SOURCE: ROT LIFECYCLE VALIDATION ==='
python3 - <<'PY'
from pathlib import Path
import re
files=[
 ('app/Services/RotWorkflowService.php',['start','pause','resume','assertWorkAllowed','ensureCheckedIn','assertCheckedIn','requireCheckIn']),
 ('app/Services/AttendanceService.php',['checkIn','checkOut','status','isCheckedIn']),
 ('app/Http/Controllers/Api/V1/RotSessionController.php',['pause','resume','start']),
 ('app/Http/Controllers/Api/V1/StaffRotController.php',['pause','resume','start']),
 ('app/Http/Controllers/Api/V1/StaffRotSessionController.php',['pause','resume','start']),
 ('app/Http/Controllers/Web/StaffPortalController.php',['rotPause','rotResume','rotStart']),
 ('app/Http/Controllers/Web/AdminRotPageController.php',['pause','resume','start']),
 ('app/Http/Controllers/Api/V1/OwnerWorkshopController.php',['pauseRot','resumeRot','startRot']),
]
for path,names in files:
 p=Path(path)
 if not p.exists():continue
 a=p.read_text(errors='replace').splitlines()
 funcs=[(i,m.group(1)) for i,x in enumerate(a) if (m:=re.search(r'function\s+(\w+)\s*\(',x))]
 print('FILE',path,'LINES',len(a))
 for name in names:
  for i,n in funcs:
   if n.lower()!=name.lower():continue
   stop=next((k for k,_ in funcs if k>i),len(a))
   print('METHOD',name,'FROM',i+1,'TO',stop)
   for k in range(i,min(stop,i+140)):
    if re.search(r'password|bearer|api.?key|secret|private.?key',a[k],re.I):continue
    print(f'{k+1}: {a[k][:260]}')
for path in ['routes/api.php','routes/web.php']:
 p=Path(path)
 if not p.exists():continue
 a=p.read_text(errors='replace').splitlines()
 for i,line in enumerate(a):
  if re.search(r'rot.*pause|pause.*rot|rot.*resume|resume.*rot|StaffRot',line,re.I):
   print('ROUTE',path,i+1,line[:270])
PY
echo '=== STAFF ATTENDANCE + FIRAJUL ROT-ENG-003 STATE (READ ONLY) ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
echo 'STAGING_CLOCK='.now('Asia/Kolkata')->toDateTimeString().PHP_EOL;
foreach(['staff','attendance','rot_sessions','rot_session_events'] as $tbl){
 if(!Schema::hasTable($tbl))continue;
 echo 'COLS_'.$tbl.'='.json_encode(array_map(fn($v)=>$v->Field,DB::select('SHOW COLUMNS FROM '.$tbl))).PHP_EOL;
}
$staff=DB::table('staff')->where('name','like','%firajul%')->get();
foreach($staff as $person){
 echo 'MATCH_STAFF='.json_encode(['id'=>$person->id,'name'=>$person->name,'staff_code'=>$person->staff_code,'status'=>$person->status]).PHP_EOL;
 foreach(DB::table('attendance')->where('staff_id',$person->id)->orderByDesc('date')->limit(4)->get() as $a)
  echo 'RECENT_ATTENDANCE='.json_encode(['staff_id'=>$a->staff_id,'date'=>$a->date,'check_in'=>$a->check_in,'check_out'=>$a->check_out,'status'=>$a->status]).PHP_EOL;
 foreach(DB::table('rot_sessions')->where('staff_id',$person->id)->where('rot_code','ROT-ENG-003')->orderByDesc('created_at')->limit(8)->get() as $r){
  echo 'MATCH_ROT='.json_encode(['id'=>$r->id,'rot_code'=>$r->rot_code,'job_card_no'=>$r->job_card_no,'status'=>$r->status,'start_time'=>$r->start_time,'end_time'=>$r->end_time,'total_seconds'=>$r->total_seconds,'updated_at'=>$r->updated_at,'pauses'=>substr((string)$r->pauses,0,1700)]).PHP_EOL;
  foreach(DB::table('rot_session_events')->where('session_id',$r->id)->orderByDesc('id')->limit(8)->get() as $e)
   echo 'RECENT_EVENT='.json_encode(['id'=>$e->id,'action'=>$e->action,'staff_id'=>$e->staff_id,'event_at'=>$e->event_at]).PHP_EOL;
 }
}
PHP
echo 'V650_ROT_PAUSE_READONLY_AUDIT=COMPLETE'
