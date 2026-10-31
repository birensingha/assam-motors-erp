#!/usr/bin/env python3
"""AM239 read-only audit of Staff/Customer payable, salary advance, refund services.
No financial POST; do not print transaction rows or personal customer/staff data.
"""
import re,sys,subprocess
from pathlib import Path
root=Path(sys.argv[1]).resolve()
files={
 'PAYMENT_CONTROLLER':'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'PAYMENT_SERVICE':'app/Services/ExpensePaymentService.php',
 'PAYMENT_VIEW':'resources/views/payments/create.blade.php',
 'ROUTES':'routes/web.php'
}
regex={
 'PAYMENT_CONTROLLER':r'function |payee_category|staff_id|customer_id|Staff|Customer|validate|source_type|multi_bill|payLubricash|return view|withErrors',
 'PAYMENT_SERVICE':r'SALARY|salary|Staff|Expense|customer|refund|request_key|EXPENSE_VOUCHER|Advance|savePayment',
 'PAYMENT_VIEW':r'am237|am231-staff|am231-customer|source-type|source-key|request_key|payment-form|Payment Against',
 'ROUTES':r'ledger|salary|payroll|advance|refund|credit.note|payment|staff.finance|employee.finance'
}
for name,relative in files.items():
 file=root/relative
 print('AM239_FILE_'+name+'_PRESENT='+str(file.is_file()))
 if not file.is_file():continue
 lines=file.read_text('utf8',errors='surrogateescape').splitlines()
 hits=[i for i,l in enumerate(lines) if re.search(regex[name],l,re.I)]
 print('AM239_FILE_'+name+'_LINES='+str(len(lines))+'_HITS='+str(len(hits)))
 for i in hits[:75]:
  print(f'AM239_SOURCE_{name}_{i+1} {lines[i][:320]}')
# Discover first-party Staff Finance Laravel services/controllers and database table names.
for folder in ['app/Services','app/Http/Controllers/Web','app/Models']:
 base=root/folder
 if not base.is_dir():continue
 for p in base.glob('*.php'):
  if re.search(r'staff.*(finance|ledger|salary|advance|expense)|customer.*(credit|refund|ledger|receipt)|(payroll|salary|finance).*staff',p.name,re.I):
   print('AM239_RELEVANT_CLASS='+str(p.relative_to(root)))
   rows=p.read_text('utf8',errors='surrogateescape').splitlines()
   for i,l in enumerate(rows):
    if re.search(r'function |Table\(|protected \$table|ledger|advance|payable|settle|refund|reimburs',l,re.I):
     print(f'AM239_CLASS_LINE_{p.name}_{i+1} {l[:290]}')
code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(DB::select('SHOW TABLES') as $row){
 $table=(string)array_values((array)$row)[0];
 if(preg_match('/staff.*(ledger|finance|salary|advance|pay)|salary|payroll|customer.*(ledger|credit|payment|refund)|credit.*note|payment.*allocation|workshop_expenses/i',$table)){
  echo 'AM239_TABLE='.$table.PHP_EOL;
  echo 'AM239_COLUMNS_'.$table.'='.implode(',',Schema::getColumnListing($table)).PHP_EOL;
 }
}"""
result=subprocess.run(['php','-r',code],cwd=root,capture_output=True,text=True,timeout=70)
print(result.stdout[:28000])
print('AM239_DB_AUDIT_EXIT='+str(result.returncode))
if result.returncode:print('AM239_DB_ERROR='+result.stderr[:350])
print('AM239_READONLY_FINANCIAL_AUDIT_COMPLETE')
