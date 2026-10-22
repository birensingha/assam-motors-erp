#!/usr/bin/env python3
"""FIX85 safe inspection: exact Parts inline add, billing table and handler."""
from pathlib import Path
import sys,hashlib,re
r=Path(sys.argv[1]);assert (r/'artisan').is_file()
files={
'resources/views/job-cards/show.blade.php':[(1,185),(398,510),(530,620)],
'app/Http/Controllers/Web/AdminJobCardPageController.php':[(670,860)],
'app/Services/JobCardTaxTotals.php':[(1,230)],
'routes/web.php':[(168,220)],
}
for name,spans in files.items():
 p=r/name
 if not p.exists():print('FIX85_FILE_MISSING',name);continue
 src=p.read_bytes();s=src.decode('utf8','surrogateescape').splitlines()
 print('FIX85_FILE',name,'sha256',hashlib.sha256(src).hexdigest(),'lines',len(s))
 for a,b in spans:
  print('FIX85_SPAN',name,a,b)
  for i in range(a-1,min(b,len(s))):
   if re.search('password|api.key|private.key|secret|bearer',s[i],re.I):continue
   print('FIX85_CODE',name,i+1,s[i][:440].encode('ascii','backslashreplace').decode('ascii'))
for p in (r/'app/Http/Controllers/Web').rglob('*.php'):
 s=p.read_text(errors='replace').splitlines()
 for idx,l in enumerate(s):
  if 'function addPartDirect' in l or 'function addPartsDirect' in l or 'function updateJobLine' in l or 'function editPart' in l:
   print('FIX85_HANDLER',p.relative_to(r),idx+1,'SHA',hashlib.sha256(p.read_bytes()).hexdigest())
   for k in range(max(0,idx-3),min(idx+165,len(s))):
    if re.search('password|api.key|private.key|secret|bearer',s[k],re.I):continue
    print('FIX85_HANDLER_CODE',k+1,s[k][:500])
print('FIX85_SOURCE_DONE')
