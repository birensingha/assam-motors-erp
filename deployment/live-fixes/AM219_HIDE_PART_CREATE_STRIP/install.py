#!/usr/bin/env python3
"""AM219: Hide the redundant collapsed '+ Add New Part / x Close' strip.
Parts Master already has a working '+ Add Part' action in its main header.
The original <details id=partCreatePanel> and AM214 modal implementation are
preserved byte-for-byte except one surgical CSS rule before that markup.
check|apply|rollback STAGING_ROOT; source-only, no DB writes.
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM219_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM219_STOP_NOT_STAGING')
part=root/'resources/views/admin/native/parts.blade.php'
backup_dir=root/'storage/app/am-backups/AM219_HIDE_PART_CREATE_STRIP'
backup=backup_dir/'parts.blade.php.before_AM219'
manifest=backup_dir/'installed-sha256.txt'
marker='AM219_HIDE_PART_CREATE_STRIP'
anchor='<details id="partCreatePanel" class="card pro-create-panel">'
prefix=(
    '{{-- AM219_HIDE_PART_CREATE_STRIP: only main-header Add Part launches popup. --}}\n'
    '<style>\n'
    '  #partCreatePanel:not([open]) { display: none !important; }\n'
    '</style>\n'
)
def read(p):return p.read_text('utf8',errors='surrogateescape')
def digest(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am219_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as out:out.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

if not part.is_file():raise SystemExit('AM219_STOP_PARTS_VIEW_MISSING')
original=read(part)
if mode=='rollback':
    if not backup.is_file() or not manifest.is_file():
        raise SystemExit('AM219_STOP_ROLLBACK_BACKUP_MISSING')
    if read(manifest).strip()!=digest(original):
        raise SystemExit('AM219_STOP_ROLLBACK_CURRENT_FILE_MODIFIED')
    atomic(part,read(backup))
    print('AM219_ROLLBACK_RESTORED_PARTS_VIEW')
    print('AM219_RUN_VIEW_CLEAR')
    sys.exit(0)
if marker in original:
    if prefix in original:
        print('AM219_ALREADY_INSTALLED')
        sys.exit(0)
    raise SystemExit('AM219_STOP_EXISTING_MARKER_MISMATCH')
if original.count(anchor)!=1:
    raise SystemExit('AM219_STOP_PART_CREATE_PANEL_ANCHOR_'+str(original.count(anchor)))
required=[
  '<h1>Parts Master</h1>',
  '''onclick="document.getElementById('partCreatePanel').open=true"''',
  '''route('erp.masters.parts.create')''',
  'AM214_NATIVE_MASTER_MODAL_SELECT',
  'am214-modal-open',
]
for tok in required:
    if tok not in original:
        raise SystemExit('AM219_STOP_ADD_PART_POPUP_REQUIRED_ANCHOR_'+tok[:55])
if re.search(r'<details\b[^>]*\bid="partCreatePanel"[^>]*\bopen(?:\s|>|=)',original):
    raise SystemExit('AM219_STOP_CREATE_PANEL_DEFAULT_OPEN')
modified=original.replace(anchor,prefix+anchor,1)
# Safe invariant: no changes to actual Create/Edit forms, update action,
# modal event listeners, search or price/stock fields.
if modified.replace(prefix,'',1)!=original:
    raise SystemExit('AM219_STOP_NON_CSS_CHANGE')
with tempfile.TemporaryDirectory(prefix='am219check_') as folder:
    temp=Path(folder)
    source=temp/'parts.blade.php'
    source.write_text(modified,encoding='utf8',errors='surrogateescape')
    compiler=temp/'compile.php';compiled=temp/'parts.compiled.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    r=subprocess.run(['php',str(compiler),str(root),str(source),str(compiled)],
                     cwd=root,text=True,capture_output=True,timeout=40)
    if r.returncode or not compiled.exists():
        raise SystemExit('AM219_STOP_BLADE_COMPILE '+r.stderr[-300:])
    r=subprocess.run(['php','-l',str(compiled)],cwd=root,text=True,capture_output=True,timeout=25)
    if r.returncode:
        raise SystemExit('AM219_STOP_COMPILED_PHP_LINT '+(r.stdout+r.stderr)[-300:])
print('AM219_ONLY_REDUNDANT_COLLAPSED_PART_STRIP_HIDDEN')
print('AM219_MAIN_ADD_PART_BUTTON_MODAL_AND_SAVE_PRESERVED')
print('AM219_BLADE_COMPILE_PHP_LINT_PASS')
if mode=='check':
    print('AM219_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if backup.exists() or manifest.exists():
    raise SystemExit('AM219_STOP_BACKUP_COLLISION')
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
shutil.copy2(part,backup)
os.chmod(backup,0o600)
try:
    atomic(part,modified)
    manifest.write_text(digest(modified)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
        if r.returncode:raise RuntimeError('VIEW_CACHE '+r.stderr[-250:])
except BaseException as error:
    atomic(part,original)
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
    raise SystemExit('AM219_APPLY_FAILED_SOURCE_RESTORED '+str(error))
print('AM219_PARTS_MASTER_REDUNDANT_STRIP_HIDDEN_STAGING')
print('AM219_BROWSER_OPEN_AND_CLOSE_CONFIRMATION_REQUIRED')
