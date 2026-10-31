#!/usr/bin/env python3
"""AM231 inspect current staging payment source vs retained first backup, no writes."""
from pathlib import Path
import difflib,hashlib,sys
root=Path(sys.argv[1]).resolve()
base=root/'storage/app/am-backups/AM231_PAYMENT_ACCOUNTING_UI'
pairs=[
 ('VIEW',root/'resources/views/payments/create.blade.php',base/'create.blade.php.before_AM231'),
 ('CONTROLLER',root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',base/'controller.php.before_AM231')
]
for label,current,old in pairs:
 print('AM231_DIAG_'+label+'_BACKUP_EXISTS='+str(old.is_file()))
 if not old.is_file():continue
 a=old.read_text('utf8',errors='surrogateescape').splitlines()
 b=current.read_text('utf8',errors='surrogateescape').splitlines()
 print('AM231_DIAG_'+label+'_CURRENT_SAME_BACKUP='+str(a==b))
 print('AM231_DIAG_'+label+'_LINE_COUNTS='+str(len(a))+'/'+str(len(b)))
 if a!=b:
  matcher=difflib.SequenceMatcher(a=a,b=b,autojunk=False)
  operations=[x for x in matcher.get_opcodes() if x[0]!='equal']
  for op in operations[:18]:
   tag,i,j,k,l=op
   before=' '.join(a[i:j])
   after=' '.join(b[k:l])
   # Show only short first 110 chars for debugging source edits,
   # no payment records are printed.
   print('AM231_DIAG_DIFF '+tag+f' old={i+1}:{j} now={k+1}:{l}')
   print('AM231_DIAG_OLD='+before[:110])
   print('AM231_DIAG_NEW='+after[:110])
print('AM231_DIAGNOSTIC_COMPLETE_NO_WRITES')
