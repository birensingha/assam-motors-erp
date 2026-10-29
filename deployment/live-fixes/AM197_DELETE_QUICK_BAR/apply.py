#!/usr/bin/env python3
"""AM197: ONE Admin-only audited Job Card deletion button in existing FIX75 bar.

Changes only the Job Card SHOW Blade UI: removes both large delete surfaces,
moves the exact AM193 form into a compact accessible modal triggered by
a fifth action-bar button, removes the old empty-only form.
Existing admin-only AM193 route/backend/guards/snapshot unchanged.
Fail closed, backup and optional rollback.
"""
import sys,re,os,shutil,subprocess,tempfile,hashlib
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback STAGING_ROOT")
mode=sys.argv[1]; root=Path(sys.argv[2]).resolve()
src=root/"resources/views/job-cards/show.blade.php"
route=root/"routes/web.php"
ctrl=root/"app/Http/Controllers/Web/AdminUnusedJobCardDeleteController.php"
backup=root/"storage/app/am-backups/AM197_DELETE_QUICK_BAR"/"show.blade.php.before_AM197"
marker="AM197_SINGLE_AUDITED_DELETE_QUICKBAR"
if not all(p.is_file() for p in [root/"artisan",src,route,ctrl]):
    raise SystemExit("AM197_STOP_STAGING_OR_AM193_FILES_MISSING")
source=src.read_text("utf8",errors="surrogateescape")
def write_atomic(path,content):
    fd,temp=tempfile.mkstemp(prefix=".am197_",dir=path.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(content)
        os.chmod(temp,path.stat().st_mode&0o777)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)

if mode=="rollback":
    if not backup.is_file():raise SystemExit("AM197_ROLLBACK_BACKUP_NOT_FOUND")
    if source.count(marker)!=1:raise SystemExit("AM197_ROLLBACK_SOURCE_HAS_CHANGED_STOP")
    write_atomic(src,backup.read_text("utf8",errors="surrogateescape"))
    print("AM197_ROLLBACK_VIEW_RESTORED")
    print("AM197_RUN_ARTISAN_VIEW_CLEAR")
    sys.exit(0)
if marker in source:
    print("AM197_ALREADY_APPLIED");sys.exit(0)

am193="{{{-- NOT USED --}}" if False else "{{-- AM193_UNUSED_JOB_CARD_DELETE_ACTION --}}"
nav_anchor='<nav class="am75-quick-actions" aria-label="Job Card quick actions">'
legacy_id='id="jc-delete-panel"'
for name,needle,cnt in [
    ("AM193_FORM_MARKER",am193,1),
    ("FOUR_BUTTON_BAR",nav_anchor,1),
    ("LEGACY_DELETE_WRAPPER",legacy_id,1),
    ("AUDITED_ACTION","route('erp.job-cards.delete-unused', $job->id)",1),
    ("LEGACY_ACTION","route('erp.job-cards.destroy',$job->id)",1),
    ("CUSTOMER_MODAL","id=\"am194-customer-modal\"",1),
]:
    n=source.count(needle)
    print(f"AM197_PREFLIGHT_{name}={'PASS' if n==cnt else 'FAIL_'+str(n)}")
    if n!=cnt:raise SystemExit("AM197_STOP_CHANGED_SOURCE_"+name)
if "AM195_SINGLE_JC_DELETE_SECTION" in source:
    raise SystemExit("AM197_STOP_AM195_ALREADY_APPLIED_DIFFERENT_LAYOUT")
if "delete-unused" not in route.read_text("utf8",errors="surrogateescape"):
    raise SystemExit("AM197_STOP_AM193_BACKEND_ROUTE_MISSING")
if "am193_deleted_job_card_audits" not in ctrl.read_text("utf8",errors="surrogateescape"):
    raise SystemExit("AM197_STOP_AM193_AUDIT_BACKEND_CHANGED")

# Grab the entire original audited AM193 block with balanced Blade @if nesting.
start=source.index(am193)
blade=re.compile(r"@(if|endif)\b")
depth=0;end=None
for m in blade.finditer(source,start):
    if m.group(1)=="if":depth+=1
    else:
        depth-=1
        if depth==0:
            end=m.end();break
if end is None or depth!=0:raise SystemExit("AM197_STOP_AM193_BLADE_BLOCK_UNBALANCED")
original=source[start:end]
if original.count("<form")!=1 or original.count("</form>")!=1:
    raise SystemExit("AM197_STOP_AM193_FORM_CONTRACT_MISMATCH")
if original.count("<details>")!=1 or original.count("</details>")!=1:
    raise SystemExit("AM197_STOP_AM193_DISCLOSURE_CHANGED")
if original.count("<summary")!=1 or original.count("</summary>")!=1:
    raise SystemExit("AM197_STOP_AM193_SUMMARY_CHANGED")
for needle in ("name=\"reason\"","name=\"confirmation\"","@csrf",
               "Permanently Delete Unstarted Job Card",
               "route('erp.job-cards.delete-unused', $job->id)"):
    if needle not in original:raise SystemExit("AM197_STOP_AM193_FORM_MISSING_"+needle)
# Transform ONLY AM193's disclosure wrappers and title, keep audited <form>
# and all its fields unchanged byte-for-byte.
modal_section=original.replace("<details>","",1).replace("</details>","",1)
modal_section,n=re.subn(r"<summary\b[^>]*>.*?</summary>",
    '<h3 id="am197-delete-title" style="margin:0;color:#9f261f;font-size:17px">'
    'Delete Unstarted Job Card — Admin Only</h3>',
    modal_section,count=1,flags=re.S|re.I)
if n!=1:raise SystemExit("AM197_STOP_CANNOT_TRANSFORM_AM193_SUMMARY")
if modal_section.count("<form")!=1 or modal_section.count("</form>")!=1:
    raise SystemExit("AM197_STOP_FORM_MUTATED")
# Remove original large AM193 area.
rest=source[:start]+source[end:]
# Remove ONLY the old legacy empty-only destructive form, including
# its exact open-only @if wrapper. Never erase another section.
old_pattern=(r"(?m)^[ \t]*@if\(strtolower\(\(string\)\$job->status\)==='open'\)\s*"
             r"<section id=\"jc-delete-panel\"[^>]*>.*?</section>\s*@endif[ \t]*\n?")
hits=list(re.finditer(old_pattern,rest,re.S))
if len(hits)!=1:raise SystemExit("AM197_STOP_LEGACY_FORM_NOT_ISOLATED_"+str(len(hits)))
legacy=hits[0].group(0)
for needle in ('Delete Empty Job Card',"route('erp.job-cards.destroy',$job->id)",
               "name=\"confirm_job_no\"","name=\"delete_reason\"",
               "@method('DELETE')"):
    if needle not in legacy:raise SystemExit("AM197_STOP_LEGACY_GUARD_MISSING_"+needle)
if legacy.count("<form")!=1 or legacy.count("</form>")!=1:
    raise SystemExit("AM197_STOP_LEGACY_FORM_SURROUNDINGS_CHANGED")
rest=rest[:hits[0].start()] + '\n{{-- AM197_LEGACY_EMPTY_DELETE_PANEL_REMOVED; AM193 BACKEND IS THE ONLY UI DELETE ACTION. --}}\n' + rest[hits[0].end():]

# Position in the existing FOUR-button row immediately after Customer Name.
if rest.count(nav_anchor)!=1:raise SystemExit("AM197_STOP_BAR_CHANGED")
nstart=rest.index(nav_anchor)
nend=rest.find("</nav>",nstart)
if nend<0 or nend-nstart>4500:raise SystemExit("AM197_STOP_BAR_BOUNDS")
bar=rest[nstart:nend]
for key in ("Job Card Summary","Credit Note","Estimate/ Reverse",
            "Correct Customer Name","id=\"am194-open\""):
    if key not in bar:raise SystemExit("AM197_STOP_ORIGINAL_ACTION_MISSING_"+key)
guard=r'''
  {{-- AM197_SINGLE_AUDITED_DELETE_QUICKBAR — same AM193 eligibility and Admin-only display. --}}
  @php
    $am197Actor=request()->user();
    $am197CanDelete=$am197Actor instanceof \App\Models\ErpAuthUser
      && (bool)$am197Actor->active && $am197Actor->isAdmin()
      && !in_array(strtolower(trim((string)($job->status ?? ''))), [
        'started','running','work_completed','work completed','completed',
        'qc_pending','qc pending','qc_passed','qc passed',
        'ready_for_delivery','ready for delivery','closed','invoiced',
        'cancelled','canceled','deleted','financial_reopened'
      ],true);
  @endphp
  @if($am197CanDelete)
    <button type="button" class="am75-action am197-delete-button" id="am197-delete-open"
            aria-haspopup="dialog" aria-controls="am197-delete-modal">
      Delete Job Card
    </button>
  @endif
'''
rest=rest[:nend]+"\n"+guard+rest[nend:]
endnav=rest.index("</nav>",nstart)+len("</nav>")

popup=r'''
{{-- AM197_DELETE_MODAL: original AM193 audited form, not a second delete method. --}}
<style id="AM197_DELETE_MODAL_STYLE">
  .am197-delete-button{color:#aa241d!important;background:#fff9f8!important;
    border:1px solid #e3a09d!important;cursor:pointer!important;white-space:nowrap!important}
  .am197-delete-button:hover,.am197-delete-button:focus-visible{background:#ffecea!important;
    outline:2px solid #e9a29c!important}
  .am197-backdrop[hidden]{display:none!important}
  .am197-backdrop{position:fixed!important;inset:0!important;z-index:12020!important;
    display:flex!important;align-items:center!important;justify-content:center!important;
    overflow:auto!important;padding:16px!important;background:rgba(8,24,42,.62)!important}
  .am197-dialog{width:min(100%,560px);max-height:calc(100vh - 32px);overflow-y:auto;
    background:#fff;border-radius:13px;box-shadow:0 20px 65px #091c3152}
  .am197-head{display:flex;align-items:center;justify-content:space-between;
    gap:10px;padding:13px 17px;border-bottom:1px solid #f0d7d3;
    color:#92241d;background:#fff7f6}
  .am197-head strong{font-weight:850;font-size:15px}
  .am197-close{cursor:pointer;border:1px solid #e7bbb4;border-radius:7px;
    font-size:23px;color:#7e241d;background:#fff;min-width:34px;min-height:34px}
  .am197-body{padding:15px 17px}
  .am197-body section.card{margin:0!important;padding:0!important;background:#fff!important;
    border:0!important;box-shadow:none!important}
  .am197-body textarea,.am197-body input{box-sizing:border-box;max-width:100%}
  .am197-note{font-size:12px;line-height:1.5;color:#586a7c;margin-top:8px}
  .am197-cancel{display:inline-block;margin:10px 0 0;cursor:pointer;
    padding:8px 11px;border:1px solid #b8cbdc;border-radius:7px;background:#fff;color:#23425e}
  @media(max-width:640px){.am197-body{padding:12px}.am197-head{padding:11px}}
</style>
@if($am197CanDelete)
<div class="am197-backdrop" id="am197-delete-modal" role="presentation" hidden
     data-restore="{{ $errors->has('delete') ? '1':'0' }}">
  <div class="am197-dialog" role="dialog" aria-modal="true"
       aria-labelledby="am197-dialog-title" aria-describedby="am197-dialog-description" tabindex="-1">
    <header class="am197-head">
      <strong id="am197-dialog-title">Delete Job Card · Admin Only</strong>
      <button type="button" id="am197-delete-close" class="am197-close"
              aria-label="Close delete confirmation">×</button>
    </header>
    <div class="am197-body">
      <p id="am197-dialog-description" class="am197-note">
        {{ $job->job_no }} · Permanent removal only if AM193 server eligibility checks pass.
        Verify actual status before proceeding.
      </p>
AM197_FORM_PLACEHOLDER
      <button type="button" id="am197-delete-cancel" class="am197-cancel">Cancel</button>
    </div>
  </div>
</div>
@endif
<script id="AM197_DELETE_MODAL_SCRIPT">
(function(){
  'use strict';
  function init(){
    var open=document.getElementById('am197-delete-open');
    var modal=document.getElementById('am197-delete-modal');
    if(!open||!modal)return;
    var dlg=modal.querySelector('.am197-dialog');
    var closeButton=document.getElementById('am197-delete-close');
    var cancel=document.getElementById('am197-delete-cancel');
    var previous=null, bodyOverflow='';
    function show(){
      previous=document.activeElement;
      bodyOverflow=document.body.style.overflow;
      modal.hidden=false;document.body.style.overflow='hidden';
      (dlg.querySelector('textarea[name="reason"]')||dlg).focus();
    }
    function hide(){
      modal.hidden=true;document.body.style.overflow=bodyOverflow;
      if(previous && typeof previous.focus==='function')previous.focus();
    }
    open.addEventListener('click',show);
    closeButton.addEventListener('click',hide);
    cancel.addEventListener('click',hide);
    modal.addEventListener('click',function(e){if(e.target===modal)hide();});
    document.addEventListener('keydown',function(e){
      if(modal.hidden)return;
      if(e.key==='Escape'){e.preventDefault();hide();}
      if(e.key==='Tab'){
        var items=Array.prototype.slice.call(modal.querySelectorAll('button:not([disabled]),textarea:not([disabled]),input:not([disabled])'))
          .filter(function(x){return x.getClientRects().length>0;});
        if(!items.length)return;
        if(e.shiftKey&&document.activeElement===items[0]){e.preventDefault();items[items.length-1].focus();}
        if(!e.shiftKey&&document.activeElement===items[items.length-1]){e.preventDefault();items[0].focus();}
      }
    });
    if(modal.getAttribute('data-restore')==='1')show();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);
  else init();
})();
</script>
'''
if popup.count("AM197_FORM_PLACEHOLDER")!=1:raise SystemExit("AM197_STOP_POPUP_TEMPLATE")
popup=popup.replace("AM197_FORM_PLACEHOLDER",modal_section,1)
rest=rest[:endnav]+"\n"+popup+"\n"+rest[endnav:]

# Strict preservation and single actionable delete form.
if rest.count(marker)!=1:raise SystemExit("AM197_STOP_MARKER_COUNT")
if rest.count(am193)!=1:raise SystemExit("AM197_STOP_AM193_MARKER_COUNT")
if rest.count("route('erp.job-cards.delete-unused', $job->id)")!=1:
    raise SystemExit("AM197_STOP_MORE_THAN_ONE_AUDITED_FORM")
if "route('erp.job-cards.destroy',$job->id)" in rest:
    raise SystemExit("AM197_STOP_LEGACY_FORM_STILL_PRESENT")
for must in ('FIX75_JC_THREE_ACTION_BAR','AM194_COMPACT_CUSTOMER_CORRECTION',
             'route(\'erp.job-cards.summary-print\'','route(\'erp.job-cards.credit-correction\'',
             'route(\'erp.estimates.create\'','id="am194-open"'):
    if must in source and must not in rest:
        raise SystemExit("AM197_STOP_UNRELATED_WORKFLOW_REMOVED_"+must)
new_form=re.search(r'<form method="POST" action="\{\{ route\(\'erp.job-cards.delete-unused\', \$job->id\) \}\}".*?</form>',rest,re.S)
old_form=re.search(r'<form method="POST" action="\{\{ route\(\'erp.job-cards.delete-unused\', \$job->id\) \}\}".*?</form>',original,re.S)
if not(new_form and old_form and new_form.group()==old_form.group()):
    raise SystemExit("AM197_STOP_AUDITED_FORM_CHANGED")
if rest.count('id="am197-delete-open"')!=1 or rest.count('id="am197-delete-modal"')!=1:
    raise SystemExit("AM197_STOP_DUPLICATE_CONTROL")
# REAL app Blade compilation, php-lint compiled output before writing served file.
with tempfile.TemporaryDirectory(prefix="am197check_") as tempdir:
    candidate=Path(tempdir)/"show.blade.php"
    compiled=Path(tempdir)/"show.compiled.php"
    candidate.write_text(rest,"utf8",errors="surrogateescape")
    phpcode=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    result=subprocess.run(["php","-r",phpcode,str(candidate),str(compiled)],
                          cwd=root,capture_output=True,text=True,timeout=60)
    if result.returncode:raise SystemExit("AM197_STOP_BLADE_COMPILER "+result.stderr[-400:])
    result=subprocess.run(["php","-l",str(compiled)],cwd=root,capture_output=True,text=True,timeout=35)
    if result.returncode:raise SystemExit("AM197_STOP_COMPILED_PHP_LINT "+(result.stdout+result.stderr)[-450:])
print("AM197_FOUR_ORIGINAL_ACTIONS_PRESERVED True")
print("AM197_FIFTH_DELETE_BUTTON_IN_SAME_BAR True")
print("AM197_AM193_ORIGINAL_AUDITED_FORM_BYTE_IDENTICAL True")
print("AM197_OLD_TOP_AND_BOTTOM_LARGE_PANELS_REMOVED True")
print("AM197_ADMIN_VISIBILITY_AND_SERVER_AUTH_PRESERVED True")
print("AM197_BLADE_COMPILE_PHP_SYNTAX_PASS")
print("AM197_ROUTES_CONTROLLERS_FINANCE_DATABASE_UNCHANGED")
if mode=="check":
    print("AM197_CHECK_PASS_NO_WRITES");sys.exit(0)
if backup.exists():raise SystemExit("AM197_STOP_BACKUP_EXISTS")
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(src,backup)
try:write_atomic(src,rest)
except BaseException:
    shutil.copy2(backup,src)
    raise
print("AM197_SINGLE_DELETE_BUTTON_INSTALLED")
print("AM197_RUN_ARTISAN_VIEW_CLEAR")
