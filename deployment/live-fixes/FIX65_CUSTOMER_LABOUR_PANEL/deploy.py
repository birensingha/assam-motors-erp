#!/usr/bin/env python3
"""FIX65: Replace customer Labour billing presentation in JC show with NEW Admin partial.
Keep all ROT operational forms, routes and mutations exactly as before.
Read-only JS/customer totals; no database changes.
"""
from pathlib import Path
import hashlib, os, sys, tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: deploy.py check|apply LARAVEL_APP_ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file() and (root/'routes/web.php').is_file()
target=root/'resources/views/job-cards/show.blade.php'
part=root/'resources/views/job-cards/partials/am65-customer-labour.blade.php'
local=Path(__file__).resolve().parent/'customer-labour.blade.php'
original=target.read_text(encoding='utf8')
newpart=local.read_text(encoding='utf8')
marker="AM65_CUSTOMER_LABOUR_PROTOTYPE"
sha="2d007280d2a0d9d4a5817aebfe89f4b8cb2f47b7329a46c3407a760f6ab85434"
if marker in original or part.exists():
    if (part.exists() and part.read_text(encoding='utf8')==newpart and
        original.count("@include('job-cards.partials.am65-customer-labour')")==1):
        print('FIX65_ALREADY_APPLIED')
        sys.exit(0)
    raise SystemExit('FIX65_CONFLICT_OR_PARTIAL_INSTALL_ABORT')
current=hashlib.sha256(original.encode()).hexdigest()
print('FIX65_LIVE_SOURCE_SHA',current)
if current!=sha:raise SystemExit('FIX65_SOURCE_CHANGED_REQUIRES_REAUDIT')
startAnchor='  {{-- AM_JC_ITEMWISE_BILLING_READONLY_V1'
endAnchor='  <div class="rot-list">'
lastAnchor='  @empty<div class="empty">No Labour/ROT lines on this Job Card.</div>@endforelse\n  </div>\n</div>'
for key,token in [('labour_billing_start',startAnchor),('workshop_card',endAnchor),('rot_last_close',lastAnchor)]:
    n=original.count(token)
    print('FIX65_ANCHOR',key,n)
    if n!=1:raise SystemExit('FIX65_ANCHOR_MISMATCH_'+key)
start=original.index(startAnchor)
end=original.index(endAnchor,start)
assert start<end
assert original.count("@include('job-cards.rot-billing-control')")==1
assert original.count("@include('job-cards.rot-time-editor')")==1
modified=original[:start]+"""  {{-- FIX65: Customer billing UI separate from operational ROT controls. --}}
  @include('job-cards.partials.am65-customer-labour')

  <details class="am65-workshop-toggle" id="am65-rot-controls">
    <summary><span>🔧 Workshop ROT / Mechanic Controls<br><small>Assign, Start, Pause/Resume, Complete, Reopen and History — click to expand</small></span></summary>
"""+original[end:]
modified=modified.replace(lastAnchor,
 '  @empty<div class="empty">No Labour/ROT lines on this Job Card.</div>@endforelse\n  </div>\n  </details>\n</div>',1)
oldHeader='<h2 class="section-title">Labour / ROT Control</h2>'
newHeader='<h2 class="section-title">Labour / ROT — Customer Billing</h2>'
if modified.count(oldHeader)!=1:raise SystemExit('FIX65_HEADER_ANCHOR_MISMATCH')
modified=modified.replace(oldHeader,newHeader,1)
oldSub='<p class="muted">Admin can assign/change mechanic, Pause/Resume, Complete and Re-open + Reset End Time. Start/Resume still requires active staff Check-In and WIP Job Card.</p>'
newSub='<p class="muted">Customer labour charges upar item-wise; staff/mechanic ROT control neeche alag expandable panel mein. Existing ROT attendance and billing restrictions apply.</p>'
if modified.count(oldSub)!=1:raise SystemExit('FIX65_SUBTITLE_ANCHOR_MISMATCH')
modified=modified.replace(oldSub,newSub,1)
for token in ["@include('dashboard.partials.rot-live-clock'","@include('job-cards.rot-billing-control')","@include('job-cards.rot-time-editor')",
              "route('erp.rot.start'","route('erp.rot.pause'","route('erp.rot.resume'","route('erp.rot.reopen'"]:
    if modified.count(token)!=original.count(token):
        raise SystemExit('FIX65_ORIGINAL_ACTION_CHANGED_'+token)
for token in [marker,'am65BillableTaxable','am65-search','am65-table','INCLUDED',"route('erp.job-cards.credit-correction'"]:
    if token not in newpart:raise SystemExit('FIX65_MISSING_PARTIAL_CONTRACT_'+token)
assert modified.count("<details class=\"am65-workshop-toggle\"")==1
assert modified.count("@include('job-cards.partials.am65-customer-labour')")==1
assert part.parent.is_dir() or not part.parent.exists()
if mode=='check':
    print('FIX65_PREFLIGHT_PASS_ONE_LAYOUT_PLUS_ONE_NEW_PARTIAL_NO_DB_WRITES')
    sys.exit(0)
part.parent.mkdir(parents=True,exist_ok=True)
def replace_atomic(p,content,perm):
    fd,tmp=tempfile.mkstemp(prefix='.fix65_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8') as f:f.write(content)
        os.chmod(tmp,perm)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
replace_atomic(part,newpart,0o644)
replace_atomic(target,modified,target.stat().st_mode & 0o777)
print('FIX65_PROTOTYPE_CUSTOMER_LABOUR_DEPLOYED_NO_DATABASE_WRITES')
