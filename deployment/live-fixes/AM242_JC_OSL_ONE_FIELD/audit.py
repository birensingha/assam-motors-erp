#!/usr/bin/env python3
"""AM242 read-only modal audit with exact JS blocks and control selectors."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM242_STOP_NOT_STAGING')
targets={
 'resources/views/job-cards/show.blade.php':[(2850,3075),(3520,3695),(3695,3850)],
 'app/Http/Controllers/Web/AdminJobCardOslLineController.php':[(1,110)]
}
for name,ranges in targets.items():
 f=root/name
 if not f.is_file():raise SystemExit('AM242_STOP_FILE_'+name)
 lines=f.read_text('utf8','replace').splitlines()
 for start,end in ranges:
  print('AM242_SECTION',name,start,end)
  for n in range(start-1,min(end,len(lines))):
   s=lines[n]
   if re.search(r'(?i)password|secret|bearer|authorization',s):continue
   print('AM242_LINE',n+1,s[:1000])
print('AM242_PANEL_INDEX_BEGIN')
lines=(root/'resources/views/job-cards/show.blade.php').read_text('utf8','replace').splitlines()
for i,line in enumerate(lines):
 if re.search(r'Select Linked OSL|OSL Jobs / Outside Services|am83-osl|am93-osl|am91-osl-dialog|am146-master|am241',line,re.I):
  print('AM242_HIT',i+1,line.strip()[:200])
print('AM242_PANEL_INDEX_END')
print('AM242_READONLY_DONE')
