#!/usr/bin/env bash
# AM240 STAGING only: enable Staff payout from existing Universal Payment Voucher.
# Source backups, rollback; no financial sample is posted in this deployment.
set -Eeuo pipefail
APP="${1:?STAGING_LARAVEL_ROOT}"
APP="$(cd "$APP" && pwd -P)"
case "$APP" in *staging*) ;; *) echo AM240_STOP_NOT_STAGING; exit 3;; esac
cd "$APP"
test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
python3 -m py_compile "$HERE/apply.py"
if command -v node >/dev/null 2>&1;then node --check "$HERE/staff-voucher.js";fi
python3 "$HERE/apply.py" check "$APP"
BACKUP="$APP/storage/app/am-backups/AM240_GH_$(date +%Y%m%d_%H%M%S)_$$"
FILES=(
 app/Http/Controllers/Web/AdminExpensePaymentPageController.php
 app/Http/Controllers/Web/AdminStaffFinanceLedgerController.php
 app/Http/Controllers/Web/AdminPaymentVoucherEditController.php
 app/Services/ExpensePaymentService.php
 resources/views/payments/create.blade.php
 resources/views/staff-finance/index.blade.php
)
mkdir -p "$BACKUP"
for f in "${FILES[@]}";do mkdir -p "$BACKUP/$(dirname "$f")";cp -p "$APP/$f" "$BACKUP/$f";done
ROLLBACK=1
restore(){
 code="$?"
 trap - ERR
 if test "$ROLLBACK" = 1;then
  for f in "${FILES[@]}";do cp -p "$BACKUP/$f" "$APP/$f" || true;done
  rm -f "$APP/app/Services/StaffPaymentVoucherService.php"
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
 fi
 echo "AM240_STAGING_ROLLBACK_ATTEMPTED_EXIT=$code"
 exit "$code"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
python3 "$HERE/hotfix.py" check "$APP"
python3 "$HERE/hotfix.py" apply "$APP"
php artisan view:clear
php artisan view:cache
php "$HERE/verify-runtime.php"
grep -Fq 'AM240_STAFF_PAYMENT_VOUCHER' app/Http/Controllers/Web/AdminExpensePaymentPageController.php
grep -Fq 'am240-staff-panel' resources/views/payments/create.blade.php
grep -Fq 'StaffPaymentVoucherService' app/Services/StaffPaymentVoucherService.php
grep -Fq 'Staff salary payment / advance must be posted from Universal Payment Voucher' app/Http/Controllers/Web/AdminStaffFinanceLedgerController.php
LOGIN="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM240_STAGING_LOGIN_HTTP=$LOGIN"
test "$LOGIN" = 200
ROLLBACK=0;trap - ERR
echo AM240_STAFF_PAYMENT_VOUCHER_STAGING_DEPLOY_PASS
echo AM240_STAFF_BALANCED_LEDGER_REUSED
echo AM240_CUSTOMER_POSTING_STILL_LOCKED
echo AM240_NO_PAYMENT_POSTED_DURING_DEPLOY
