#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1])
for rel in ['public/js/estimate-entry.js','public/css/erp.css','resources/views/invoices/print.blade.php']:
 p=root/rel;ls=p.read_text(errors='replace').splitlines()
 print('FIX103_DETAIL_META',rel,len(ls),hashlib.sha256(p.read_bytes()).hexdigest())
 if rel.endswith('estimate-entry.js'):
  parts=range(len(ls))
 elif rel.endswith('erp.css'):
  picks=[i for i,x in enumerate(ls) if any(k in x for k in ['estimate-line-table','preview-total','purchase-foot','line-scroll','line-table','estimate-lines-area'])]
  indices=set()
  for i in picks:indices.update(range(max(0,i-4),min(len(ls),i+12)))
  parts=sorted(indices)
 else:
  parts=list(range(8,68))+list(range(192,219))+list(range(280,345))+list(range(370,405))+list(range(465,545))
 for i in parts:
  if i>=len(ls):break
  x=ls[i]
  if re.search(r'password|bearer|api.key|secret\s*[=:]|private.key',x,re.I):continue
  print('FIX103_DETAIL',rel,i+1,x[:1300].encode('ascii','backslashreplace').decode('ascii'))
print('FIX103_DETAIL_DONE')
