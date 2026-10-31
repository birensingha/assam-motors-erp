#!/usr/bin/env bash
# AM237 STAGING view+GET lookup+reference-number safeguard only.
# No payment POST or historical data edits during deploy.
set -Eeuo pipefail
APP="${1:?STAGING_LARAVEL_ROOT}"
APP="$(cd "$APP" && pwd -P)"
case "$APP" in *staging*) ;; *) echo AM237_STOP_NON_STAGING_APP;exit 3;; esac
cd "$APP";test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
python3 -m py_compile "$HERE/apply.py"
if command -v node >/dev/null 2>&1; then node --check "$HERE/unified.js"; fi
python3 "$HERE/apply.py" check "$APP"
BACKUP="$APP/storage/app/am-backups/AM237_GH_$(date +%Y%m%d_%H%M%S)_$$"
FILES=(resources/views/payments/create.blade.php app/Http/Controllers/Web/AdminExpensePaymentPageController.php)
mkdir -p "$BACKUP"
for f in "${FILES[@]}";do mkdir -p "$BACKUP/$(dirname "$f")";cp -p "$APP/$f" "$BACKUP/$f";done
echo AM237_BACKUP_BEFORE_VIEW_CONTROLLER_CHANGE_PASS
ROLLBACK=1
restore(){
 code="$?"
 trap - ERR
 if test "$ROLLBACK" = 1;then
   for f in "${FILES[@]}";do cp -p "$BACKUP/$f" "$APP/$f" || true;done
   php artisan view:clear >/dev/null 2>&1 || true
   php artisan view:cache >/dev/null 2>&1 || true
 fi
 echo "AM237_ROLLBACK_ATTEMPTED_EXIT=$code";exit "$code"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
grep -Fq 'AM237_UNIFIED_PAYMENT_TO' resources/views/payments/create.blade.php
grep -Fq 'AM230 no Staff/Customer ledger key' app/Http/Controllers/Web/AdminExpensePaymentPageController.php
grep -Fq 'AM236 vendor New Reference' app/Http/Controllers/Web/AdminExpensePaymentPageController.php
HTTP="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM237_STAGING_LOGIN_HTTP=$HTTP"
test "$HTTP" = 200
ROLLBACK=0;trap - ERR
echo AM237_UNIFIED_PAYMENT_TO_STAGING_DEPLOY_PASS
echo AM237_EXISTING_VENDOR_AND_OSL_LEDGER_SERVICES_UNCHANGED
echo AM237_CUSTOMER_AND_STAFF_POSTING_REMAINS_LOCKED
echo AM237_NO_PAYMENT_POST_DURING_DEPLOY
