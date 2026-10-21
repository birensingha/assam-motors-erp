#!/usr/bin/env python3
"""FIX81 targeted no-write audit for unified JC ROT input and billing save."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
for p in [view,root/'resources/views/job-cards/partials/am65-customer-labour.blade.php',root/'app/Http/Controllers/Web/AdminWorkshopControlPageController.php']:
 b=p.read_bytes();print('FIX81_FILE',p.relative_to(root),hashlib.sha256(b).hexdigest(),len(b.splitlines()))
ls=view.read_text(errors='replace').splitlines()
for lo,hi in [(375,436),(566,646)]:
 for n in range(lo-1,min(hi,len(ls))):print('FIX81_SHOW',n+1,ls[n][:500])
routes=(root/'routes/web.php').read_text(errors='replace').splitlines()
for n,l in enumerate(routes,1):
 if re.search('labour.direct|labour.assign|rot.start|rot.pause|rot.resume|rot.complete|fix48.job-line',l,re.I):print('FIX81_ROUTE',n,l[:450])
names=['function addDirectLabour','function addLabourDirect','function assignLabour','function directLabour','function saveDirectLabour','function assignMechanic','function updateJobLine']
for p in (root/'app/Http/Controllers/Web').rglob('*.php'):
 l=p.read_text(errors='replace').splitlines()
 idxs=[i for i,x in enumerate(l) if any(w in x for w in names)]
 for i in idxs:
  print('FIX81_HANDLER',p.relative_to(root),i+1,'SHA',hashlib.sha256(p.read_bytes()).hexdigest())
  for n in range(max(0,i-10),min(i+170,len(l))):
   if re.search('password|secret|private.key|api.key',l[n],re.I):continue
   print('FIX81_HANDLER_CODE',n+1,l[n][:520])
print('FIX81_AUDIT_DONE')
