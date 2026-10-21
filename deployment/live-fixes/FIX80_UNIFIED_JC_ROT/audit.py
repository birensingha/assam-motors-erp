#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').exists()
paths=['resources/views/job-cards/show.blade.php','resources/views/job-cards/partials/am65-customer-labour.blade.php','resources/views/job-cards/rot-billing-control.blade.php','resources/views/job-cards/rot-time-editor.blade.php','app/Http/Controllers/Web/AdminWorkshopControlPageController.php','app/Http/Controllers/Web/AdminJobCardPageController.php','routes/web.php']
for name in paths:
 p=root/name
 if not p.exists():print('FIX80_MISSING',name);continue
 b=p.read_bytes();l=b.decode('utf8','replace').splitlines()
 print('FIX80_FILE',name,'sha256',hashlib.sha256(b).hexdigest(),'lines',len(l))
 if name=='resources/views/job-cards/show.blade.php':
  hits=[i for i,v in enumerate(l) if re.search(r'am65|Labour / ROT|rot-list|rot.start|rot.pause|rot.resume|rot.complete|mechanic|labour|rot.billing|rot-time|estimate',v,re.I)]
  chunks=[(max(0,i-5),min(len(l),i+13)) for i in hits]
  spans=[]
  for a,z in chunks:
   if spans and a<=spans[-1][1]:spans[-1]=(spans[-1][0],max(z,spans[-1][1]))
   else:spans.append((a,z))
  for a,z in spans:
   if a>1200:continue
   print('FIX80_SPAN',name,a+1,z)
   for k in range(a,z):print('FIX80_CODE',k+1,l[k][:295])
 elif 'AdminWorkshopControlPageController' in name:
  for i,v in enumerate(l):
   if re.search(r'function updateJobLine|function jobAmounts|taxBasisUnverified|gross|gst_total|pre.round|amount',v,re.I):
    if (i<245):print('FIX80_CODE',i+1,v[:240])
 elif 'routes/web.php' in name:
  for i,v in enumerate(l):
   if re.search(r'rot.start|rot.pause|rot.resume|rot.complete|rot.assign|job-cards.*labour|job-amounts|job-line|job-cards.*edit',v,re.I):print('FIX80_CODE',i+1,v[:270])
 else:
  for i,v in enumerate(l[:175]):print('FIX80_CODE',i+1,v[:275])
print('FIX80_AUDIT_SUCCESS')
