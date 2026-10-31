#!/usr/bin/env python3
"""AM246 read-only inspect live Parts Purchase line JC / Vehicle controls and JS bindings."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM246_STOP_NOT_STAGING')
needles=re.compile(r'PART search is connected|STOCK is default|Search JC / vehicle / customer|AGAINST|job.card|job-select|job-filter|vehicle',re.I)
roots=['resources/views/purchases','public/js','app/Http/Controllers/Web']
for dr in roots:
 d=root/dr
 if not d.exists():continue
 for p in d.rglob('*'):
  if not p.is_file() or p.suffix not in ('.js','.php') or p.stat().st_size>420000:continue
  lines=p.read_text('utf8','replace').splitlines()
  hits=[i for i,v in enumerate(lines) if needles.search(v)]
  if hits:
   name=str(p.relative_to(root))
   if dr=='app/Http/Controllers/Web' and 'Purchase' not in name:continue
   print('AM246_FILE',name,'LINES',len(lines),'HITS',','.join(str(i+1) for i in hits[:65]))
   seen=set()
   for i in hits[:55]:
    if dr=='public/js' and not re.search(r'job|purchase|part|stock|vehicle',name,re.I):continue
    for n in range(max(0,i-3),min(len(lines),i+4)):
     if n in seen:continue
     seen.add(n)
     if re.search(r'(?i)password|token|secret|authorization|bearer',lines[n]):continue
     print('AM246_LINE',n+1,lines[n][:900])

for name,ranges in {
 'public/js/purchase-entry.js':[(300,351),(380,465)],
 'public/js/purchase-edit.js':[(210,285),(360,435)],
 'resources/views/purchases/create.blade.php':[(388,442)],
 'resources/views/purchases/edit.blade.php':[(465,526)]
}.items():
 p=root/name
 if not p.is_file():continue
 source=p.read_text('utf8','replace').splitlines()
 for start,end in ranges:
  print('AM247_SECTION',name,start,end)
  for i in range(start-1,min(end,len(source))):
   line=source[i]
   if re.search(r'(?i)password|secret|bearer|authorization',line):continue
   print('AM247_LINE',i+1,line[:800])
print('AM247_READONLY_DONE')
print('AM246_READONLY_DONE')
