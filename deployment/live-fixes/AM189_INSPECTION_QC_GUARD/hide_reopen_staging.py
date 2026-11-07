#!/usr/bin/env python3
"""STAGING-only, view-only: collapse Reopen for Repair for DECLINED findings."""
from pathlib import Path
from datetime import datetime
import shutil, subprocess, sys

root=Path.home()/"domains/assammotors.com/assam-erp-staging"
view=root/"resources/views/inspection-defects/index.blade.php"
if not (root/"artisan").is_file() or not view.is_file():
    raise SystemExit("STOP: expected staging app/view missing")

src=view.read_text(encoding="utf-8")
anchor='<form method="post" action="{{ route(\'erp.job-cards.inspection-defects.reopen-declined\',$f[\'record\']->id) }}">'
end_marker="<button type=\"submit\">Reopen for Repair — OPEN</button>\n</form>"
# Safe no-op for already-collapsed forms, without editing other sections.
if "<summary" in src and "Reopen for Repair (only if customer changes decision)" in src:
    print("AM_QC_REOPEN_ALREADY_COLLAPSED")
    sys.exit(0)
if src.count(anchor)!=1 or src.count(end_marker)!=1:
    raise SystemExit("STOP: unexpected reopen markup, no writes")

old=anchor+"\n@csrf\n<label>Reopen reason / customer changed decision</label>\n<textarea name=\"reopen_reason\" minlength=\"8\" maxlength=\"2000\" required rows=\"2\"></textarea>\n"+end_marker
new='<details class="am-reopen-optional" style="margin-top:12px">\n<summary class="btn secondary" style="cursor:pointer">Reopen for Repair (only if customer changes decision)</summary>\n'+anchor+'\n@csrf\n<label>Reason for reopening repair</label>\n<textarea name="reopen_reason" minlength="8" maxlength="2000" required rows="2"></textarea>\n<button type="submit">Confirm Reopen — OPEN</button>\n</form>\n</details>'
if src.count(old)!=1:
    raise SystemExit("STOP: expected old reopen markup not found, no writes")

backup=root/"storage/app/am-backups"/("AM_QC_REOPEN_UI_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False)
shutil.copy2(view,backup/view.name)
temp=view.with_name(view.name+".amreopen.tmp")
try:
    temp.write_text(src.replace(old,new,1),encoding="utf-8")
    shutil.copymode(view,temp)
    temp.replace(view)
    check=subprocess.run(["php","artisan","view:cache"],cwd=root,capture_output=True,text=True)
    if check.returncode:
        raise RuntimeError("Blade view compile failed: "+check.stderr[-800:])
    clear=subprocess.run(["php","artisan","view:clear"],cwd=root,capture_output=True,text=True)
    if clear.returncode:
        raise RuntimeError("View cache clear failed: "+clear.stderr[-800:])
except Exception as exc:
    shutil.copy2(backup/view.name,view)
    subprocess.run(["php","artisan","view:clear"],cwd=root,capture_output=True)
    raise SystemExit("STOP: restored original view: "+str(exc))
print("AM_QC_REOPEN_UI_INSTALLED")
print("BACKUP:",backup)
print("No database, defect decision, QC or delivery status changed.")
