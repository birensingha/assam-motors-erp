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
echo AM220_READONLY_AUDIT_COMPLETE_NO_WRITES
