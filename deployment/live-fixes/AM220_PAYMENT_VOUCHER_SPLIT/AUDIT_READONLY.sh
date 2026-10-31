#!/usr/bin/env bash
# AM220 read-only Staging investigation, no database writes, no PII row data.
set -Eeuo pipefail
cd "$1"; test -f artisan
echo AM220_READONLY_AUDIT_BEGIN
echo "== PAYMENT / EXPENSE ROUTES =="
php artisan route:list --no-ansi 2>&1 | grep -Ei 'payments/(create|store)|payments *|expense|voucher' | head -65 | cut -c1-240 || true
echo "== RELEVANT VIEWS & CONTROLLERS =="
find resources/views app/Http/Controllers -type f \( -iname '*pay*' -o -iname '*expens*' -o -iname '*voucher*' \) | head -100
echo "== PAYMENTS CREATE SOURCE CANDIDATES =="
for file in $(find resources/views -type f -iname '*.blade.php' | xargs grep -l -E 'New Payment Voucher|Adjust Multiple Bills' 2>/dev/null | head -6);do
 echo "BEGIN_PAYMENT_VIEW=$file";echo "LINES=$(wc -l < "$file")"
 grep -nEi -m 95 'Payment Against|payment_against|payee_type|vendor_id|staff_id|customer_id|Expense Voucher|Other Payment|On Account|Advance|Adjust Multiple|select vendor|select.outstanding|name="|id="' "$file" | cut -c1-250 || true
 for word in "Payment Against" "select_vendor" "payment_against" "payee_type";do
  n="$(grep -n -Fi -m1 "$word" "$file" | cut -d: -f1 || true)"
  if test -n "$n";then lo=$((n>25?n-25:1));hi=$((n+100));echo "=== PAYMENT VIEW: $word at $n ===";sed -n "${lo},${hi}p" "$file" | cut -c1-250;fi
 done
done
echo "== PAYMENT CREATE/STORE & POSTING =="
grep -RnlE -m 1 --include='*.php' 'function (store|create)Payment|function (create|store)\(' app/Http/Controllers | grep -Ei 'payment|expense' | head -20 | while read -r file;do
 echo "=== CONTROLLER $file ==="
 grep -nEi -m 95 'function|payment_against|payee_type|vendor_id|staff_id|customer_id|source_type|reference|expense|purchase|credit|DB::transaction|validate|amount|allocation' "$file" | cut -c1-240 || true
done
echo "== EXPENSE VOUCHER STANDALONE ROUTE/CODE =="
grep -Rni -m 10 --include='*.php' 'Expense Voucher\|expense-voucher\|erp.expenses' routes app/Http/Controllers | head -50 | cut -c1-220 || true

echo "=== PAYMENT SERVICE SAVE/PAYEE SOURCE ==="
service=app/Services/ExpensePaymentService.php
if test -f "$service";then
 echo "SERVICE_LINES=$(wc -l < "$service")"
 grep -nEi -m 90 'function (paymentSources|vendors|savePayment|searchPayments|createPayment|paymentSource|staff|customers)|payee|source_type|EXPENSE_VOUCHER|OSL_JOB|ON_ACCOUNT|vendor_id|savePayment|updateOrInsert' "$service" | cut -c1-240 || true
 for term in 'function savePayment(' 'function paymentSources(' 'function vendors(' ;do
  n="$(grep -n -F -m1 "$term" "$service" | cut -d: -f1 || true)"
  if test -n "$n";then lo=$((n>10?n-10:1));hi=$((n+220));echo "=== SERVICE $term at $n ===";sed -n "${lo},${hi}p" "$service" | cut -c1-245;fi
 done
fi
echo "=== PAYMENT CONTROLLER VALIDATION / SOURCE ==="
sed -n '57,110p' app/Http/Controllers/Web/AdminExpensePaymentPageController.php | cut -c1-260
echo "=== NATIVE PAYMENT JS FIRST 250 / HANDLERS ==="
if test -f public/js/payment-voucher.js;then
 sed -n '1,270p' public/js/payment-voucher.js | cut -c1-230
fi
echo "=== PAYMENT CREATE BLADES INLINE EVENT IMPLEMENTATIONS ==="
sed -n '176,270p' resources/views/payments/create.blade.php | cut -c1-245
sed -n '425,536p' resources/views/payments/create.blade.php | cut -c1-245
echo "=== SOURCE TABLE FIELDS (no private rows) ==="
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';$app=require getcwd().'/bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['payment_vouchers','payments','vendors','customers','staff','supplier_payments','expense_vouchers'] as $table){
 if(\Illuminate\Support\Facades\Schema::hasTable($table)){
  echo $table.'_COLUMNS='.implode(',',\Illuminate\Support\Facades\Schema::getColumnListing($table))."\n";
 }else echo $table.'_NOT_FOUND'."\n";
}
PHP

echo AM220_READONLY_AUDIT_COMPLETE_NO_WRITES
