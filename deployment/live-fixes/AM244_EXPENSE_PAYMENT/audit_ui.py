#!/usr/bin/env python3
"""AM244 focused source audit of staff payment fields and expense prefill."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging':raise SystemExit('NOT STAGING')
p=root/'resources/views/payments/create.blade.php'
rows=p.read_text('utf8','replace').splitlines()
pattern=re.compile(r'am240StaffExpenses|staff_expense_id|staff_payment_kind|staff_payment_against|am240|am242|payee_category|request\(.*source|request\(.*id|query\(.*source|source_key|\.source_key|EXPENSE_VOUCHER|AMOUNT|Against Reference',re.I)
hits=[i for i,l in enumerate(rows) if pattern.search(l)]
wanted=set()
for i in hits:
 for j in range(max(0,i-9),min(len(rows),i+13)):
  wanted.add(j)
for j in sorted(wanted):
 line=rows[j]
 if re.search('password|secret|private.key|api[_-]?key|env\(',line,re.I):line='[REDACTED]'
 print('AM244_UI',j+1,line[:650])
print('AM244_UI_LINES',len(rows),'HITS',len(hits))
for rel in ['public/js/payment-voucher.js','public/js/am220-payment-single-vendor-search.js','public/js/am218-payment-source-search.js','public/js/payment-voucher-multi.js']:
 file=root/rel
 if not file.exists():continue
 lines=file.read_text('utf8','replace').splitlines()
 print('AM244_JS_START',rel,len(lines))
 for i,l in enumerate(lines,1):
  if re.search('source_key|source_type|expense|staff|payment_against|reference',l,re.I):
   print('AM244_JS_RELEVANT',rel,i,l[:550])
print('AM244_UI_AUDIT_PASS')
