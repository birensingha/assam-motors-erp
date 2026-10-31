#!/usr/bin/env bash
set -Eeuo pipefail
cd "$1";test -f artisan
echo "AM231_AUDIT_BEGIN"
echo "=== CURRENT PAYMENT VIEW TOP ==="
sed -n '95,190p' resources/views/payments/create.blade.php | cut -c1-245
echo "=== CURRENT JS AFTER AM230 ==="
sed -n '350,420p' resources/views/payments/create.blade.php | cut -c1-245
sed -n '520,700p' resources/views/payments/create.blade.php | cut -c1-245
echo "=== PAYMENT CONTROLLER/POST ==="
sed -n '60,115p' app/Http/Controllers/Web/AdminExpensePaymentPageController.php | cut -c1-245
echo "=== PAYMENT MODELS ==="
grep -RniE -m 20 'payee_type|customer_id|staff_id|customer_ref|staff_ref|general.ledger|journal' app/Models/WorkshopPaymentVoucher.php app/Services/ExpensePaymentService.php app/Services/MultiPurchasePaymentService.php | head -100 | cut -c1-200 || true
echo "=== PAYEE ENTITY SCHEMAS ==="
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['staff','customers','workshop_customers','customer_master','erp_customers','workshop_payment_vouchers','workshop_vendor_ledger','workshop_vendor_payments','customer_ledger_entries','staff_salary_payments','salary_payments','workshop_expenses'] as $table){
 if(Schema::hasTable($table)){
  echo $table.'_FIELDS='.implode(',',Schema::getColumnListing($table))."\n";
 }else echo $table.'_ABSENT'."\n";
}
$names=DB::select('show tables');
foreach($names as $n){$x=implode('',(array)$n);if(preg_match('/customer|staff.*ledger|salary|journal|ledger|payroll/i',$x))echo "RELEVANT_TABLE=$x\n";}
echo "AM231_SCHEMA_READONLY_PASS\n";
PHP
echo AM231_AUDIT_COMPLETE_NO_WRITES
