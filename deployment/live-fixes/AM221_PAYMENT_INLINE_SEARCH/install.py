#!/usr/bin/env python3
"""AM221 inline the real AM220 search JS in the Laravel Payment Voucher view.
Eliminates 404 asset path dependency. check|apply|rollback STAGING_ROOT.
No data or accounting writes. Guarded backups and compiled Blade validation.
"""
from pathlib import Path
import hashlib,os,sys,tempfile,subprocess
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM221_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM221_STOP_NOT_STAGING')
view=root/'resources/views/payments/create.blade.php'
payload=Path(__file__).resolve().parent/'search.js'
backup=root/'storage/app/am-backups/AM221_PAYMENT_INLINE_SEARCH'
before=backup/'view.before'
hashfile=backup/'view.after.sha256'
needle='<script src="{{ asset(\'js/am220-payment-single-vendor-search.js\') }}?v=220" defer></script>'
marker='id="am221-payment-source-search-inline"'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
    fd,t=tempfile.mkstemp(dir=p.parent,prefix='.am221_')
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(t,p.stat().st_mode&0o777)
        os.replace(t,p)
    finally:
        if os.path.exists(t):os.unlink(t)
if not view.is_file() or not payload.is_file():raise SystemExit('AM221_MISSING_SOURCE')
orig=read(view);js=read(payload)
if 'AM220_VENDOR_SOURCE_SINGLE_SEARCH' not in js or '</script' in js.lower():
    raise SystemExit('AM221_STOP_UNSAFE_OR_WRONG_JS')
if mode=='rollback':
    if not before.is_file() or not hashfile.is_file() or sha(orig)!=hashfile.read_text().strip():
        raise SystemExit('AM221_STOP_ROLLBACK_SOURCE_CHANGED')
    atomic(view,read(before))
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=30)
    print('AM221_ROLLBACK_DONE');sys.exit(0)
if marker in orig:
    if orig.count(marker)==1 and needle not in orig:
        print('AM221_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM221_STOP_INCOMPLETE_INLINE')
if orig.count(needle)!=1 or 'AM218_PAYMENT_SIDENAV_OSL' not in orig:
    raise SystemExit('AM221_STOP_MISSING_EXPECTED_PAYMENT_SCRIPT')
inline='<script '+marker+'>\n'+js+'\n</script>'
candidate=orig.replace(needle,inline,1)
if candidate.count(marker)!=1 or needle in candidate:raise SystemExit('AM221_STOP_CANDIDATE')
with tempfile.TemporaryDirectory(prefix='am221_qa_') as tmp:
    temp=Path(tmp);p=temp/'view.blade.php';out=temp/'compiled.php';helper=temp/'helper.php'
    p.write_text(candidate,encoding='utf8',errors='surrogateescape')
    helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    r=subprocess.run(['php',str(helper),str(root),str(p),str(out)],cwd=root,capture_output=True,text=True,timeout=50)
    if r.returncode:raise SystemExit('AM221_BLADE_COMPILE_FAILED '+r.stderr[-400:])
    r=subprocess.run(['php','-l',str(out)],cwd=root,capture_output=True,text=True,timeout=30)
    if r.returncode:raise SystemExit('AM221_COMPILED_PHP_FAILED '+(r.stderr+r.stdout)[-400:])
    if 'AM220_VENDOR_SOURCE_SINGLE_SEARCH' not in out.read_text('utf8','replace'):
        raise SystemExit('AM221_COMPILED_VIEW_MISSING_INLINE_JS')
    node=__import__('shutil').which('node')
    if node:
        r=subprocess.run([node,'--check',str(payload)],cwd=root,capture_output=True,text=True,timeout=30)
        if r.returncode:raise SystemExit('AM221_JS_SYNTAX_FAILED '+r.stderr[-350:])
print('AM221_INLINE_JS_BLADE_PHP_PREFLIGHT_PASS')
if mode=='check':print('AM221_CHECK_ONLY_PASS');sys.exit(0)
if backup.exists():raise SystemExit('AM221_BACKUP_COLLISION')
if read(view)!=orig:raise SystemExit('AM221_CONCURRENT_VIEW_CHANGE')
backup.mkdir(mode=0o700,parents=True,exist_ok=False)
before.write_text(orig,encoding='utf8',errors='surrogateescape');os.chmod(before,0o600)
try:
    atomic(view,candidate)
    hashfile.write_text(sha(candidate),encoding='ascii');os.chmod(hashfile,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if r.returncode:raise RuntimeError('AM221_VIEW_CACHE_FAILED '+r.stderr[-400:])
except BaseException as e:
    atomic(view,orig)
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=30)
    raise SystemExit('AM221_APPLY_ROLLED_BACK '+str(e))
print('AM221_STAGING_INLINE_SCRIPT_APPLIED_NO_FINANCIAL_WRITES')
