#!/usr/bin/env python3
"""AM220 replace double-empty search with one live Parts + OSL vendor/bill selector.
Usage: install.py check|apply|rollback STAGING_ROOT
Only payments/create Blade asset reference + public JS. No DB writes.
"""
from pathlib import Path
import hashlib,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM220_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM220_STOP_NOT_STAGING')
view=root/'resources/views/payments/create.blade.php'
js=root/'public/js/am220-payment-single-vendor-search.js'
package=Path(__file__).resolve().parent
payload=package/'search.js'
backup=root/'storage/app/am-backups/AM220_PAYMENT_SINGLE_VENDOR_SEARCH'
before=backup/'create.blade.php.before'
afterhash=backup/'view.sha256'
expected_prefix='8c45cf73b994e08a'
old='<script src="{{ asset(\'js/am218-payment-source-search.js\') }}?v=218" defer></script>'
new='<script src="{{ asset(\'js/am220-payment-single-vendor-search.js\') }}?v=220" defer></script>'
def digest(v):return hashlib.sha256(v.encode('utf8','surrogateescape')).hexdigest()
def read(p):return p.read_text('utf8',errors='surrogateescape')
def atomic(p,v):
    fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.am220_')
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as out:out.write(v)
        os.chmod(tmp,p.stat().st_mode&0o777 if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not view.is_file() or view.is_symlink() or not payload.is_file():raise SystemExit('AM220_STOP_MISSING_INPUT')
source=read(view)
script=read(payload)
if mode=='rollback':
    if not before.is_file() or not afterhash.is_file() or not js.is_file():
        raise SystemExit('AM220_STOP_ROLLBACK_BACKUP_MISSING')
    if digest(source)!=afterhash.read_text().strip() or digest(read(js))!=digest(script):
        raise SystemExit('AM220_STOP_ROLLBACK_CONCURRENT_CHANGE')
    atomic(view,read(before))
    js.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
    print('AM220_ROLLBACK_PASS');sys.exit(0)
if new in source:
    if js.is_file() and digest(read(js))==digest(script):
        print('AM220_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM220_STOP_PARTIAL_INSTALL')
if not digest(source).startswith(expected_prefix):
    raise SystemExit('AM220_STOP_UNEXPECTED_STAGING_VIEW_SHA '+digest(source)[:16])
if source.count(old)!=1:raise SystemExit('AM220_STOP_OLD_JS_ANCHOR_COUNT_'+str(source.count(old)))
if 'id="am218-source-search"' not in source or 'id="source-key"' not in source:
    raise SystemExit('AM220_STOP_MISSING_SEARCH_OR_REFERENCE_ANCHOR')
if js.exists() or backup.exists():raise SystemExit('AM220_STOP_DEST_ALREADY_EXISTS')
if 'AM220_VENDOR_SOURCE_SINGLE_SEARCH' not in script:raise SystemExit('AM220_STOP_BAD_SCRIPT')
candidate=source.replace(old,new,1)
with tempfile.TemporaryDirectory(prefix='am220_qa_') as folder:
    folder=Path(folder)
    src=folder/'view.blade.php'; dst=folder/'compiled.php'; php=folder/'compile.php'
    src.write_text(candidate,encoding='utf8',errors='surrogateescape')
    php.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    ret=subprocess.run(['php',str(php),str(root),str(src),str(dst)],cwd=root,capture_output=True,text=True,timeout=45)
    if ret.returncode:raise SystemExit('AM220_STOP_BLADE_COMPILE '+ret.stderr[-320:])
    ret=subprocess.run(['php','-l',str(dst)],cwd=root,capture_output=True,text=True,timeout=30)
    if ret.returncode:raise SystemExit('AM220_STOP_PHP_LINT '+(ret.stderr+ret.stdout)[-320:])
    node=__import__('shutil').which('node')
    if node:
        ret=subprocess.run([node,'--check',str(payload)],cwd=root,capture_output=True,text=True,timeout=30)
        if ret.returncode:raise SystemExit('AM220_STOP_JS_LINT '+ret.stderr[-320:])
print('AM220_BLADE_JS_SYNTAX_PASS')
if mode=='check':print('AM220_CHECK_ONLY_PASS');sys.exit(0)
if read(view)!=source:raise SystemExit('AM220_STOP_SOURCE_RACE')
backup.mkdir(parents=True,exist_ok=False)
os.chmod(backup,0o700)
before.write_text(source,encoding='utf8',errors='surrogateescape')
os.chmod(before,0o600)
try:
    atomic(view,candidate)
    atomic(js,script)
    afterhash.write_text(digest(candidate),encoding='ascii')
    os.chmod(afterhash,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        ret=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if ret.returncode:raise RuntimeError('AM220_STOP_VIEW_CACHE '+ret.stderr[-250:])
except BaseException as exc:
    atomic(view,source)
    if js.is_file() and digest(read(js))==digest(script):js.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=30)
    raise SystemExit('AM220_ROLLBACK_ON_FAILURE '+str(exc))
print('AM220_SINGLE_SEARCH_STAGING_APPLIED_NO_FINANCIAL_DB_WRITES')
