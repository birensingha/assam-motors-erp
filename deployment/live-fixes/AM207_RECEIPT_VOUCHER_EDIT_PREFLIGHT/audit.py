#!/usr/bin/env python3
"""AM207 read-only deployment preflight for AUDITED Money Receipt + Payment Voucher edit.
Staging source and schema shape only. NO financial/customer/vendor data and NO writes.

Requirements targeted:
- Existing Money Receipt correction: mode / bank reference, Against Reference ↔
  On Account / Advance, multi-invoice allocations and audit; never silently
  overwrite immutable posted receipt, cash ledger or invoice history.
- Payment Voucher edit: vendor/source, mode/account, amount, invoice allocation,
  vendor outstanding and ledger; atomic audited reversal/repost or adjustment.

Usage: python3 audit.py /home/.../assam-erp-staging
"""
from pathlib import Path
import re,sys,hashlib,subprocess,tempfile
if len(sys.argv)!=2:raise SystemExit('AM207_USAGE python3 audit.py STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/'artisan').is_file():raise SystemExit('AM207_STOP_INVALID_STAGING_ROOT')
print('AM207_MONEY_RECEIPT_AND_PAYMENT_VOUCHER_EDIT_PREFLIGHT')
print('AM207_READ_ONLY_SOURCE_AND_SCHEMA_AUDIT')
scan=[
'app/Http/Controllers/Web/AdminMoneyReceiptController.php',
'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
'app/Http/Controllers/Web/AdminNativeModuleController.php',
'app/Services/ExpensePaymentService.php',
'resources/views/money-receipts/index.blade.php',
'resources/views/money-receipts/create.blade.php',
'resources/views/money-receipts/show.blade.php',
'resources/views/money-receipts/edit.blade.php',
'resources/views/payments/index.blade.php',
'resources/views/payments/create.blade.php',
'resources/views/payments/show.blade.php',
'resources/views/payments/edit.blade.php',
'routes/web.php',
]
sensitive=re.compile(r'(?i)(password|secret|private.?key|bearer|api.?key|authorization|\.env|smtp)')
method=re.compile(r'^\s*(?:public|protected|private)\s+(?:static\s+)?function\s+[\w]+\s*\(')
field=re.compile(r'(?i)(validate\(|DB::transaction|DB::table|lockForUpdate|customer_payments|customer_payment_allocations|customer_ledger|workshop_payment_vouchers|cash_ledger|vendor_ledger|expense_payments|reference_kind|against.reference|on.account|bank_account|payment_mode|payment_status|payment_no|voucher_no|update\(|insert\(|delete\(|reverse|void|audit|posted|allocation)')
html_rx=re.compile(r'(?i)(<form|<input|<select|<button|name=|route\(|payment.mode|reference.kind|against.reference|on.account|edit|voucher|receipt)')
def printlines(p,inds,limit):
 lines=p.read_text('utf8','replace').splitlines()
 count=0
 for i in inds:
  if i<0 or i>=len(lines):continue
  line=lines[i]
  if sensitive.search(line):continue
  s=line.strip()
  if len(s)>260:s=s[:260]+' …'
  print(' L'+str(i+1),s)
  count+=1
  if count>=limit:break
for item in scan:
 p=root/item
 if not p.is_file():
  print('MISSING',item);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('SOURCE',item,'LINES',len(lines),'SHA256',hashlib.sha256(p.read_bytes()).hexdigest()[:14])
 if item.endswith('routes/web.php'):
  ids=[i for i,l in enumerate(lines) if re.search(r'money.receipts|receipt|payments|voucher|expense.payment',l,re.I)]
  printlines(p,ids,85)
 elif 'Controllers' in item or 'Services' in item:
  meth=[i for i,l in enumerate(lines) if method.search(l)]
  print('METHODS_BEGIN')
  printlines(p,meth,38)
  print('METHODS_END')
  # Group around key posting / reconciliation operations, not secrets.
  indices=[i for i,l in enumerate(lines) if field.search(l)]
  print('FINANCE_FLOW_REFERENCES_BEGIN')
  printlines(p,indices,64 if ('MoneyReceiptController' in item or 'ExpensePaymentService' in item) else 30)
  print('FINANCE_FLOW_REFERENCES_END')
 else:
  indices=[i for i,l in enumerate(lines) if html_rx.search(l)]
  print('FORM_AND_EDIT_REFERENCES_BEGIN')
  printlines(p,indices,55)
  print('FORM_AND_EDIT_REFERENCES_END')

php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
$names=['customer_payments','customer_payment_allocations','customer_ledger',
'customer_advance_transfers','customer_advance_applications','invoices',
'workshop_payment_vouchers','workshop_vendor_payments','workshop_vendor_ledger',
'vendor_ledger','vendor_payments','workshop_vendors','workshop_purchases',
'workshop_purchase_batches','cash_bank_ledger','account_ledger','ledger_entries',
'account_transactions','workshop_expenses','payment_voucher_audits',
'money_receipt_audits','erp_users','bank_accounts'];
foreach($names as $name){
 try {
  if(!Schema::hasTable($name)){echo "NOT_FOUND $name\n";continue;}
  $cols=Schema::getColumnListing($name);
  echo 'TABLE '.$name.' COLUMNS '.implode(',',$cols)."\n";
 }catch(\Throwable $e){echo 'SCHEMA_ERROR '.$name.' '.get_class($e)."\n";}
}
echo "AM207_REGISTERED_ROUTES_BEGIN\n";
try {
 foreach(app('router')->getRoutes() as $route){
  $uri=$route->uri();
  if(!preg_match('~^erp/(money-receipts|payments|payment|vendor|expenses)~i',$uri))continue;
  echo 'ROUTE '.implode(',',array_diff($route->methods(),['HEAD'])).' '.$uri
    .' NAME '.($route->getName()??'-').' CONTROLLER '.$route->getActionName()."\n";
 }
}catch(\Throwable $e){echo "AM207_ROUTE_SCAN_ERROR ".get_class($e)."\n";}
echo "AM207_DB_SCAN_COMPLETE_NO_ROWS_READ\n";
"""
with tempfile.TemporaryDirectory(prefix='am207_') as folder:
 f=Path(folder)/'read_only.php'
 f.write_text(php,encoding='utf8')
 p=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=35)
 print('AM207_SCHEMA_AND_ROUTES_BEGIN')
 if p.stdout:print(p.stdout[:18500].rstrip())
 if p.returncode:
  print('AM207_SCHEMA_ERROR_PHP_EXIT',p.returncode)
  print('AM207_SCHEMA_ERROR_CLASS_BRIEF',p.stderr[-260:].replace('\n',' '))
  sys.exit(p.returncode)
 print('AM207_SCHEMA_AND_ROUTES_END')
print('AM207_AUDIT_FINISHED_NO_DATA_OR_CODE_WRITES')
