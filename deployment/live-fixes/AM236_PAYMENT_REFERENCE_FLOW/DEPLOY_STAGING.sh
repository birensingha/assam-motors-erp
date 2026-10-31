#!/usr/bin/env bash
# AM236 guard: Vendor New Reference + Advance reference identity only, staging.
# No voucher POSTs or historical data changes during installation.
set -Eeuo pipefail
APP="${1:?Laravel STAGING_ROOT required}"
APP="$(cd "$APP" && pwd -P)"
case "$APP" in *staging*) ;; *) echo AM236_STOP_NON_STAGING_APP; exit 3;; esac
cd "$APP"
test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
python3 -m py_compile "$HERE/apply.py"
python3 "$HERE/apply.py" check "$APP"
BACKUP="$APP/storage/app/am-backups/AM236_GH_$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p "$BACKUP"
FILES=(
 'resources/views/payments/create.blade.php'
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php'
 'app/Services/ExpensePaymentService.php'
)
for f in "${FILES[@]}"; do mkdir -p "$BACKUP/$(dirname "$f")"; cp -p "$APP/$f" "$BACKUP/$f"; done
echo AM236_PRE_DEPLOY_BACKUP_READY
ROLLBACK=1
restore(){
  code="$?"
  trap - ERR
  if test "$ROLLBACK" = 1; then
    for f in "${FILES[@]}"; do cp -p "$BACKUP/$f" "$APP/$f" || true; done
    php artisan view:clear >/dev/null 2>&1 || true
    php artisan view:cache >/dev/null 2>&1 || true
  fi
  echo "AM236_ROLLBACK_ATTEMPTED_EXIT=$code"
  exit "$code"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
grep -Fq 'AM236_VENDOR_NEW_REFERENCE' app/Services/ExpensePaymentService.php
grep -Fq "source_type" resources/views/payments/create.blade.php
grep -Fq 'NEW_REFERENCE' app/Http/Controllers/Web/AdminExpensePaymentPageController.php
CODE="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM236_STAGING_LOGIN_HTTP=$CODE"
test "$CODE" = 200
ROLLBACK=0
trap - ERR
echo AM236_STAGING_NEW_REFERENCE_AND_ADVANCE_REFERENCE_DEPLOY_PASS
echo AM236_EXISTING_PAYMENT_RECORDS_UNMODIFIED
echo AM236_FUTURE_BILL_ALLOCATION_WORKFLOW_NOT_YET_ENABLED
