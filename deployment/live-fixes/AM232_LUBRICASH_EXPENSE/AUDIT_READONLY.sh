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

echo '== EXPENSE CREATE JS =='
sed -n '1,230p' public/js/expense-entry.js | cut -c1-245
echo '== EXPENSE REGISTER & SHOW =='
for f in resources/views/expenses/index.blade.php resources/views/expenses/show.blade.php;do
 echo "FILE=$f";echo "LINES=$(wc -l < "$f")";sed -n '1,155p' "$f" | cut -c1-235
done
echo '== EXPENSE MODEL FILLABLE & REGISTER QUERIES =='
cat app/Models/WorkshopExpense.php | head -140 | cut -c1-235
sed -n '31,70p' app/Services/ExpensePaymentService.php | cut -c1-235
sed -n '116,138p' app/Services/ExpensePaymentService.php | cut -c1-235
echo '== EXPENSE LAYOUT CSS =='
grep -RniE -m 24 'expense-grid|expense-wide|job-preview|expense-form|payment-now-card' resources/views/layouts public/css | head -55 | cut -c1-235 || true
echo '== DB CURRENT STAFF CATEGORY VOLUME (AGG ONLY) =='
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';$a=require getcwd().'/bootstrap/app.php';$a->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
echo 'STAFF_TOTAL='.DB::table('staff')->count()."\n";
echo 'JOB_TOTAL='.DB::table('job_cards')->count()."\n";
echo 'EXISTING_LUBRICASH_CATEGORY_ROWS='.DB::table('workshop_expenses')->where('expense_category','STAFF_LUBRICASH')->count()."\n";
PHP

echo AM232_AUDIT_COMPLETE_NO_WRITES
