#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="${1:?Staging Laravel root required}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"
test -f artisan || { echo AM195_WRONG_APP_ROOT; exit 2; }
python3 -m py_compile "$HERE/apply.py"
php -l "$HERE/2026_11_18_190000_am195_expense_category_master.php"
php -l "$HERE/ExpenseCategoryMasterController.php"
python3 "$HERE/apply.py" check "$ROOT"
MIG=2026_11_18_190000_am195_expense_category_master.php
test ! -e "database/migrations/$MIG" || cmp -s "$HERE/$MIG" "database/migrations/$MIG" || { echo AM195_MIGRATION_CONFLICT;exit 3; }
cp -n "$HERE/$MIG" "database/migrations/$MIG"
php artisan migrate --force --path="database/migrations/$MIG" --no-interaction
cleanup() {
 code="$?"
 if [ "$code" != 0 ]; then
  BACKUP="$ROOT/storage/app/am-backups/AM195_EXPENSE_CATEGORIES"
  if [ -d "$BACKUP" ];then
    cp -p "$BACKUP/ExpensePaymentService.php" app/Services/ExpensePaymentService.php || true
    cp -p "$BACKUP/web.php" routes/web.php || true
    cp -p "$BACKUP/create.blade.php" resources/views/expenses/create.blade.php || true
  fi
  rm -f app/Http/Controllers/Web/ExpenseCategoryMasterController.php resources/views/expenses/category-master.blade.php
  php artisan route:clear >/dev/null 2>&1 || true
  php artisan view:clear >/dev/null 2>&1 || true
  echo AM195_DEPLOY_FAILED_CODE="$code"_SOURCE_RESTORED
 fi
 exit "$code"
}
trap cleanup EXIT
python3 "$HERE/apply.py" apply "$ROOT"
php artisan route:clear
php artisan view:clear
php artisan view:cache
php artisan route:list --name=erp.expense-categories
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$codes=$app->make(\\App\\Services\\ExpensePaymentService::class)->expenseCategories();
if(!in_array("DONATION",$codes,true))exit(8);
echo "AM195_DONATION_SELECTABLE_PASS\\n";
'
STATUS="$(curl -fsSL -o /dev/null -w '%{http_code}' --max-time 25 https://staging.assammotors.com/erp/login)" || exit 9
test "$STATUS" = 200
trap - EXIT
echo AM195_STAGING_DEPLOY_PASS
