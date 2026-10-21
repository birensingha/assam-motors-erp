#!/usr/bin/env python3
"""FIX83 read-only: locate every Labour and OSL area; inspect estimate release."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]); assert (root/'artisan').is_file()
for name in ['resources/views/job-cards/show.blade.php','resources/views/job-cards/partials/am65-customer-labour.blade.php','routes/web.php']:
 p=root/name
 if not p.is_file():continue
 lines=p.read_text(errors='replace').splitlines()
 print('FIX83_FILE',name,hashlib.sha256(p.read_bytes()).hexdigest(),len(lines))
 terms=r'labour|rot|osl|estimate|send.to|release|parts|add new'
 if name.endswith('show.blade.php'):
  for i,line in enumerate(lines,1):
   if re.search(r'<h[234]|section-title|jc-.*block|FIX82|rot-controls|am82-rot|job-cards.*labour|osl|Send to JC|include.*labour',line,re.I):
    print('FIX83_SHOW_ANCHOR',i,line[:245])
  # Only relevant block sections.
  for lo,hi in [(377,476),(570,810)]:
   for i in range(lo-1,min(hi,len(lines))):
    print('FIX83_SHOW_LINE',i+1,lines[i][:360])
 elif name=='routes/web.php':
  for i,line in enumerate(lines,1):
   if re.search(r'estimate|osl|labour\.direct|labour\.assign|rot\.start|rot\.pause|rot\.resume|rot\.complete',line,re.I):
    print('FIX83_ROUTE',i,line[:360])
for rootdir in ['app/Http/Controllers/Web','app/Services','resources/views/estimates','resources/views/job-cards']:
 folder=root/rootdir
 if not folder.is_dir():continue
 for p in folder.rglob('*.php'):
  if p.stat().st_size>650000:continue
  t=p.read_text(errors='replace')
  if any(q in t for q in ["Send to Job Card","SEND TO JOB CARD","sent_to_job_card","releaseToJobCard","sendToJobCard","send-to-job-card","releaseEstimate","release_to_job_card"]):
   l=t.splitlines()
   print('FIX83_ESTIMATE_FILE',p.relative_to(root),'sha',hashlib.sha256(p.read_bytes()).hexdigest(),'lines',len(l))
   for i,s in enumerate(l):
    if re.search('send.to.job.card|release.to.job.card|releaseEstimate|sent_to_job_card',s,re.I):
     print('FIX83_ESTIMATE_HIT',i+1,s[:280])
     for k in range(max(0,i-7),min(len(l),i+17)):
      print('FIX83_ESTIMATE_CODE',k+1,l[k][:260])
print('FIX83_READONLY_DONE')
