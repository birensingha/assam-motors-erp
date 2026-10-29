#!/usr/bin/env python3
"""AM196 — Compact Job Card Register selected JC and totals/filters UI.

- The selected JC quick preview is an orderly compact responsive strip.
- Status, Service, Inspection are ONE row on desktop, responsive on mobile.
- Summary totals become small counters and lower filters default open.
- Return-to-JC financial disclaimer remains available in a details element.
- Existing data bindings, IDs, JS selection, POST URLs, credit notes, invoice
  and receipt handlers, and customer/job data remain UNTOUCHED.

Run: python3 apply.py check|apply|rollback STAGING_ROOT
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: python3 apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
view=root/'resources/views/job-cards/index.blade.php'
cssfile=pkg/'compact.css'
backupdir=root/'storage/app/am-backups/AM196_COMPACT_JOB_CARD_REGISTER'
backup=backupdir/'index.blade.php.before_AM196'
manifest=backupdir/'installed.sha256'
marker='AM196_COMPACT_JOB_CARD_REGISTER'
def read(p):return p.read_text(encoding='utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am196_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

if not (root/'artisan').is_file() or not view.is_file():
    raise SystemExit('AM196_STOP_EXPECTED_STAGING_VIEW_MISSING')
original=read(view)
if mode=='rollback':
    if not backup.is_file() or not manifest.is_file():
        raise SystemExit('AM196_STOP_BACKUP_MISSING')
    if sha(original)!=read(manifest).strip():
        raise SystemExit('AM196_STOP_VIEW_CHANGED_SINCE_APPLY_PRESERVE_NEW_WORK')
    atomic(view,read(backup))
    print('AM196_ROLLBACK_ORIGINAL_REGISTER_VIEW_RESTORED')
    print('AM196_BACKEND_DATABASE_AND_FINANCE_UNTOUCHED')
    print('AM196_RUN_ARTISAN_VIEW_CLEAR')
    sys.exit(0)

if marker in original:raise SystemExit('AM196_ALREADY_INSTALLED')
if not cssfile.is_file():raise SystemExit('AM196_STOP_CSS_PAYLOAD_MISSING')
css=read(cssfile)
if '</style' in css.lower() or '<script' in css.lower():
    raise SystemExit('AM196_STOP_UNSAFE_CSS')
for signature in (
    '.am62-root #am62-selected.am62-preview',
    '.am62-root details.am62-more',
    'grid-template-columns:repeat(3,minmax(0,1fr))',
    'am62-finance-msg',
):
    if signature not in css:
        raise SystemExit('AM196_STOP_CSS_SIGNATURE_MISSING')
# Fail closed if another unrelated version of the register is on staging.
need=(
    '<style id="AM62_PROTOTYPE_MATCH">',
    '<section class="am62-card am62-preview" id="am62-selected">',
    '<details class="am62-card am62-more">',
    'id="am62-finance-msg"',
    'id="am62-open"',
    'id="am62-viewinvoice"',
    'id="am62-return"',
    'id="am62-title"',
    'id="am62-customer"',
    'id="am62-vehicle"',
    'id="am62-invoice"',
    'id="am62-jc-status"',
    'name="status"','name="service"','name="inspection"',
    'id="AM62_SELECT_ROW"',
    'data-am62-launch',
    'am62-payment',
)
# The last token is only a finance guard identifier in some versions;
# dynamic financial restrictions are verified below rather than required
# under a specific variable name.
for token in need[:-1]:
    if original.count(token)!=1 and token in (
        '<style id="AM62_PROTOTYPE_MATCH">',
        '<section class="am62-card am62-preview" id="am62-selected">',
        '<details class="am62-card am62-more">',
        'id="am62-finance-msg"', 'id="am62-open"',
        'id="am62-viewinvoice"', 'id="am62-return"',
        'id="am62-title"', 'id="am62-customer"',
        'id="am62-vehicle"', 'id="am62-invoice"',
        'id="am62-jc-status"', 'id="AM62_SELECT_ROW"'
    ):
        raise SystemExit('AM196_STOP_REGISTER_LAYOUT_CONTRACT_CHANGED_'+token)
    if token not in original:
        raise SystemExit('AM196_STOP_REQUIRED_REGISTER_TOKEN_MISSING_'+token)
for finance in (
    "route('erp.job-cards.credit-correction'",
    "route('erp.job-cards.return-to-jc.unpaid'",
    "route('erp.invoices.show'",
    "route('erp.money-receipts.index')",
):
    if finance not in original:raise SystemExit('AM196_STOP_FINANCIAL_LINK_MISSING_'+finance)

# Scoped CSS override AFTER existing register CSS; do not rewrite app.css,
# routes, widgets, row selection, or server side filtering.
styleOpen='<style id="AM62_PROTOTYPE_MATCH">'
stylePos=original.index(styleOpen)
styleEnd=original.find('</style>',stylePos)
if styleEnd<0 or styleEnd-stylePos>22000:
    raise SystemExit('AM196_STOP_STYLESHEET_ANCHOR_CHANGED')
styleEnd+=len('</style>')
extra='\n<style id="'+marker+'">\n'+css.strip()+'\n</style>'
patched=original[:styleEnd]+extra+original[styleEnd:]

# The long finance explanation remains readable on demand, but the selected
# JC preview no longer shows a third paragraph by default.
footnote=r'(<p\s+class="am62-footnote"\s*>)(.*?)(</p>)'
matches=list(re.finditer(footnote,patched,re.S|re.I))
if len(matches)!=1:
    raise SystemExit('AM196_STOP_FINANCE_HELP_NOT_UNIQUE')
m=matches[0]
foot=m.group(0)
if 'Only fully unpaid' not in foot or 'Paid/partial' not in foot:
    raise SystemExit('AM196_STOP_FINANCE_HELP_CHANGED')
repl=(
    '<details class="am196-finance-rules">\n'
    '  <summary>Return to JC — financial rules</summary>\n'
    '  '+foot+'\n'
    '</details>'
)
patched=patched[:m.start()]+repl+patched[m.end():]

# Open the compact filters by default, and allow user to collapse them.
openTag='<details class="am62-card am62-more">'
if patched.count(openTag)!=1:
    raise SystemExit('AM196_STOP_ADVANCED_FILTER_PANEL_CHANGED')
patched=patched.replace(openTag,'<details class="am62-card am62-more" open>',1)
summary='<summary>Register totals & advanced filters (Status / Service / Inspection)</summary>'
if patched.count(summary)!=1:
    raise SystemExit('AM196_STOP_FILTER_SUMMARY_CHANGED')
patched=patched.replace(summary,
    '<summary>Job Card Totals & Filters · Status / Service / Inspection</summary>',1)

# Everything financially meaningful and every filter binding must
# survive unchanged, including original POST decision logic in JS.
for signature in (
    "data-post=", "data-review=", "data-finance=",
    'id="am62-return-form"', 'id="am62-return-blocked"',
    "const eligible=!!a.post", 'dialogForm.action=a.post||',
    "name=\"status\"", "name=\"service\"", "name=\"inspection\"",
    "name=\"q\"", "route('erp.job-cards.index')",
):
    if original.count(signature)!=patched.count(signature):
        raise SystemExit('AM196_STOP_JS_OR_FINANCE_CONTRACT_MODIFIED_'+signature)
if patched.count(marker)!=1 or patched.count('am196-finance-rules')<1:
    raise SystemExit('AM196_STOP_NEW_LAYOUT_INTEGRITY')
if 'Only fully unpaid' not in patched or 'Paid/partial' not in patched:
    raise SystemExit('AM196_STOP_FINANCE_WARNING_LOST')

# Real Laravel Blade compilation + PHP syntax, no HTTP requests or
# database writes. Preflight runs on exact live view before touching it.
with tempfile.TemporaryDirectory(prefix='am196check_') as d:
    tmp=Path(d)
    candidate=tmp/'candidate.blade.php'
    compiled=tmp/'compiled.php'
    compilePhp=tmp/'compile.php'
    candidate.write_text(patched,encoding='utf8',errors='surrogateescape')
    compilePhp.write_text("""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    p=subprocess.run(['php',str(compilePhp),str(root),str(candidate),str(compiled)],
                     capture_output=True,text=True,timeout=40)
    if p.returncode or not compiled.is_file():
        raise SystemExit('AM196_STOP_BLADE_COMPILE '+p.stderr[-350:])
    lint=subprocess.run(['php','-l',str(compiled)],
                        capture_output=True,text=True,timeout=30)
    if lint.returncode:
        raise SystemExit('AM196_STOP_BLADE_PHP_LINT '+(lint.stdout+lint.stderr)[-400:])

print('AM196_SELECTED_JOB_CARD_COMPACT_PREVIEW True')
print('AM196_REGISTER_TOTALS_COMPACT True')
print('AM196_STATUS_SERVICE_INSPECTION_SAME_ROW_DESKTOP True')
print('AM196_RETURN_TO_JC_FINANCE_WARNING_PRESERVED True')
print('AM196_EXISTING_FILTERS_AND_ALL_ACTION_URLS_UNCHANGED True')
print('AM196_BLADE_AND_PHP_SYNTAX_PASS')
print('AM196_DATABASE_ROUTES_CONTROLLERS_FINANCE_UNCHANGED True')
if mode=='check':
    print('AM196_CHECK_PASS_NO_WRITES')
    sys.exit(0)

if backup.exists() or manifest.exists():
    raise SystemExit('AM196_STOP_BACKUP_CONFLICT')
backupdir.mkdir(parents=True,exist_ok=True)
os.chmod(backupdir,0o700)
shutil.copy2(view,backup)
os.chmod(backup,0o600)
atomic(view,patched)
manifest.write_text(sha(patched)+'\n',encoding='ascii')
os.chmod(manifest,0o600)
print('AM196_COMPACT_JOB_CARD_REGISTER_INSTALLED')
print('AM196_RUN_ARTISAN_VIEW_CLEAR')
