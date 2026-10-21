#!/usr/bin/env python3
"""FIX77 read-only tight code ranges for receipt amount backend acceptance and Blade anchors."""
from pathlib import Path
import sys,hashlib,re
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
for path,ranges in {
 "resources/views/money-receipts/create.blade.php":[(8,40),(108,154),(155,205)],
 "app/Http/Controllers/Web/AdminMoneyReceiptController.php":[(113,174),(220,292)]
}.items():
 p=root/path;b=p.read_bytes();ls=b.decode('utf8','surrogateescape').splitlines()
 print('FIX77_DETAIL_FILE',path,'sha',hashlib.sha256(b).hexdigest())
 for a,z in ranges:
  print('FIX77_DETAIL_RANGE',path,a,z)
  for idx in range(a-1,min(z,len(ls))):
   x=ls[idx]
   if re.search('private.key|password|secret|api.key|auth.token',x,re.I):continue
   print('FIX77_DETAIL',idx+1,x[:320].encode('utf8','backslashreplace').decode('utf8'))
print('FIX77_DETAIL_DONE')
