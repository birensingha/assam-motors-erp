#!/usr/bin/env python3
from pathlib import Path
import hashlib,sys,re
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
terms=['function show(','function sendToJobCard(','function sync(','function save(','function addLabourDirect(']
files=['app/Http/Controllers/Web/AdminJobCardPageController.php',
'app/Http/Controllers/Web/AdminEstimatePageController.php',
'app/Http/Controllers/Web/AdminWorkshopControlPageController.php',
'app/Services/EstimateService.php',
'app/Services/EstimateReleaseService.php',
'resources/views/estimates/show.blade.php']
for name in files:
 p=root/name
 if not p.is_file():continue
 lines=p.read_text(errors='replace').splitlines()
 print('FIX83_DETAIL_FILE',name,'sha',hashlib.sha256(p.read_bytes()).hexdigest(),len(lines))
 h=[i for i,l in enumerate(lines) if any(t in l for t in terms)]
 if name.endswith('AdminJobCardPageController.php'):h=[i for i,l in enumerate(lines) if 'function show(' in l]
 if name.endswith('AdminWorkshopControlPageController.php'):h=[]
 if name.endswith('show.blade.php'):h=[0]
 windows=[(max(0,i-8),min(len(lines),i+105)) for i in h[:5]]
 if name.endswith('AdminEstimatePageController.php'):windows=[(0,len(lines))]
 for lo,hi in windows:
  print('FIX83_DETAIL_SPAN',name,lo+1,hi)
  for n in range(lo,hi):
   l=lines[n]
   if re.search(r'password|secret|api.key|private.key|token',l,re.I):continue
   print('FIX83_DETAIL',name,n+1,l[:370].encode('ascii','backslashreplace').decode('ascii'))
print('FIX83_DETAIL_DONE')
