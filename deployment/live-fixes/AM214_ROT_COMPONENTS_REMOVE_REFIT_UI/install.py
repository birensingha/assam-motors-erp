#!/usr/bin/env python3
"""AM214 - Redesign native ROT internal work-procedure field around the real workshop use:
list only parts/components that must be removed/disconnected and refitted for the ROT.

UI-only. Keeps the existing labour_master.work_procedure column and all saved values.
No DB, Job Card, Estimate, Invoice, finance, Android, or OLD ERP writes.
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM214_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM214_STOP_NOT_STAGING')

view=root/'resources/views/admin/native/labour.blade.php'
backup=root/'storage/app/am-backups/AM214_ROT_COMPONENTS_REMOVE_REFIT_UI'
view_bak=backup/'labour.blade.php.before_AM214'
manifest=backup/'installed-sha256.txt'
marker='AM214_ROT_COMPONENTS_REMOVE_REFIT_UI'

def read(p): return p.read_text('utf8',errors='surrogateescape')
def sha(s): return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am214_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh: fh.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp): os.unlink(tmp)

if not view.is_file():
    raise SystemExit('AM214_STOP_LABOUR_VIEW_MISSING')
current=read(view)

if mode=='rollback':
    if not view_bak.is_file() or not manifest.is_file():
        raise SystemExit('AM214_STOP_ROLLBACK_BACKUP_MISSING')
    if read(manifest).strip()!=sha(current):
        raise SystemExit('AM214_STOP_ROLLBACK_CURRENT_VIEW_CHANGED')
    atomic(view,read(view_bak))
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=45)
    print('AM214_ROLLBACK_UI_RESTORED_NO_DATA_CHANGE')
    sys.exit(0)

if marker in current:
    print('AM214_ALREADY_INSTALLED_NO_CHANGES')
    sys.exit(0)

if current.count('name="work_procedure"')!=2:
    raise SystemExit('AM214_STOP_EXPECTED_CREATE_EDIT_WORK_PROCEDURE_FIELDS_'+str(current.count('name="work_procedure"')))
if current.count('am212-work-procedure')!=2:
    raise SystemExit('AM214_STOP_AM212_FIELD_WRAPPER_COUNT_'+str(current.count('am212-work-procedure')))

pattern=re.compile(r'<label\s+class="am212-work-procedure"[^>]*>[\s\S]*?</label>',re.I)
blocks=list(pattern.finditer(current))
if len(blocks)!=2:
    raise SystemExit('AM214_STOP_WORK_PROCEDURE_BLOCK_COUNT_'+str(len(blocks)))

def new_block(old):
    if '$r->work_procedure' in old:
        value="{{ old('work_procedure',$r->work_procedure??'') }}"
    else:
        value="{{ old('work_procedure','') }}"
    return r'''<label class="am212-work-procedure am214-rot-components" style="display:block;grid-column:1/-1;margin-top:2px">
      <span style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:3px">
        <strong style="font-weight:800;color:#173b63">Parts / Components to Remove &amp; Refit</strong>
        <span style="font-size:9px;font-weight:800;letter-spacing:.35px;text-transform:uppercase;color:#40698f;background:#edf5fc;border:1px solid #cddfed;border-radius:999px;padding:2px 7px">Internal · Optional</span>
      </span>
      <small style="display:block;color:#587187;line-height:1.35;margin:0 0 6px">
        Mention only the parts/components that must be removed, disconnected and refitted to perform this ROT.
      </small>
      <textarea name="work_procedure" rows="4" maxlength="12000"
        style="display:block;width:100%;min-height:88px;padding:9px 11px;box-sizing:border-box;resize:vertical;border:1px solid #b8cbdd;border-radius:7px;background:#fff;font:inherit;line-height:1.5;white-space:pre-wrap"
        placeholder="Example: Both rear wheels, rear brake drums, handbrake cables, brake hose/pipe brackets, ABS sensor wiring, shock lower bolts, coil springs, torsion beam mounting bolts.">''' + value + r'''</textarea>
      <small style="display:flex;align-items:center;gap:5px;color:#6a7f92;line-height:1.35;margin-top:4px">
        <strong style="color:#4d6880">Workshop reference only.</strong>
        <span>Not printed on Estimate / Invoice.</span>
      </small>
    </label>'''

candidate=current
for m in reversed(blocks):
    candidate=candidate[:m.start()]+new_block(m.group())+candidate[m.end():]

candidate='{{-- '+marker+': work_procedure data preserved; UI renamed to removal/refit components. --}}\n'+candidate

for token in [
    'Parts / Components to Remove &amp; Refit',
    'Mention only the parts/components that must be removed, disconnected and refitted',
    'Not printed on Estimate / Invoice.',
    'name="work_procedure"',
    "route('erp.masters.labour.create')",
    "route('erp.masters.labour.update',$r->id)",
    '@csrf'
]:
    if token not in candidate:
        raise SystemExit('AM214_STOP_OUTPUT_MISSING_'+token[:40])
if candidate.count('name="work_procedure"')!=2:
    raise SystemExit('AM214_STOP_OUTPUT_FIELD_COUNT')
if candidate.count(marker)!=1:
    raise SystemExit('AM214_STOP_MARKER_COUNT')

# Compile candidate with Laravel's Blade compiler before touching staging.
with tempfile.TemporaryDirectory(prefix='am214_check_') as work:
    d=Path(work)
    src=d/'labour.blade.php'; src.write_text(candidate,encoding='utf8',errors='surrogateescape')
    compiled=d/'compiled.php'
    compiler=d/'compile.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    r=subprocess.run(['php',str(compiler),str(root),str(src),str(compiled)],
        cwd=root,capture_output=True,text=True,timeout=35)
    if r.returncode or not compiled.is_file():
        raise SystemExit('AM214_STOP_BLADE_COMPILE '+r.stderr[-450:])
    r=subprocess.run(['php','-l',str(compiled)],cwd=root,capture_output=True,text=True,timeout=25)
    if r.returncode:
        raise SystemExit('AM214_STOP_COMPILED_BLADE_LINT '+(r.stdout+r.stderr)[-450:])

print('AM214_ROT_COMPONENTS_UI_PREFLIGHT_PASS')
print('AM214_EXISTING_WORK_PROCEDURE_VALUES_PRESERVED')
print('AM214_NO_DB_JC_ESTIMATE_INVOICE_ANDROID_CHANGE')
if mode=='check':
    print('AM214_CHECK_PASS_NO_WRITES')
    sys.exit(0)

if view_bak.exists() or manifest.exists():
    raise SystemExit('AM214_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True)
os.chmod(backup,0o700)
shutil.copy2(view,view_bak)
os.chmod(view_bak,0o600)
try:
    atomic(view,candidate)
    manifest.write_text(sha(candidate)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if r.returncode: raise RuntimeError('BLADE_CACHE '+r.stderr[-400:])
except BaseException as error:
    atomic(view,current)
    if manifest.exists(): manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=45)
    raise SystemExit('AM214_SAFE_ROLLBACK '+str(error))

print('AM214_ROT_COMPONENTS_REMOVE_REFIT_UI_APPLIED')
print('AM214_BROWSER_CREATE_EDIT_VISUAL_CHECK_REQUIRED')
