#!/usr/bin/env python3
"""AM250: show rework assignment + native ROT status on Admin Job Card.
Read-only presentation; no database write, no new ROT or billing line.
Usage: python3 install.py check|apply|verify|rollback STAGING_ROOT
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
    raise SystemExit('AM250_USAGE check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM250_NOT_STAGING')
life=root/'resources/views/job-cards/lifecycle-panel.blade.php'
panel=root/'resources/views/work-complaints/rework-jc-panel.blade.php'
source=pkg/'files/resources/views/work-complaints/rework-jc-panel.blade.php'
backupbase=root/'storage/app/am-backups'
pointer=backupbase/'AM250_REWORK_PROGRESS_LAST_BACKUP.txt'
marker='AM250_REWORK_JC_PROGRESS'
include="@include('work-complaints.rework-jc-panel')"
anchor='<div class="card section-gap am-life-card">'
if not source.is_file() or not life.is_file():raise SystemExit('STOP_AM250_REQUIRED_VIEW_MISSING')

def sha(data):return hashlib.sha256(data).hexdigest()
def cmd(args,timeout=100):
    return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(dest,data):
    dest.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am250_',dir=dest.parent)
    try:
        with os.fdopen(fd,'wb') as h:h.write(data)
        os.chmod(tmp,(dest.stat().st_mode & 0o777) if dest.exists() else 0o644)
        os.replace(tmp,dest)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def php_lint(b,label):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as h:
        h.write(b);path=Path(h.name)
    try:
        p=cmd(['php','-l',str(path)],25)
        if p.returncode:raise SystemExit('STOP_AM250_PHP_LINT_'+label+' '+(p.stdout+p.stderr)[-600:])
    finally:path.unlink(missing_ok=True)
def blade_lint(b,label):
    with tempfile.TemporaryDirectory(prefix='am250_blade_') as d:
        a=Path(d)/'src.blade.php';out=Path(d)/'compiled.php'
        a.write_bytes(b)
        ph=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        p=cmd(['php','-r',ph,str(a),str(out)],50)
        if p.returncode or not out.is_file():
            raise SystemExit('STOP_AM250_BLADE_COMPILE_'+label+' '+(p.stdout+p.stderr)[-550:])
        php_lint(out.read_bytes(),'BLADE_'+label)
def rendered_panel():
    probe=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$html=view('work-complaints.rework-jc-panel',['job'=>(object)['id'=>11]])->render();
if(!str_contains($html,'Complaint Rework Progress') ||
   !str_contains($html,'Firajul') && !str_contains($html,'FIRAJUL')){
 fwrite(STDERR,'STOP_AM250_JC11_REWORK_PANEL_NOT_RENDERED');exit(13);
}
if(!str_contains($html,'ROT PENDING') && !str_contains($html,'Rework ROT')){
 fwrite(STDERR,'STOP_AM250_REWORK_PROGRESS_NOT_RENDERED');exit(14);
}
echo 'AM250_JC11_REWORK_PANEL_RENDER_PASS';"""
    p=cmd(['php','-r',probe],50)
    if p.returncode:raise RuntimeError('AM250_STAGING_RENDER_FAILED '+(p.stdout+p.stderr)[-800:])
    print('AM250_JC11_REWORK_PANEL_RENDER_PASS')
def verify():
    if not panel.is_file() or not life.is_file():raise SystemExit('STOP_AM250_VERIFY_FILES_MISSING')
    if life.read_text('utf8','replace').count(include)!=1:
        raise SystemExit('STOP_AM250_VERIFY_JOB_CARD_INCLUDE_COUNT')
    if marker not in panel.read_text('utf8','replace'):
        raise SystemExit('STOP_AM250_VERIFY_PANEL_MARKER_MISSING')
    rendered_panel()
    print('AM250_STAGING_JC_REWORK_PROGRESS_VERIFIED_READONLY')

if mode=='verify':
    verify()
    sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM250_NO_BACKUP_POINTER')
    backup=Path(pointer.read_text().strip()).resolve()
    if backupbase.resolve() not in backup.parents or not backup.name.startswith('AM250_REWORK_PROGRESS_'):
        raise SystemExit('STOP_AM250_BACKUP_PATH_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    if sha(life.read_bytes())!=manifest['patched_lifecycle_sha'] or sha(panel.read_bytes())!=manifest['new_panel_sha']:
        raise SystemExit('STOP_AM250_FILES_CHANGED_SINCE_INSTALL')
    atomic(life,(backup/'lifecycle-panel.before.blade.php').read_bytes())
    panel.unlink()
    r=cmd(['php','artisan','view:clear'],90)
    if r.returncode:raise SystemExit('STOP_AM250_ROLLBACK_VIEW_CLEAR_FAILED')
    print('AM250_ROLLBACK_RESTORED_JOB_CARD_BLADE')
    sys.exit(0)

raw=life.read_bytes()
src=raw.decode('utf8','surrogateescape')
if marker in src or include in src or panel.exists():
    raise SystemExit('STOP_AM250_ALREADY_OR_PARTIALLY_INSTALLED_USE_VERIFY')
if src.count(anchor)!=1:
    raise SystemExit('STOP_AM250_LIFECYCLE_CARD_ANCHOR_COUNT_'+str(src.count(anchor)))
if not source.read_text('utf8','replace').startswith('{{-- AM250_REWORK_JC_PROGRESS'):
    raise SystemExit('STOP_AM250_PANEL_PAYLOAD_MARKER')
new=(src.replace(anchor,include+'\n'+anchor,1)).encode('utf8','surrogateescape')
paneldata=source.read_bytes()
blade_lint(raw,'EXISTING_LIFECYCLE')
blade_lint(new,'UPDATED_LIFECYCLE')
blade_lint(paneldata,'NEW_PANEL')
print('AM250_LARAVEL_BLADE_PREFLIGHT_PASS')
print('AM250_NO_REWORK_ROT_TIMERS_BILLING_OR_QC_RECORD_WRITES')
print('AM250_PREFLIGHT_PASS')
if mode=='check':
    print('AM250_CHECK_READ_ONLY')
    sys.exit(0)

backup=backupbase/('AM250_REWORK_PROGRESS_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False)
os.chmod(backup,0o700)
(backup/'lifecycle-panel.before.blade.php').write_bytes(raw)
os.chmod(backup/'lifecycle-panel.before.blade.php',0o600)
written=[]
try:
    if life.read_bytes()!=raw:raise RuntimeError('LIFECYCLE_CHANGED_SINCE_PREFLIGHT')
    atomic(life,new);written.append(life)
    atomic(panel,paneldata);written.append(panel)
    for command in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        p=cmd(command,115)
        if p.returncode:raise RuntimeError('VIEW_CACHE_FAILED '+(p.stdout+p.stderr)[-750:])
    verify()
except Exception as exc:
    for f in written:
        if f==life:atomic(life,raw)
        else:f.unlink(missing_ok=True)
    cmd(['php','artisan','view:clear'],80)
    raise SystemExit('STOP_AM250_APPLY_FAILED_CODE_RESTORED '+str(exc))

(backup/'manifest.json').write_text(json.dumps({
    'patched_lifecycle_sha':sha(new),
    'new_panel_sha':sha(paneldata),
},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n')
os.chmod(pointer,0o600)
print('AM250_JOBCARD_REWORK_PROGRESS_INSTALLED')
print('AM250_PRIVATE_BLADE_BACKUP',backup)
print('AM250_BROWSER_CHECK_PENDING_JC11 /erp/job-cards/11')
