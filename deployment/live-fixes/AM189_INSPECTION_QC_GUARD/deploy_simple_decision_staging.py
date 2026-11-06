#!/usr/bin/env python3
"""STAGING ONLY: repair-scoped QC with recorded customer refusal, never unguarded delivery."""
from pathlib import Path
from datetime import datetime
import subprocess, tempfile, shutil, sys, re, os

root=Path.home()/"domains/assammotors.com/assam-erp-staging"
repo=Path.home()/"assam-motors-deploy"
prefix="deployment/live-fixes/AM189_INSPECTION_QC_GUARD/files/"
guard="app/Services/InspectionQcGuard.php"
view="resources/views/inspection-defects/index.blade.php"
lifecycle="app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"
if not (root/"artisan").is_file() or not (repo/".git").exists():
    raise SystemExit("STOP: correct STAGING root or deploy checkout missing")
def fetch(p):
    cp=subprocess.run(["git","show","FETCH_HEAD:"+prefix+p],cwd=repo,capture_output=True,text=True)
    if cp.returncode: raise SystemExit("STOP: unable to retrieve reviewed patch file "+p)
    return cp.stdout
def lint(source,label):
    with tempfile.NamedTemporaryFile(mode="w",suffix=".php",delete=False) as f:
        f.write(source); name=f.name
    try:
        cp=subprocess.run(["php","-l",name],capture_output=True,text=True)
        if cp.returncode: raise SystemExit("STOP: PHP syntax failure "+label+": "+cp.stdout+" "+cp.stderr)
    finally: Path(name).unlink(missing_ok=True)

new_guard=fetch(guard)
new_view=fetch(view)
current_guard=(root/guard).read_text()
current_view=(root/view).read_text()
current_lifecycle=(root/lifecycle).read_text()
if "function assertQcReady" not in current_guard or "function decline" not in (root/"app/Http/Controllers/Web/InspectionQcDefectController.php").read_text():
    raise SystemExit("STOP: existing AM189 customer decline workflow not installed")
if "QC of Approved Work — Separate from Full Vehicle QC" in current_view:
    raise SystemExit("STOP: extra QC panel still installed; review and roll it back before replacing this view")
if "Customer Declined Repair" not in current_view and "Customer Denied to Work" not in current_view:
    raise SystemExit("STOP: current view is not the expected AM189 defect page")
if "function assertDeliveryRiskReady" in current_guard or "assertDeliveryRiskReady" in current_lifecycle:
    raise SystemExit("STOP: delivery guard already present, manual code review needed")
if "function assertDeliveryRiskReady" not in new_guard or "am-decision-picker" not in new_view:
    raise SystemExit("STOP: incomplete reviewed source patch")
# Only attach the delivery guard when the lifecycle controller declares the known
# ready-for-delivery action with a $job argument. Do not relax QC without this.
m=list(re.finditer(r'public\s+function\s+readyForDelivery\s*\(([^)]*)\)\s*(?::\s*[^{]+)?\s*\{',current_lifecycle,re.S))
if len(m)!=1 or not re.search(r'\$job\b',m[0].group(1)):
    raise SystemExit("STOP: readyForDelivery($job) anchor absent / ambiguous; NO FILES CHANGED")
injection="\n        // AM_QC_DECLINED_DELIVERY_HOLD: completed work QC is NOT vehicle release.\n        app(\\App\\Services\\InspectionQcGuard::class)->assertDeliveryRiskReady((int)$job);\n"
new_lifecycle=current_lifecycle[:m[0].end()]+injection+current_lifecycle[m[0].end():]
lint(new_guard,guard)
lint(new_lifecycle,lifecycle)
lint(new_view,view)
print("AM_QC_SIMPLE_PREFLIGHT_PASS")
backup=root/"storage/app/am-backups"/("AM_QC_SIMPLE_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
for rel in (guard,view,lifecycle):
    dest=backup/rel
    dest.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(root/rel,dest)
print("BACKUP:",backup)
changes={guard:new_guard,view:new_view,lifecycle:new_lifecycle}
done=[]
try:
    for rel,contents in changes.items():
        target=root/rel
        temp=target.with_name(target.name+".amqcsimple.tmp")
        temp.write_text(contents)
        shutil.copymode(target,temp)
        temp.replace(target)
        done.append(rel)
    cp=subprocess.run(["php","artisan","optimize:clear"],cwd=root,text=True)
    if cp.returncode: raise RuntimeError("Cache clear failed")
except Exception as exc:
    for rel in done:
        shutil.copy2(backup/rel,root/rel)
    subprocess.run(["php","artisan","optimize:clear"],cwd=root,check=False)
    raise SystemExit("STOP: original files restored after failed apply: "+str(exc))
print("AM_QC_SIMPLE_DECISION_INSTALLED")
print("Customer DECLINED is never labeled repaired or VERIFIED")
print("QC PASS can cover performed work; delivery HOLD stays active for declined defects")
print("NO DATABASE OR CUSTOMER QC RECORD MODIFIED")
print("Browser QC save test and ready-for-delivery HOLD test required")
