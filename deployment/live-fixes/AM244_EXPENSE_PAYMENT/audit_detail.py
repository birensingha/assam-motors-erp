#!/usr/bin/env python3
from pathlib import Path
import sys
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging':raise SystemExit('INVALID')
for rel,sections in {
  'resources/views/payments/create.blade.php':[(1410,1638),(1768,1800)],
  'resources/views/expenses/show.blade.php':[(1,8),(19,24)],
  'app/Services/ExpensePaymentService.php':[(263,335)],
}.items():
 lines=(root/rel).read_text('utf8','replace').splitlines()
 for lo,hi in sections:
  for i in range(lo-1,min(len(lines),hi)):
   print('AM244_DETAIL',rel,i+1,lines[i][:430])
print('AM244_DETAIL_DONE')
