#!/usr/bin/env python3
"""STAGING ONLY - Customer acknowledgement view-only simplification."""
from pathlib import Path
from datetime import datetime
import subprocess, shutil, tempfile, sys
root=Path.home()/"domains/assammotors.com/assam-erp-staging"
repo=Path.home()/"assam-motors-deploy"
rel="resources/views/inspection-defects/index.blade.php"
target=root/rel
if not (root/"artisan").is_file() or not (repo/".git").is_dir() or not target.is_file():
    raise SystemExit("STOP: staging root or current view missing")
source=subprocess.run(["git","show","FETCH_HEAD:deployment/live-fixes/AM189_INSPECTION_QC_GUARD/files/"+rel],cwd=repo,capture_output=True,text=True)
if source.returncode:
    raise SystemExit("STOP: source not found in fetched branch")
old=target.read_text()
new=source.stdout
if "am-ack-select" in old:
    print("AM_ACK_ALREADY_INSTALLED");sys.exit(0)
if "Customer Denied to Work" not in old or 'name="decline_acknowledgement"' not in old:
    raise SystemExit("STOP: existing customer-declined view not recognized")
if "am-ack-select" not in new or "Signed Proof Reference" not in new:
    raise SystemExit("STOP: incomplete patch payload")
# Safety: limit changes to expected acknowledgement UI and its independent JS helper.
if old.count("<label>Customer Acknowledgement</label>")!=1:
    raise SystemExit("STOP: acknowledgement anchor mismatch")
backup=root/"storage/app/am-backups"/("AM_QC_ACK_UI_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False)
shutil.copy2(target,backup/target.name)
tmp=target.with_name(target.name+".amack.tmp")
try:
    tmp.write_text(new)
    shutil.copymode(target,tmp)
    tmp.replace(target)
    cmd=subprocess.run(["php","artisan","view:cache"],cwd=root,capture_output=True,text=True)
    if cmd.returncode: raise RuntimeError("Blade compilation error: "+cmd.stderr[-1000:])
    clear=subprocess.run(["php","artisan","view:clear"],cwd=root,capture_output=True,text=True)
    if clear.returncode: raise RuntimeError("view clear failed")
except Exception as e:
    shutil.copy2(backup/target.name,target)
    subprocess.run(["php","artisan","view:clear"],cwd=root,capture_output=True)
    raise SystemExit("STOP: restored original view after error: "+str(e))
print("AM_QC_ACK_UI_INSTALLED")
print("BACKUP:",backup)
print("No database, QC guard, or job card records changed.")
