#!/usr/bin/env python3
"""AM190B: add a visible Defect Management shortcut to the *ADMIN Job Card* page.

Exact known source: resources/views/job-cards/show.blade.php
First choice: immediately after existing Inspection Report link in same template.
Fallback: into existing FIX75 Job Card header action bar (never a Staff view).
check does no writes; apply creates backup, compiles Blade and restores on failure.
"""
from pathlib import Path
from datetime import datetime
import json
import os
import re
import shutil
import subprocess
import sys
import tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply"):
    raise SystemExit("Usage: add_to_job_card.py check|apply STAGING_LARAVEL_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
target = root / "resources/views/job-cards/show.blade.php"
if ("assam-erp-staging" not in str(root)
    or not (root / "artisan").is_file() or not target.is_file()):
    raise SystemExit("AM190B_STOP_NOT_STAGING_ADMIN_JOB_CARD")

text = target.read_text(encoding="utf-8")
marker = "AM190B_ADMIN_JOB_CARD_DEFECT_MANAGEMENT"
if marker in text:
    print("AM190B_ALREADY_INSTALLED")
    sys.exit(0)
if text.count("am75-quick-actions") < 1:
    raise SystemExit("AM190B_STOP_JOB_CARD_ACTION_BAR_UNRECOGNIZED")
if "$job->id" not in text:
    raise SystemExit("AM190B_STOP_JOB_CARD_ID_UNRECOGNIZED")

# Do not offer a broken shortcut if the protected AM189 route is absent.
routes_result = subprocess.run(
    ["php", "artisan", "route:list", "--json"],
    cwd=root, capture_output=True, text=True
)
if routes_result.returncode != 0:
    raise SystemExit("AM190B_STOP_CANNOT_VERIFY_ROUTES")
try:
    routes = json.loads(routes_result.stdout)
except ValueError:
    raise SystemExit("AM190B_STOP_ROUTE_JSON_INVALID")
routes = [x for x in routes if x.get("name") ==
          "erp.job-cards.inspection-defects"]
if (len(routes) != 1 or
    not any("EnsureErpAdmin" in str(x) for x in routes[0].get("middleware", []))):
    raise SystemExit("AM190B_STOP_AM189_ADMIN_ROUTE_NOT_SECURED")
if routes[0].get("uri") != "erp/job-cards/{job}/inspection-defects":
    raise SystemExit("AM190B_STOP_UNEXPECTED_DEFECT_ROUTE")

# Match only the existing ADMIN JC display template; no broad scan.
# If the Inspection Report link is in this template, place beside it.
# Otherwise place on its known top Job Card action bar so it is easy to find.
buttons = [
    (m.start(), m.end(), m.group(0))
    for m in re.finditer(r"<a\b[^>]*>[\s\S]*?</a\s*>", text, re.I)
    if re.search(r"inspection[\s-]*report|inspection-report", m.group(0), re.I)
]
# Don't put the button into a print view or a nested form; this is the JC show.
if len(buttons) == 1:
    location = buttons[0][1]
    placement = "ADJACENT_TO_INSPECTION_REPORT"
    button = (
        "\n{{-- " + marker + " --}}\n"
        '<a href="{{ route(\'erp.job-cards.inspection-defects\', $job->id) }}" '
        'class="btn am190b-defect-action" '
        'style="display:inline-flex;align-items:center;gap:6px;'
        'margin:4px 6px;padding:8px 12px;background:#155b85;'
        'color:white!important;border-radius:7px;'
        'text-decoration:none!important;font-weight:700;font-size:12px;" '
        'title="Repair and re-inspect saved defects for this Job Card">'
        '&#9888; Defect Management</a>\n'
    )
else:
    navs = list(re.finditer(r'<nav\b[^>]*class=["\'][^"\']*\bam75-quick-actions\b[^"\']*["\'][^>]*>', text, re.I))
    if len(navs) != 1:
        print("AM190B_INSPECTION_REPORT_LINKS", len(buttons))
        raise SystemExit("AM190B_STOP_AMBIGUOUS_JOB_CARD_TOOLBAR")
    close = text.find("</nav>", navs[0].end())
    if close < 0 or close - navs[0].end() > 16000:
        raise SystemExit("AM190B_STOP_TOOLBAR_END_MISSING")
    location = close
    placement = "JOB_CARD_TOP_ACTION_BAR"
    button = (
        "  {{-- " + marker + " --}}\n"
        '  <a class="am75-action am190b-defect-action" '
        'href="{{ route(\'erp.job-cards.inspection-defects\', $job->id) }}" '
        'style="background:#155b85;color:white!important;" '
        'title="Repair and re-inspect saved defects for this Job Card">'
        '&#9888; Defect Management</a>\n'
    )

updated = text[:location] + button + text[location:]
if updated.count(marker) != 1:
    raise SystemExit("AM190B_STOP_UNEXPECTED_MARKER_COUNT")

print("AM190B_TARGET resources/views/job-cards/show.blade.php")
print("AM190B_BUTTON_LOCATION", placement)
print("AM190B_SOURCE_LINE", text[:location].count("\n")+1)
print("AM190B_ADMIN_ROUTE_VERIFIED")
if mode == "check":
    print("AM190B_CHECK_PASS_NO_WRITES")
    sys.exit(0)

backupdir = root / "storage/app/am-backups" / (
    "AM190B_JC_BUTTON_" + datetime.now().strftime("%Y%m%d_%H%M%S"))
backupdir.mkdir(parents=True, exist_ok=False)
backup = backupdir / "show.blade.php"
shutil.copy2(target,backup)
tmp = None
try:
    fd, tmppath = tempfile.mkstemp(prefix=".am190b_", suffix=".blade.php", dir=target.parent)
    tmp = Path(tmppath)
    with os.fdopen(fd,"w",encoding="utf-8") as output:
        output.write(updated)
    shutil.copymode(target,tmp)
    os.replace(tmp,target)
    tmp = None
    compile_result = subprocess.run(
        ["php", "artisan", "view:cache"],
        cwd=root, text=True, capture_output=True
    )
    if compile_result.returncode != 0:
        raise RuntimeError("BLADE_COMPILE_ERROR: "+(compile_result.stderr or compile_result.stdout)[-500:])
except Exception as exc:
    shutil.copy2(backup,target)
    subprocess.run(["php","artisan","view:clear"],cwd=root,capture_output=True,text=True)
    raise SystemExit("AM190B_ROLLBACK_ORIGINAL_RESTORED " + str(exc))
finally:
    if tmp is not None:
        tmp.unlink(missing_ok=True)

print("AM190B_ADMIN_JOB_CARD_BUTTON_INSTALLED")
print("AM190B_BACKUP", backup)
print("AM190B_BLADE_COMPILE_PASS")
print("AM190B_NO_QC_DATABASE_OR_INSPECTION_CHANGES")
