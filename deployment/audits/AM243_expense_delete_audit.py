#!/usr/bin/env python3
"""READ ONLY: inspect Expense voucher deletion/cancellation/void routes and safeguards."""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('NO_STAGING')
targets=[root/'routes/web.php']
for area in ['app/Http/Controllers/Web','app/Services','resources/views']:
 for p in (root/area).rglob('*.php'):
  if re.search('Expense|expense',p.name) and p.stat().st_size<300000:targets.append(p)
for p in targets:
 if not p.is_file():continue
 src=p.read_text('utf8','replace').splitlines()
 rel=p.relative_to(root).as_posix()
 terms=re.compile(r'expense|delete|void|cancel|destroy|unpaid|payment|voucher|edit|action',re.I)
 matches=[i for i,l in enumerate(src) if terms.search(l)]
 if not matches:continue
 print('AM243_FILE',rel,len(src),'HITS',len(matches))
 seen=set()
 for index in matches[:125]:
  start=max(0,index-3);end=min(len(src),index+5)
  for i in range(start,end):
   if i in seen:continue
   seen.add(i)
   s=src[i]
   if re.search(r'(password|secret|api[_-]?key|env\()',s,re.I):s='[REDACTED]'
   if rel=='routes/web.php' and not re.search(r'expens|payment|voucher',s,re.I):continue
   print('AM243_SRC',rel,i+1,s[:460])
print('AM243_EXPENSE_READONLY_AUDIT_PASS')
