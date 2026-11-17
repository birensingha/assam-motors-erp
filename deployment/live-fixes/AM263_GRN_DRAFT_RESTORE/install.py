#!/usr/bin/env python3
"""AM263 - STAGING GRN 'Restore Draft' hotfix.

Only:
  public/js/am254-global-form-autosave.js (GRN-specific helpers only)
  resources/views/procurement/receive.blade.php
  app/Http/Controllers/Web/AdminProcurementGrnController.php

NEVER deletes browser localStorage or drafts. Does not touch GRN/stock data.
Usage: install.py check|apply|rollback STAGING_ROOT
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM263: install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM263_NOT_STAGING')
pkg=Path(__file__).resolve().parent
auto='public/js/am254-global-form-autosave.js'
controller='app/Http/Controllers/Web/AdminProcurementGrnController.php'
view='resources/views/procurement/receive.blade.php'
paths=[auto,controller,view]
base=root/'storage/app/am-backups'
pointer=base/'AM263_GRN_DRAFT_LAST_CODE_BACKUP.txt'
sha_expected={auto:'db4f7ed0b49aa939e587a8644de98b45a22e83cf5c58db6ba4d2be3784e47fd9'}
blob_expected={
    controller:'ed0640002d9d74b014e1e189af14b3081bd28b69',
    view:'69562d2b56f517f52d3df8c831e0caac2506d4e4',
}
def sha(data):return hashlib.sha256(data).hexdigest()
def blob(data):return hashlib.sha1(b'blob '+str(len(data)).encode()+b'\x00'+data).hexdigest()
def run(cmd,timeout=110):return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(t,old,new,key):
    n=t.count(old)
    if n!=1:raise SystemExit('STOP_AM263_PATCH_ANCHOR_'+key+'_COUNT_'+str(n))
    return t.replace(old,new,1)
def atomic(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am263_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(content,label):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(content);p=Path(f.name)
    try:
        o=run(['php','-l',str(p)],48)
        if o.returncode:raise SystemExit('STOP_AM263_PHP_LINT_'+label+': '+(o.stdout+o.stderr)[-650:])
    finally:p.unlink(missing_ok=True)
def blade(content,label):
    with tempfile.TemporaryDirectory(prefix='am263blade_') as td:
        src=Path(td)/'index.blade.php';dst=Path(td)/'compiled.php';src.write_bytes(content)
        snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        o=run(['php','-r',snippet,str(src),str(dst)],65)
        if o.returncode or not dst.is_file():
            raise SystemExit('STOP_AM263_BLADE_COMPILE_'+label+': '+(o.stdout+o.stderr)[-650:])
        lint(dst.read_bytes(),'COMPILED_'+label)

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM263_CODE_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if base.resolve() not in backup.parents or not backup.name.startswith('AM263_DRAFT_CODE_'):
        raise SystemExit('STOP_AM263_BACKUP_PATH_UNSAFE')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,after in manifest['installed'].items():
        if not(root/path).is_file() or sha((root/path).read_bytes())!=after:
            raise SystemExit('STOP_AM263_SOURCE_CHANGED_AFTER_INSTALL_'+path)
    for path in paths:atomic(root/path,(backup/path).read_bytes())
    o=run(['php','artisan','optimize:clear'],105)
    if o.returncode:raise SystemExit('AM263_CODE_ROLLBACK_CACHE_FAILURE')
    print('AM263_RESTORED_CODE_NO_BROWSER_DRAFT_OR_STOCK_CHANGED')
    sys.exit(0)

for path in paths:
    if not (root/path).is_file():
        raise SystemExit('STOP_AM263_EXISTING_SOURCE_MISSING_'+path)
for path in [controller,view]:
    if not(pkg/'files'/path).is_file():
        raise SystemExit('STOP_AM263_PACKAGE_SOURCE_MISSING_'+path)
helper=(pkg/'grn_draft_js_helpers.js').read_text()
if 'AM263_GRN_DRAFT_RESTORE' not in helper:
    raise SystemExit('STOP_AM263_NO_HELPER_MARKER')
original={p:(root/p).read_bytes() for p in paths}
js=original[auto].decode('utf-8','strict')
js=once(js,'  function restore(form,draft) {',
  helper+'  function restore(form,draft) {\n    prepareGrnDraftRows(form,draft);','RESTORE_PREPARE')
js=once(js,'    return {missing:missing};\n  }\n  function bind(form) {',
  '    return {missing:missing + hydrateGrnPartSearches(form)};\n  }\n  function bind(form) {',
  'RESTORE_HYDRATE')
patched={
 auto:js.encode('utf-8'),
 controller:(pkg/'files'/controller).read_bytes(),
 view:(pkg/'files'/view).read_bytes()
}
if all(sha(original[p])==sha(patched[p]) for p in paths):
    print('AM263_ALREADY_INSTALLED_GRN_DRAFT_RESTORE')
    sys.exit(0)
if sha(original[auto])!=sha_expected[auto]:
    raise SystemExit('STOP_AM263_GLOBAL_AUTOSAVE_CHANGED_CONFLICT')
for path,expected in blob_expected.items():
    if blob(original[path])!=expected:
        raise SystemExit('STOP_AM263_DEPLOYED_GRN_SOURCE_CHANGED_CONFLICT_'+path)
if patched[auto].count(b'AM263_GRN_DRAFT_RESTORE')!=1:
    raise SystemExit('STOP_AM263_HELPER_PATCH_UNSAFE')
lint(patched[controller],'GRN_CONTROLLER')
blade(patched[view],'GRN_FORM')
# AM263 is intentionally JavaScript/browser-only and stock-neutral.
print('AM263_EXISTING_DRAFT_STORAGE_NOT_CLEARED')
print('AM263_GUARDED_CODE_AND_FORM_PREFLIGHT_PASS')
if mode=='check':
    print('AM263_STAGING_PREFLIGHT_NO_WRITES')
    sys.exit(0)

backup=base/('AM263_DRAFT_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in original.items():
    target=backup/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
written=[]
try:
    for path,data in patched.items():
        if (root/path).read_bytes()!=original[path]:
            raise RuntimeError('AM263_CONCURRENT_STAGING_EDIT_'+path)
        atomic(root/path,data);written.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        o=run(cmd,125)
        if o.returncode:raise RuntimeError('AM263_CACHE_BUILD_FAILED '+(o.stdout+o.stderr)[-750:])
except BaseException as e:
    for path in written:atomic(root/path,original[path])
    run(['php','artisan','optimize:clear'],100)
    raise SystemExit('STOP_AM263_INSTALL_ROLLED_BACK_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed':{p:sha(d) for p,d in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM263_GRN_DYNAMIC_ROW_DRAFT_RESTORE_STAGING_INSTALLED')
print('AM263_OLDER_HIDDEN_PART_IDS_REHYDRATE_VISIBLE_PART_NAMES')
print('AM263_NO_BROWSER_STORAGE_CLEARED_NO_TRANSACTIONS_POSTED')
print('AM263_BACKUP='+str(backup))
