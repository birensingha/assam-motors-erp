#!/usr/bin/env python3
"""AM236 read-only target contract audit of staging Payment Voucher source.
Prints relevant code fragments only (NO database rows, connection credentials, writes).
"""
from pathlib import Path
import re,sys
root=Path(sys.argv[1]).resolve()
files={
 'VIEW':root/'resources/views/payments/create.blade.php',
 'CONTROLLER':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'SERVICE':root/'app/Services/ExpensePaymentService.php',
 'MULTI':root/'app/Services/MultiPurchasePaymentService.php'
}
patterns={
 'VIEW':r'source-type|source_type|source-key|source_reference|reference_no|am231-payment-purpose|payment-value|payment_mode|name="notes"|id="payment-form"|name="account_name"',
 'CONTROLLER':r'function storePayment|source_type|source_reference|reference_no|source_key|ADVANCE|ON_ACCOUNT|payee_category|request_key',
 'SERVICE':r'function createVoucher|function resolveSource|ADVANCE|ON_ACCOUNT|source_reference|reference_no|source_type|source_id|function pay|createPayment',
 'MULTI':r'function |source_type|source_reference|reference_no|ADVANCE|ON_ACCOUNT'
}
for name,file in files.items():
 print('AM236_FILE_'+name+'_EXISTS='+str(file.is_file()))
 if not file.is_file():continue
 lines=file.read_text('utf8',errors='surrogateescape').splitlines()
 # Limit output to matching lines and 3 lines context for structural inspection.
 # Deliberately skip actual environment/secrets, literal DB values, and SQL rows.
 spans=[]
 for i,line in enumerate(lines):
  if re.search(patterns[name],line,re.IGNORECASE):
   spans.append((max(0,i-2),min(len(lines),i+4)))
 merged=[]
 for a,b in spans:
  if merged and a<=merged[-1][1]:
   merged[-1]=(merged[-1][0],max(merged[-1][1],b))
  else:merged.append((a,b))
 emitted=0
 print('AM236_FILE_'+name+'_LINES='+str(len(lines)))
 for start,end in merged:
  if emitted>=650:break
  print('AM236_SNIP_'+name+'_'+str(start+1)+'_'+str(end))
  for i in range(start,end):
   if emitted>=650:break
   print(f'{i+1}: {lines[i][:350]}')
   emitted+=1
 print('AM236_FILE_'+name+'_MATCH_GROUPS='+str(len(merged)))
print('AM236_READONLY_PAYMENT_REFERENCE_SOURCE_AUDIT_COMPLETE')

# Extra targeted UI JS/source visibility contract for NEW_REFERENCE implementation.
for title,path,ranges in [
 ('VIEW_JS',files['VIEW'],[(385,470),(475,560),(595,670)]),
 ('CONTROLLER',files['CONTROLLER'],[(103,145)]),
 ('SERVICE',files['SERVICE'],[(258,278),(308,327)])
]:
 if not path.is_file():continue
 lines=path.read_text('utf8',errors='surrogateescape').splitlines()
 for a,b in ranges:
  print(f'AM236_RANGED_{title}_{a}_{b}')
  for i in range(a-1,min(b,len(lines))):print(f'AM236_LINE_{title}_{i+1}: {lines[i][:450]}')
