#!/usr/bin/env python3
"""AM198 staging-only view rearrangement.
Move original Edit Job Card and Return to Estimate Review (incl. reason POST)
from page title right into FIX75/AM197 compact actions. Keep all routes,
conditions/CSRF/validation and any non-action JC content untouched.
Strict fail-closed source preflight, Blade/PHP compile, backup and rollback.
"""
import sys,re,shutil,os,tempfile,subprocess
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit("Usage: apply.py check|apply|rollback STAGING_LARAVEL_ROOT")
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
view=root/"resources/views/job-cards/show.blade.php"
bak=root/"storage/app/am-backups/AM198_ACTION_BAR_REORDER/show.blade.php.before_AM198"
marker="AM198_EDIT_RETURN_IN_QUICKBAR"
if not (root/"artisan").is_file() or not view.is_file():
    raise SystemExit("AM198_STOP_INVALID_STAGING_ROOT")
old=view.read_text("utf8",errors="surrogateescape")
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix=".am198_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if mode=="rollback":
    if not bak.is_file() or old.count(marker)!=1:
        raise SystemExit("AM198_ROLLBACK_STOP_MISSING_BACKUP_OR_SOURCE_CHANGED")
    atomic(view,bak.read_text("utf8",errors="surrogateescape"))
    print("AM198_ROLLBACK_PREVIOUS_JC_VIEW_RESTORED")
    sys.exit(0)
if marker in old:
    print("AM198_ALREADY_APPLIED");sys.exit(0)
nav_start='<nav class="am75-quick-actions" aria-label="Job Card quick actions">'
nav_end='</nav>'
page_head='<div class="page-head">'
action='<div class="actions wrap">'
review_route="route('erp.job-cards.return-estimate-review',$job->id)"
edit_route="route('erp.job-cards.edit',$job->id)"
for name,needle,count in [
    ("ACTION_BAR",nav_start,1),
    ("PAGE_HEAD",page_head,1),
    ("TITLE_ACTION_WRAPPER",action,1),
    ("EDIT_ROUTE",edit_route,1),
    ("REVIEW_POST_ROUTE",review_route,1),
    ("FOUR_BUTTONS",'id="am194-open"',1),
    ("AUDITED_DELETE_BUTTON",'id="am197-delete-open"',1),
    ("AUDITED_DELETE_MODAL",'id="am197-delete-modal"',1),
]:
    n=old.count(needle)
    print(f"AM198_PREFLIGHT_{name}={'PASS' if n==count else 'FAIL_'+str(n)}")
    if n!=count:raise SystemExit("AM198_STOP_UNEXPECTED_"+name)
# Entire existing title-right block is a bounded short span with exactly two
# status-gated controls, and closes immediately before closing page-head.
h=old.index(page_head)
stop=old.index("{{-- FIX61B_ONE_BUTTON_RETURN_JC_V1 --}}",h)
head=old[h:stop]
hit=re.search(r'(?ms)^  <div class="actions wrap">\s*(.*?)^  </div>\s*(?=</div>\s*$)',head)
if not hit:raise SystemExit("AM198_STOP_CANNOT_ISOLATE_TITLE_ACTIONS")
title_block=hit.group(0)
inner=hit.group(1)
if title_block.count("<a ")!=1 or title_block.count("<details")!=1 or title_block.count("<form")!=1:
    raise SystemExit("AM198_STOP_TITLE_ACTIONS_CONTRACT_DRIFT")
if title_block.count("@if")!=2 or title_block.count("@endif")!=2:
    raise SystemExit("AM198_STOP_STATUS_GUARD_CHANGED")
if ("@csrf" not in title_block or "name=\"reason\"" not in title_block
    or 'required minlength="5"' not in title_block):
    raise SystemExit("AM198_STOP_REASON_AND_CSRF_CONTRACT_MISSING")
if "✏ Edit Job Card" not in title_block or "↩ Return to Estimate Review" not in title_block:
    raise SystemExit("AM198_STOP_EXPECTED_BUTTON_LABELS_CHANGED")
if 'class="pop-form"' not in title_block:
    raise SystemExit("AM198_STOP_REVIEW_FORM_STYLING_CHANGED")
# Preserve exact @if bodies and form, with only classes for consistent toolbar.
new_controls=inner
before_edit='<a class="btn primary" href="{{ '+edit_route+' }}">'
after_edit='<a class="am75-action am198-edit-action" href="{{ '+edit_route+' }}">'
if new_controls.count(before_edit)!=1:
    raise SystemExit("AM198_STOP_EDIT_BUTTON_MARKUP_CHANGED")
new_controls=new_controls.replace(before_edit,after_edit,1)
before_return='<details class="action-pop">'
after_return='<details class="action-pop am198-review-action">'
if new_controls.count(before_return)!=1:
    raise SystemExit("AM198_STOP_REVIEW_DETAILS_CHANGED")
new_controls=new_controls.replace(before_return,after_return,1)
before_summary='<summary class="btn small warning-btn">'
after_summary='<summary class="am75-action am198-review-summary btn small warning-btn">'
if new_controls.count(before_summary)!=1:
    raise SystemExit("AM198_STOP_REVIEW_SUMMARY_CHANGED")
new_controls=new_controls.replace(before_summary,after_summary,1)
# We only remove the title-right action wrapper, not its breadcrumb/title.
new_head=head.replace(title_block,"",1)
if new_head.count('class="actions wrap"')!=0 or new_head.count(page_head)!=1:
    raise SystemExit("AM198_STOP_PAGE_HEAD_CHANGED")
output=old[:h]+new_head+old[stop:]
start=output.index(nav_start)
end=output.index(nav_end,start)
if end-start>6000:raise SystemExit("AM198_STOP_UNEXPECTED_NAV_BOUNDS")
nav=output[start:end]
for key in ("Job Card Summary","Credit Note","Estimate/ Reverse",
            "Correct Customer Name","Delete Job Card"):
    if key not in nav:raise SystemExit("AM198_STOP_ORIGINAL_BAR_ACTION_MISSING_"+key)
# Append exactly two controls in same nav, after existing action shortcuts.
add=r'''
  {{-- AM198_EDIT_RETURN_IN_QUICKBAR: original status-guarded actions relocated; same routes and reason form. --}}
'''+new_controls
output=output[:end]+add+output[end:]
# Only typography/positioning changes. On desktop, compact spacing and
# single toolbar row, on narrow screens natural wrap. Do not clip dropdown.
style=r'''
<style id="AM198_ACTION_BAR_STYLE">
.am75-quick-actions .am198-edit-action{background:#113d78!important;
  color:white!important;border:1px solid #113d78!important;white-space:nowrap!important}
.am75-quick-actions .am198-review-action{display:inline-flex;align-items:center;position:relative;
  flex:0 0 auto;min-width:0;margin:0!important}
.am75-quick-actions .am198-review-summary{display:inline-flex!important;align-items:center;
  list-style:none!important;cursor:pointer!important;white-space:nowrap!important;
  background:#fff4df!important;color:#94520d!important;border:1px solid #e4c48d!important}
.am75-quick-actions .am198-review-summary::-webkit-details-marker{display:none}
.am75-quick-actions .am198-review-action .pop-form{z-index:220!important;
  min-width:min(320px,90vw)}
@media(min-width:1000px){
 .am75-quick-actions{gap:5px!important;padding:7px 9px!important}
 .am75-quick-actions>.am75-action,.am75-quick-actions>.am194-top-button,
 .am75-quick-actions>.am198-review-action>.am75-action{
   padding:8px 9px!important;font-size:10.5px!important;min-height:33px!important}
}
@media(max-width:700px){
 .am75-quick-actions .am198-review-action .pop-form{max-width:90vw}
}
</style>
'''
# Insert the style before nav not inside any conditional. Compiling ensures
# Blade directive/escaped characters preserve original rendered content.
insert_at=output.index(nav_start)
output=output[:insert_at]+style+"\n"+output[insert_at:]
if output.count(marker)!=1:raise SystemExit("AM198_STOP_MARKER")
if output.count(edit_route)!=1 or output.count(review_route)!=1:
    raise SystemExit("AM198_STOP_DUPLICATED_OR_MISSING_ACTION_ROUTES")
nav2=output[output.index(nav_start):output.index(nav_end,output.index(nav_start))]
if not(all(x in nav2 for x in [edit_route,review_route,'id="am197-delete-open"'])
    and 'name="reason"' in nav2 and '@csrf' in nav2):
    raise SystemExit("AM198_STOP_ACTIONS_NOT_IN_SAME_TOOLBAR")
after=output[output.index(page_head):output.index("{{-- FIX61B_ONE_BUTTON_RETURN_JC_V1 --}}")]
if "return-estimate-review" in after or "✏ Edit Job Card" in after:
    raise SystemExit("AM198_STOP_OLD_SECONDARY_BUTTONS_REMAIN")
# Preserve all other content exactly: reconstruct prechange file after
# deleting style and moved nav fragment and restoring title section.
reconstitute=output.replace(style+"\n","",1).replace(add,"",1).replace(new_head,head,1)
if reconstitute!=old:
    raise SystemExit("AM198_STOP_UNRELATED_SOURCE_MUTATED")
with tempfile.TemporaryDirectory(prefix="am198preflight_") as t:
    candidate=Path(t)/"show.blade.php"
    compiled=Path(t)/"compiled.php"
    candidate.write_text(output,"utf8",errors="surrogateescape")
    compiler=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    p=subprocess.run(["php","-r",compiler,str(candidate),str(compiled)],
                     cwd=root,capture_output=True,text=True,timeout=50)
    if p.returncode:raise SystemExit("AM198_STOP_BLADE_COMPILE "+p.stderr[-340:])
    p=subprocess.run(["php","-l",str(compiled)],cwd=root,capture_output=True,text=True,timeout=25)
    if p.returncode:raise SystemExit("AM198_STOP_PHP_SYNTAX "+(p.stdout+p.stderr)[-350:])
print("AM198_EDIT_AND_ESTIMATE_REVIEW_IN_SAME_QUICKBAR True")
print("AM198_ORIGINAL_STATUS_RULES_CSRF_POST_REASON_PRESERVED True")
print("AM198_TITLE_RIGHT_EXTRA_BUTTONS_REMOVED True")
print("AM198_OTHER_ACTIONS_AND_DELETE_AUDIT_UNCHANGED True")
print("AM198_BLADE_AND_COMPILED_PHP_LINT_PASS")
print("AM198_NO_ROUTES_DATABASE_CONTROLLER_FINANCE_WRITES")
if mode=="check":
    print("AM198_CHECK_PASS_NO_WRITES");sys.exit(0)
if bak.exists():raise SystemExit("AM198_STOP_BACKUP_ALREADY_EXISTS")
bak.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(view,bak)
try:atomic(view,output)
except BaseException:
    shutil.copy2(bak,view)
    raise
print("AM198_ACTION_BAR_REORDER_INSTALLED")
print("AM198_RUN_ARTISAN_VIEW_CLEAR")
