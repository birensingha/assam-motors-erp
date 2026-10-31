#!/usr/bin/env python3
"""AM224 exact Staff Portal Blade/controller and alert link targets source-only.
No user rows, SQL values, secrets, credentials, or personal identifiers.
"""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM224_DETAIL_STOP_NOT_STAGING')
paths={
 'STAFF_PORTAL_BLADE':(root/'resources/views/staff/portal.blade.php',[(1,100),(175,326)]),
 'PORTAL_CONTROLLER':(root/'app/Http/Controllers/Web/StaffPortalController.php',[(1,175)]),
 'LEGACY_REMINDERS':(root/'public/legacy/api/staff-reminders.php',[(45,135)])
}
for label,(p,areas) in paths.items():
    if not p.is_file():continue
    lines=p.read_text('utf8','replace').splitlines()
    print('AM224_DETAIL_BEGIN',label,len(lines))
    for lo,hi in areas:
        for i in range(lo-1,min(hi,len(lines))):
            line=lines[i]
            if re.search(r'(?i)(password|secret|bearer|authorization|token|private_key|DB_PASS|\\.env)',line):
                line='[REDACTED]'
            print('AM224_SRC',label,i+1,line.rstrip()[:800])
    print('AM224_DETAIL_END',label)
print('AM224_DETAIL_DONE_READONLY')
