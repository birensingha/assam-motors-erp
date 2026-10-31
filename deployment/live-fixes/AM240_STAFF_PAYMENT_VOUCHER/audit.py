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

more={
 'PAYMENT_SERVICE':('app/Services/ExpensePaymentService.php',[(8,80),(210,265),(275,360),(390,455)]),
 'PAYMENT_MODEL':('app/Models/WorkshopPaymentVoucher.php',[(1,110)]),
 'PAYMENT_EDIT':('app/Http/Controllers/Web/AdminPaymentVoucherEditController.php',[(1,155)]),
 'PAYMENT_CONTROLLER':('app/Http/Controllers/Web/AdminExpensePaymentPageController.php',[(175,225)]),
 'STAFF_LEDGER':('app/Http/Controllers/Web/AdminStaffFinanceLedgerController.php',[(206,267)]),
 'PAYMENT_VIEW':('resources/views/payments/create.blade.php',[(335,382),(665,708)]),
}
for name,(relative,spans) in more.items():
 p=root/relative
 if not p.is_file():continue
 lines=p.read_text('utf8',errors='surrogateescape').splitlines()
 for a,b in spans:
  print('AM240_MORE_'+name+'_'+str(a)+'_'+str(b))
  for i in range(a-1,min(b,len(lines))):
   print('AM240_LINE_'+name+'_'+str(i+1)+' '+lines[i][:420])

p=root/'app/Models/ErpAuthUser.php'
if p.is_file():
 for i,l in enumerate(p.read_text('utf8',errors='surrogateescape').splitlines()):
  if i<155:print('AM240_AUTH_MODEL_'+str(i+1)+' '+l[:380])
