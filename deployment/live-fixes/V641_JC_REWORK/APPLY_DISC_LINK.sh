#!/usr/bin/env bash
# V641 UI navigation: direct ROT discount + visible amount editor in Job Card + WIP-stage banner.
set -euo pipefail
ROOT="$1"
test -f "$ROOT/artisan"
export V641_ROOT="$ROOT"
python3 - <<'PY'
from pathlib import Path
from datetime import datetime,timezone
import os,subprocess
root=Path(os.environ['V641_ROOT'])
files={
 'jc':'resources/views/job-cards/show.blade.php',
 'edit':'resources/views/workshop-controls/job-amounts.blade.php',
}
old={k:(root/p).read_bytes() for k,p in files.items()}
def once(s,a,b,label):
 if b in s:return s
 n=s.count(a)
 if n!=1:raise RuntimeError('V641_UI_ANCHOR '+label+' count='+str(n))
 return s.replace(a,b,1)
jc=old['jc'].decode('utf-8','surrogateescape')
# Only inside the direct Labour form (Parts entry also has a Rate field).
head,rest=jc.split('<form method="post" action="{{ route(\'erp.job-cards.labour.direct\',$job->id) }}" class="jc-entry-form">@csrf',1)
anchor='<label>Rate<input type="number" step="0.01" min="0" name="labour_rate" placeholder="Master rate"></label>'
if rest.count(anchor)!=1:raise RuntimeError('Labour direct rate label unexpected')
discount='<label>Discount %<input type="number" step="0.01" min="0" max="100" name="discount_percent" value="0" title="Customer discount on this Labour/ROT item"></label>'
if discount not in rest:rest=rest.replace(anchor,anchor+'\n        '+discount,1)
jc=head+'<form method="post" action="{{ route(\'erp.job-cards.labour.direct\',$job->id) }}" class="jc-entry-form">@csrf'+rest
link="""  {{-- V641_FINANCIAL_NAV: show billing editor at the operational stage where saves are permitted. --}}
  @if(in_array(strtolower(trim((string)$job->status)),['wip','in progress','in-progress','work in progress'],true) && empty($job->invoice_id))
    <div class="actions" style="padding:0 12px 10px">
      <a class="btn primary" href="{{ route('erp.fix48.job-amounts',$job->id) }}">₹ Edit Labour Rate / Discount / GST →</a>
      <span class="muted">Standard Hours are operational; live/actual ROT seconds stay unchanged. Linked sessions cannot be physically erased.</span>
    </div>
  @elseif(in_array(strtolower(trim((string)$job->status)),['completed'],true))
    <p class="muted" style="padding:0 12px">To correct Labour Rate or Discount after Work Complete / QC, use the audited <b>Return to WIP</b> action in Workshop Lifecycle above.</p>
  @endif
"""
jc=once(jc,'  <div class="rot-list">',link+'  <div class="rot-list">','labour billing edit link')
editor=old['edit'].decode('utf-8','surrogateescape')
editor=once(editor,
 "@section('content')\n<div class=\"am-modern am50-integrated-page\">",
 "@section('content')\n @php($amAmountsWip=in_array(strtolower(trim((string)$card->status)),['wip','in progress','in-progress','work in progress'],true))\n<div class=\"am-modern am50-integrated-page\">",
 'billing editor status helper')
editor=once(editor,
 """@elseif(strtolower((string)$card->status)==='closed')
<div class="note">Closed but not invoiced: Admin financial correction is allowed only with modification reason. Previous amount is preserved in the change audit.</div>""",
 """@elseif(!$amAmountsWip)
<div class="note danger"><b>Job Card is not WIP: amount editing is locked.</b> Open <a href="{{ route('erp.job-cards.show',$card->id) }}">Job Card {{ $card->job_no }}</a> and select the authorized <b>Return to WIP</b> action (or Start WIP for OPEN). Invoice-linked jobs require accounting reversal first. ROT actual productive time is never overwritten.</div>""",
 'billing editor closed stale advice')
editor=editor.replace('($invoiced||$taxBasisUnverified)','($invoiced||$taxBasisUnverified||!$amAmountsWip)')
planned={'jc':jc.encode('utf-8','surrogateescape'),'edit':editor.encode('utf-8','surrogateescape')}
backup=root.parent/('BACKUP_V641_BILLING_NAV_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700)
done=[]
try:
 for key,name in files.items():
  if planned[key]==old[key]:continue
  destination=root/name
  target=backup/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(old[key]);target.chmod(0o600)
  tmp=destination.with_name('.'+destination.name+'.v641tmp')
  tmp.write_bytes(planned[key]);tmp.chmod(destination.stat().st_mode&0o777);os.replace(tmp,destination);done.append(key)
 result=subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,text=True,timeout=100)
 if result.returncode:raise RuntimeError('V641_BLADE_BUILD_FAILED '+result.stdout+result.stderr)
except BaseException:
 for key in done:(root/files[key]).write_bytes(old[key])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
 print('V641_BILLING_NAV_ROLLBACK=RESTORED')
 raise
print('V641_BILLING_NAV_BLADE=PASS')
print('V641_BILLING_NAV_BACKUP='+str(backup))
print('V641_DIRECT_ROT_DISCOUNT_AND_WIP_EDITOR_LINK=DEPLOYED')
PY
