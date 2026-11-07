#!/usr/bin/env python3
"""AM QC: remove only the experimental customer-declined delivery HOLD
from STAGING job-card lifecycle, preserving original QC and inspection records.
This does NOT certify unresolved faults as repaired or vehicle as safe.
"""
from pathlib import Path
from datetime import datetime
import subprocess,shutil,tempfile,sys,re

root=Path.home()/"domains/assammotors.com/assam-erp-staging"
controller=root/"app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"
if not (root/"artisan").is_file() or not controller.is_file():
    raise SystemExit("STOP: staging controller not found")
original=controller.read_text(encoding="utf-8")
call="app(\\App\\Services\\InspectionQcGuard::class)->assertDeliveryRiskReady((int)$job);"
marker="// AM_QC_DECLINED_DELIVERY_HOLD: completed work QC is NOT vehicle release."
if original.count(call)==0:
    print("AM_CUSTOM_DELIVERY_HOLD_ALREADY_REMOVED")
    sys.exit(0)
if original.count(call)!=1:
    raise SystemExit("STOP: multiple delivery risk guards found; no modifications")
start=original.find("public function readyForDelivery(")
end=original.find("public function closeJob(",start)
if start<0 or end<0 or call not in original[start:end]:
    raise SystemExit("STOP: unexpected blocking call location; no modifications")
block=original[start:end]
if marker not in block or block.count(marker)!=1:
    raise SystemExit("STOP: expected custom hold marker missing; no modifications")
old="        "+marker+"\n        "+call+"\n"
if block.count(old)!=1:
    raise SystemExit("STOP: exact custom hold block differs; no modifications")
changed=original[:start]+block.replace(old,"",1)+original[end:]
if changed.count("assertDeliveryRiskReady(")!=0:
    raise SystemExit("STOP: another delivery risk call remains; review manually")
if not all(key in changed for key in [
    "QC PASS + completed Job Card is required before Ready for Delivery",
    "Latest QC record PASS nahi hai.",
    "Ready for Delivery blocked:",
]):
    raise SystemExit("STOP: required existing lifecycle guards not recognised")
temp=controller.with_name(controller.name+".amhold.tmp")
temp.write_text(changed,encoding="utf-8")
check=subprocess.run(["php","-l",str(temp)],capture_output=True,text=True)
if check.returncode:
    temp.unlink(missing_ok=True)
    raise SystemExit("STOP PHP lint failed: "+check.stdout+check.stderr)
backup=root/"storage/app/am-backups"/("AM_REMOVE_CUSTOM_HOLD_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False)
shutil.copy2(controller,backup/controller.name)
print("AM_REMOVE_CUSTOM_HOLD_PREFLIGHT_PASS")
print("BACKUP:",backup)
try:
    shutil.copymode(controller,temp)
    temp.replace(controller)
    for cmd in (["php","artisan","optimize:clear"],):
        p=subprocess.run(cmd,cwd=root,capture_output=True,text=True)
        if p.returncode: raise RuntimeError("Cache clear failure "+p.stderr[-1000:])
except Exception as exc:
    shutil.copy2(backup/controller.name,controller)
    subprocess.run(["php","artisan","optimize:clear"],cwd=root,capture_output=True)
    raise SystemExit("STOP: restored previous controller after failure: "+str(exc))
print("AM_CUSTOM_DELIVERY_HOLD_REMOVED")
print("PHP_SYNTAX_PASS")
print("Original QC, Job Card stage, unfinished-work, and invoice checks preserved")
print("No customer, inspection, payment, QC or billing data changed")
print("NOTE: Unrepaired MIL P0115 remains recorded. This does not certify roadworthiness.")
print("Browser Ready for Delivery saving still requires verification.")
