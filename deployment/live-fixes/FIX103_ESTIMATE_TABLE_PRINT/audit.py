#!/usr/bin/env python3
from pathlib import Path
import hashlib,re,sys
root=Path(sys.argv[1]);assert (root/'artisan').exists()
for rel in [
 'resources/views/estimates/create.blade.php',
 'resources/views/estimates/print.blade.php',
 'resources/views/estimates/show.blade.php',
 'resources/views/invoices/print.blade.php',
 'resources/views/job-cards/invoice-print.blade.php',
 'resources/views/admin/invoices/print.blade.php',
 'resources/views/layouts/erp.blade.php',
 'app/Http/Controllers/Web/EstimateController.php']:
 p=root/rel;print('FIX103_FILE',rel,p.exists())
 if not p.exists():continue
 s=p.read_text(errors='replace');ls=s.splitlines()
 print('FIX103_META',rel,len(ls),hashlib.sha256(p.read_bytes()).hexdigest())
 if 'estimates/' in rel or 'invoices/print' in rel or 'job-cards/invoice-print' in rel:
  inds=range(len(ls))
 else:
  hits=[i for i,x in enumerate(ls) if any(y in x.lower() for y in ['estimates','invoice','print','asset','@vite','script src','link href'])]
  sel=set()
  for i in hits[:15]:sel.update(range(max(0,i-3),min(len(ls),i+8)))
  inds=sorted(sel)
 for i in inds:
  if i>=280 and 'print.blade.php' not in rel and 'create.blade.php' not in rel:break
  x=ls[i]
  if re.search(r'password|api.key|bearer|private.key|secret\s*[=:]',x,re.I):continue
  print('FIX103_CODE',rel,i+1,x[:1100].encode('ascii','backslashreplace').decode('ascii'))
# Find loaded estimate specific css/js
for area in ['public/js','public/css','resources/js','resources/css']:
 p=root/area
 if not p.exists():continue
 for f in p.glob('*'):
  if f.is_file() and f.suffix in ['.js','.css'] and f.stat().st_size<1000000:
   x=f.read_text(errors='replace')
   terms=[k for k in ['estimate-line-table','estimate-preview-total','estimate-lines-area','AM_ESTIMATE_DATA','line-total','estimate-line'] if k in x]
   if terms:print('FIX103_ASSET',f.relative_to(root),hashlib.sha256(f.read_bytes()).hexdigest(),terms,'BYTES',len(x))
print('FIX103_AUDIT_COMPLETE')
