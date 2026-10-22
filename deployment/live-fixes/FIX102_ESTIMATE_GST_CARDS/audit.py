#!/usr/bin/env python3
"""FIX102 read-only estimate summary totals, including tax split and print logic."""
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').exists()
files=['resources/views/estimates/show.blade.php',
       'resources/views/estimates/print.blade.php',
       'app/Http/Controllers/Web/EstimateController.php',
       'app/Http/Controllers/Web/AdminEstimateController.php',
       'resources/views/estimates/create.blade.php']
for rel in files:
 p=root/rel
 print('FIX102_FILE',rel,'EXISTS',p.exists())
 if not p.exists():continue
 lines=p.read_text(errors='replace').splitlines()
 print('FIX102_HASH',rel,hashlib.sha256(p.read_bytes()).hexdigest(),'LINES',len(lines))
 if rel.endswith('show.blade.php') or rel.endswith('print.blade.php'):
  targets=range(len(lines))
 else:
  match=[i for i,l in enumerate(lines) if any(k in l.lower() for k in
   ['function show(','function print(','function view(','part_total','labour_total','tax_total','discount_total','grand_total'])]
  indexes=set()
  for i in match[:15]:indexes.update(range(max(0,i-4),min(len(lines),i+14)))
  targets=sorted(indexes)
 for i in targets:
  l=lines[i]
  if re.search(r'password|bearer|secret\s*[=:]|private.key',l,re.I):continue
  print('FIX102_SOURCE',rel,i+1,l[:740].encode('ascii','backslashreplace').decode('ascii'))
print('FIX102_AUDIT_DONE')
