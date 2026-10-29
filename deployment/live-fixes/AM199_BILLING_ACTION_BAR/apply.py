#!/usr/bin/env python3
"""AM199: view-only move of Edit Job Card Amount / GST into main JC quickbar.
No route, finance, permission, invoice, form, or backend changes.
Strict staging preflight, real Blade->PHP compile, backup + rollback.
"""
import sys,re,shutil,os,tempfile,subprocess
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit("Use: python3 apply.py check|apply|rollback STAGING_LARAVEL_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
view=root/"resources/views/job-cards/show.blade.php"
backup=root/"storage/app/am-backups/AM199_BILLING_ACTION_BAR/show.blade.php.before_AM199"
marker="AM199_JC_AMOUNT_GST_IN_TOP_ACTION_BAR"
if not (root/"artisan").is_file() or not view.is_file():
    raise SystemExit("AM199_STOP_NOT_LARAVEL_STAGING")
source=view.read_text("utf8",errors="surrogateescape")
def atomic_write(path,s):
    fd,tmp=tempfile.mkstemp(prefix=".am199_",dir=path.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as file:file.write(s)
        os.chmod(tmp,path.stat().st_mode & 0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if mode=='rollback':
    if source.count(marker)!=1 or not backup.is_file():
        raise SystemExit("AM199_ROLLBACK_STOP_MISSING_BACKUP_OR_CHANGED_SOURCE")
    atomic_write(view,backup.read_text("utf8",errors="surrogateescape"))
    print("AM199_ROLLBACK_PREVIOUS_VIEW_RESTORED")
    sys.exit(0)
if marker in source:
    print("AM199_ALREADY_APPLIED")
    sys.exit(0)
route="route('erp.fix48.job-amounts',$job->id)"
nav_start='<nav class="am75-quick-actions" aria-label="Job Card quick actions">'
nav_close='</nav>'
for name,needle,expected in [
    ("BILLING_ACTION_ROUTE",route,1),
    ("TOP_ACTION_BAR",nav_start,1),
    ("AM198_EDIT_ACTION",'class="am75-action am198-edit-action"',1),
    ("AM198_REVIEW_ACTION",'class="action-pop am198-review-action"',1),
    ("AM197_AUDITED_DELETE",'id="am197-delete-open"',1),
    ("BILLING_SECTION_COMMENT","{{-- FIX53_STAGE1: only the Amount/GST action remains in the former three-button bar. --}}",1),
]:
    num=source.count(needle)
    print(f"AM199_PREFLIGHT_{name}={'PASS' if num==expected else 'FAIL_'+str(num)}")
    if num!=expected:
        raise SystemExit("AM199_STOP_SOURCE_DRIFT_"+name)
# Isolate the *single* lower standalone Amount/GST link and its wrapper.
# Intentionally narrow: do not remove any other action or form.
pattern=re.compile(
    r'(?s)\{\{-- FIX53_STAGE1: only the Amount/GST action remains in the former three-button bar\. --\}\}\s*'
    r'<div style="display:flex;flex-wrap:wrap;gap:9px;margin:12px 0 16px">\s*'
    r'(?P<anchor><a href="\{\{ route\('
    r"'erp\.fix48\.job-amounts',\$job->id"
    r'\) \}\}" style="[^"]+">✎ Edit Job Card Amount / GST</a>)'
    r'\s*</div>\s*'
)
found=list(pattern.finditer(source))
if len(found)!=1:
    raise SystemExit("AM199_STOP_CANNOT_SAFELY_ISOLATE_LOWER_BILLING_SECTION_"+str(len(found)))
match=found[0]
anchor=match.group('anchor')
if anchor.count("<a ")!=1 or anchor.count("</a>")!=1 or anchor.count(route)!=1:
    raise SystemExit("AM199_STOP_BILLING_ANCHOR_CONTRACT")
# Retain original href and visible label, change only styling to match toolbar.
new_anchor=anchor.replace('<a href=', '<a class="am75-action am199-billing-action" href=',1)
new_anchor,n=re.subn(r' style="[^"]+"','',new_anchor,count=1)
if n!=1:
    raise SystemExit("AM199_STOP_ORIGINAL_INLINE_STYLE_MISSING")
if route not in new_anchor or "✎ Edit Job Card Amount / GST" not in new_anchor:
    raise SystemExit("AM199_STOP_ROUTE_OR_TEXT_MUTATED")
without=source[:match.start()] + "\n{{-- AM199_LOWER_DUPLICATE_BILLING_BUTTON_REMOVED --}}\n" + source[match.end():]
if without.count(nav_start)!=1:
    raise SystemExit("AM199_STOP_UNEXPECTED_BAR_AFTER_REPLACEMENT")
begin=without.index(nav_start)
end=without.index(nav_close,begin)
if end-begin>12000:
    raise SystemExit("AM199_STOP_UNEXPECTED_BAR_SIZE")
nav=without[begin:end]
for x in ("Job Card Summary","Credit Note","Estimate/ Reverse",
          "Correct Customer Name","Delete Job Card","Edit Job Card",
          "Return to Estimate Review"):
    if x not in nav:raise SystemExit("AM199_STOP_MISSING_EXISTING_ACTION_"+x)
# Add new billing action after existing buttons in same nav. On narrower
# screens, existing flex-wrap preserves accessibility with no horizontal clip.
insert=("\n  {{-- "+marker+": only moved FIX48 link; original amount/GST page unchanged. --}}\n"
        "  "+new_anchor+"\n")
candidate=without[:end]+insert+without[end:]
# CSS keeps it in visual language of the original bar.
style=r'''
<style id="AM199_BILLING_ACTION_STYLE">
.am75-quick-actions .am199-billing-action{
  display:inline-flex!important;align-items:center!important;justify-content:center!important;
  color:#fff!important;background:#096bb2!important;border:1px solid #096bb2!important;
  white-space:nowrap!important;flex:0 0 auto!important;min-height:33px!important;
  padding:8px 9px!important;font-size:10.5px!important;font-weight:800!important;
  border-radius:8px!important;text-decoration:none!important
}
.am75-quick-actions .am199-billing-action:hover,
.am75-quick-actions .am199-billing-action:focus-visible{
  background:#06568f!important;outline:2px solid #7ab5e8!important
}
@media(min-width:1000px){
  .am75-quick-actions{column-gap:5px!important;row-gap:6px!important}
}
@media(max-width:700px){
  .am75-quick-actions .am199-billing-action{white-space:normal!important}
}
</style>
'''
if candidate.count(nav_start)!=1:
    raise SystemExit("AM199_STOP_TOOLBAR_GUARD")
candidate=candidate.replace(nav_start,style+"\n"+nav_start,1)
# Only one link, and in same toolbar.
nav2=candidate[candidate.index(nav_start):candidate.index(nav_close,candidate.index(nav_start))]
if nav2.count(route)!=1 or candidate.count(route)!=1:
    raise SystemExit("AM199_STOP_DUPLICATE_BILLING_ROUTE")
if "id=\"am197-delete-open\"" not in nav2 or "am198-edit-action" not in nav2:
    raise SystemExit("AM199_STOP_OTHER_ACTIONS_LOST")
if candidate.count(marker)!=1 or 'id="AM199_BILLING_ACTION_STYLE"' not in candidate:
    raise SystemExit("AM199_STOP_MARKER_MISSING")
if "FIX53_STAGE1: only the Amount/GST action remains" in candidate:
    raise SystemExit("AM199_STOP_STANDALONE_SECTION_REMAINS")
# Strict source reconstruction verifies unrelated markup stays byte-for-byte.
reverted=candidate.replace(style+"\n","",1).replace(insert,"",1)
reverted=reverted.replace("\n{{-- AM199_LOWER_DUPLICATE_BILLING_BUTTON_REMOVED --}}\n",source[match.start():match.end()],1)
if reverted!=source:
    raise SystemExit("AM199_STOP_UNRELATED_SOURCE_CHANGED")
with tempfile.TemporaryDirectory(prefix="am199candidate_") as folder:
    blade=Path(folder)/"job-show.blade.php"
    php=Path(folder)/"compiled.php"
    blade.write_text(candidate,"utf8",errors="surrogateescape")
    compile_cmd=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    result=subprocess.run(["php","-r",compile_cmd,str(blade),str(php)],
                          cwd=root,capture_output=True,text=True,timeout=60)
    if result.returncode:
        raise SystemExit("AM199_STOP_BLADE_COMPILE_"+result.stderr[-450:])
    result=subprocess.run(["php","-l",str(php)],cwd=root,capture_output=True,text=True,timeout=35)
    if result.returncode:
        raise SystemExit("AM199_STOP_COMPILED_PHP_SYNTAX_"+(result.stdout+result.stderr)[-400:])
print("AM199_BILLING_LINK_MOVED_TO_SAME_TOP_ACTION_BAR True")
print("AM199_LOWER_STANDALONE_BUTTON_REMOVED True")
print("AM199_EDIT_AND_REVIEW_AND_DELETE_UNCHANGED True")
print("AM199_FINANCIAL_ROUTE_AND_LABEL_PRESERVED True")
print("AM199_FULL_BLADE_COMPILE_PHP_LINT_PASS")
print("AM199_DB_ROUTES_CONTROLLERS_INVOICES_UNCHANGED True")
if mode=='check':
    print("AM199_CHECK_PASS_NO_WRITES");sys.exit(0)
if backup.exists():
    raise SystemExit("AM199_STOP_BACKUP_ALREADY_EXISTS")
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(view,backup)
try:atomic_write(view,candidate)
except BaseException:
    shutil.copy2(backup,view)
    raise
print("AM199_TOP_ACTION_BAR_BILLING_LINK_INSTALLED")
print("AM199_RUN_ARTISAN_VIEW_CLEAR")
