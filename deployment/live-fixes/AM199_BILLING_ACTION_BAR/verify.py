#!/usr/bin/env python3
"""AM199 independent read-only deployed view/backup verification."""
from pathlib import Path
import sys,re
if len(sys.argv)!=2:raise SystemExit("Use: python3 verify.py STAGING_ROOT")
root=Path(sys.argv[1]).resolve()
view=root/"resources/views/job-cards/show.blade.php"
backup=root/"storage/app/am-backups/AM199_BILLING_ACTION_BAR/show.blade.php.before_AM199"
if not all(p.is_file() for p in (root/"artisan",view,backup)):
    raise SystemExit("AM199_QA_STOP_SOURCE_OR_BACKUP_MISSING")
now=view.read_text("utf8",errors="surrogateescape")
before=backup.read_text("utf8",errors="surrogateescape")
def check(yes,label):
    if not yes:raise SystemExit("AM199_QA_FAIL_"+label)
    print("AM199_"+label+"=PASS")
route="route('erp.fix48.job-amounts',$job->id)"
nav_start='<nav class="am75-quick-actions" aria-label="Job Card quick actions">'
nav_end='</nav>'
check(now.count("AM199_JC_AMOUNT_GST_IN_TOP_ACTION_BAR")==1,"SINGLE_MARKER")
check(now.count(nav_start)==1,"SINGLE_MAIN_ACTION_BAR")
start=now.index(nav_start)
end=now.index(nav_end,start)
nav=now[start:end]
check(now.count(route)==1 and nav.count(route)==1,"BILLING_ROUTE_ONLY_IN_ACTION_BAR")
check(nav.count("✎ Edit Job Card Amount / GST")==1,"FINANCIAL_BUTTON_VISIBLE_IN_BAR")
check('class="am75-action am199-billing-action"' in nav,"MATCHES_ACTION_BAR_STYLE")
check("FIX53_STAGE1: only the Amount/GST action remains" not in now,
      "OLD_STANDALONE_BLUE_PANEL_REMOVED")
for x in ("Job Card Summary","Credit Note","Estimate/ Reverse","Correct Customer Name",
          "Delete Job Card","Edit Job Card","Return to Estimate Review"):
    check(x in nav,"EXISTING_"+x.upper().replace(" ","_").replace("/","_"))
check(now.count('id="am197-delete-open"')==1,"SINGLE_AM193_DELETE_BUTTON")
check(now.count("route('erp.job-cards.delete-unused', $job->id)")==1,
      "AM193_AUDITED_FORM_UNCHANGED")
check(now.count("route('erp.job-cards.return-estimate-review',$job->id)")==1,
      "RETURN_ESTIMATE_POST_ROUTE_UNCHANGED")
check(now.count("route('erp.job-cards.edit',$job->id)")==1,
      "JOB_CARD_EDIT_ROUTE_UNCHANGED")
m_old=re.search(r'<a href="\{\{ route\(\'erp.fix48.job-amounts\',\$job->id\) \}\}" style="[^"]+">([^<]+)</a>',before)
m_new=re.search(r'<a class="am75-action am199-billing-action" href="\{\{ route\(\'erp.fix48.job-amounts\',\$job->id\) \}\}">([^<]+)</a>',now)
check(m_old is not None and m_new is not None
      and m_old.group(1)==m_new.group(1),
      "ORIGINAL_FINANCIAL_HREF_AND_LABEL_PRESERVED")
check(now.count("id=\"AM199_BILLING_ACTION_STYLE\"")==1,"TOP_BAR_BILLING_STYLING_PRESENT")
print("AM199_QA_COMPLETE_NO_DATABASE_WRITES")
