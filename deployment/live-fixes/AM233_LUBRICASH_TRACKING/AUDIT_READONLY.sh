#!/usr/bin/env bash
# AM233 read-only: exact placement of authenticated Expense voucher + linked staff payout.
set -Eeuo pipefail
cd "$1"; test -f artisan
echo AM233_AUDIT_BEGIN
echo "=== CREATE CONTROLLER ==="
sed -n '1,69p' app/Http/Controllers/Web/AdminExpensePaymentPageController.php | cut -c1-280
echo "=== EXPENSE SAVE ==="
sed -n '66,116p' app/Services/ExpensePaymentService.php | cut -c1-290
echo "=== EXPENSE PAYMENT CREATE / STATUS EXACT ==="
grep -n -F -e 'function createVoucher(' -e 'function refreshExpensePaymentStatus(' -e 'function showExpense(' app/Services/ExpensePaymentService.php
for key in 'function createVoucher(' 'function refreshExpensePaymentStatus(' 'function showExpense(';do
 n="$(grep -nF -m1 "$key" app/Services/ExpensePaymentService.php | cut -d: -f1)";lo=$((n>4?n-4:1));hi=$((n+39))
 sed -n "${lo},${hi}p" app/Services/ExpensePaymentService.php | cut -c1-260
done
echo "=== EXPENSE SHOW VIEW ==="
cat resources/views/expenses/show.blade.php | cut -c1-280
echo "=== EXPENSE INDEX VIEW ==="
cat resources/views/expenses/index.blade.php | cut -c1-280
echo "=== JS EXPENSE ==="
cat public/js/expense-entry.js | cut -c1-270
echo "=== ROUTE DECLARATIONS ==="
grep -nE -m 24 "erp.expenses\\.|AdminExpensePaymentPageController|expenses/\\{expense\\}" routes/web.php | cut -c1-290 || true
echo "=== EXISTING DATABASE FIELDS CHECK ==="
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
foreach(['workshop_expenses','workshop_payment_vouchers','staff','job_cards'] as $name)
echo $name.'_COLUMNS='.implode(',',Schema::getColumnListing($name))."\n";
PHP
echo AM233_AUDIT_FINISHED_NO_WRITES
