#!/usr/bin/env python3
from pathlib import Path
import re,sys
root=Path(sys.argv[1])
for rel in ['resources/views/dashboard/partials/rot-live-clock.blade.php',
'resources/views/job-cards/show.blade.php',
'resources/views/layouts/erp.blade.php']:
 p=root/rel
 if not p.exists():print('FIX94_MISSING',rel);continue
 lines=p.read_text(errors='replace').splitlines()
 print('FIX94_SRC',rel,'LINES',len(lines))
 if 'rot-live-clock' in rel:
  for i,l in enumerate(lines):
   if i>=215:break
   print('FIX94_CLOCK',i+1,l[:600].encode('ascii','backslashreplace').decode('ascii'))
 elif 'show.blade.php' in rel:
  for a,b in [(745,810),(1588,1694),(2130,2255),(1288,1335)]:
   for i in range(a-1,min(b,len(lines))):
    print('FIX94_STYLE',i+1,lines[i][:550].encode('ascii','backslashreplace').decode('ascii'))
 else:
  hits=[i for i,l in enumerate(lines) if ('rot-' in l or 'live' in l) and ('css' in l or 'style' in l or 'link' in l)]
  print('FIX94_LAYOUT_LINKS',[(i+1,lines[i][:450]) for i in hits[:30]])
print('FIX94_CLOCK_AUDIT_COMPLETE')
