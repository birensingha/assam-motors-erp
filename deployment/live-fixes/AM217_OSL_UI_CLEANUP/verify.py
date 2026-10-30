#!/usr/bin/env python3
"""Read-only deployed view QA. No attendance/JC/OSL data writes."""
import sys
from pathlib import Path
if len(sys.argv)!=2:raise SystemExit('Usage: verify.py STAGING_ROOT')
root=Path(sys.argv[1]).resolve()
view=root/'resources/views/job-cards/show.blade.php'
before=root/'storage/app/am-backups/AM217_OSL_UI_CLEANUP/show.blade.php.before_AM217'
if not all(p.is_file() for p in (root/'artisan',view,before)):
    raise SystemExit('AM217_QA_STOP_MISSING_SOURCE_OR_BACKUP')
s=view.read_text('utf8',errors='surrogateescape')
old=before.read_text('utf8',errors='surrogateescape')
def ck(x,name):
    if not x:raise SystemExit('AM217_QA_FAIL_'+name)
    print('AM217_'+name+'=PASS')
ck(s.count('AM217_OSL_UI_CLEANUP')==1,'SINGLE_PATCH_MARKER')
ck('OSL · PURCHASE ONLY' not in s and 'am93-purchase-pending' not in s[s.index('<tbody id="am90-table-body">'):s.index('</tbody>',s.index('<tbody id="am90-table-body">'))],'NO_SUPPLIER_PURCHASE_ONLY_BILLING_ROW')
ck(s.count('@forelse($am217UnbilledPurchases as $p)')==1,'ONLY_UNBILLED_SUPPLIER_CANDIDATES')
ck("!empty($p->billing_line_created)" in s and "$p->labour_line_id" in s,'ACTUAL_BILLING_LINK_FILTER')
ck("toolsBlock.open=false;" in s,'SUPPLIER_CHOOSER_COLLAPSED_BY_DEFAULT')
ck("Use Unbilled Supplier Purchase (optional)" in s,'OPTIONAL_PURCHASE_CHOICE_TEXT')
ck("am217-searchable-osl-master-script" in s and "am217-searchable-osl-master-style" in s,
   'MASTER_SEARCH_COMPONENT_PRESENT')
for label,needle in [
('MASTER_NATIVE_FIELD',"select.name='osl_master_id'"),
('MASTER_CREATE_ROUTE',"route('erp.osl-masters.quick-create')"),
('OSL_BILLING_ROUTE',"route('erp.job-cards.osl.admin-save',$job->id)"),
('SUPPLIER_PURCHASE_REGISTER',"route('erp.fix48.osl-index')"),
('SUPPLIER_PURCHASE_EDIT',"route('erp.fix48.osl-edit',$p->id)"),
('CUSTOMER_GST_FINANCIAL_FORMULA','number_format($am90Taxable+$am90GstAmount,2)'),
]:
    ck(needle in s,label)
for label,needle in [
('BILLING_FORMULA_UNCHANGED','number_format($am90Taxable+$am90GstAmount,2)'),
('OSL_EDIT_UI_UNCHANGED','data-am91-osl-edit'),
('SERVICE_PRICING_UNCHANGED',"makeInput('rate','Rate ₹'"),
('SUPPLIER_REGISTER_ROUTE_UNCHANGED',"route('erp.fix48.osl-index')")]:
    ck(s.count(needle)==old.count(needle),label)
print('AM217_QA_COMPLETE_NO_DATABASE_WRITES')
