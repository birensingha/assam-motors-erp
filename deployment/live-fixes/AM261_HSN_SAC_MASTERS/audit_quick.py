#!/usr/bin/env python3
"""Read-only targeted OSL quick-create and Estimate catalog selectors."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('STOP_AM261_ROOT')
for rel in ['app/Http/Controllers/Web/AdminEstimatePageController.php','public/js/am242-jc-osl-one-field.js','resources/views/job-cards/show.blade.php','resources/views/estimates/create.blade.php','resources/views/purchases/osl-create.blade.php']:
 p=root/rel
 if not p.is_file():continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM261_QUICK_FILE',rel,'LINES',len(lines))
 if 'AdminEstimate' in rel:ids=set(range(min(len(lines),85)))
 else:
  ids=set()
  patterns=[r'osl-masters.quick-create',r'osl_masters',r'oslMaster',r'createOsl',r'quick.?create',r'Create OSL',r'AM241_OSL',r'oslMasters']
  for i,x in enumerate(lines):
   if any(re.search(pat,x,re.I) for pat in patterns):
    ids.update(range(max(0,i-4),min(len(lines),i+13)))
 for i in sorted(ids)[:250]:
  line=lines[i].strip()
  if re.search(r'password|secret|csrf_token\(|DB_PASS|APP_KEY',line,re.I):line='[REDACTED]'
  print('AM261_QUICK_CODE',rel,i+1,line[:700])
print('AM261_QUICK_READONLY_DONE')
