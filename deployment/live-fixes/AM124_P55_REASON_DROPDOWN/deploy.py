#!/usr/bin/env python3
"""AM124: fix Alteration Reason dropdown clipped/behind Admin ROT dialog.
STAGING ONLY. Modes: check / apply / rollback. Backup-first.
No DB changes; no controller/routes changes. Scoped to Main JC AM116 dialog.
"""
import datetime
import os
import re
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply"):
    raise SystemExit("Use: python3 deploy.py check|apply STAGING_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).resolve()
payload = Path(__file__).resolve().with_name("modal-reason-picker.blade.php")
target = root/"resources/views/job-cards/rot-alteration.blade.php"
show = root/"resources/views/job-cards/show.blade.php"
for p in (root/"artisan", payload, target, show):
    if not p.is_file():raise SystemExit("AM124_STOP_MISSING_"+str(p))
original = target.read_text("utf-8",errors="surrogateescape")
patch = payload.read_text("utf-8",errors="surrogateescape")
marker = 'id="AM124_P55_REASON_PICKER"'
checks = {
    "EXISTING_P55": 'p55-rot-edit' in original and 'data-p55-form' in original,
    "EXISTING_AM116_DIALOG": 'AM116_UNIFIED_ROT_UI' in original,
    "REASON_SELECT": re.search(r'<select\b[^>]*\bname\s*=\s*["\']alteration_reason["\']',original,re.I|re.S) is not None,
    "MAIN_JC_USES_P55": "job-cards.partials.am123-unified-correction" in show.read_text("utf-8",errors="surrogateescape"),
    "PATCH_ONCE": patch.count(marker)==1,
    "PRESERVES_NATIVE_FORM_SELECT": "select.style.setProperty('display','none','important')" in patch
        and "select.dispatchEvent(new Event('change',{bubbles:true}))" in patch,
    "POPUP_INSIDE_DIALOG": "dialog.appendChild(popup)" in patch,
    "PATCH_SCOPED_TO_AM116": "var dialogId='am116-rot-dialog'" in patch,
}
for name,passed in checks.items():
    print("AM124_PREFLIGHT_"+name+"="+("PASS" if passed else "FAIL"))
if marker in original:
    print("AM124_ALREADY_APPLIED_NO_WRITES")
    sys.exit(0)
if not all(checks.values()):
    raise SystemExit("AM124_STOP_SOURCE_GUARD_FAILED_NO_WRITES")
candidate = original.rstrip()+"\n"+patch
if candidate.count(marker)!=1:
    raise SystemExit("AM124_STOP_PATCH_DUPLICATE_MARKER")
if mode == "check":
    print("AM124_PREFLIGHT_PASS_NO_WRITES")
    sys.exit(0)

backup_dir = root/"storage/app/am-backups"
backup_dir.mkdir(parents=True,exist_ok=True)
backup = backup_dir / (
    "AM124_rot_alteration_" + datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
    +"_"+str(os.getpid())+".blade.php"
)
shutil.copy2(target,backup)
print("AM124_BACKUP="+str(backup))

def rollback(reason):
    shutil.copy2(backup,target)
    subprocess.run(["php","artisan","view:clear"],cwd=root,
        stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    print("AM124_ROLLBACK_RESTORED="+str(backup))
    raise SystemExit("AM124_APPLY_FAILED_ROLLED_BACK="+str(reason))

def atomic(p,data):
    fd,tmp=tempfile.mkstemp(prefix=".am124_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf-8",errors="surrogateescape") as f:f.write(data)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

try:
    atomic(target,candidate)
    for command in (["php","artisan","view:clear"],["php","artisan","view:cache"]):
        p=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=100)
        print((p.stdout or p.stderr)[-250:])
        if p.returncode:
            raise RuntimeError("Blade compilation failed: "+" ".join(command))
    found=[]
    for file in (root/"storage/framework/views").glob("*.php"):
        compiled=file.read_text("utf-8",errors="replace")
        if 'id="AM124_P55_REASON_PICKER"' in compiled:
            lint=subprocess.run(["php","-l",str(file)],cwd=root,
                capture_output=True,text=True,timeout=20)
            if lint.returncode:
                raise RuntimeError("Compiled Blade PHP syntax invalid: "+lint.stderr[-250:])
            if "am124-reason-popup" in compiled and "dialog.appendChild(popup)" in compiled:
                found.append(str(file))
    if len(found)!=1:raise RuntimeError(
        "AM124 compiled picker missing or duplicate; compiled count="+str(len(found)))
    print("AM124_COMPILED_PICKER_FOUND_AND_PHP_LINT_PASS="+found[0])
except BaseException as e:
    rollback(e)

print("AM124_SOURCE_AND_BLADE_DEPLOY_PASS")
print("AM124_DB_ROUTES_CONTROLLERS_UNCHANGED")
print("AM124_BROWSER_DROPDOWN_TEST=NOT_YET_VERIFIED")
