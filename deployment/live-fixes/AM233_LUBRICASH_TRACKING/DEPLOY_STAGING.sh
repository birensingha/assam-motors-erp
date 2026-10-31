#!/usr/bin/env bash
# AM233 Staging only. Additive schema and UI/linked payment; no financial POSTs.
set -Eeuo pipefail
APP="${1:?STAGING_LARAVEL_ROOT}"
cd "$APP";test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
MIG=2026_10_31_210000_am233_lubricash_tracking_fields.php
DEST="$APP/database/migrations/$MIG"
python3 -m py_compile "$HERE/apply.py"
node --version >/dev/null 2>&1 && node --check "$HERE/coupon.js" || true
php -l "$HERE/$MIG"
php -l "$HERE/verify.php"
python3 "$HERE/apply.py" check "$APP"
SOURCE_BACKUP="$APP/storage/app/am-backups/AM233_GH_$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p "$SOURCE_BACKUP"
for f in \
 app/Http/Controllers/Web/AdminExpensePaymentPageController.php \
 app/Services/ExpensePaymentService.php \
 resources/views/expenses/create.blade.php \
 resources/views/expenses/show.blade.php \
 resources/views/expenses/index.blade.php routes/web.php
do mkdir -p "$SOURCE_BACKUP/$(dirname "$f")";cp -p "$f" "$SOURCE_BACKUP/$f";done
echo AM233_SIX_SOURCE_FILE_BACKUP_PASS
ROLLBACK_REQUIRED=1
restore(){
 code="$?"
 trap - ERR
 if test "$ROLLBACK_REQUIRED" = 1;then
  for f in app/Http/Controllers/Web/AdminExpensePaymentPageController.php \
   app/Services/ExpensePaymentService.php \
   resources/views/expenses/create.blade.php \
   resources/views/expenses/show.blade.php \
   resources/views/expenses/index.blade.php routes/web.php;do
    cp -p "$SOURCE_BACKUP/$f" "$APP/$f" || true
   done
  php artisan route:clear >/dev/null 2>&1 || true
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
 fi
 echo "AM233_STAGING_DEPLOY_FAILED_SOURCE_RESTORED=$code"
 echo "AM233_NULLABLE_AUDIT_COLUMNS_MAY_REMAIN_SAFE_NO_HISTORICAL_WRITES"
 exit "$code"
}
trap restore ERR
if test -f "$DEST";then
 cmp "$HERE/$MIG" "$DEST" || { echo AM233_STOP_MIGRATION_FILENAME_CONFLICT;exit 3; }
else
 cp "$HERE/$MIG" "$DEST"
fi
# Add ONLY nullable fields and indexes. No existing voucher modifications.
php artisan migrate --force --path="database/migrations/$MIG" --no-interaction
echo AM233_ADDITIVE_STAFF_COUPON_SCHEMA_APPLIED
python3 "$HERE/apply.py" apply "$APP"
php artisan route:clear
php artisan view:clear
php artisan view:cache
QA="$(php "$HERE/verify.php" 2>&1 || true)"
printf '%s\n' "$QA"
printf '%s\n' "$QA" | grep -Fxq AM233_RUNTIME_READONLY_QA_PASS
CODE="$(curl -sSL -o /dev/null -w '%{http_code}' --max-time 25 https://staging.assammotors.com/erp/login)"
echo "AM233_STAGING_LOGIN_HTTP=$CODE"
test "$CODE" = 200
ROLLBACK_REQUIRED=0
trap - ERR
echo AM233_STAGING_STAFF_LUBRICASH_AND_PAYMENT_AUDIT_DEPLOY_PASS
echo AM233_NO_EXPENSE_OR_PAYMENT_DATA_WRITES_DURING_DEPLOY
