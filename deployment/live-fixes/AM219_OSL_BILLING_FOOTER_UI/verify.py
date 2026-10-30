#!/usr/bin/env python3
"""AM219 read-only acceptance: only one scoped CSS display rule added."""
from pathlib import Path
import sys,re
if len(sys.argv)!=2:raise SystemExit("Usage: verify.py STAGING_APP_ROOT")
root=Path(sys.argv[1]).resolve()
view=root/"resources/views/job-cards/show.blade.php"
backup=root/"storage/app/am-backups/AM219_OSL_BILLING_FOOTER_UI/show.blade.php.before_AM219"
if not all(p.is_file() for p in (root/"artisan",view,backup)):
    raise SystemExit("AM219_QA_MISSING_VIEW_OR_BACKUP")
now=view.read_text("utf8",errors="surrogateescape")
before=backup.read_text("utf8",errors="surrogateescape")
added='''<style id="am219-hide-purchase-only-billing">
/* AM219_HIDE_PURCHASE_ONLY_IN_CUSTOMER_BILLING: supplier cost belongs in Purchase Register. */
.am93-purchase-pending { display: none !important; }
</style>
'''
def ck(ok,label):
    if not ok:raise SystemExit("AM219_QA_FAIL_"+label)
    print("AM219_"+label+"=PASS")
ck(now.count(added)==1,"ONE_SCOPED_CSS_RULE")
ck(now.replace(added,"",1)==before,"ENTIRE_ORIGINAL_BLADE_PRESERVED")
ck(now.count('class="am93-purchase-pending"')==1,"ORIGINAL_SUPPLIER_ROW_DATA_UNTOUCHED")
ck(now.count("route('erp.fix48.osl-edit',$am93Purchase->id)")==1,"SUPPLIER_PURCHASE_REVIEW_LINK_PRESERVED")
ck(now.count("route('erp.job-cards.osl.admin-save',$job->id)")==1,"CUSTOMER_OSL_BILLING_ROUTE_PRESERVED")
ck(now.count("route('erp.fix48.osl-index')")==1,"SUPPLIER_OSL_REGISTER_ROUTE_PRESERVED")
ck(now.count("route('erp.job-cards.delete-unused', $job->id)")==1,"JOB_CARD_DELETE_AUDIT_UNCHANGED")
ck(now.count("AM219_HIDE_PURCHASE_ONLY_IN_CUSTOMER_BILLING")==1,"UI_PATCH_MARKER_SINGLE")
print("AM219_READONLY_VIEW_QA_PASS")
