#!/usr/bin/env python3
from pathlib import Path
import sys,hashlib,re
root=Path(sys.argv[1]);v=root/'resources/views/job-cards/show.blade.php'
s=v.read_text(errors='replace').splitlines()
print('FIX94_VIEW_SHA',hashlib.sha256(v.read_bytes()).hexdigest(),'LINES',len(s))
for a,b in [(1190,1310),(1,145)]:
 for i in range(a-1,min(b,len(s))):
  x=s[i]
  if re.search(r'(secret|password|bearer|private.key)',x,re.I):continue
  print('FIX94_CODE',i+1,x[:440].encode('ascii','backslashreplace').decode('ascii'))
for rel in ['resources/views/job-cards/rot-time-editor.blade.php','resources/views/layouts/erp.blade.php']:
 p=root/rel
 if p.exists():
  ls=p.read_text(errors='replace').splitlines()
  print('FIX94_FILE',rel,'LINES',len(ls))
  if 'rot-time' in rel:
   for i,l in enumerate(ls[:150]):print('FIX94_TIME',i+1,l[:350].encode('ascii','backslashreplace').decode('ascii'))
for k in ['.rot-card','.rot-list','.rot-head','.rot-timer','rot-progress','am83-grid-header','ROT Work Controls','V645_ADMIN_WIP_ROT_TIME_EDIT','am92-productive','rot-remaining']:
 inds=[i+1 for i,l in enumerate(s) if k in l]
 print('FIX94_ANCHOR',k,inds[:30])
print('FIX94_AUDIT_COMPLETE')
