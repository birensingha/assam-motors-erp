#!/usr/bin/env python3
from pathlib import Path
import sys,re
root=Path(sys.argv[1]);p=root/'resources/views/job-cards/show.blade.php'
lines=p.read_text(errors='replace').splitlines()
for a,b in [(1504,1590),(1630,1705),(1873,2085),(3040,3090)]:
 print('FIX100_SEGMENT',a,b)
 for i in range(a-1,min(len(lines),b)):
  l=lines[i]
  if re.search(r'password|secret\s*[=:]|bearer|private.key',l,re.I):continue
  print('FIX100_SOURCE',i+1,l[:600].encode('ascii','backslashreplace').decode('ascii'))
print('FIX100_DETAIL_DONE')
