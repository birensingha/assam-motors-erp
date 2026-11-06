#!/usr/bin/env python3
"""Narrow staging-only correction: normal defects don't require safety attestation."""
from pathlib import Path
from datetime import datetime
import subprocess, shutil, sys
root=Path.home()/"domains/assammotors.com/assam-erp-staging"
controller=root/"app/Http/Controllers/Web/InspectionQcDefectController.php"
view=root/"resources/views/inspection-defects/index.blade.php"
if not (root/"artisan").is_file() or not controller.is_file() or not view.is_file():
    raise SystemExit("STOP: staging app / required source missing")
before=controller.read_text()
old="'independent_attestation'=>['nullable','accepted']"
new="'independent_attestation'=>['sometimes','accepted']"
if new in before and old not in before:
    print("AM_QC_NORMAL_ATTESTATION_ALREADY_FIXED")
    raise SystemExit(0)
if before.count(old)!=1:
    raise SystemExit("STOP: attestation validation anchor changed; no files modified")
backup=root/"storage/app/am-backups"/("AM_QC_ATTESTATION_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True)
shutil.copy2(controller,backup/controller.name)
staged=controller.with_name(controller.name+".amqc.tmp")
staged.write_text(before.replace(old,new,1))
check=subprocess.run(["php","-l",str(staged)],capture_output=True,text=True)
if check.returncode:
    staged.unlink(missing_ok=True)
    raise SystemExit("STOP: PHP syntax failure. Original source preserved. "+check.stderr)
shutil.copymode(controller,staged)
staged.replace(controller)
clear=subprocess.run(["php","artisan","optimize:clear"],cwd=root)
print("AM_QC_NORMAL_ATTESTATION_PATCH_APPLIED")
print("BACKUP:",backup)
print("PHP_SYNTAX_PASS")
print("CACHE_CLEAR_PASS" if clear.returncode==0 else "CACHE_CLEAR_FAILED")
print("No inspection or QC database rows changed")
if clear.returncode: sys.exit(1)
