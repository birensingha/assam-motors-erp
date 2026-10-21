#!/usr/bin/env python3
from pathlib import Path
import sys,hashlib,re
r=Path(sys.argv[1]); assert (r/'artisan').exists()
items=[
 ('resources/views/job-cards/show.blade.php',250,650),
 ('resources/views/job-cards/partials/am65-customer-labour.blade.php',1,178),
 ('app/Http/Controllers/Web/AdminJobCardPageController.php',285,430),
 ('app/Http/Controllers/Web/AdminJobCardPageController.php',1180,1198),
]
for name,lo,hi in items:
 p=r/name
 if not p.is_file(): print('FIX82_MISSING',name); continue
 raw=p.read_bytes(); t=raw.decode('utf8','replace').splitlines()
 print('FIX82_FILE',name,hashlib.sha256(raw).hexdigest(),len(t))
 for i in range(lo-1,min(hi,len(t))):
  if re.search(r'password|secret|private.key|api.key',t[i],re.I):continue
  print('FIX82_LINE',name,i+1,t[i][:600].encode('ascii','backslashreplace').decode('ascii'))
print('FIX82_DONE')
