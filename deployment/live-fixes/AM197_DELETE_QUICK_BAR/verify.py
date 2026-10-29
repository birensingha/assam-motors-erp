#!/usr/bin/env python3
"""AM197 read-only deployed source & backup diff acceptance."""
from pathlib import Path
import sys,re,subprocess
if len(sys.argv)!=2:raise SystemExit("Usage: verify.py STAGING_ROOT")
root=Path(sys.argv[1]).resolve()
source_path=root/"resources/views/job-cards/show.blade.php"
backup=root/"storage/app/am-backups/AM197_DELETE_QUICK_BAR/show.blade.php.before_AM197"
route=root/"routes/web.php"
ctrl=root/"app/Http/Controllers/Web/AdminUnusedJobCardDeleteController.php"
if not all(x.is_file() for x in [root/"artisan",source_path,backup,route,ctrl]):
    raise SystemExit("AM197_VERIFY_MISSING_SOURCE_OR_BACKUP")
source=source_path.read_text("utf8",errors="surrogateescape")
original=backup.read_text("utf8",errors="surrogateescape")
def require(ok,name):
    if not ok:raise SystemExit("AM197_VERIFY_FAIL_"+name)
    print("AM197_"+name+"=PASS")
require(source.count("AM197_SINGLE_AUDITED_DELETE_QUICKBAR")==1,"FIFTH_BUTTON_MARKER")
require(source.count("AM193_UNUSED_JOB_CARD_DELETE_ACTION")==1,"AM193_ORIGINAL_AUDITED_MARKER")
require("Delete Empty Job Card" not in source,"LEGACY_LARGE_PANEL_REMOVED")
require("route('erp.job-cards.destroy',$job->id)" not in source,"LEGACY_DELETE_SUBMIT_REMOVED")
require(source.count("route('erp.job-cards.delete-unused', $job->id)")==1,"ONLY_ONE_AUDITED_DELETE_FORM")
require(source.count('id="am197-delete-open"')==1 and source.count('id="am197-delete-modal"')==1,"SINGLE_BUTTON_MODAL")
require("am197-backdrop[hidden]{display:none!important}" in source,"POPUP_HIDDEN_BY_DEFAULT")
require("data-restore=\"{{ $errors->has('delete') ? '1':'0' }}\"" in source,"DELETE_SERVER_ERROR_REOPENS_POPUP")
require("&& (bool)$am197Actor->active && $am197Actor->isAdmin()" in source,"ADMIN_ONLY_UI")
require("AM197_DELETE_MODAL_SCRIPT" in source and "aria-modal=\"true\"" in source,"ACCESSIBLE_POPUP")
require("name=\"reason\"" in source and "name=\"confirmation\"" in source,"REASON_AND_DELETE_CONFIRMATION")
require("am193_deleted_job_card_audits" in ctrl.read_text("utf8",errors="surrogateescape"),"SERVER_AUDIT_UNCHANGED")
for needle in ("Job Card Summary","Credit Note","Estimate/ Reverse","Correct Customer Name"):
    nav_start=source.index('<nav class="am75-quick-actions" aria-label="Job Card quick actions">')
    nav_end=source.index("</nav>",nav_start)
    require(needle in source[nav_start:nav_end],"QUICKBAR_"+needle.replace(" ","_").replace("/","_").upper())
require(nav_start<source.index('id="am197-delete-open"')<nav_end,"DELETE_BUTTON_INSIDE_ORIGINAL_BAR")
require(source.index("AM193_UNUSED_JOB_CARD_DELETE_ACTION")>nav_end,"AM193_FORM_MOVED_BELOW_BAR")
def form(text):
    m=re.search(r'<form method="POST" action="\{\{ route\(\'erp.job-cards.delete-unused\', \$job->id\) \}\}".*?</form>',text,re.S)
    require(m is not None,"AM193_FORM_PRESENT");return m.group()
require(form(source)==form(original),"AM193_FORM_BYTE_IDENTICAL")
php=subprocess.run(["php","artisan","route:list","--name=erp.job-cards.delete-unused","--no-ansi"],
    cwd=root,capture_output=True,text=True,timeout=40)
require(php.returncode==0 and "delete-unused" in php.stdout,"ADMIN_DELETE_ROUTE_STILL_REGISTERED")
print("AM197_ALL_CHECKS_PASS_READ_ONLY")
