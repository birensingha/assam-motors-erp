#!/usr/bin/env python3
"""AM235 targeted source audit, READ ONLY. No business-row queries."""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).resolve()
assert root.name=='assam-erp-staging' and (root/'artisan').is_file()
targets={
 'app/Services/PurchaseService.php':[(270,325),(322,445),(1090,1160)],
 'app/Http/Controllers/Web/AdminJobCardPartIssueController.php':[(1,350)],
 'app/Http/Controllers/Web/AdminJobCardPartsBatchController.php':[(1,260)],
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php':[(195,620)],
 'resources/views/purchases/index.blade.php':[(41,105)],
 'resources/views/purchases/create.blade.php':[(310,410)],
 'routes/web.php':[(1,1)]
}
for rel,ranges in targets.items():
 p=root/rel
 if not p.is_file():print('AM235_DETAIL_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM235_DETAIL_FILE',rel,'LINECOUNT',len(lines))
 if rel=='routes/web.php':
  for i,line in enumerate(lines,1):
   if 'purchase' in line.lower() or ('middleware' in line.lower() and i<100):
    print('DETAIL',rel,i,line[:350])
  continue
 for lo,hi in ranges:
  for i in range(lo-1,min(len(lines),hi)):
   line=lines[i]
   if re.search(r'password|secret|api[_-]?key|private[_-]?key|env\(',line,re.I):line='[REDACTED]'
   print('DETAIL',rel,i+1,line[:450])
print('AM235_DETAIL_COMPLETE_NO_WRITES')
