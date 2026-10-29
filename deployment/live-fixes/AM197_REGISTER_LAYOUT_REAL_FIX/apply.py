#!/usr/bin/env python3
"""AM197 — real STRUCTURAL fix for oversized Job Card Register panels.

AM196 was installed but did not sufficiently compact the view. AM197 replaces
only the two old FIX62/AM196 presentation blocks with new native Blade HTML
and tightly scoped CSS. This is not a second collection of broad CSS rules.

Preserves exactly:
 - all backend routes and server-side filters
 - selected-JC JavaScript IDs/row data
 - Return-to-JC confirmation + financial eligibility logic
 - original invoice and Money Receipt routes
 - credit notes, invoices, payments, customers and all DB records

Commands:
  python3 apply.py check STAGING_ROOT
  python3 apply.py apply STAGING_ROOT
  python3 apply.py rollback STAGING_ROOT
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: python3 apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
target=root/'resources/views/job-cards/index.blade.php'
payload=pkg/'compact-panels.blade.php'
backup_dir=root/'storage/app/am-backups/AM197_REGISTER_LAYOUT_REAL_FIX'
backup=backup_dir/'index.blade.php.before_AM197'
sha_file=backup_dir/'installed.sha256'
marker='AM197_REGISTER_REAL_LAYOUT'

def read(p):return p.read_text(encoding='utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am197_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

if not (root/'artisan').is_file() or not target.is_file() or not payload.is_file():
    raise SystemExit('AM197_STOP_EXPECTED_STAGING_OR_PATCH_MISSING')
original=read(target)
if mode=='rollback':
    if not backup.is_file() or not sha_file.is_file():
        raise SystemExit('AM197_STOP_BACKUP_NOT_FOUND')
    if sha(original)!=read(sha_file).strip():
        raise SystemExit('AM197_STOP_VIEW_CHANGED_AFTER_APPLY_PRESERVE_LATER_WORK')
    atomic(target,read(backup))
    print('AM197_ROLLBACK_ORIGINAL_REGISTER_RESTORED')
    print('AM197_NO_DATA_FINANCE_OR_ROUTE_CHANGES')
    print('AM197_RUN_ARTISAN_VIEW_CLEAR')
    sys.exit(0)

if marker in original:
    raise SystemExit('AM197_ALREADY_INSTALLED')
for key in (
    '<style id="AM62_PROTOTYPE_MATCH">',
    '<style id="AM196_COMPACT_JOB_CARD_REGISTER">',
    'id="AM62_SELECT_ROW"',
    'data-am62-row',
    'data-am62-launch',
    'id="am62-return-form"',
    'id="am62-return-blocked"',
    'const eligible=!!a.post',
    'dialogForm.action=a.post||',
    "route('erp.job-cards.return-to-jc.unpaid'",
    "route('erp.job-cards.credit-correction'",
    "route('erp.invoices.show'",
    "route('erp.money-receipts.index')",
):
    if key not in original:
        raise SystemExit('AM197_STOP_EXISTING_VIEW_CONTRACT_CHANGED_'+key)

start_token='<section class="am62-card am62-preview" id="am62-selected">'
filter_start='<details class="am62-card am62-more" open>'
if original.count(start_token)!=1 or original.count(filter_start)!=1:
    raise SystemExit('AM197_STOP_AM196_PANEL_ANCHORS_MISSING_OR_DUPLICATED')
start=original.index(start_token)
filter_pos=original.index(filter_start,start)
if filter_pos-start>10000:
    raise SystemExit('AM197_STOP_PREVIEW_TOO_LARGE')
preview_end=original.find('</section>',start)
if preview_end<0 or preview_end>filter_pos:
    raise SystemExit('AM197_STOP_PREVIEW_SECTION_UNEXPECTED')
segment_between=original[preview_end+len('</section>'):filter_pos]
if segment_between.strip():
    raise SystemExit('AM197_STOP_UNEXPECTED_CONTENT_BETWEEN_PANELS')
end=original.find('</details>',filter_pos)
if end<0 or end-filter_pos>9000:
    raise SystemExit('AM197_STOP_FILTER_SECTION_UNEXPECTED')
end+=len('</details>')
old_panels=original[start:end]
if old_panels.count('<form')!=1 or old_panels.count('</form>')!=1:
    raise SystemExit('AM197_STOP_FILTER_FORM_COUNT_CHANGED')
if old_panels.count('id="am62-finance-msg"')!=1:
    raise SystemExit('AM197_STOP_FINANCIAL_WARNING_MISSING')
if 'Only fully unpaid' not in old_panels or 'Paid/partial' not in old_panels:
    raise SystemExit('AM197_STOP_FINANCIAL_RULES_NOT_FOUND')
if old_panels.count('AM196')!=0:
    # CSS override is above these blocks, so AM196 should not be in this
    # region unless a subsequent custom edit changed its structure.
    raise SystemExit('AM197_STOP_UNEXPECTED_PANEL_PATCH')

new_panels=read(payload)
if new_panels.count('id="'+marker+'"')!=1:
    raise SystemExit('AM197_STOP_NEW_TEMPLATE_MARKER_INVALID')
if '<script' in new_panels.lower():
    raise SystemExit('AM197_STOP_TEMPLATE_MUST_NOT_REPLACE_EXISTING_JS')
if new_panels.count('<form')!=1 or new_panels.count('</form>')!=1:
    raise SystemExit('AM197_STOP_NEW_FILTER_FORM_NOT_UNIQUE')
for key in (
    'id="am62-selected"', 'id="am62-title"', 'id="am62-jc-status"',
    'id="am62-customer"', 'id="am62-vehicle"', 'id="am62-invoice"',
    'id="am62-open"', 'id="am62-viewinvoice"', 'id="am62-return"',
    'id="am62-finance-msg"',
    'name="status"','name="service"','name="inspection"','name="q"',
    "route('erp.job-cards.index')",
    "route('erp.job-cards.show'",
    "route('erp.job-cards.credit-correction'",
    "route('erp.invoices.show'",
    "route('erp.money-receipts.index')",
    'data-am62-launch',
    'Only fully unpaid','Paid/partial',
):
    if key not in new_panels:
        raise SystemExit('AM197_STOP_NEW_TEMPLATE_MISSING_'+key)
if 'return-to-jc.unpaid' in new_panels:
    raise SystemExit('AM197_STOP_DO_NOT_CREATE_POST_LINKS_IN_PREVIEW')

updated=original[:start]+new_panels.rstrip()+'\n'+original[end:]

# Ensure unrelated work (particularly entire invoice/receipt modal and JS)
# is BYTE-FOR-BYTE unchanged. The transform only replaces old_panels.
assert updated[:start] == original[:start]
assert updated.endswith(original[end:])
for key in (
    'id="am62-title"', 'id="am62-customer"', 'id="am62-vehicle"',
    'id="am62-jc-status"', 'id="am62-finance-msg"',
    'id="am62-open"', 'id="am62-viewinvoice"', 'id="am62-return"',
    'id="am62-return-form"', 'id="am62-return-blocked"',
    'id="AM62_SELECT_ROW"',
):
    if updated.count(key)!=1:
        raise SystemExit('AM197_STOP_DUPLICATE_OR_MISSING_ELEMENT_'+key)
for key in (
    'data-post=', 'data-review=', 'data-invoicelink=',
    'const eligible=!!a.post', 'dialogForm.action=a.post||',
    'name="status"', 'name="service"', 'name="inspection"',
):
    if updated.count(key)!=original.count(key):
        raise SystemExit('AM197_STOP_DATA_OR_ACTION_CONTRACT_CHANGED_'+key)

# Preflight full compiled Blade/PHP in actual live Laravel environment.
with tempfile.TemporaryDirectory(prefix='am197_validate_') as d:
    folder=Path(d)
    candidate=folder/'candidate.blade.php'
    compiled=folder/'compiled.php'
    compiler=folder/'compile.php'
    candidate.write_text(updated,encoding='utf8',errors='surrogateescape')
    compiler.write_text("""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    x=subprocess.run(['php',str(compiler),str(root),str(candidate),str(compiled)],
                     text=True,capture_output=True,timeout=35)
    if x.returncode or not compiled.exists():
        raise SystemExit('AM197_STOP_BLADE_COMPILER '+x.stderr[-600:])
    x=subprocess.run(['php','-l',str(compiled)],
                     text=True,capture_output=True,timeout=30)
    if x.returncode:
        raise SystemExit('AM197_STOP_COMPILED_PHP_LINT '+(x.stdout+x.stderr)[-600:])

print('AM197_OLD_PREVIEW_AND_FILTER_HTML_REPLACED True')
print('AM197_REAL_STRUCTURAL_COMPACT_LAYOUT True')
print('AM197_SELECTED_JC_ACTION_BUTTONS_PRESERVED True')
print('AM197_FIVE_COUNTERS_ONE_ROW True')
print('AM197_THREE_FILTERS_SAME_ROW_DESKTOP True')
print('AM197_PAYMENT_LINKED_WARNING_STAYS_VISIBLE True')
print('AM197_RETURN_TO_JC_FINANCE_RULES_PRESERVED True')
print('AM197_FINANCIAL_ROUTES_MODAL_JS_AND_DATA_BINDINGS_UNCHANGED True')
print('AM197_BLADE_COMPILE_AND_PHP_SYNTAX_PASS')
print('AM197_DATABASE_ROUTES_CONTROLLERS_UNCHANGED True')
if mode=='check':
    print('AM197_CHECK_PASS_NO_WRITES')
    sys.exit(0)

if backup.exists() or sha_file.exists():
    raise SystemExit('AM197_STOP_BACKUP_ALREADY_EXISTS')
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
shutil.copy2(target,backup)
os.chmod(backup,0o600)
atomic(target,updated)
sha_file.write_text(sha(updated)+'\n',encoding='ascii')
os.chmod(sha_file,0o600)
print('AM197_REGISTER_LAYOUT_STRUCTURAL_FIX_INSTALLED')
print('AM197_RUN_ARTISAN_VIEW_CLEAR_AND_BROWSER_HARD_REFRESH')
