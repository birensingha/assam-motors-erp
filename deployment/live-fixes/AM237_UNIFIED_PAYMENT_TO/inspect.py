#!/usr/bin/env python3
"""AM237 targeted read-only lookup of current staging payment posting, payee masters and ledger schema.
Never writes/queries voucher rows or prints env credentials.
"""
import sys,re
from pathlib import Path
root=Path(sys.argv[1]).resolve()
for name,path,regex in [
 ('PAYMENT_PAGE','resources/views/payments/create.blade.php',r'payment-form|am230-payee-box|am231-purpose-wrap|am234-feature-panel|am235-combobox|am218-search-wrap|source-type|source-key|am236-reference|payment-value|p133-payment-mode|p133-multi|source-preview|am225-allocate|account_name|payment-payee'),
 ('PAYMENT_CONTROLLER','app/Http/Controllers/Web/AdminExpensePaymentPageController.php',r'function createPayment|function storePayment|function indexPayment|am231Staff|am231Customers|payableSources|request->validate|source_type|vendor_id|staff_id|customer_id|request_key|MultiPurchase'),
 ('PAYMENT_SERVICE','app/Services/ExpensePaymentService.php',r'function payableSources|function vendors|function jobs|function savePayment|function resolveSource|function createVoucher|SOURCE_TYPES|NEW_REFERENCE|ADVANCE|source_reference|request_key|payee_type|payment_mode'),
 ('MULTI_SERVICE','app/Services/MultiPurchasePaymentService.php',r'function save|vendor_id|source_type|allocations|DB::transaction'),
 ('PAYMENT_ROUTES','routes/web.php',r'erp.payments|payment|customer-ledger|staff-ledger|refund|payroll|salary|credit.note')
]:
 p=root/path
 print('AM237_FILE_'+name+'_EXISTS='+str(p.is_file()))
 if not p.is_file():continue
 lines=p.read_text('utf8',errors='surrogateescape').splitlines()
 matches=[(i,l[:300]) for i,l in enumerate(lines,1) if re.search(regex,l,re.I)]
 print('AM237_MATCHES_'+name+'='+str(len(matches)))
 for i,s in matches[:75]:print('AM237_'+name+'_'+str(i)+' '+s)
print('AM237_SCHEMA_START')
try:
 import os
 os.chdir(root)
 from subprocess import run
 code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$tables=['workshop_payment_vouchers','workshop_payment_allocations','workshop_vendors','workshop_purchases','workshop_expenses','staff','customer_master','job_cards','workshop_customer_receipts','workshop_credit_notes','customer_money_receipts','workshop_vendor_ledgers','staff_salary_advances','staff_payroll_advances','salary_advances','staff_ledgers','workshop_payments','workshop_ledger_entries','journal_entries','accounting_ledgers','workshop_cashbook'];
foreach($tables as $t){
 if(!Schema::hasTable($t)){echo 'AM237_TABLE_'.$t.'_MISSING'.PHP_EOL;continue;}
 $cols=Schema::getColumnListing($t);
 echo 'AM237_TABLE_'.$t.'='.implode(',',$cols).PHP_EOL;
}
$rows=DB::select('SHOW TABLES');
foreach($rows as $r){
 $t=(string)array_values((array)$r)[0];
 if(preg_match('/credit|receipt|payroll|salary|ledger|voucher|account|advance|refund|adjust|payment/i',$t))echo 'AM237_RELATED_TABLE='.$t.PHP_EOL;
}
"""
 response=run(['php','-r',code],capture_output=True,text=True,timeout=65)
 print(response.stdout[:18000])
 if response.returncode:print('AM237_SCHEMA_CHECK_ERROR='+response.stderr[:500])
except Exception as err:
 print('AM237_SCHEMA_EXCEPTION='+str(err))
print('AM237_READONLY_SOURCE_AND_SCHEMA_AUDIT_COMPLETE')
