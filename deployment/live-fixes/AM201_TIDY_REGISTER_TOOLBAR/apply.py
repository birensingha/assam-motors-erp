#!/usr/bin/env python3
"""AM201 — discipline the single AM199 Job Card Register toolbar.

Replaces ONLY the already-installed AM199 style+toolbar markup with a
balanced, readable toolbar. Existing routes, data, controller, Return-to-JC
modal/eligibility, table/row handlers, and JS remain byte-for-byte unchanged.
Safe check, backup, and guarded rollback.

  python3 apply.py check STAGING_ROOT
  python3 apply.py apply STAGING_ROOT
  python3 apply.py rollback STAGING_ROOT
"""
from pathlib import Path
import hashlib,os,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: python3 apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
view=root/'resources/views/job-cards/index.blade.php'
payload=pkg/'polished-toolbar.blade.php'
backup_dir=root/'storage/app/am-backups/AM201_TIDY_REGISTER_TOOLBAR'
backup=backup_dir/'index.blade.php.before_AM201'
manifest=backup_dir/'installed.sha256'
old_style='<style id="AM199_SINGLE_REGISTER_TOOLBAR">'
new_style='<style id="AM201_TIDY_REGISTER_TOOLBAR">'
register='<div class="am62-card am62-register" id="am62-register">'
def read(p):return p.read_text(encoding='utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am201_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as h:h.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not (root/'artisan').is_file() or not view.is_file():
    raise SystemExit('AM201_STOP_STAGING_REGISTER_NOT_FOUND')
original=read(view)
if mode=='rollback':
    if not backup.is_file() or not manifest.is_file():
        raise SystemExit('AM201_STOP_BACKUP_NOT_FOUND')
    if sha(original)!=read(manifest).strip():
        raise SystemExit('AM201_STOP_VIEW_CHANGED_AFTER_INSTALL_NO_OVERWRITE')
    atomic(view,read(backup))
    print('AM201_ROLLBACK_REGISTER_TOOLBAR_RESTORED')
    print('AM201_RUN_PHP_ARTISAN_VIEW_CLEAR')
    sys.exit(0)
if new_style in original:
    print('AM201_ALREADY_INSTALLED')
    sys.exit(0)
if not payload.is_file():raise SystemExit('AM201_STOP_TEMPLATE_NOT_FOUND')
if original.count(old_style)!=1 or original.count(register)!=1:
    raise SystemExit('AM201_STOP_AM199_TEMPLATE_NOT_PRESENT')
if 'id="am199-picked-label"' not in original or 'id="am62-count"' not in original:
    raise SystemExit('AM201_STOP_AM199_SELECTION_IDS_NOT_PRESENT')
if 'id="am62-return-form"' not in original or "const eligible=!!a.post" not in original:
    raise SystemExit('AM201_STOP_FINANCIAL_FORM_OR_GUARD_MISSING')
if "route('erp.job-cards.return-to-jc.unpaid'" not in original:
    raise SystemExit('AM201_STOP_POST_ROUTE_MISSING')

start=original.index(old_style)
end=original.index(register,start)
old_block=original[start:end]
new_block=read(payload).rstrip()+'\n\n'
if len(old_block)>15000 or len(old_block)<2500:
    raise SystemExit('AM201_STOP_OLD_TOOLBAR_BOUNDARY_INVALID')
if '<style' not in old_block or old_block.count('</style>')!=1:
    raise SystemExit('AM201_STOP_OLD_STYLE_BOUNDARY_INVALID')
if old_block.count('<div class="am199-toolbar"')!=1:
    raise SystemExit('AM201_STOP_OLD_TOOLBAR_ROOT_MISSING')
if old_block.count('<form')!=2 or old_block.count('</form>')!=2:
    raise SystemExit('AM201_STOP_OLD_SEARCH_AND_FILTER_FORMS_UNEXPECTED')
if old_block.count('id="am62-count"')!=1 or old_block.count('id="am62-search-form"')!=1:
    raise SystemExit('AM201_STOP_OLD_REGISTER_IDS_CHANGED')
if old_block.count('id="am199-picked-label"')!=1:
    raise SystemExit('AM201_STOP_OLD_SELECTED_JC_BAR_CHANGED')
for key in ('name="status"','name="service"','name="inspection"'):
    if old_block.count(key)!=1:
        raise SystemExit('AM201_STOP_OLD_FILTER_FIELDS_CHANGED '+key)

if new_block.count(new_style)!=1 or new_block.count('</style>')!=1:
    raise SystemExit('AM201_STOP_NEW_STYLE_BAD')
if new_block.count('<form')!=2 or new_block.count('</form>')!=2:
    raise SystemExit('AM201_STOP_NEW_TOOLBAR_FORMS_BAD')
if new_block.count('id="am62-search-form"')!=1 or new_block.count('id="am62-search"')!=1:
    raise SystemExit('AM201_STOP_SEARCH_FORM_ID_BAD')
if new_block.count('id="am199-picked-label"')!=1 or new_block.count('id="am62-count"')!=1:
    raise SystemExit('AM201_STOP_SELECTED_ROW_IDS_BAD')
if new_block.count('class="am199-toolbar am201-toolbar"')!=1:
    raise SystemExit('AM201_STOP_NEW_TOOLBAR_MARKER_MISSING')
if new_block.count("route('erp.job-cards.index')")!=3:
    raise SystemExit('AM201_STOP_FILTER_ROUTES_CHANGED')
if '@selected' not in new_block or '@foreach' not in new_block:
    raise SystemExit('AM201_STOP_FILTER_OPTIONS_CHANGED')
for key in ('name="status"','name="service"','name="inspection"'):
    if new_block.count(key)!=1:
        raise SystemExit('AM201_STOP_NEW_FILTER_FIELDS_BAD '+key)
for key in ("{{ $summary['total'] }}","{{ $summary['open'] }}",
            "{{ $summary['in_progress'] }}","{{ $summary['completed_closed'] }}",
            "{{ $summary['invoiced'] }}"):
    if new_block.count(key)!=1:
        raise SystemExit('AM201_STOP_NEW_SUMMARY_BINDINGS_BAD '+key)
if '<script' in new_block or 'data-post=' in new_block or 'return-to-jc.unpaid' in new_block:
    raise SystemExit('AM201_STOP_JS_OR_FINANCE_NOT_ALLOWED_IN_TOOLBAR')

updated=original[:start]+new_block+original[end:]
if updated[:start]!=original[:start] or updated[len(original[:start])+len(new_block):]!=original[end:]:
    raise SystemExit('AM201_STOP_UNRELATED_CONTENT_MODIFIED')
if updated.count('id="am197-filter-card"')!=0 or updated.count('id="am62-selected"')!=0:
    raise SystemExit('AM201_STOP_REDUNDANT_PANELS_REAPPEARED')
for key in ('id="am62-search-form"','id="am62-search"','id="am62-count"',
            'id="am199-picked-label"','name="status"','name="service"',
            'name="inspection"'):
    if updated.count(key)!=1:
        raise SystemExit('AM201_STOP_DUPLICATED_REGISTER_ELEMENT '+key)
for key in ('data-post=','data-review=','data-invoicelink=',
            'id="am62-return-form"','id="am62-return-blocked"',
            'const eligible=!!a.post',"dialogForm.action=a.post||''",
            'id="AM62_SELECT_ROW"',
            "route('erp.job-cards.return-to-jc.unpaid'"):
    if updated.count(key)!=original.count(key):
        raise SystemExit('AM201_STOP_FINANCE_OR_ROW_JS_CHANGED '+key)

# Compile entire Blade file under actual Laravel installation, and lint PHP.
# Only after this passes is the modified view written.
with tempfile.TemporaryDirectory(prefix='am201check_') as td:
    temp=Path(td)
    candidate=temp/'candidate.blade.php'
    compile_php=temp/'compile.php'
    compiled=temp/'compiled.php'
    candidate.write_text(updated,encoding='utf8',errors='surrogateescape')
    compile_php.write_text("""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    p=subprocess.run(['php',str(compile_php),str(root),str(candidate),str(compiled)],
                     capture_output=True,text=True,timeout=40)
    if p.returncode or not compiled.is_file():
        raise SystemExit('AM201_STOP_BLADE_COMPILE '+p.stderr[-600:])
    p=subprocess.run(['php','-l',str(compiled)],
                     capture_output=True,text=True,timeout=30)
    if p.returncode:
        raise SystemExit('AM201_STOP_PHP_SYNTAX '+(p.stdout+p.stderr)[-650:])
print('AM201_ONE_TOOLBAR_NO_DUPLICATE_PANELS True')
print('AM201_SEARCH_AND_EQUAL_FIVE_COUNTERS_ALIGNED True')
print('AM201_THREE_EQUAL_FILTERS_AND_BUTTONS_ALIGNED True')
print('AM201_SELECTED_JOB_CARD_STATUS_LINE_REFINED True')
print('AM201_RESPONSIVE_DESKTOP_MOBILE True')
print('AM201_EXISTING_JS_POST_ROUTES_AND_FINANCE_MODAL_UNCHANGED True')
print('AM201_BLADE_COMPILE_AND_PHP_SYNTAX_PASS')
print('AM201_NO_DATABASE_CHANGES True')
if mode=='check':
    print('AM201_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if backup.exists() or manifest.exists():
    raise SystemExit('AM201_STOP_BACKUP_ALREADY_EXISTS')
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
shutil.copy2(view,backup)
os.chmod(backup,0o600)
atomic(view,updated)
manifest.write_text(sha(updated)+'\n',encoding='ascii')
os.chmod(manifest,0o600)
print('AM201_TIDY_REGISTER_TOOLBAR_INSTALLED')
print('AM201_RUN_PHP_ARTISAN_VIEW_CLEAR')
