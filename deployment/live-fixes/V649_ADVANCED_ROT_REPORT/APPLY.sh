#!/usr/bin/env bash
# Code-only Staging update. No database row writes.
set -euo pipefail
ROOT="$1"
test "$ROOT" = "/home/u956497103/domains/assammotors.com/assam-erp-staging"
test -f "$ROOT/artisan"
SRC="$(cd "$(dirname "$0")" && pwd)"
export V649_ROOT="$ROOT" V649_SOURCE="$SRC"
php -l "$SRC/AdvancedRotPerformanceReportService.php"
python3 - <<'PY'
import os,subprocess,json
from pathlib import Path
from datetime import datetime,timezone
root=Path(os.environ['V649_ROOT']);src=Path(os.environ['V649_SOURCE'])
files={
 'service':'app/Services/AdvancedRotPerformanceReportService.php',
 'view':'resources/views/dashboard/partials/advanced-rot-report.blade.php',
 'controller':'app/Http/Controllers/Web/AdminDashboardPageController.php',
 'index':'resources/views/dashboard/rot-performance.blade.php',
}
before={}
for key,p in files.items():
 f=root/p
 if key in ('controller','index') and not f.is_file():raise RuntimeError('Required staging file missing: '+p)
 before[key]=f.read_bytes() if f.exists() else None
new={
 'service':(src/'AdvancedRotPerformanceReportService.php').read_bytes(),
 'view':(src/'advanced-rot-report.blade.php').read_bytes(),
}
def one(s,old,repl,where):
 if repl in s:return s
 if s.count(old)!=1:raise RuntimeError('V649 unexpected '+where+' anchor count='+str(s.count(old)))
 return s.replace(old,repl,1)
controller=before['controller'].decode('utf-8','surrogateescape')
marker="""        return view('dashboard.rot-performance',compact('summary','rows','reportDate',"""
addition="""        // V649_ADVANCED_ROT_PERFORMANCE_FILTERS: read-only JC-wise history drilldown.
        $advancedService=app(\\App\\Services\\AdvancedRotPerformanceReportService::class);
        $advancedRotReport=$advancedService->report($request);
        $advancedRotCodes=$advancedService->rotCodes();
"""
if 'V649_ADVANCED_ROT_PERFORMANCE_FILTERS' not in controller:
 controller=one(controller,marker,addition+marker,'report service injection')
compact="""            'pendingRots','pendingCounts'));"""
revised="""            'pendingRots','pendingCounts','advancedRotReport','advancedRotCodes'));"""
if 'advancedRotReport' not in controller.split("return view('dashboard.rot-performance'")[1]:
 pos=controller.index("return view('dashboard.rot-performance'")
 controller=controller[:pos]+one(controller[pos:],compact,revised,'ROT performance compact report variables')
new['controller']=controller.encode('utf-8','surrogateescape')
index=before['index'].decode('utf-8','surrogateescape')
if "advanced-rot-report" not in index:
 index=one(index,
   "@include('dashboard.partials.staff-daily-report')",
   "@include('dashboard.partials.advanced-rot-report')",
   'replace old daily filter with advanced report')
new['index']=index.encode('utf-8','surrogateescape')
if b'V649_ADVANCED_ROT_PERFORMANCE_FILTERS' not in new['controller'] or b'advanced-rot-report' not in new['index']:
 raise RuntimeError('V649 controller/view integration absent')
for key in ['service','controller']:
 p=subprocess.run(['php','-l'],input=new[key],capture_output=True)
 if p.returncode:raise RuntimeError('PHP lint '+key+' '+(p.stdout+p.stderr).decode('utf-8','replace'))
backup=root.parent/('BACKUP_V649_ADVANCED_ROT_REPORT_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700)
changed=[]
try:
 for key,path in files.items():
  f=root/path
  if before[key]==new[key]:continue
  if before[key] is not None:
   dest=backup/path
   dest.parent.mkdir(parents=True,exist_ok=True)
   dest.write_bytes(before[key]);dest.chmod(0o600)
  f.parent.mkdir(parents=True,exist_ok=True)
  tmp=f.with_name('.'+f.name+'.v649tmp')
  tmp.write_bytes(new[key])
  tmp.chmod(f.stat().st_mode & 0o777 if f.exists() else 0o644)
  os.replace(tmp,f);changed.append(key)
 for key in ['service','controller']:
  p=subprocess.run(['php','-l',str(root/files[key])],capture_output=True,text=True)
  if p.returncode:raise RuntimeError('Live PHP lint '+key+p.stdout+p.stderr)
 p=subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError('Blade view compilation '+p.stdout+p.stderr)
 p=subprocess.run(['php','artisan','route:list','--json'],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError('Route compilation '+p.stdout+p.stderr)
 if not any(x.get('name')=='erp.rot-performance' for x in json.loads(p.stdout)):
  raise RuntimeError('ROT Performance route missing')
 print('V649_PHP_BLADE_ROUTE=PASS')
except BaseException:
 for key in reversed(changed):
  f=root/files[key]
  if before[key] is None:f.unlink(missing_ok=True)
  else:f.write_bytes(before[key])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
 print('V649_ROLLBACK=RESTORED')
 raise
print('V649_BACKUP='+str(backup))
print('V649_CHANGED='+','.join(changed))
print('V649_ADVANCED_ROT_FILTERS_REPORT=DEPLOYED')
PY
