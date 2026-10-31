#!/usr/bin/env python3
"""AM250 read-only trace master tables and delete actions across ERP including API and JS."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM250_STOP_NOT_STAGING')
terms=[r'parts_master|part_masters|part_master|workshop_parts',r'parts?/.*(delete|destroy)',r'(delete|destroy).*parts?',
r'customer_master|customers.*delete|delete.*customers',r'expense_heads?|expense_head',r'delete.*(vendor|customer|part|head)',
r'master.?data',r'part_master_id',r'function\s+(destroy|delete|remove|archive)\s*\(',r'Route::(delete|post)']
q=[re.compile(t,re.I) for t in terms]
print('AM250_SOURCE_INDEX_BEGIN')
for dr in ['app','routes','resources/views','public/js','database']:
 folder=root/dr
 if not folder.exists():continue
 for f in folder.rglob('*'):
  if not f.is_file() or f.suffix not in ('.php','.js') or f.stat().st_size>700000:continue
  src=f.read_text('utf8','replace').splitlines()
  hits=[(i+1,line[:240]) for i,line in enumerate(src) if any(x.search(line) for x in q)]
  if not hits:continue
  name=str(f.relative_to(root))
  if len(hits)>85 and not re.search(r'(Part|Customer|Expense|Master|Vendor|API|web\.php)',name,re.I):
   hits=hits[:8]
  print('AM250_FILE',name,'HITS',len(hits),'LINES',','.join(str(h[0]) for h in hits[:70]))
  for ln,t in hits[:38]:
   if re.search(r'password|token|secret|bearer',t,re.I):continue
   if re.search(r'delet|destroy|parts_master|part_master|expense_head|customer.*id|Route::',t,re.I):
    print('AM250_LINE',ln,t)
print('AM250_SOURCE_INDEX_END')
for fname in ['app/Http/Controllers/Web/AdminVendorSearchDeleteController.php','app/Services/VendorReferenceGuard.php']:
 p=root/fname
 if p.is_file():
  print('AM250_EXACT_BEGIN',fname)
  for i,line in enumerate(p.read_text('utf8','replace').splitlines()):
   if re.search(r'password|token|secret',line,re.I):continue
   print('AM250_EXACT_LINE',i+1,line[:600])
  print('AM250_EXACT_END',fname)
print('AM250_NO_DB_READ_OR_WRITES')
