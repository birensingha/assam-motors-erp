#!/usr/bin/env bash
set -Eeuo pipefail
cd "$1";test -f artisan
echo AM232_AUDIT_BEGIN
echo '== EXPENSE ROUTES =='
php artisan route:list --path=erp/expenses --no-ansi | head -30 | cut -c1-220
echo '== EXPENSE VIEW CREATE FORM =='
f=resources/views/expenses/create.blade.php
echo "EXPENSE_CREATE_LINES=$(wc -l < "$f")"
sed -n '1,290p' "$f" | cut -c1-235
echo '== EXPENSE CONTROLLER & SERVICE =='
for f in app/Http/Controllers/Web/AdminExpensePaymentPageController.php app/Services/ExpensePaymentService.php;do
 echo "FILE=$f"
 grep -nEi -m 95 'function (createExpense|storeExpense|saveExpense|createExpense|expense)|expense_category|expense_against|job_card_id|record_payment|pay_now|expense_reference|payee_name|staff_id|function jobs' "$f" | cut -c1-235
done
sed -n '12,65p' app/Http/Controllers/Web/AdminExpensePaymentPageController.php | cut -c1-235
sed -n '1,165p' app/Services/ExpensePaymentService.php | cut -c1-235
echo '== JOB CARD, STAFF AND EXPENSE TABLE SCHEMAS NO PRIVATE RECORDS =='
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
foreach(['staff','job_cards','workshop_job_cards','workshop_expenses','workshop_expense_categories'] as $t){
 echo $t.':'.(Schema::hasTable($t)?implode(',',Schema::getColumnListing($t)):'ABSENT')."\n";
}
echo 'AM232_SCHEMA_READONLY_PASS'."\n";
PHP
echo AM232_AUDIT_COMPLETE_NO_WRITES
