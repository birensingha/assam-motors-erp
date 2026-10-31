#!/usr/bin/env python3
"""AM224 read-only STAGING audit: Staff Portal alert UI, Open Work target,
notification permission, attendance next-day missed checkout enforcement.
No row data, tokens, secrets, password or user profile values.
"""
import re,os,sys
from pathlib import Path
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM224_STOP_NOT_STAGING")
locations=[
  root/"public/legacy/staff.js",
  root/"public/legacy/staff.html",
  root/"public/legacy/staff.css",
  root/"public/legacy/api/staff-reminders.php",
  root/"public/legacy/api/staff-attendance.php",
  root/"app/Services/AttendanceService.php",
  root/"app/Http/Controllers/Api/V1/AttendanceController.php",
  root/"app/Services/MandatoryNotificationService.php",
  root/"resources/views/staff/dashboard.blade.php",
  root/"resources/views/staff/portal.blade.php",
]
needle=re.compile(r"(?i)(mandatory.notifications|notification.*required|pending.alert|notification_disabled|login_missed|checkin_missed|checkout_pending|open work|openWork|notifications?\\.map|notifications?\\.filter|check.?in|missed.?checkout|unfinished.?checkout|previous.?day|blocked.?checkin|work-completion|staff-reminders)")
def trim(line):
    if re.search(r"(?i)(password|password_hash|secret|authorization|bearer|private.key|api.?token|\\.env|DB_PASS|cookie)",line):
        return "[REDACTED_SENSITIVE_SOURCE]"
    return line.rstrip()[:700]
print("AM224_AUDIT_READONLY_BEGIN")
for p in locations:
    if not p.is_file():continue
    lines=p.read_text('utf8','replace').splitlines()
    relative=p.relative_to(root)
    print("AM224_FILE",str(relative),"LINES",len(lines),"BYTES",p.stat().st_size)
    hits=[i for i,s in enumerate(lines) if needle.search(s)]
    print("AM224_MATCH_COUNT",str(relative),len(hits))
    # Source code fragments needed to build guarded safe patches.
    prev=-4
    for i in hits[:34]:
        lo=max(0,i-3);hi=min(len(lines),i+5)
        for j in range(lo,hi):
            if j<=prev:continue
            print("AM224_CODE",p.name,j+1,trim(lines[j]))
            prev=j
# List layout and staff related source without exposing personal records.
for folder in [root/'public/legacy',root/'resources/views/staff',root/'app/Http/Controllers/Web']:
    if not folder.is_dir():continue
    for p in sorted(folder.iterdir()):
        if p.is_file() and re.search(r'(?i)staff|portal|attendance|remind|notification|dashboard',p.name):
            print("AM224_SOURCE",str(p.relative_to(root)),p.stat().st_size)
print("AM224_AUDIT_READONLY_PASS_NO_DB_CHANGES")
