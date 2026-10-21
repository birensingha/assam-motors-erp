#!/usr/bin/env python3
"""FIX76 exact source read-only target. Do not expose customer/vehicle PII."""
from pathlib import Path
import sys,hashlib,re
root=Path(sys.argv[1]);assert(root/'artisan').is_file()
ranges=[
('resources/views/admin/native/labour.blade.php',1,65),
('app/Http/Controllers/Web/AdminNativeModuleController.php',772,823),
('resources/views/invoices/print.blade.php',70,145),
('resources/views/invoices/print.blade.php',285,365),
('app/Http/Controllers/Web/AdminJobCardPageController.php',810,882),
('resources/views/estimates/create.blade.php',100,148),
('app/Services/EstimateService.php',376,412),
('resources/views/layouts/erp.blade.php',19,65),
]
for rel,a,b in ranges:
 p=root/rel
 if not p.exists():print('FIX76_MISSING',rel);continue
 ls=p.read_bytes().decode('utf8','surrogateescape').splitlines()
 print('FIX76_TARGET',rel,'SHA256',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(ls))
 for n in range(a,min(b,len(ls))+1):
  if re.search(r'password|secret|token|API_KEY',ls[n-1],re.I):continue
  print('FIX76_CODE',rel,n,ls[n-1][:330].encode('utf8','backslashreplace').decode('utf8'))
print('FIX76_TARGET_AUDIT_COMPLETE')
