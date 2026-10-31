#!/usr/bin/env python3
"""AM240 read-only audit: Job Card Add OSL Billing dropdown, data loading and caching.
No database data or credentials printed.
"""
from pathlib import Path
import sys,re
if len(sys.argv)!=2:raise SystemExit('AM240_USAGE ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').exists():
 raise SystemExit('AM240_STOP_NOT_STAGING')
locations=[
 root/'resources/views/job-cards',root/'resources/views/dashboard',
 root/'resources/views/partials',
 root/'app/Http/Controllers/Web/AdminJobCardPageController.php',
 root/'app/Http/Controllers/Web/AdminJobCardOslLineController.php',
 root/'routes/web.php',
]
pattern=re.compile(r'(Manual / legacy item|OSL Master Item|Add OSL Billing|oslMasters|osl_masters|oslMaster|osl_master|osl-master|workshop_osl_master|am146|am150|OSL Register|Create OSL Master)',re.I)
print('AM240_INDEX_BEGIN')
candidates=[]
for loc in locations:
 if loc.is_file():items=[loc]
 elif loc.is_dir():items=list(loc.rglob('*.blade.php'))
 else:continue
 for p in items:
  if p.stat().st_size>800000:continue
  lines=p.read_text('utf8','replace').splitlines()
  hits=[i for i,line in enumerate(lines) if pattern.search(line)]
  if hits:
   candidates.append((p,lines,hits))
   print('AM240_FILE',str(p.relative_to(root)),len(lines),'HITS',','.join(str(x+1) for x in hits[:120]))
print('AM240_INDEX_END')
for p,lines,hits in candidates:
 print('AM240_SNIPPET_BEGIN',str(p.relative_to(root)))
 printed=set()
 for i in hits[:60]:
  for j in range(max(0,i-5),min(len(lines),i+8)):
   if j in printed:continue
   printed.add(j)
   line=lines[j]
   if re.search(r'(?i)password|secret|bearer|authorization|api[_-]?key',line):continue
   print('AM240_LINE',j+1,line[:750])
 print('AM240_SNIPPET_END',str(p.relative_to(root)))
print('AM240_READONLY_COMPLETE')
