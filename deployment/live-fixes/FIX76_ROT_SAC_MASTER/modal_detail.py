#!/usr/bin/env python3
from pathlib import Path
import hashlib,sys,re
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
for rel,spans in {
 'public/legacy/workshop/labour-master.html':[(1,154),(167,288),(300,374)],
 'public/legacy/api/labour-crud.php':[(1,270)],
 'public/legacy/api/labour-master.php':[(1,240)],
 'public/legacy/api/labour.php':[(1,160)],
 'public/legacy/api/labours.php':[(1,120)],
}.items():
 f=root/rel
 if not f.is_file():print('FIX76_MODAL_DETAIL_ABSENT',rel);continue
 raw=f.read_bytes();ls=raw.decode('utf8','surrogateescape').splitlines()
 print('FIX76_MODAL_DETAIL_FILE',rel,'SHA',hashlib.sha256(raw).hexdigest(),'LINECOUNT',len(ls))
 for a,b in spans:
  print('FIX76_DETAIL_SLICE',rel,a,b)
  for i in range(a-1,min(b,len(ls))):
   if re.search(r'password|secret|private.key|api.key|token',ls[i],re.I):continue
   print('FIX76_DETAIL',i+1,ls[i][:360].encode('utf8','backslashreplace').decode('utf8'))
for folder in ['public/legacy/api','public/legacy/workshop']:
 d=root/folder
 if not d.is_dir():continue
 for f in d.glob('*labour*'):
  print('FIX76_LABOUR_FILE',str(f.relative_to(root)))
print('FIX76_MODAL_DETAIL_COMPLETE')
