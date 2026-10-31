#!/usr/bin/env python3
"""AM259 read-only schema/code audit of Staff, Expenses, Payment, Salary, ledgers. No DB changes."""
from pathlib import Path
import sys,re
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM259_STOP_NOT_STAGING')
pats=[
 re.compile(x,re.I) for x in [
 r'(Staff|Expense|Salary|Payroll|Payment|Ledger|Advance|Provision)',
 r'function\s+(store|create|post|pay|index|show)\(',
 r'(expenses|expense_heads|payment_vouchers|staff_salary|staff_advances|staff_ledgers)'
]]
for location in ['app/Http/Controllers/Web','app/Services','resources/views','routes','database/migrations']:
 folder=root/location
 if not folder.exists():continue
 for file in folder.rglob('*.php'):
  name=str(file.relative_to(root))
  if file.stat().st_size>500000:continue
  selected=bool(re.search(r'Expense|Payment|Staff|Salary|Payroll|Ledger|Advance|Voucher|HR|Configuration|web.php|hr_',name,re.I))
  if selected:
   content=file.read_text('utf8','replace').splitlines()
   hits=[i+1 for i,line in enumerate(content) if any(p.search(line) for p in pats)]
   print('AM259_FILE',name,'LINES',len(content),'HITS',','.join(str(i) for i in hits[:65]))
for name,spans in {
 'routes/web.php':[(80,225),(225,365)],
 'app/Http/Controllers/Web/AdminExpensePageController.php':[(1,255)],
 'app/Http/Controllers/Web/AdminPaymentPageController.php':[(1,220)],
 'app/Services/ExpenseService.php':[(1,250)],
 'app/Services/PaymentService.php':[(1,225)],
 'resources/views/dashboard/expenses.blade.php':[(1,200)]
}.items():
 file=root/name
 if not file.is_file():continue
 lines=file.read_text('utf8','replace').splitlines()
 for a,b in spans:
  print('AM259_SECTION',name,a,b)
  for i in range(a-1,min(b,len(lines))):
   line=lines[i]
   if re.search(r'(?i)(secret|token|password|authorization|private.?key)',line):continue
   print('AM259_LINE',i+1,line[:700])
print('AM259_READONLY_SOURCE_PASS')
