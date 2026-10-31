#!/usr/bin/env python3
from pathlib import Path
import sys,re,subprocess
root=Path(sys.argv[1]).resolve()
targets={
 'STAFF_CONTROLLER':('app/Http/Controllers/Web/AdminStaffFinanceLedgerController.php',[(52,123),(124,208)]),
 'STAFF_VIEW':('resources/views/staff-finance/index.blade.php',[(1,275)]),
 'PAYMENT_CONTROLLER':('app/Http/Controllers/Web/AdminExpensePaymentPageController.php',[(1,91),(168,205)]),
 'PAYMENT_VIEW':('resources/views/payments/create.blade.php',[(1,155),(200,275),(410,470)]),
 'PAYMENT_SHOW':('resources/views/payments/show.blade.php',[(1,130)]),
 'PAYMENT_CSS_JS':('resources/views/payments/create.blade.php',[(845,890)]),
}
for key,(relative,ranges) in targets.items():
 p=root/relative;print('AM240_FILE_'+key+'_EXISTS='+str(p.is_file()))
 if not p.is_file():continue
 rows=p.read_text('utf8',errors='surrogateescape').splitlines()
 for a,b in ranges:
  print('AM240_SECTION_'+key+'_'+str(a)+'_'+str(b))
  for i in range(a-1,min(b,len(rows))):
   print('AM240_DETAIL_'+key+'_'+str(i+1)+' '+rows[i][:460])
print('AM240_AUDIT_READONLY_COMPLETE')
