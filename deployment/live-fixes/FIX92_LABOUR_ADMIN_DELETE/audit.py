#!/usr/bin/env python3
"""FIX92 read-only: inspect Job Card ROT session relations & existing deletion safeguards."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').exists()
for rel in ['resources/views/job-cards/show.blade.php','routes/web.php',
            'app/Http/Controllers/Web/JobCardRotAlterationController.php',
            'app/Http/Controllers/Web/AdminRotPageController.php',
            'app/Http/Controllers/Web/AdminWorkshopControlPageController.php',
            'app/Http/Controllers/Web/AdminJobCardPageController.php',
            'app/Services/JobCardTaxTotals.php']:
 p=root/rel
 if not p.exists():print('FIX92_MISSING',rel);continue
 lines=p.read_text(errors='replace').splitlines()
 print('FIX92_FILE',rel,'SHA',hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(lines))
 if rel.startswith('resources/'):
  needle=['function showEditor','function closeDialog','function rebuild','am90-editor-context','data-am90-labour-id','rot-meta','rot-status-block','rot-live-clock','data-am91-osl']
  for term in needle:
   hits=[i+1 for i,x in enumerate(lines) if term in x]
   print('FIX92_ANCHOR',term,hits[:35])
  ranges=[(1157,1239),(1605,1734),(2033,2250)]
 elif 'RotAlteration' in rel:
  ranges=[(1,min(200,len(lines)))]
 elif 'AdminRotPage' in rel:
  ranges=[(1,min(230,len(lines)))]
 elif 'AdminWorkshopControlPage' in rel:
  hits=[i for i,l in enumerate(lines) if 'function updateJobLine' in l or 'function recalculateJob' in l]
  ranges=[(max(1,i-2),min(i+118,len(lines))) for i in hits]
 elif 'AdminJobCardPage' in rel:
  hits=[i for i,l in enumerate(lines) if 'function addLabourDirect' in l or 'function admin(' in l]
  ranges=[(max(1,i-2),min(i+85,len(lines))) for i in hits]
 elif 'web.php' in rel:
  ranges=[(126,153),(203,221)]
 else:ranges=[(1,min(100,len(lines)))]
 seen=set()
 for start,end in ranges:
  for n in range(start,min(end,len(lines))+1):
   if n in seen:continue
   seen.add(n);l=lines[n-1]
   if re.search(r'password|bearer|api.key|secret|private.key',l,re.I):continue
   print('FIX92_CODE',rel,n,l[:380].encode('ascii','backslashreplace').decode('ascii'))
try:
 import subprocess
 result=subprocess.run(['php','artisan','route:list','--path=erp/job-cards','--json'],cwd=str(root),capture_output=True,text=True,timeout=22)
 print('FIX92_ROUTES_RETURN',result.returncode)
 if result.returncode==0:
  import json
  routes=json.loads(result.stdout)
  for item in routes:
   if any(x in item.get('uri','') for x in ['/labour/','/osl-billing']):print('FIX92_ROUTE',item['method'],item['uri'],item.get('name',''))
except Exception as e:print('FIX92_ROUTES_UNAVAILABLE',type(e).__name__)
# No rows or customer data queried or printed.
print('FIX92_AUDIT_COMPLETE')
