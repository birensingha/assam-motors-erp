#!/usr/bin/env bash
# Code-only V647 patch. No test writes to customers, job cards, ROTs or invoices.
set -euo pipefail
ROOT="$1"
test "$ROOT" = "/home/u956497103/domains/assammotors.com/assam-erp-staging"
test -f "$ROOT/artisan"
HERE="$(cd "$(dirname "$0")" && pwd)"
export V647_ROOT="$ROOT" V647_SOURCE="$HERE"
php -l "$HERE/AdminRotTimelineController.php"
python3 - <<'PY'
import os,json,subprocess
from pathlib import Path
from datetime import datetime,timezone
root=Path(os.environ['V647_ROOT']);source=Path(os.environ['V647_SOURCE'])
files={
 'controller':'app/Http/Controllers/Web/AdminRotTimelineController.php',
 'partial':'resources/views/job-cards/rot-time-editor.blade.php',
 'routes':'routes/web.php',
}
before={}
for key,p in files.items():
 f=root/p
 if key=='routes' and not f.exists():raise RuntimeError('V647 route source absent')
 before[key]=f.read_bytes() if f.exists() else None
modified={
 'controller':(source/'AdminRotTimelineController.php').read_bytes(),
 'partial':(source/'rot-time-editor.blade.php').read_bytes(),
}
if before['controller'] is not None and before['controller']!=modified['controller']:
 raise RuntimeError('A different timeline controller already exists; refusing overwrite')
routes=before['routes'].decode('utf-8','surrogateescape')
anchor="    Route::post('/rot/{session}/time/actual', [\\App\\Http\\Controllers\\Web\\AdminRotTimeEditController::class,'actual'])->name('erp.rot.time.actual');"
addon="""    // V647_ADMIN_ROT_START_END: historical correction, Admin role enforced by controller.
    Route::post('/rot/{session}/time/dates', [\\App\\Http\\Controllers\\Web\\AdminRotTimelineController::class,'update'])->name('erp.rot.time.dates');
"""
if 'V647_ADMIN_ROT_START_END' not in routes:
 if routes.count(anchor)!=1:raise RuntimeError('ROT time route anchor missing or duplicated')
 routes=routes.replace(anchor,anchor+'\n'+addon.rstrip(),1)
modified['routes']=routes.encode('utf-8','surrogateescape')
if b"start->lessThan($jobOpened)" not in modified['controller'] or b"CORRECT_START_END_DATETIME" not in modified['controller']:
 raise RuntimeError('JC opening guard or audit code absent')
if b"total_seconds" not in modified['controller'] or b"productive_seconds" not in modified['controller']:
 raise RuntimeError('Productive times audit disclosure missing')
if modified['partial'].count(b'@if($rotClockStandardAllowed)')!=1 or modified['partial'].count(b'route(\'erp.rot.time.dates\'')!=1:
 raise RuntimeError('Duplicate/missing ROT editor controls')
for k in ['controller','routes']:
 p=subprocess.run(['php','-l'],input=modified[k],capture_output=True)
 if p.returncode:raise RuntimeError('PHP syntax '+k+': '+(p.stderr+p.stdout).decode('utf-8','replace'))
backup=root.parent/('BACKUP_V647_ROT_DATE_EDITOR_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700)
changed=[]
try:
 for key,p in files.items():
  if before[key]==modified[key]:continue
  dest=root/p
  if before[key] is not None:
   old=backup/p;old.parent.mkdir(parents=True,exist_ok=True);old.write_bytes(before[key]);old.chmod(0o600)
  tmp=dest.with_name('.'+dest.name+'.v647tmp')
  tmp.write_bytes(modified[key]);tmp.chmod(dest.stat().st_mode&0o777 if dest.exists() else 0o644)
  os.replace(tmp,dest);changed.append(key)
 for k in ['controller','routes']:
  p=subprocess.run(['php','-l',str(root/files[k])],capture_output=True,text=True)
  if p.returncode:raise RuntimeError(k+' lint fail '+p.stderr+p.stdout)
 p=subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError('Blade compile '+p.stderr+p.stdout)
 p=subprocess.run(['php','artisan','route:list','--json'],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError('Route list '+p.stderr+p.stdout)
 routes=json.loads(p.stdout)
 hit=[r for r in routes if r.get('name')=='erp.rot.time.dates']
 if len(hit)!=1 or 'POST' not in hit[0].get('method','') or not any('EnsureErpAdmin' in m for m in hit[0].get('middleware',[])):
  raise RuntimeError('Protected ROT date edit route missing or unauthorized')
 print('V647_AUTHORIZED_POST_ROUTE=PASS')
 print('V647_PHP_BLADE=PASS')
except BaseException:
 for key in reversed(changed):
  dest=root/files[key]
  if before[key] is None:dest.unlink(missing_ok=True)
  else:dest.write_bytes(before[key])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
 print('V647_SOURCE_ROLLBACK=RESTORED')
 raise
print('V647_BACKUP='+str(backup))
print('V647_SOURCE_FILES='+','.join(changed))
print('V647_ROT_START_END_JC_OPENING_GUARD=DEPLOYED')
PY
