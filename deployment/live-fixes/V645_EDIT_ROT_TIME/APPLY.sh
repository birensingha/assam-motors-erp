#!/usr/bin/env bash
# V645 code-only Staging deployment. No ROT/JC or financial records are modified.
set -euo pipefail
ROOT="$1"
test "$ROOT" = "/home/u956497103/domains/assammotors.com/assam-erp-staging"
test -f "$ROOT/artisan"
HERE="$(cd "$(dirname "$0")" && pwd)"
export V645_ROOT="$ROOT" V645_SRC="$HERE"
php -l "$HERE/AdminRotTimeEditController.php"
python3 - <<'PY'
from pathlib import Path
from datetime import datetime,timezone
import os,subprocess,json
root=Path(os.environ['V645_ROOT'])
src=Path(os.environ['V645_SRC'])
paths={
 'controller':'app/Http/Controllers/Web/AdminRotTimeEditController.php',
 'partial':'resources/views/job-cards/rot-time-editor.blade.php',
 'routes':'routes/web.php',
 'job_card':'resources/views/job-cards/show.blade.php',
}
old={}
for key,path in paths.items():
 f=root/path
 if key in ('routes','job_card') and not f.is_file():raise RuntimeError('Missing ERP file: '+path)
 old[key]=f.read_bytes() if f.exists() else None
new={
 'controller':(src/'AdminRotTimeEditController.php').read_bytes(),
 'partial':(src/'rot-time-editor.blade.php').read_bytes(),
}
for key in ['controller','partial']:
 if old[key] is not None and old[key]!=new[key]:raise RuntimeError('Existing code differs: '+paths[key])

routes=old['routes'].decode('utf-8','surrogateescape')
anchor="    Route::get('/rot/{session}/history', [AdminRotPageController::class,'history'])->name('erp.rot.history');"
added="""    // V645_ROT_TIME_ADMIN_ONLY: authenticated Admin-only handler.
    Route::post('/rot/{session}/time/standard', [\\App\\Http\\Controllers\\Web\\AdminRotTimeEditController::class,'standard'])->name('erp.rot.time.standard');
    Route::post('/rot/{session}/time/actual', [\\App\\Http\\Controllers\\Web\\AdminRotTimeEditController::class,'actual'])->name('erp.rot.time.actual');
"""
if 'V645_ROT_TIME_ADMIN_ONLY' not in routes:
 if routes.count(anchor)!=1:raise RuntimeError('Web route anchor not unique')
 routes=routes.replace(anchor,added+anchor,1)
new['routes']=routes.encode('utf-8','surrogateescape')

view=old['job_card'].decode('utf-8','surrogateescape')
hist="""        <a class="btn small ghost" href="{{ route('erp.rot.history',$rot->id) }}">📜 History</a>"""
widget="""        {{-- V645_ADMIN_WIP_ROT_TIME_EDIT --}}
        @include('job-cards.rot-time-editor')
"""
if 'V645_ADMIN_WIP_ROT_TIME_EDIT' not in view:
 if view.count(hist)!=1:raise RuntimeError('JC ROT action anchor not unique')
 view=view.replace(hist,widget+hist,1)
new['job_card']=view.encode('utf-8','surrogateescape')
lint=subprocess.run(['php','-l'],input=new['controller'],capture_output=True)
if lint.returncode:raise RuntimeError('Controller PHP preflight failed: '+(lint.stdout+lint.stderr).decode('utf-8','replace'))
if b"strtolower(trim((string)$user->role))==='admin'" not in new['controller']:
 raise RuntimeError('Strict Admin role guard missing')
if b"['Paused','Completed']" not in new['controller'] or b'CORRECT_PRODUCTIVE_TIME' not in new['controller']:
 raise RuntimeError('Productive-time safe edit / audit guard missing')
if b"LOWER(COALESCE(status" not in new['controller']:
 raise RuntimeError('Invoice lock missing')
backup=root.parent/('BACKUP_V645_ADMIN_ROT_TIME_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700)
changed=[]
try:
 for key,path in paths.items():
  dest=root/path
  if old[key]==new[key]:continue
  if old[key] is not None:
   saved=backup/path;saved.parent.mkdir(parents=True,exist_ok=True)
   saved.write_bytes(old[key]);saved.chmod(0o600)
  dest.parent.mkdir(parents=True,exist_ok=True)
  temp=dest.with_name('.'+dest.name+'.v645tmp')
  temp.write_bytes(new[key])
  temp.chmod(dest.stat().st_mode & 0o777 if dest.exists() else 0o644)
  os.replace(temp,dest);changed.append(key)
 for key in ['controller','routes']:
  p=subprocess.run(['php','-l',str(root/paths[key])],capture_output=True,text=True)
  if p.returncode:raise RuntimeError('PHP lint failed '+key+': '+p.stdout+p.stderr)
 p=subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError('Blade compilation failed: '+p.stdout+p.stderr)
 p=subprocess.run(['php','artisan','route:list','--json'],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError('Route verification failed: '+p.stdout+p.stderr)
 all_routes=json.loads(p.stdout)
 for name in ['erp.rot.time.standard','erp.rot.time.actual']:
  r=[item for item in all_routes if item.get('name')==name]
  if len(r)!=1 or 'POST' not in r[0].get('method','') or not any('EnsureErpAdmin' in m for m in r[0].get('middleware',[])):
   raise RuntimeError('Missing authenticated Admin route: '+name)
 print('V645_ROUTE_AUTHORIZATION=PASS')
 print('V645_BLADE_AND_PHP=PASS')
except BaseException:
 for key in reversed(changed):
  dest=root/paths[key]
  if old[key] is None:dest.unlink(missing_ok=True)
  else:dest.write_bytes(old[key])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
 print('V645_ROLLBACK=RESTORED')
 raise
print('V645_BACKUP='+str(backup))
print('V645_FILES='+','.join(changed))
print('V645_ADMIN_ROT_TIME_FEATURE=DEPLOYED')
PY
