#!/usr/bin/env python3
"""FIX75: JC Show quick action bar exactly beneath blue top navigation.

- Job Card Summary -> existing one-page print
- Credit Note -> existing billing correction/return flow
- Estimate/ Reverse -> existing estimate create/revise permission routing
- remove redundant Purchase for Job & Expense for Job buttons only
No changes to functionality, purchasing, expenses, finance records, or routes.
"""
from pathlib import Path
import sys,os,tempfile,hashlib
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
target=root/'resources/views/job-cards/show.blade.php'
raw=target.read_bytes()
expected='e0fc6d80735e3752b8c7ec75478add5005e1d9674352b68a2a386b041c4f7014'
new_marker='FIX75_JC_THREE_ACTION_BAR'
if new_marker in raw.decode('utf-8',errors='surrogateescape'):
 print('FIX75_ALREADY_APPLIED');sys.exit(0)
actual=hashlib.sha256(raw).hexdigest()
print('FIX75_ORIGINAL_SHA256',actual)
if actual!=expected:raise SystemExit('FIX75_SOURCE_CHANGED_REQUIRE_REVIEW')
src=raw.decode('utf-8',errors='surrogateescape')
original=src
def once(a,b,name):
 global src
 count=src.count(a)
 print('FIX75_ANCHOR',name,count)
 if count!=1:raise RuntimeError('FIX75_AMBIGUOUS_'+name)
 src=src.replace(a,b,1)
# Remove the earlier free-floating two-button row.
oldtop="""{{-- FIX69_ONE_PAGE_PRINT — shortcut above full interactive JC --}}
<div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;margin:6px 0 12px">
 <a class="btn primary" href="{{ route('erp.job-cards.summary-print',$job->id) }}" target="_blank" rel="noopener">🖨 One-Page Printable Job Card Summary</a>
 <a class="btn" href="{{ route('erp.job-cards.credit-correction',$job->id) }}">↩ Billing Correction / Return to JC</a>
</div>
"""
once(oldtop,'','original_two_button_row')
# Preserve original dynamically-protected estimate action. Move it from page-head.
startToken='    @if($supplementaryReady)\n'
endToken='    @if(in_array($amJobStatusNorm,'
if src.count(startToken)!=1:raise RuntimeError('FIX75_ESTIMATE_CONDITION_NOT_UNIQUE')
start=src.index(startToken)
end=src.index(endToken,start)
segment=src[start:end]
for tok in ["route('erp.estimates.create',$job->id)","$supplementaryReady","$diagnosisReady","Complete Diagnosis First"]:
 if tok not in segment:raise RuntimeError('FIX75_ESTIMATE_GUARD_NOT_PRESERVED_'+tok)
src=src[:start]+src[end:]
# Remove only the two shortcut links; do not delete any backend route.
once('    <a class="btn" href="{{ route(\'erp.job.purchase\',$job->id) }}">Purchase for Job</a>\n',
     '','purchase_shortcut')
once('    <a class="btn" href="{{ route(\'erp.expenses.create\',[\'job\'=>$job->id]) }}">Expense for Job</a>\n',
     '','expense_shortcut')
# Keep route and conditional intact, only update the user-specified button label.
# Estimate control remains gated; supplementary estimate uses same destination.
segment=segment.replace('＋ Supplementary Estimate','Estimate/ Reverse')
segment=segment.replace('Estimate / Revise','Estimate/ Reverse')
# Same third button text even while Diagnosis permission is pending; do not
# enable its route before diagnosis approval.
segment=segment.replace(
 '<a class="btn warning-btn" href="#diagnosis-panel">Complete Diagnosis First</a>',
 '<span class="am75-action am75-warning" aria-disabled="true" title="Complete Diagnosis First">Estimate/ Reverse</span>')
if segment.count('Estimate/ Reverse')!=3:raise RuntimeError('FIX75_ESTIMATE_LABEL_COUNT')
segment=segment.replace('class="btn primary"','class="am75-action"',2)
segment=segment.replace('class="btn warning-btn"','class="am75-action am75-warning"')
# The only markup physically above the Job Card header content.
css="""<style id="FIX75_JC_THREE_ACTION_BAR">
/* One horizontal quick-action row immediately after the shared ERP header. */
.am75-quick-actions{
 display:flex;align-items:center;justify-content:flex-start;flex-wrap:nowrap;
 gap:9px;overflow-x:auto;overflow-y:hidden;max-width:100%;
 margin:2px 0 14px;padding:10px 11px;background:#eff5fc;
 border:1px solid #cadcee;border-radius:11px;
 scrollbar-width:thin;scrollbar-color:#a6bfda transparent;
}
.am75-quick-actions .am75-action{
 display:inline-flex!important;align-items:center!important;justify-content:center!important;
 flex:0 0 auto!important;min-height:38px!important;white-space:nowrap!important;
 padding:9px 16px!important;background:#164a82!important;color:#fff!important;
 border:1px solid #164a82!important;border-radius:8px!important;
 text-decoration:none!important;font-size:12px!important;font-weight:850!important;
 line-height:1.25!important;box-shadow:none!important;
}
.am75-quick-actions .am75-action:hover,.am75-quick-actions .am75-action:focus-visible{
 background:#2167ac!important;color:#fff!important;border-color:#2167ac!important;
 outline:2px solid #93c5fd;outline-offset:1px;
}
.am75-quick-actions .am75-action.am75-credit{
 background:#ffffff!important;color:#164a82!important;border-color:#8aafd6!important;
}
.am75-quick-actions .am75-action.am75-credit:hover{
 background:#e1edff!important;color:#123d71!important;
}
.am75-quick-actions .am75-action.am75-warning{
 background:#fff5e3!important;color:#75480f!important;border-color:#ead1a6!important;
}
@media(max-width:650px){
 .am75-quick-actions{padding:8px;gap:7px}
 .am75-quick-actions .am75-action{font-size:11px!important;padding:9px 12px!important}
}
</style>
"""
toolbar="""{{-- FIX75_JC_THREE_ACTION_BAR: exactly three requested shortcuts, no duplicate purchase/expense links. --}}
<nav class="am75-quick-actions" aria-label="Job Card quick actions">
  <a class="am75-action" href="{{ route('erp.job-cards.summary-print',$job->id) }}" target="_blank" rel="noopener">Job Card Summary</a>
  <a class="am75-action am75-credit" href="{{ route('erp.job-cards.credit-correction',$job->id) }}">Credit Note</a>
"""+segment+"""</nav>
"""
anchor='@endphp\n<div class="page-head">'
once(anchor,'@endphp\n'+toolbar+'<div class="page-head">','toolbar_immediately_before_JC_header')
once('<style id="FIX37_JOB_CARD_CORE_UI">',css+'<style id="FIX37_JOB_CARD_CORE_UI">','responsive_toolbar_style')
# Confirm preexisting action routes and all other JC elements untouched.
for tok in ["route('erp.job-cards.summary-print',$job->id)",
            "route('erp.job-cards.credit-correction',$job->id)"]:
 if src.count(tok)!=1:raise RuntimeError('FIX75_DUPLICATE_MISSING_ACTION_'+tok)
# Two exclusive branches share the estimate route; only one renders.
if src.count("route('erp.estimates.create',$job->id)")!=original.count("route('erp.estimates.create',$job->id)"):
 raise RuntimeError('FIX75_ESTIMATE_ROUTES_CHANGED')
if toolbar.count("route('erp.estimates.create',$job->id)")!=2:
 raise RuntimeError('FIX75_ESTIMATE_TOOLBAR_BRANCHES_CHANGED')
# Other sections may discuss supplementary estimates; check former top buttons only.
for tok in ['Purchase for Job','Expense for Job','One-Page Printable Job Card Summary',
            'Billing Correction / Return to JC']:
 if tok in src:raise RuntimeError('FIX75_REDUNDANT_ACTION_REMAINS_'+tok)
if src.count('class="am75-quick-actions"')!=1:raise RuntimeError('FIX75_TOOLBAR_NOT_UNIQUE')
if src.count('Estimate/ Reverse')!=3:raise RuntimeError('FIX75_ESTIMATE_NOT_PRESERVED')
if original.count("route('erp.job-cards.edit',$job->id)")!=src.count("route('erp.job-cards.edit',$job->id)"):
 raise RuntimeError('FIX75_EDIT_ROUTE_CHANGED')
if src.count("@include('job-cards.lifecycle-panel')")!=1:raise RuntimeError('FIX75_WORKFLOW_CHANGED')
if mode=='check':
 print('FIX75_ACTION_BAR_PREFLIGHT_PASS_ONLY_VIEW_CHANGES')
 sys.exit(0)
fd,temp=tempfile.mkstemp(prefix='.fix75_',dir=target.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as handle:handle.write(src)
 os.chmod(temp,target.stat().st_mode & 0o777)
 os.replace(temp,target)
finally:
 if os.path.exists(temp):os.unlink(temp)
print('FIX75_JC_ACTION_BAR_APPLIED_NO_FINANCIAL_WRITES')
