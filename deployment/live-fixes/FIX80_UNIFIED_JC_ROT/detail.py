#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').exists()
for name,ranges in {
'resources/views/job-cards/show.blade.php':[(355,425),(555,645)],
'app/Http/Controllers/Web/AdminWorkshopControlPageController.php':[(140,235)],
'app/Http/Controllers/Web/AdminJobCardPageController.php':[(610,735)],
'app/Http/Controllers/Web/AdminJobCardDirectLabourController.php':[(1,210)],
'routes/web.php':[(160,212)]}.items():
 p=root/name
 if not p.exists():continue
 l=p.read_bytes().decode('utf8','replace').splitlines()
 print('FIX80_DETAIL',name,'sha',hashlib.sha256(p.read_bytes()).hexdigest())
 for a,z in ranges:
  for n in range(a-1,min(z,len(l))):
   t=l[n]
   if re.search('secret|password|private.key|api.key|bearer',t,re.I):continue
   print('FIX80_CODE',name,n+1,t[:450])
print('FIX80_DETAILS_PASS')
