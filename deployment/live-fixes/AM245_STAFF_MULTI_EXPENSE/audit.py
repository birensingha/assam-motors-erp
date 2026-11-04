#!/usr/bin/env python3
"""AM245 read-only inspection of staff expense allocation, vendor multi-bill and server guards."""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM245_BAD_ROOT')
targets={
 'app/Services/StaffPaymentVoucherService.php':[(1,580)],
 'app/Services/ExpensePaymentService.php':[(260,430)],
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php':[(185,280)],
 'resources/views/payments/create.blade.php':[(50,125),(290,475),(1000,1180),(1200,1540),(1550,1820)],
 'app/Models/WorkshopPaymentVoucher.php':[(1,115)],
 'app/Models/WorkshopExpense.php':[(1,115)],
}
for rel,ranges in targets.items():
 p=root/rel
 if not p.is_file():print('AM245_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM245_FILE',rel,'LINES',len(lines))
 for start,end in ranges:
  for n in range(start,min(end,len(lines))+1):
   s=lines[n-1]
   if re.search(r'password|secret|private.key|api[_-]?key|env\(',s,re.I):s='[REDACTED]'
   print('AM245_SRC',rel,n,s[:600])
print('AM245_SOURCE_COMPLETE_READONLY')
