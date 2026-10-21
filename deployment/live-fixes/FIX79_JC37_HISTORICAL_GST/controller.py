#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
names=['taxBasisUnverified','Historical GST requires review','jobLine(','function jobAmounts','job-amounts','tax-basis']
for d in ['app/Http/Controllers/Web','app/Services','routes']:
 folder=root/d
 if not folder.exists():continue
 for f in folder.rglob('*.php'):
  s=f.read_text(errors='replace')
  lines=s.splitlines()
  hits=[i for i,x in enumerate(lines) if any(n in x for n in names)]
  if not hits:continue
  print('FIX79_CTRL_FILE',f.relative_to(root),'hash',hashlib.sha256(f.read_bytes()).hexdigest(),'lines',len(lines),'hits',len(hits))
  for i in hits[:20]:
   print('FIX79_CTRL_HIT',i+1,lines[i][:270])
   for j in range(max(0,i-16),min(len(lines),i+39)):
    x=lines[j]
    if re.search(r'password|private.key|api.key|secret',x,re.I):continue
    print('FIX79_CTRL_CODE',j+1,x[:280])
print('FIX79_CONTROLLER_READONLY_DONE')
