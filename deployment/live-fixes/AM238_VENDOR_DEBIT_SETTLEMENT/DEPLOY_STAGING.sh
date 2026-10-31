#!/usr/bin/env bash
# AM238: Staging-only rollout for Vendor Debit/On Account settlement.
# Source backups, guarded migration, read-only runtime smoke test, rollback on fail.
# Does NOT post any sample financial Voucher.
set -Eeuo pipefail
APP="${1:?Pass STAGING Laravel root}"
APP="$(cd "$APP" && pwd -P)"
case "$APP" in *staging*) ;; *) echo AM238_STOP_NOT_STAGING;exit 3;; esac
cd "$APP";test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
python3 -m py_compile "$HERE/apply.py"
php -l "$HERE/verify.php"
if command -v node >/dev/null 2>&1;then node --check "$HERE/vendor-debit-ui.js";fi
python3 "$HERE/apply.py" check "$APP"
BACKUP="$APP/storage/app/am-backups/AM238_GH_$(date +%Y%m%d_%H%M%S)_$$"
FILES=(
 app/Services/MultiPurchasePaymentService.php
 app/Services/ExpensePaymentService.php
 app/Http/Controllers/Web/AdminExpensePaymentPageController.php
 resources/views/payments/create.blade.php
 resources/views/payments/show.blade.php
)
mkdir -p "$BACKUP"
for f in "${FILES[@]}";do mkdir -p "$BACKUP/$(dirname "$f")";cp -p "$APP/$f" "$BACKUP/$f";done
ROLLBACK=1
restore(){
 code="$?"
 trap - ERR
 if test "$ROLLBACK" = 1;then
  for f in "${FILES[@]}";do cp -p "$BACKUP/$f" "$APP/$f" || true;done
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
  # Do not drop potentially posted financial allocations. Unused table is harmless.
 fi
 echo "AM238_SOURCE_ROLLBACK_ATTEMPTED_CODE=$code"
 exit "$code"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan migrate --path=database/migrations/2026_11_01_001200_create_workshop_vendor_debit_uses.php --force
php artisan view:clear
php artisan view:cache
php "$HERE/verify.php"
HTTP="$(curl -sSL -o /dev/null -w '%{http_code}' --max-time 25 https://staging.assammotors.com/erp/login)"
echo "AM238_STAGING_LOGIN_HTTP=$HTTP"
test "$HTTP" = 200
ROLLBACK=0;trap - ERR
echo AM238_VENDOR_DEBIT_ADJUSTMENT_STAGING_DEPLOY_PASS
echo AM238_NO_FINANCIAL_VOUCHER_CREATED_DURING_DEPLOY
echo AM238_BROWSER_LEDGER_POSTING_QA_STILL_REQUIRED
