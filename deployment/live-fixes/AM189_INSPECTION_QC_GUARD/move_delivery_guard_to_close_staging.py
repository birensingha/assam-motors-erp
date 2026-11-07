#!/usr/bin/env python3
"""Staging-only: let QC-passed job enter READY, retain HOLD on closure/handover."""
from pathlib import Path
from datetime import datetime
import subprocess, tempfile, shutil, sys

root=Path.home()/"domains/assammotors.com/assam-erp-staging"
controller=root/"app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"
view=root/"resources/views/job-cards/lifecycle-panel.blade.php"
if not (root/"artisan").is_file() or not controller.is_file() or not view.is_file():
    raise SystemExit("STOP: STAGING Laravel controller / lifecycle view missing")
src=controller.read_text()
v=view.read_text()
marker="AM_QC_DECLINED_DELIVERY_HOLD"
call="app(\\App\\Services\\InspectionQcGuard::class)->assertDeliveryRiskReady((int)$job);"
start=src.find("    public function readyForDelivery(")
end=src.find("    public function closeJob(",start)
end2=src.find("    public function reopenClosed(",end)
if min(start,end,end2)<0: raise SystemExit("STOP: unexpected controller method layout")
ready=src[start:end]; close=src[end:end2]
if "AM_QC_CLOSE_RELEASE_HOLD" in close:
    print("AM_READY_STAGE_RELEASE_HOLD_ALREADY_INSTALLED");sys.exit(0)
if ready.count(call)!=1 or marker not in ready or call in close:
    raise SystemExit("STOP: unexpected existing delivery guard position; no change")
if "if(!str_contains(strtolower((string)$card->customer_public_status),'ready for delivery'))" not in close:
    raise SystemExit("STOP: close lifecycle guard is not the expected implementation")
lock="$this->assertNotLocked($card);"
if close.count(lock)!=1: raise SystemExit("STOP: close job lock anchor missing/ambiguous")
ready=ready.replace("        // "+marker+": completed work QC is NOT vehicle release.\n        "+call+"\n", "",1)
if call in ready: raise SystemExit("STOP: ready guard couldn't be removed safely")
close=close.replace(lock,lock+"\n            // AM_QC_CLOSE_RELEASE_HOLD: READY stage does not authorize vehicle release.\n            "+call,1)
newsrc=src[:start]+ready+close+src[end2:]
# Status banner is explicit so staff never mistake "Ready" for legal/physical release.
anchor="@if($amLifeStage==='QC_PASSED')"
if v.count(anchor)!=1: raise SystemExit("STOP: QC_PASSED lifecycle UI anchor missing/ambiguous")
banner= """@php
  $amQcDeclinedDeliveryHold = \\Illuminate\\Support\\Facades\\Schema::hasTable('am189_inspection_defects')
      && \\Illuminate\\Support\\Facades\\DB::table('am189_inspection_defects')
          ->where('job_card_id', $job->id)->where('status', 'DECLINED')->exists();
@endphp
@if($amQcDeclinedDeliveryHold)
  <div style="padding:10px;margin:8px 0;border:2px solid #c75b24;background:#fff4ea;color:#8c3012;border-radius:8px;font-weight:700">
  CUSTOMER DECLINED — DELIVERY HOLD. Ready for Delivery is only an internal workflow status.
  Do NOT hand over the vehicle or close this Job Card before a genuine roadworthiness review
  and signed customer acknowledgement are recorded and authorised.
  </div>
@endif
"""
newv=v.replace(anchor,banner+anchor,1)
def lint(text,label):
    with tempfile.NamedTemporaryFile(mode="w",suffix=".php",delete=False) as f:
        f.write(text); name=f.name
    try:
        result=subprocess.run(["php","-l",name],capture_output=True,text=True)
        if result.returncode: raise SystemExit("STOP PHP LINT "+label+": "+result.stdout+" "+result.stderr)
    finally: Path(name).unlink(missing_ok=True)
lint(newsrc,"controller")
lint(newv,"lifecycle Blade")
print("AM_READY_RELEASE_PREFLIGHT_PASS")
backup=root/"storage/app/am-backups"/("AM_READY_RELEASE_SPLIT_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
for source in [controller,view]:
    dest=backup/source.relative_to(root)
    dest.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(source,dest)
print("BACKUP:",backup)
try:
    for target,newtext in [(controller,newsrc),(view,newv)]:
        temp=target.with_name(target.name+".amready.tmp")
        temp.write_text(newtext)
        shutil.copymode(target,temp)
        temp.replace(target)
    for args in [["php","artisan","view:cache"],["php","artisan","optimize:clear"]]:
        outcome=subprocess.run(args,cwd=root,text=True,capture_output=True)
        if outcome.returncode: raise RuntimeError("Failed "+" ".join(args)+": "+outcome.stderr[-700:])
except Exception as exc:
    for f in [controller,view]:
        shutil.copy2(backup/f.relative_to(root),f)
    subprocess.run(["php","artisan","optimize:clear"],cwd=root,capture_output=True)
    raise SystemExit("STOP: RESTORED BACKUP: "+str(exc))
print("AM_READY_STAGE_RELEASE_HOLD_INSTALLED")
print("QC PASS -> READY allowed if all existing work/QC rules pass")
print("DECLINED remains unrepaired; CLOSE / handover risk guard retained")
print("No existing job-card status, refusal record or DB entry modified")
print("Manual READY and CLOSE HOLD browser checks still required")
