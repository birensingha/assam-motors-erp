#!/usr/bin/env python3
"""AM198 read-only Staging source/backup acceptance, no JC edits or POSTs."""
import sys,re,subprocess
from pathlib import Path
if len(sys.argv)!=2:raise SystemExit("Usage: verify.py STAGING_ROOT")
root=Path(sys.argv[1]).resolve()
view=root/"resources/views/job-cards/show.blade.php"
bak=root/"storage/app/am-backups/AM198_ACTION_BAR_REORDER/show.blade.php.before_AM198"
if not all(p.is_file() for p in [root/"artisan",view,bak]):
    raise SystemExit("AM198_QA_STOP_EXPECTED_VIEW_AND_BACKUP_MISSING")
current=view.read_text("utf8",errors="surrogateescape")
before=bak.read_text("utf8",errors="surrogateescape")
def must(v,name):
    if not v:raise SystemExit("AM198_QA_FAIL_"+name)
    print("AM198_"+name+"=PASS")
navstart='<nav class="am75-quick-actions" aria-label="Job Card quick actions">'
navend='</nav>'
must(current.count("AM198_EDIT_RETURN_IN_QUICKBAR")==1,"ONE_RELOCATION")
a=current.index(navstart);b=current.index(navend,a)
nav=current[a:b]
must(current.count(navstart)==1,"SINGLE_TOP_QUICKBAR")
must(nav.count("Edit Job Card")==1,"EDIT_IN_SAME_BAR")
must(nav.count("↩ Return to Estimate Review")==1,"REVIEW_IN_SAME_BAR")
for label in ("Job Card Summary","Credit Note","Estimate/ Reverse","Correct Customer Name","Delete Job Card"):
    must(label in nav,"ORIGINAL_ACTION_"+label.replace(" ","_").replace("/","_").upper())
must(nav.count("route('erp.job-cards.edit',$job->id)")==1,"EDIT_URL_UNCHANGED")
must(nav.count("route('erp.job-cards.return-estimate-review',$job->id)")==1,"REVIEW_POST_ROUTE_UNCHANGED")
must(nav.count('<details class="action-pop am198-review-action">')==1,"REVIEW_CONFIRMATION_POPOVER_RETAINED")
must(nav.count("name=\"reason\"")==1 and "required minlength=\"5\"" in nav,"REVIEW_REASON_REQUIRED")
must("@csrf" in nav and "class=\"pop-form\"" in nav,"REVIEW_CSRF_FORM_UNCHANGED")
must("@if(!in_array($amJobStatusNorm,['closed','invoiced','cancelled'],true))" in nav,
     "EDIT_STATUS_GUARD_UNCHANGED")
must("@if(in_array($amJobStatusNorm,['wip','in progress','in-progress','work in progress'],true))" in nav,
     "REVIEW_STATUS_GUARD_UNCHANGED")
start=current.index('<div class="page-head">')
end=current.index("{{-- FIX61B_ONE_BUTTON_RETURN_JC_V1 --}}",start)
head=current[start:end]
must("class=\"actions wrap\"" not in head,"NO_DUPLICATE_TITLE_RIGHT_ACTIONS")
must("return-estimate-review" not in head and "Edit Job Card" not in head,
     "PAGE_HEAD_CLEAN")
must(current.count('id="am197-delete-open"')==1 and current.count('id="am197-delete-modal"')==1,
     "AM197_SINGLE_DELETE_WORKFLOW_INTACT")
must("AM194_COMPACT_CUSTOMER_CORRECTION" in current and "FIX75_JC_THREE_ACTION_BAR" in current,
     "OTHER_MODULES_PRESENT")
# Exact original wrapper contents (including CSRF/validation) must be present,
# except for the three toolbar-appropriate classes that were changed.
oh=before[before.index('<div class="page-head">'):before.index("{{-- FIX61B_ONE_BUTTON_RETURN_JC_V1 --}}")]
match=re.search(r'(?ms)^  <div class="actions wrap">\s*(.*?)^  </div>\s*(?=</div>\s*$)',oh)
must(match is not None,"ORIGINAL_ACTION_SOURCE_FOUND")
orig=match.group(1)
expected=orig.replace('<a class="btn primary"','<a class="am75-action am198-edit-action"',1)
expected=expected.replace('<details class="action-pop">','<details class="action-pop am198-review-action">',1)
expected=expected.replace('<summary class="btn small warning-btn">','<summary class="am75-action am198-review-summary btn small warning-btn">',1)
must(expected in nav,"ORIGINAL_FORMS_AND_CONDITIONS_PRESERVED")
print("AM198_QA_COMPLETE_NO_DATA_WRITES")
