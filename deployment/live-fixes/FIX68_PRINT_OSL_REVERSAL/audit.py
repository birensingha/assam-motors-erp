#!/usr/bin/env python3
"""Read-only introspection of JC print, Advance Reversal and OSL Purchase code."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1])
assert (root/'artisan').is_file()
patterns=[re.compile(s,re.I) for s in ['advance.{0,12}reversal','reversal.{0,12}centre','osl.{0,20}purchase','job.?card.{0,20}(summary|print)','gst.{0,20}(inclusive|included|applicable|exclusive)','company.{0,16}(logo|address)','print-logo']]
routes=root/'routes/web.php'
for n,l in enumerate(routes.read_text(errors='replace').splitlines(),1):
 if any(p.search(l) for p in patterns):
  print('ROUTE',n,l[:270].strip())
files=[]
for folder in ('resources/views/job-cards','resources/views/purchases','resources/views/osl','resources/views/admin','app/Http/Controllers/Web','app/Services'):
 d=root/folder
 if not d.exists():continue
 for f in d.rglob('*'):
  if not f.is_file() or f.suffix not in ('.php','.css','.js'):continue
  if any(x in str(f).lower() for x in ('vendor','node_modules')):continue
  s=f.read_text(errors='replace')
  relevant=any(p.search(s) for p in patterns)
  if not relevant:continue
  lines=s.splitlines()
  hits=[(i+1,l[:220]) for i,l in enumerate(lines) if any(p.search(l) for p in patterns)]
  print('CANDIDATE',str(f.relative_to(root)),'LINES',len(lines),'HITS',len(hits),'SHA',hashlib.sha256(f.read_bytes()).hexdigest())
  for i,l in hits[:24]:print('HIT',i,l.strip())
  files.append((f,hits))
# Expose bounded relevant view text and controller methods, excluding unrelated data
prioritized=[]
for f,hits in files:
 path=str(f.relative_to(root)).lower()
 if any(x in path for x in ('print.blade','summary.blade','osl','purchase')) or 'show.blade.php' in path:
  prioritized.append((f,hits))
for f,hits in prioritized[:16]:
 lines=f.read_text(errors='replace').splitlines()
 path=str(f.relative_to(root))
 windows=[]
 if len(lines)<=240:windows=[(1,len(lines))]
 else:
  for line,_ in hits[:6]:
   windows.append((max(1,line-11),min(len(lines),line+24)))
 for start,end in windows:
  print('SLICE',path,start,end)
  for i in range(start-1,end):
   s=lines[i]
   if re.search(r'(password|private.key|secret|api.key|token|authorization)',s,re.I):continue
   print('CODE',i+1,s[:250])
print('FIX68_READONLY_AUDIT_COMPLETE')
