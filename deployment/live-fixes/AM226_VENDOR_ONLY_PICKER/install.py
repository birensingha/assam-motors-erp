#!/usr/bin/env python3
"""AM226 vendor-only Payment Voucher search, guarded STAGING source patch.
Usage: install.py check|apply|rollback STAGING_ROOT.
Only changes resources/views/payments/create.blade.php; does not alter any finance DB rows.
"""
from pathlib import Path
import sys,os,hashlib,subprocess,shutil,tempfile,json
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM226_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM226_STOP_NOT_STAGING')
view=root/'resources/views/payments/create.blade.php'
patch=Path(__file__).resolve().parent
js=patch/'vendor-only.js'
tests=patch/'test-vendor.js'
backup=root/'storage/app/am-backups/AM226_VENDOR_ONLY_PICKER'
backup_file=backup/'create.blade.php.before'
manifest=backup/'checksums.json'
marker='AM226_VENDOR_ONLY_PICKER'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,data):
    fd,name=tempfile.mkstemp(prefix='.am226_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(data)
        os.chmod(name,p.stat().st_mode&0o777)
        os.replace(name,p)
    finally:
        if os.path.exists(name):os.unlink(name)
if not view.is_file() or view.is_symlink() or not js.is_file() or not tests.is_file():
    raise SystemExit('AM226_STOP_MISSING_VIEW_OR_SCRIPT')
original=read(view)
if mode=='rollback':
    if not manifest.is_file() or not backup_file.is_file():raise SystemExit('AM226_STOP_BACKUP_MISSING')
    hashes=json.loads(read(manifest))
    if sha(original)!=hashes['after']:raise SystemExit('AM226_STOP_CONCURRENT_VIEW_MODIFICATION')
    atomic(view,read(backup_file))
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    print('AM226_ROLLBACK_RESTORED_PREVIOUS_VIEW')
    sys.exit(0)
if marker in original:
    if 'am226-vendor-only-inline' in original and original.count(marker)==1:
        print('AM226_ALREADY_INSTALLED')
        sys.exit(0)
    raise SystemExit('AM226_STOP_PARTIAL_INSTALL')
if 'am225-payment-amount-first-inline' not in original:
    raise SystemExit('AM226_STOP_AM225_MISSING')
source_script=read(js)
if marker not in source_script or '</script' in source_script.lower():
    raise SystemExit('AM226_STOP_UNSAFE_JAVASCRIPT')
def replace(old,new,name):
    global candidate
    count=candidate.count(old)
    if count!=1:raise SystemExit('AM226_STOP_ANCHOR_'+name+'_COUNT_'+str(count))
    candidate=candidate.replace(old,new,1)
candidate=original
replace('Search Vendor / Purchase Bill — Parts + OSL','SELECT VENDOR NAME','LABEL')
replace('Search outstanding Parts and OSL bills together. Select one to fill the correct reference and vendor.',
        'Type and select a vendor name. Its unpaid Parts and OSL bills appear in the table below.',
        'HELP')
replace('placeholder="Type vendor name, OSL / purchase number, JC or vehicle"',
        'placeholder="Type and select a vendor name"',
        'PLACEHOLDER')
replace('<label class="p133-label">Select Vendor *<select id="p133-multi-vendor"><option value="">— Select Vendor —</option></select></label>',
        '<div id="am226-vendor-master-select" hidden aria-hidden="true"><select id="p133-multi-vendor" tabindex="-1"><option value="">— Select Vendor —</option></select></div>',
        'DUPLICATE_SELECT_VENDOR')
replace('Choose 2 or more outstanding bills from the SAME vendor. Set the amount for each bill. Server verifies balances again before posting.',
        'Bills below belong to the selected vendor. Enter Payment Amount first, then auto-adjust or change each bill allocation.',
        'MULTI_INSTRUCTION')
start=candidate.index('<script id="am225-payment-amount-first-inline">')
end=candidate.find('</script>',start)
if end<start:raise SystemExit('AM226_STOP_OLD_INLINE_SCRIPT_UNCLOSED')
candidate=candidate[:start]+'<script id="am226-vendor-only-inline">\n'+source_script+'\n</script>'+candidate[end+len('</script>'):]
if candidate.count(marker)!=1 or candidate.count('am226-vendor-only-inline')!=1:
    raise SystemExit('AM226_STOP_OUTPUT_INVARIANT')
with tempfile.TemporaryDirectory(prefix='am226_qa_') as directory:
    folder=Path(directory)
    tmp_view=folder/'create.blade.php'
    php_compiled=folder/'compiled.php'
    compile_script=folder/'compile.php'
    tmp_view.write_text(candidate,encoding='utf8',errors='surrogateescape')
    compile_script.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    result=subprocess.run(['php',str(compile_script),str(root),str(tmp_view),str(php_compiled)],cwd=root,capture_output=True,text=True,timeout=60)
    if result.returncode:raise SystemExit('AM226_STOP_BLADE_COMPILE '+result.stderr[-420:])
    result=subprocess.run(['php','-l',str(php_compiled)],cwd=root,capture_output=True,text=True,timeout=35)
    if result.returncode:raise SystemExit('AM226_STOP_PHP_LINT '+(result.stdout+result.stderr)[-420:])
    if marker not in read(php_compiled):raise SystemExit('AM226_STOP_RENDERED_SCRIPT_MISSING')
    node=shutil.which('node')
    if node:
        for cmd in ([node,'--check',str(js)],[node,str(tests)]):
            result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=30)
            if result.returncode:raise SystemExit('AM226_STOP_NODE_TEST '+(result.stderr+result.stdout)[-550:])
        print('AM226_VENDOR_ONLY_SEARCH_TESTS_PASS')
print('AM226_BLADE_AND_JAVASCRIPT_PREFLIGHT_PASS')
if mode=='check':
    print('AM226_READ_ONLY_CHECK_PASS')
    sys.exit(0)
if backup.exists():raise SystemExit('AM226_STOP_EXISTING_BACKUP')
if read(view)!=original:raise SystemExit('AM226_STOP_SOURCE_RACE')
backup.mkdir(parents=True,exist_ok=False)
os.chmod(backup,0o700)
backup_file.write_text(original,encoding='utf8',errors='surrogateescape')
os.chmod(backup_file,0o600)
try:
    atomic(view,candidate)
    manifest.write_text(json.dumps({'before':sha(original),'after':sha(candidate)}),encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if result.returncode:raise RuntimeError('AM226_VIEW_CACHE '+result.stderr[-400:])
except BaseException as error:
    atomic(view,original)
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    raise SystemExit('AM226_FAILED_ROLLED_BACK '+str(error))
print('AM226_STAGING_VENDOR_ONLY_PICKER_APPLIED_NO_FINANCIAL_WRITES')
print('AM226_BROWSER_ACCEPTANCE_QA_PENDING')
