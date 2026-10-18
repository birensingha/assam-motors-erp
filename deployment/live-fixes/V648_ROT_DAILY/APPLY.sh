#!/usr/bin/env bash
set -euo pipefail
ROOT="$1"
test "$ROOT" = "/home/u956497103/domains/assammotors.com/assam-erp-staging"
test -f "$ROOT/artisan"
HERE="$(cd "$(dirname "$0")" && pwd)"
export V648_ROOT="$ROOT" V648_SRC="$HERE"
php -l "$HERE/StaffDailyRotPerformanceService.php"
python3 - <<'PY'
import os,subprocess,json
from pathlib import Path
from datetime import datetime,timezone
root=Path(os.environ['V648_ROOT']);src=Path(os.environ['V648_SRC'])
paths={'attendance':'app/Services/AttendanceService.php',
'admin_attendance':'app/Http/Controllers/Web/AdminSelfAttendanceController.php',
'dashboard':'app/Http/Controllers/Web/AdminDashboardPageController.php',
'main':'resources/views/dashboard/main.blade.php',
'workshop':'resources/views/dashboard/workshop.blade.php',
'performance':'resources/views/dashboard/rot-performance.blade.php',
'service':'app/Services/StaffDailyRotPerformanceService.php',
'daily_view':'resources/views/dashboard/partials/staff-daily-report.blade.php',
'pending_view':'resources/views/dashboard/partials/pending-rot-panel.blade.php'}
prior={}
for k,p in paths.items():
 f=root/p
 if k not in ('service','daily_view','pending_view') and not f.exists():
  raise RuntimeError('V648 required source missing: '+p)
 prior[k]=f.read_bytes() if f.exists() else None
new={'service':(src/'StaffDailyRotPerformanceService.php').read_bytes(),
'daily_view':(src/'staff-daily-report.blade.php').read_bytes(),
'pending_view':(src/'pending-rot-panel.blade.php').read_bytes()}
for k in ('service','daily_view','pending_view'):
 if prior[k] is not None and prior[k]!=new[k] and b'V648' not in prior[k]:
  raise RuntimeError('V648 existing target conflict: '+paths[k])
def replace(raw,anchor,repl,label):
 if repl in raw:return raw
 if raw.count(anchor)!=1:raise RuntimeError('V648 precheck '+label+' count='+str(raw.count(anchor)))
 return raw.replace(anchor,repl,1)

att=prior['attendance'].decode('utf-8','surrogateescape')
old="""            $runningRot = RotSession::query()
                ->where('staff_id', $staffId)
                ->where('status', 'Running')
                ->lockForUpdate()
                ->first();
            if ($runningRot) {
                throw ValidationException::withMessages([
                    'attendance' => 'Running ROT must be Paused or Completed before Check-Out.',
                ]);
            }

"""
if 'V648_AUTO_PAUSE_ON_CHECKOUT' not in att:
 att=replace(att,old,'','manual staff ROT pause barrier')
 att=replace(att,
 """            $attendance->check_out = $now;""",
 """            // V648_AUTO_PAUSE_ON_CHECKOUT: pause all running ROT in this transaction.
            $runningIds=RotSession::query()->where('staff_id',$staffId)
                ->where('status','Running')->orderBy('id')->pluck('id');
            foreach($runningIds as $id){
                app(RotWorkflowService::class)->pause((string)$id,'AUTO_CHECK_OUT',
                    'Auto Paused at attendance Check-Out; resume only after next Check-In.',
                    'staff',$staffId,$staffId);
            }
            $attendance->check_out = $now;""",'insert staff checkout auto-pause')
new['attendance']=att.encode('utf-8','surrogateescape')

adm=prior['admin_attendance'].decode('utf-8','surrogateescape')
adm=replace(adm,
"""            // Same active-ROT check as Staff attendance: never silently stop a ROT.
            if(DB::table('rot_sessions')->where('staff_id',$staffId)->where('status','Running')->exists()){
                throw ValidationException::withMessages(['attendance'=>'Pause or Complete your Running ROT before Check-Out.']);
            }
""",
"""            // V648_OWNER_AUTO_PAUSE_ON_CHECKOUT: shared ROT timer audit, in same transaction.
            $runningIds=DB::table('rot_sessions')->where('staff_id',$staffId)
                ->where('status','Running')->orderBy('id')->pluck('id');
            foreach($runningIds as $id){
                app(\\App\\Services\\RotWorkflowService::class)->pause((string)$id,'AUTO_CHECK_OUT',
                    'Auto Paused at Admin Check-Out; manual Resume after next Check-In.',
                    'admin',$staffId,null);
            }
""",'owner/admin checkout auto-pause')
new['admin_attendance']=adm.encode('utf-8','surrogateescape')

dash=prior['dashboard'].decode('utf-8','surrogateescape')
dash=replace(dash,
"""        return view('dashboard.main', compact('counts','revenue','recentBookings','recentOrders'));""",
"""        // V648_MAIN_PENDING_ROTS
        $rotDaily=app(\\App\\Services\\StaffDailyRotPerformanceService::class);
        $pendingRots=$rotDaily->pending(12);
        $pendingCounts=$rotDaily->pendingCount();
        return view('dashboard.main',compact('counts','revenue','recentBookings','recentOrders',
            'pendingRots','pendingCounts'));""",'admin main pending')
dash=replace(dash,
"""        return view('dashboard.workshop', compact('jobs','rotRows'));""",
"""        $rotDaily=app(\\App\\Services\\StaffDailyRotPerformanceService::class);
        $pendingRots=$rotDaily->pending(18);
        $pendingCounts=$rotDaily->pendingCount();
        return view('dashboard.workshop',compact('jobs','rotRows','pendingRots','pendingCounts'));""",'workshop pending')
dash=replace(dash,
"""        return view('dashboard.rot-performance', compact('summary','rows'));""",
"""        // V648_STAFF_DAILY_PERFORMANCE: choose date, staff, and event-based daily ROT register.
        $reportDate=trim((string)$request->query('date',now('Asia/Kolkata')->toDateString()));
        $valid=\\DateTimeImmutable::createFromFormat('!Y-m-d',$reportDate);
        if(!$valid||$valid->format('Y-m-d')!==$reportDate ||
            $reportDate>now('Asia/Kolkata')->toDateString()){
            $reportDate=now('Asia/Kolkata')->toDateString();
        }
        $rotDaily=app(\\App\\Services\\StaffDailyRotPerformanceService::class);
        $staffOptions=$rotDaily->staffList();
        $selectedStaffId=filter_var($request->query('staff_id',0),FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1]]) ?: 0;
        if($selectedStaffId && !$staffOptions->contains('id',$selectedStaffId))$selectedStaffId=0;
        $dailyDetail=$selectedStaffId?$rotDaily->daily($selectedStaffId,$reportDate):null;
        $attendanceOverview=DB::table('attendance')->where('date',$reportDate)
            ->orderBy('staff_name')->limit(150)->get();
        $pendingRots=$rotDaily->pending(25);
        $pendingCounts=$rotDaily->pendingCount();
        return view('dashboard.rot-performance',compact('summary','rows','reportDate',
            'staffOptions','selectedStaffId','dailyDetail','attendanceOverview',
            'pendingRots','pendingCounts'));""",'daily report controller')
new['dashboard']=dash.encode('utf-8','surrogateescape')

main=prior['main'].decode('utf-8','surrogateescape')
main=replace(main,'<div class="dashboard-grid">',
"""{{-- V648_PENDING_PANEL_MAIN --}}
@include('dashboard.partials.pending-rot-panel')
<div class="dashboard-grid">""",'main dashboard panel')
new['main']=main.encode('utf-8','surrogateescape')
work=prior['workshop'].decode('utf-8','surrogateescape')
work=replace(work,'<div class="section-gap module-grid">',
"""{{-- V648_PENDING_PANEL_WORKSHOP --}}
@include('dashboard.partials.pending-rot-panel')
<div class="section-gap module-grid">""",'workshop pending panel')
new['workshop']=work.encode('utf-8','surrogateescape')
perf=prior['performance'].decode('utf-8','surrogateescape')
perf=replace(perf,'<section class="card">',
"""{{-- V648_STAFF_DAILY_ROT_REPORT --}}
@include('dashboard.partials.staff-daily-report')
@include('dashboard.partials.pending-rot-panel')
<section class="card">""",'performance daily report view')
new['performance']=perf.encode('utf-8','surrogateescape')

for key in ['attendance','admin_attendance','dashboard','service']:
 p=subprocess.run(['php','-l'],input=new[key],capture_output=True)
 if p.returncode:raise RuntimeError('V648 PHP precheck '+key+': '+(p.stdout+p.stderr).decode('utf-8','replace'))
backup=root.parent/('BACKUP_V648_STAFF_ROT_DAILY_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700);changed=[]
try:
 for k,p in paths.items():
  dest=root/p
  if new[k]==prior[k]:continue
  if prior[k] is not None:
   b=backup/p;b.parent.mkdir(parents=True,exist_ok=True)
   b.write_bytes(prior[k]);b.chmod(0o600)
  dest.parent.mkdir(parents=True,exist_ok=True)
  t=dest.with_name('.'+dest.name+'.v648tmp')
  t.write_bytes(new[k]);t.chmod(dest.stat().st_mode&0o777 if dest.exists() else 0o644)
  os.replace(t,dest);changed.append(k)
 for key in ['attendance','admin_attendance','dashboard','service']:
  c=subprocess.run(['php','-l',str(root/paths[key])],capture_output=True,text=True)
  if c.returncode:raise RuntimeError('Source lint failed '+key+c.stdout+c.stderr)
 c=subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,text=True,timeout=100)
 if c.returncode:raise RuntimeError('Blade build failed '+c.stdout+c.stderr)
 c=subprocess.run(['php','artisan','route:list','--json'],cwd=root,capture_output=True,text=True,timeout=100)
 if c.returncode:raise RuntimeError('Route bootstrap failed '+c.stdout+c.stderr)
 if not any(r.get('name')=='erp.rot-performance' for r in json.loads(c.stdout)):
  raise RuntimeError('ROT performance route not available')
 print('V648_PHP_BLADE_ROUTE=PASS')
except BaseException:
 for k in reversed(changed):
  dest=root/paths[k]
  if prior[k] is None:dest.unlink(missing_ok=True)
  else:dest.write_bytes(prior[k])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
 print('V648_ROLLBACK=RESTORED')
 raise
print('V648_BACKUP='+str(backup))
print('V648_CHANGED='+','.join(changed))
print('V648_DAILY_STAFF_ROT_AUTO_PAUSE_AND_PENDING=DEPLOYED')
PY
