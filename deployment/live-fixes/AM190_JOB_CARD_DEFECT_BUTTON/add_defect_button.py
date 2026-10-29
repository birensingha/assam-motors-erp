#!/usr/bin/env python3
"""AM190 - add Defect Management next to Inspection Report on the actual STAGING JC page.

Usage:
  python3 add_defect_button.py check /home/.../assam-erp-staging
  python3 add_defect_button.py apply /home/.../assam-erp-staging

Conservative: updates exactly one existing Job Card Blade view; fail-closed if
ambiguous; never touches QC controllers, routes, job card data, or production.
"""
from pathlib import Path
from datetime import datetime
import html
import os
import re
import subprocess
import sys

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply"):
    raise SystemExit("Usage: add_defect_button.py check|apply STAGING_LARAVEL_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
if "assam-erp-staging" not in str(root) or not (root/"artisan").is_file():
    raise SystemExit("AM190_STOP_NOT_STAGING_LARAVEL")
views = root/"resources/views"
if not views.is_dir():
    raise SystemExit("AM190_STOP_VIEWS_MISSING")

marker = "AM190_DEFECT_MANAGEMENT_BUTTON"
# Route is already installed and verified under AM189 admin middleware. The
# view link derives its job ID from the *current* Job Card route, not a hardcoded
# example JC. A null route parameter hides the button instead of breaking page.
snippet = """
{{-- AM190_DEFECT_MANAGEMENT_BUTTON: existing Admin-only AM189 defect register --}}
@if(request()->route('job'))
<a class="am190-defect-management-link" href="{{ route('erp.job-cards.inspection-defects', ['job' => request()->route('job')]) }}"
   title="Open repair and re-inspection defects for this Job Card"
   style="display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#175b82;color:#fff!important;text-decoration:none!important;padding:8px 12px;margin:3px 5px;border:1px solid #0f4567;border-radius:7px;font-size:12px;font-weight:700;line-height:1.3;white-space:nowrap;">
    &#9888; Defect Management
</a>
@endif
"""
# Only match the actual HTML action control, not a random description paragraph.
element = re.compile(r"<(?:a|button)\b[^>]*>[\s\S]*?</(?:a|button)>", re.I)
phrase = re.compile(r"\bInspection\s*(?:<[^>]*>\s*)*Report\b", re.I)

def candidates():
    found = []
    mentions = []
    for file in views.rglob("*.blade.php"):
        if any(x in file.parts for x in ("backup", "backups")):
            continue
        try:
            contents = file.read_text(encoding="utf-8")
        except (OSError, UnicodeError):
            continue
        if "Inspection Report" not in contents and not phrase.search(contents):
            continue
        rel = str(file.relative_to(root))
        for match in element.finditer(contents):
            control = match.group(0)
            visible_text = re.sub(r"<[^>]*>", " ", control)
            visible_text = html.unescape(visible_text)
            if re.search(r"Inspection\s+Report", visible_text, re.I):
                found.append((file, match.start(), match.end(), control))
        mentions.append(rel)
    return found, mentions

matches, mentions = candidates()
print("AM190_INSPECTION_LABEL_TEMPLATES", ", ".join(mentions[:15]) or "NONE")
if not matches:
    # Idempotence, including the possibility that the report link was later
    # renamed after the patch.
    already = []
    for file in views.rglob("*.blade.php"):
        try:
            if marker in file.read_text(encoding="utf-8"):
                already.append(str(file.relative_to(root)))
        except (OSError, UnicodeError):
            continue
    if len(already) == 1:
        print("AM190_ALREADY_PRESENT", already[0])
        sys.exit(0)
    raise SystemExit("AM190_STOP_CANNOT_LOCATE_INSPECTION_REPORT_ACTION")

# Prefer a Job Card show view; do not modify an inspections listing, printable
# report, or shared toolbar by accident.
job = [(p,s,e,t) for p,s,e,t in matches if "job" in str(p.relative_to(views)).lower()]
if job:
    matches = job
show = [(p,s,e,t) for p,s,e,t in matches
        if "show" in p.stem.lower() or "detail" in p.stem.lower()]
if show:
    matches = show

# Same Blade file can have distinct report buttons for different responsive
# toolbars. A human should inspect such a case.
if len(matches) != 1:
    print("AM190_CANDIDATES")
    for p,s,e,t in matches[:12]:
        print(" ", p.relative_to(root), "line", p.read_text(encoding="utf-8").count("\n",0,s)+1,
              "control", " ".join(re.sub(r"<[^>]*>"," ",t).split())[:120])
    raise SystemExit("AM190_STOP_AMBIGUOUS_REPORT_CONTROLS")

file, start, end, original = matches[0]
before = file.read_text(encoding="utf-8")
print("AM190_TARGET", file.relative_to(root))
print("AM190_ANCHOR_LINE", before.count("\n",0,start)+1)
if marker in before:
    print("AM190_ALREADY_PRESENT")
    sys.exit(0)
if not re.search(r"erp\.job-cards|job.cards", before, re.I):
    raise SystemExit("AM190_STOP_NOT_RECOGNIZED_JOB_CARD_VIEW")

# The new action appears alongside the original report link. No old action is
# removed or renamed. Preserve newline style of original file.
newline = "\r\n" if "\r\n" in before else "\n"
replacement = before[:end] + newline + snippet.strip().replace("\n", newline) + before[end:]
if mode == "check":
    print("AM190_PREFLIGHT_PASS_NO_WRITES")
    print("AM190_ACTION=Inspection Report + Defect Management")
    sys.exit(0)

backupdir = root/"storage/app/am-backups"/("AM190_NAV_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backupdir.mkdir(parents=True,exist_ok=False)
backup = backupdir/file.relative_to(root)
backup.parent.mkdir(parents=True,exist_ok=True)
import shutil
shutil.copy2(file, backup)
try:
    temp = file.with_name(file.name + ".am190-temp")
    with temp.open("x", encoding="utf-8",newline="") as out:
        out.write(replacement)
    shutil.copymode(file, temp)
    os.replace(temp, file)
    # Validate by compiling all views, not just PHP lexing raw Blade syntax.
    # A failure restores original file and clears compiled views to prevent
    # serving a half-applied navigation change.
    check = subprocess.run(["php","artisan","view:cache"],cwd=root,
                           capture_output=True,text=True,timeout=90)
    if check.returncode != 0:
        raise RuntimeError("Blade compile failed: "+(check.stderr or check.stdout)[-700:])
    print("AM190_DEFECT_MANAGEMENT_BUTTON_INSTALLED")
    print("AM190_BACKUP", backup)
    print("AM190_VIEW_COMPILE_PASS")
    print("AM190_NO_DATABASE_OR_QC_CHANGES")
except Exception as exc:
    shutil.copy2(backup,file)
    subprocess.run(["php","artisan","view:clear"],cwd=root,capture_output=True,text=True)
    print("AM190_ROLLBACK_RESTORED_ORIGINAL")
    raise SystemExit(str(exc))
