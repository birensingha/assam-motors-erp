#!/usr/bin/env python3
"""AM244 source audit — no DB writes. Inspect exact Staff expense against-reference path."""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').exists():raise SystemExit('INVALID_STAGING')
names=['app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
'app/Services/ExpensePaymentService.php','app/Services/StaffPaymentVoucherService.php',
'resources/views/payments/create.blade.php','resources/views/expenses/show.blade.php',
'app/Models/WorkshopExpense.php']
for rel in names:
 p=root/rel
 if not p.is_file():print('AM244_MISSING',rel);continue
 src=p.read_text('utf8','replace').splitlines()
 print('AM244_FILE',rel,len(src))
 if rel.endswith('create.blade.php'):
  ranges=[(1,min(500,len(src)))]
 elif rel.endswith('ExpensePaymentService.php'):
  ranges=[(215,335),(365,430)]
 elif rel.endswith('AdminExpensePaymentPageController.php'):
  ranges=[(1,240)]
 else:ranges=[(1,min(len(src),300))]
 for a,b in ranges:
  for i in range(a-1,min(b,len(src))):
   s=src[i]
   if re.search(r'password|secret|private.key|api[_-]?key|env\(',s,re.I):s='[REDACTED]'
   print('AM244_SRC',rel,i+1,s[:550])
print('AM244_READ_ONLY_DONE')
