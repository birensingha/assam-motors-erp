#!/usr/bin/env python3
"""AM261 targeted read-only source details for Parts/ROT/Labour/OSL master paths."""
from pathlib import Path
import sys,re
if len(sys.argv)!=2:raise SystemExit("USAGE audit_details.py STAGING_ROOT")
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('STOP_AM261_NOT_STAGING')
sources={
'app/Http/Controllers/Web/AdminNativeModuleController.php':[(825,998)],
'resources/views/admin/native/parts.blade.php':[(1,115),(115,265),(265,435),(435,615)],
'resources/views/admin/native/labour.blade.php':[(1,85)],
'app/Http/Controllers/Web/AdminOslQuickMasterController.php':[(1,134)],
'resources/views/osl-masters/index.blade.php':[(1,102)],
'public/js/am241-jc-osl-master.js':[(1,110)],
'resources/views/estimates/create.blade.php':[(302,340)],
'resources/views/invoices/print.blade.php':[(178,205)],
'resources/views/workshop-controls/osl-edit.blade.php':[(1,95)],
}
sensitive=re.compile(r'password|secret|bearer|api.?key|DB_PASSWORD|csrf_token\(|authorization',re.I)
for rel,ranges in sources.items():
    p=root/rel
    if not p.is_file():print('AM261_DETAIL_MISSING',rel);continue
    ls=p.read_text('utf8','replace').splitlines()
    print('AM261_DETAIL_FILE',rel,'TOTAL_LINES',len(ls))
    for aa,bb in ranges:
        for i in range(aa-1,min(len(ls),bb)):
            l=ls[i].rstrip()
            if sensitive.search(l):l='[REDACTED_SENSITIVE]'
            print('AM261_DETAIL',rel,i+1,l[:900])
print('AM261_DETAILS_AUDIT_READONLY_COMPLETE')
