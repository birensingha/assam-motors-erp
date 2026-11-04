#!/usr/bin/env python3
from pathlib import Path
import sys
r=Path(sys.argv[1]).resolve()
if r.name!='assam-erp-staging':raise SystemExit('NO')
for rel,sections in {
'app/Http/Controllers/Web/AdminOslQuickMasterController.php':[(1,46)],
'app/Http/Controllers/Web/AdminNativeModuleController.php':[(380,470),(565,610)],
'resources/views/admin/native/parts.blade.php':[(16,26)],
'resources/views/admin/native/labour.blade.php':[(38,52)]
}.items():
 p=r/rel
 if not p.exists():continue
 a=p.read_text('utf8','replace').splitlines()
 for lo,hi in sections:
  for i in range(lo-1,min(hi,len(a))):
   print('AM246_SPOT',rel,i+1,a[i][:1300])
print('AM246_SPOT_DONE')
