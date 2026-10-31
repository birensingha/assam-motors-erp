#!/usr/bin/env bash
# AM232: read-only expense voucher, UNPAID status, settlement / cash ledger audit.
set -Eeuo pipefail
cd "$1";test -f artisan
echo AM232_AUDIT_BEGIN
echo "=== EXPENSE CREATE VIEW ==="
f=resources/views/expenses/create.blade.php
test -f "$f";wc -l "$f"
sed -n '1,265p' "$f" | cut -c1-220
echo "=== EXPENSE ROUTES / CONTROLLER ==="
php artisan route:list --path=erp/expenses --no-ansi 2>&1 | head -45 | cut -c1-235
c=app/Http/Controllers/Web/AdminExpensePaymentPageController.php
grep -n -Ei 'function (createExpense|storeExpense|expense|showExpense|indexExpense)|record_payment|payment_now|paid|unpaid|category|staff_id|job_card_id' "$c" | head -95 | cut -c1-220
for text in 'public function storeExpense(' 'public function createExpense(' 'public function expenseIndex(';do
 n="$(grep -nF -m1 "$text" "$c" | cut -d: -f1 || true)"
 if test -n "$n";then lo=$((n>3?n-3:1));hi=$((n+110));echo "=== CONTROLLER $text ===";sed -n "${lo},${hi}p" "$c" | cut -c1-245;fi
done
echo "=== SERVICE SAVING EXPENSE / CASH POSTING ==="
s=app/Services/ExpensePaymentService.php
grep -nEi -m 100 'function (saveExpense|createExpense|postPayment|savePayment|expense|expenseCategories)|UNPAID|PAID|payment_now|payment_vouchers|workshop_expenses|amount_paid|cash|balance|ledger|job_card_id' "$s" | cut -c1-230 || true
for key in 'function saveExpense(' 'function expenses(' 'function expenseCategories('; do
 n="$(grep -nF -m1 "$key" "$s" | cut -d: -f1 || true)"
 if test -n "$n";then lo=$((n>5?n-5:1));hi=$((n+180));echo "=== SERVICE $key ===";sed -n "${lo},${hi}p" "$s" | cut -c1-245;fi
done
echo "=== EXPENSE REGISTER VIEW STATUS AND PAY ACTION ==="
find resources/views/expenses -type f -name '*.blade.php' -maxdepth 2 | head -20
for file in resources/views/expenses/index.blade.php resources/views/expenses/show.blade.php;do
 if test -f "$file";then grep -nEi -m 55 'unpaid|paid|payment|balance|status|ledger|expense.*total' "$file" | cut -c1-230;fi
done
echo "=== SCHEMA ONLY; NO PRIVATE ROWS ==="
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
foreach(['workshop_expenses','workshop_payment_vouchers','workshop_payment_allocations','staff','job_cards','workshop_job_cards','workshop_expense_categories','expense_categories','workshop_payment_sources'] as $t){
 if(Schema::hasTable($t)){echo $t.'_FIELDS='.implode(',',Schema::getColumnListing($t))."\n";}
 else echo $t.'_NO_TABLE'."\n";
}
echo "AM232_SCHEMA_READONLY_PASS\n";
PHP
echo "=== EXPENSE INDEX/SHOW COMPLETE SHORT VIEWS ==="
sed -n '1,90p' resources/views/expenses/index.blade.php | cut -c1-250
sed -n '1,70p' resources/views/expenses/show.blade.php | cut -c1-250
echo "=== EXPENSE FRONT JS ==="
sed -n '1,300p' public/js/expense-entry.js | cut -c1-240
echo "=== EXPENSE PAGE SERVICE QUERY AND STATUS ==="
sed -n '34,67p' app/Services/ExpensePaymentService.php | cut -c1-235
sed -n '296,325p' app/Services/ExpensePaymentService.php | cut -c1-240
echo "=== EXISTING EXPENSE MIGRATIONS ==="
find database/migrations -type f | grep -Ei 'expense|payment' | tail -20
echo "=== STAFF/JOB ACTIVE STATUS DISTRIBUTION COUNTS ==="
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
foreach(['staff','job_cards'] as $table){ echo strtoupper($table).'_COUNT='.DB::table($table)->count()."\n";}
echo 'LUBRICASH_CATEGORY_EXISTS='.DB::table('workshop_expenses')->where('expense_category','LUBRICASH_COUPON')->exists()."\n";
echo 'EXPENSES_UNPAID_COUNT='.DB::table('workshop_expenses')->where('record_status','ACTIVE')->where('payment_status','UNPAID')->count()."\n";
PHP

echo AM232_AUDIT_FINISHED_NO_WRITES
