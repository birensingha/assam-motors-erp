#!/usr/bin/env python3
"""AM199 - One slim Job Card Register toolbar; remove both duplicate lower cards.
Only the Job Card Register Blade view changes. Keeps existing finance, routes,
modal POST and payment eligibility. Backup + read-only check + safe rollback.
Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
view=root/'resources/views/job-cards/index.blade.php'
payload=pkg/'toolbar.blade.php'
backup_dir=root/'storage/app/am-backups/AM199_SINGLE_REGISTER_TOOLBAR'
backup=backup_dir/'index.blade.php.before_AM199'
manifest=backup_dir/'installed.sha256'
marker='AM199_SINGLE_REGISTER_TOOLBAR'
def read(p):return p.read_text(encoding='utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am199_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as h:h.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

if not (root/'artisan').is_file() or not view.is_file():
    raise SystemExit('AM199_STOP_LARAVEL_REGISTER_VIEW_MISSING')
original=read(view)
if mode=='rollback':
    if not backup.is_file() or not manifest.is_file():
        raise SystemExit('AM199_STOP_ROLLBACK_BACKUP_MISSING')
    if sha(original)!=read(manifest).strip():
        raise SystemExit('AM199_STOP_VIEW_MODIFIED_AFTER_APPLY_NO_OVERWRITE')
    atomic(view,read(backup))
    print('AM199_ROLLBACK_ORIGINAL_VIEW_RESTORED')
    print('AM199_RUN_PHP_ARTISAN_VIEW_CLEAR')
    sys.exit(0)
if marker in original:
    print('AM199_ALREADY_INSTALLED')
    sys.exit(0)
if not payload.is_file():raise SystemExit('AM199_STOP_TOOLBAR_FILE_MISSING')
for token in (
    '<style id="AM62_PROTOTYPE_MATCH">',
    'id="AM196_COMPACT_JOB_CARD_REGISTER"',
    'id="AM197_REGISTER_REAL_LAYOUT"',
    '<div class="am62-card am62-searchbar">',
    '<div class="am62-card am62-register" id="am62-register">',
    '<section class="am62-card am197-preview" id="am62-selected">',
    '<section class="am62-card am197-filter-card" id="am197-filter-card">',
    'id="am62-search-form"', 'id="am62-search"', 'id="am62-count"',
    'id="am62-top-return"', 'id="am62-return-form"', 'id="am62-return-blocked"',
    'id="AM62_SELECT_ROW"', 'data-am62-row','data-am62-launch',
    'const eligible=!!a.post', "dialogForm.action=a.post||''",
    "route('erp.job-cards.return-to-jc.unpaid'",
):
    if token not in original:raise SystemExit('AM199_STOP_REGISTER_CONTRACT_CHANGED '+token)

# Isolate the adjacent AM197 cards by exact semantic anchors. No broad CSS
# hiding or deleting of unrelated forms and no changes to table or modal.
preview='<section class="am62-card am197-preview" id="am62-selected">'
filters='<section class="am62-card am197-filter-card" id="am197-filter-card">'
if original.count(preview)!=1 or original.count(filters)!=1:
    raise SystemExit('AM199_STOP_PREVIEW_FILTER_CARD_COUNT')
start=original.index(preview)
fpos=original.index(filters,start)
preview_end=original.find('</section>',start)
if preview_end<0 or preview_end>fpos or original[preview_end+len('</section>'):fpos].strip():
    raise SystemExit('AM199_STOP_UNEXPECTED_CONTENT_BETWEEN_OLD_CARDS')
end=original.find('</section>',fpos)
if end<0 or end-fpos>12000:raise SystemExit('AM199_STOP_FILTER_BOUNDARY_CHANGED')
end+=len('</section>')
old_cards=original[start:end]
if old_cards.count('id="am62-selected"')!=1 or old_cards.count('id="am197-filter-card"')!=1:
    raise SystemExit('AM199_STOP_UNEXPECTED_OLD_CARDS')
if old_cards.count('<form')!=1 or old_cards.count('</form>')!=1:
    raise SystemExit('AM199_STOP_OLD_FILTER_FORM_UNEXPECTED')
for x in ('id="am62-finance-msg"','Only fully unpaid','Paid/partial'):
    if x not in old_cards:raise SystemExit('AM199_STOP_UNEXPECTED_FINANCE_WARNING '+x)
modal_start='<div class="am62-overlay" id="am62-modal" hidden>'
js_start='<script id="AM62_SELECT_ROW">'
original_modal=original[original.index(modal_start):original.index(js_start)]
for x in ('FINANCE REVIEW REQUIRED','confirmed_finance','id="am62-return-form"',
          'id="am62-return-blocked"','id="am62-review-link"'):
    if x not in original_modal:raise SystemExit('AM199_STOP_MODAL_SAFETY_NOT_FOUND '+x)

without=original[:start]+original[end:]
old_search_token='<div class="am62-card am62-searchbar">'
register_token='<div class="am62-card am62-register" id="am62-register">'
if without.count(old_search_token)!=1 or without.count(register_token)!=1:
    raise SystemExit('AM199_STOP_REGISTER_SEARCH_UNEXPECTED')
a=without.index(old_search_token)
b=without.index(register_token,a)
old_search=without[a:b]
if old_search.count('<form')!=1 or old_search.count('</form>')!=1:
    raise SystemExit('AM199_STOP_SEARCH_FORM_UNEXPECTED')
if old_search.count('id="am62-search"')!=1 or old_search.count('id="am62-count"')!=1:
    raise SystemExit('AM199_STOP_SEARCH_IDS_UNEXPECTED')
toolbar=read(payload)
if toolbar.count('id="'+marker+'"')!=1 or toolbar.count('id="am62-search-form"')!=1:
    raise SystemExit('AM199_STOP_TOOLBAR_ID_INVALID')
for x in ('id="am62-search"','id="am62-count"','id="am199-picked-label"',
          'name="q"','name="status"','name="service"','name="inspection"',
          "route('erp.job-cards.index')",'@selected',
          "{{ $summary['total'] }}","{{ $summary['invoiced'] }}"):
    if x not in toolbar:raise SystemExit('AM199_STOP_MISSING_FILTER '+x)
if toolbar.count('<form')!=2 or toolbar.count('</form>')!=2:
    raise SystemExit('AM199_STOP_NEW_FORMS_INVALID')
updated=without[:a]+toolbar.rstrip()+'\n\n'+without[b:]

# Make Money Receipts accessible in the existing TOP actions after deleting
# the redundant lower JC preview. Preserve create + Return links.
create_link='<a class="am62-white" href="{{ route(\'erp.job-cards.create\') }}">＋ Create Job Card</a>'
if updated.count(create_link)!=1:raise SystemExit('AM199_STOP_TOP_ACTIONS_CHANGED')
updated=updated.replace(create_link,create_link+
    '\n      <a class="am62-white" href="{{ route(\'erp.money-receipts.index\') }}">Money Receipts</a>',1)

# Existing selection script depended on the duplicate lower preview. Retain
# selection for the top Return button and one-line selected label only.
# The FIX62 source uses ONE leading space before "const top", whereas
# AM199 v1 mistakenly required TWO. That blocked the whole installation
# (AM199_STOP_SELECTION_SCRIPT_HEAD_CHANGED) even after AM197 succeeded.
# Match the semantic JS declaration with flexible indentation, but require
# exactly ONE verified declaration and ONE subsequent row event block.
head_pattern=re.compile(
    r"(?m)^[ \t]*const top=get\('top-return'\),button=get\('return'\),"
    r"invoiceBtn=get\('viewinvoice'\),openBtn=get\('open'\),"
    r"notice=get\('finance-msg'\);[ \t]*$"
)
heads=list(head_pattern.finditer(updated))
if len(heads)!=1:
    raise SystemExit('AM199_STOP_SELECTION_JS_VARIANT_NOT_RECOGNIZED_COUNT_'+str(len(heads)))
head=heads[0]
row_pattern=re.compile(r"(?m)^[ \t]*rows\.forEach\(row=>\{[ \t]*$")
rows_blocks=list(row_pattern.finditer(updated,head.end()))
if not rows_blocks:
    raise SystemExit('AM199_STOP_SELECTION_ROW_HANDLERS_NOT_FOUND')
c=head.start()
d=rows_blocks[0].start()
old_selected=updated[c:d]
if len(old_selected)>3500:
    raise SystemExit('AM199_STOP_SELECTION_BLOCK_TOO_LARGE')
for x in ("function destination(el,href)","function select(row)", "get('title').textContent",
          "destination(top,a.review)",'notice.hidden='):
    if x not in old_selected:raise SystemExit('AM199_STOP_SELECTION_SCRIPT_SHAPE_CHANGED '+x)
selection="""  const top=get('top-return'),picked=document.getElementById('am199-picked-label');
  function destination(el,href){if(!el)return;el.href=href||'#';if(href){el.removeAttribute('aria-disabled');}else{el.setAttribute('aria-disabled','true');}}
  function select(row){
   if(!row)return;
   rows.forEach(x=>x.classList.toggle('am62-picked',x===row));
   const a=row.dataset;
   if(picked)picked.textContent='Selected: '+a.jc+' · '+a.customer+' · '+a.vehicle;
   destination(top,a.review);
  }
"""
updated=updated[:c]+selection+updated[d:]
search_pattern=re.compile(
    r"(?m)^[ \t]*if\(count\)count\.textContent=visible\+' VISIBLE / '\+rows\.length\+' LOADED';[ \t]*$"
)
searches=list(search_pattern.finditer(updated))
if len(searches)!=1:
    raise SystemExit('AM199_STOP_LIVE_SEARCH_VARIANT_NOT_RECOGNIZED_COUNT_'+str(len(searches)))
search_end=searches[0].group(0)
updated=updated.replace(search_end,search_end+"""
   const selected=rows.find(row=>row.classList.contains('am62-picked'));
   if(!selected || selected.hidden){
     const firstVisible=rows.find(row=>!row.hidden);
     if(firstVisible)select(firstVisible);
     else{
       rows.forEach(row=>row.classList.remove('am62-picked'));
       destination(top,'');
       if(picked)picked.textContent='No matching Job Card selected';
     }
   }
""",1)

# Financial modal, POST decision, table row actions must remain intact.
if original_modal!=updated[updated.index(modal_start):updated.index(js_start)]:
    raise SystemExit('AM199_STOP_FINANCIAL_MODAL_CHANGED')
for x in ('id="am62-selected"','id="am197-filter-card"'):
    if x in updated:raise SystemExit('AM199_STOP_OLD_CARDS_STILL_EXIST '+x)
for x in ('id="am62-search-form"','id="am62-search"','id="am62-count"',
          'id="am199-picked-label"','name="status"','name="service"',
          'name="inspection"'):
    if updated.count(x)!=1:raise SystemExit('AM199_STOP_DUPLICATE_OR_MISSING '+x)
for x in ('data-post=','data-review=','data-invoicelink=','data-am62-row',
          'data-am62-launch','const eligible=!!a.post',
          "dialogForm.action=a.post||''",
          "route('erp.job-cards.credit-correction'",
          "route('erp.invoices.show'",'id="am62-return-form"',
          'id="am62-return-blocked"'):
    expected=original.count(x)-old_cards.count(x)+toolbar.count(x)
    if updated.count(x)!=expected:
        raise SystemExit('AM199_STOP_FINANCE_OR_ROW_CONTRACT_CHANGED '+x)
if updated.count("route('erp.job-cards.return-to-jc.unpaid'")!=original.count("route('erp.job-cards.return-to-jc.unpaid'"):
    raise SystemExit('AM199_STOP_POST_ROUTE_COUNT_CHANGED')
if updated.count(marker)!=2:raise SystemExit('AM199_STOP_NEW_MARKER_INVALID')

# Full Laravel Blade compiler and PHP syntax preflight before any file write.
with tempfile.TemporaryDirectory(prefix='am199check_') as folder:
    tmp=Path(folder)
    candidate=tmp/'candidate.blade.php'
    compiled=tmp/'compiled.php'
    compiler=tmp/'compile.php'
    candidate.write_text(updated,encoding='utf8',errors='surrogateescape')
    compiler.write_text("""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    r=subprocess.run(['php',str(compiler),str(root),str(candidate),str(compiled)],
                     capture_output=True,text=True,timeout=40)
    if r.returncode or not compiled.is_file():
        raise SystemExit('AM199_STOP_BLADE_COMPILE '+r.stderr[-550:])
    r=subprocess.run(['php','-l',str(compiled)],capture_output=True,text=True,timeout=30)
    if r.returncode:raise SystemExit('AM199_STOP_COMPILED_PHP_LINT '+(r.stdout+r.stderr)[-550:])

print('AM199_REDUNDANT_SELECTED_JC_PANEL_REMOVED True')
print('AM199_REDUNDANT_BOTTOM_FILTER_CARD_REMOVED True')
print('AM199_ONE_COMPACT_SEARCH_TOTALS_FILTER_TOOLBAR_ABOVE_REGISTER True')
print('AM199_RETURN_TO_JC_MODAL_FINANCE_GUARDS_UNCHANGED True')
print('AM199_PER_ROW_JC_INVOICE_RETURN_ACTIONS_UNCHANGED True')
print('AM199_FILTERS_SELECTED_ROW_AND_SEARCH_VALIDATED True')
print('AM199_FULL_BLADE_AND_PHP_SYNTAX_PASS')
print('AM199_DATABASE_ROUTES_CONTROLLERS_FINANCE_UNTOUCHED True')
if mode=='check':
    print('AM199_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if backup.exists() or manifest.exists():raise SystemExit('AM199_STOP_BACKUP_CONFLICT')
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
shutil.copy2(view,backup)
os.chmod(backup,0o600)
atomic(view,updated)
manifest.write_text(sha(updated)+'\n',encoding='ascii')
os.chmod(manifest,0o600)
print('AM199_SINGLE_REGISTER_TOOLBAR_INSTALLED')
print('AM199_RUN_PHP_ARTISAN_VIEW_CLEAR')
