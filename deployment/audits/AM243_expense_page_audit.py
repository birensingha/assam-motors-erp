#!/usr/bin/env python3
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').exists():raise SystemExit('INVALID_STAGING')
for rel in ('resources/views/expenses/show.blade.php','resources/views/expenses/index.blade.php','app/Services/ExpensePaymentService.php'):
 p=root/rel
 if not p.is_file():print('AM243_MISSING',rel);continue
 src=p.read_text('utf8','replace').splitlines()
 print('AM243_VIEW_FILE',rel,len(src))
 if rel.endswith('ExpensePaymentService.php'):
  idx=[i for i,s in enumerate(src) if 'function voidExpense' in s or 'function showExpense' in s]
  windows=[(max(0,i-4),min(len(src),i+31)) for i in idx]
 else:windows=[(0,min(420,len(src)))]
 for start,end in windows:
  for i in range(start,end):
   s=src[i]
   if re.search(r'password|secret|api[_-]?key|env\(',s,re.I):s='[REDACTED]'
   print('AM243_VIEW',rel,i+1,s[:500])
print('AM243_VIEW_INSPECT_PASS')
