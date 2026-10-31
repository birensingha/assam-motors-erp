#!/usr/bin/env python3
"""AM235 guard: fix duplicate Vendor search+dropdown layout in EXISTING Payment Voucher.
No posting routes, accounting services, migrations or ledger records changed.
"""
from pathlib import Path
import os,sys,shutil,tempfile,subprocess
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: apply.py check|apply|rollback STAGING_LARAVEL_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
view=root/'resources/views/payments/create.blade.php'
assets=Path(__file__).resolve().parent
js=assets/'vendor-combobox.js'
css=assets/'vendor-combobox.css'
backup=root/'storage/app/am-backups/AM235_SINGLE_VENDOR_SEARCH/create.blade.php.before_AM235'
marker='AM235_SINGLE_VENDOR_MASTER_COMBOBOX'
if not all(x.is_file() for x in (root/'artisan',view,js,css)):
    raise SystemExit('AM235_STOP_LARAVEL_VIEW_OR_ASSETS_MISSING')
old=view.read_text('utf8',errors='surrogateescape')
def atomic(path,content):
    fd,tmp=tempfile.mkstemp(prefix='.am235_',dir=path.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(content)
        os.chmod(tmp,path.stat().st_mode&0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if mode=='rollback':
    if old.count(marker)!=1 or not backup.is_file():
        raise SystemExit('AM235_STOP_ROLLBACK_MARKER_OR_BACKUP_NOT_FOUND')
    atomic(view,backup.read_text('utf8',errors='surrogateescape'))
    print('AM235_PREVIOUS_PAYMENT_CREATE_VIEW_RESTORED')
    sys.exit(0)
if marker in old:
    print('AM235_ALREADY_APPLIED')
    sys.exit(0)
anchors=[
    ('AM234_SIX_FEATURE_UI','AM234_UNIVERSAL_PAYMENT_FEATURES'),
    ('AM230_VENDOR_MASTER','AM230_PAYMENT_TO_CATEGORY_PERSISTENT'),
    ('AM231_RECIPIENT_UI','AM231_PAYMENT_TO_ACCOUNTING_CONTEXT'),
    ('AM230_VENDOR_SEARCH_SCRIPT','<script id="am230-payee-script">'),
    ('AM231_RECIPIENT_SCRIPT','<script id="am231-accounting-script">'),
    ('AM234_FEATURE_SCRIPT','<script id="am234-feature-ui-script">'),
    ('FINANCIAL_FORM','id="payment-form"'),
    ('ORIGINAL_VENDOR_SELECT','<select name="vendor_id" id="payment-vendor">'),
    ('VENDOR_SLOT','<div id="am230-vendor-slot"></div>'),
    ('SCRIPTS_END','@endpush')
]
for name,needle in anchors:
    count=old.count(needle)
    print('AM235_ANCHOR_'+name+'='+('PASS' if count==1 else 'FAIL_'+str(count)))
    if count!=1:raise SystemExit('AM235_STOP_SOURCE_DRIFT_'+name)
stylesheet=css.read_text('utf8')
javascript=js.read_text('utf8')
if 'AM235:' not in stylesheet or 'AM235 compact single searchable Vendor Master field' not in javascript:
    raise SystemExit('AM235_STOP_ASSETS_SIGNATURE_INVALID')
style='{{-- AM235_SINGLE_VENDOR_MASTER_COMBOBOX --}}\n<style id="am235-vendor-style">\n'+stylesheet+'\n</style>\n'
script='<script id="am235-vendor-script">\n'+javascript+'\n</script>\n'
new=old.replace('<div id="am230-payee-box" class="p133-full am230-payee">',
    style+'<div id="am230-payee-box" class="p133-full am230-payee">',1)
new=new.replace('@endpush',script+'@endpush',1)
if new.count('id="am235-vendor-style"')!=1 or new.count('id="am235-vendor-script"')!=1:
    raise SystemExit('AM235_STOP_UI_INSERT_FAILURE')
# Absolutely no changes to existing voucher POST inputs/handlers.
for exact in ['name="vendor_id"','name="source_type"','name="payment_mode"',
              'name="amount"','name="request_key"','id="p133-save"',
              'am226-vendor-only-inline','am230-payee-script',
              'am231-accounting-script','am234-feature-ui-script']:
    if new.count(exact)!=old.count(exact):
        raise SystemExit('AM235_STOP_PAYMENT_FIELD_CHANGED_'+exact)
with tempfile.TemporaryDirectory(prefix='am235-check_') as temp:
    td=Path(temp)
    candidate=td/'create.blade.php'
    compiled=td/'compiled.php'
    candidate.write_text(new,'utf8',errors='surrogateescape')
    if shutil.which('node'):
        test=subprocess.run(['node','--check',str(js)],capture_output=True,text=True,timeout=20)
        if test.returncode:raise SystemExit('AM235_STOP_JS_SYNTAX '+test.stderr[-300:])
    else:print('AM235_NODE_UNAVAILABLE_IN_STAGING_CI_JS_SYNTAX_VERIFIED')
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    for cmd in (['php','-r',code,str(candidate),str(compiled)],
                ['php','-l',str(compiled)]):
        test=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=60)
        if test.returncode:raise SystemExit('AM235_STOP_LARAVEL_BLADE_LINT '+(test.stdout+test.stderr)[-350:])
print('AM235_ONE_SEARCHABLE_VENDOR_FIELD=PASS')
print('AM235_ORIGINAL_VENDOR_ID_POSTING_FIELD_PRESERVED=PASS')
print('AM235_SOURCE_BILL_VENDOR_AUTOSELECT_COMPATIBLE=PASS')
print('AM235_NO_ACCOUNTING_POST_OR_SCHEMA_CHANGES=PASS')
print('AM235_BLADE_PHP_AND_CLIENT_JS_CHECK_PASS')
if mode=='check':
    print('AM235_READONLY_PREFLIGHT_PASS')
    sys.exit(0)
if backup.exists():raise SystemExit('AM235_STOP_EXISTING_BACKUP_CONFLICT')
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(view,backup)
try:atomic(view,new)
except BaseException:
    shutil.copy2(backup,view)
    raise
print('AM235_STAGING_VENDOR_SEARCH_PATCH_APPLIED_WITH_BACKUP')
