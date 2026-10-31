#!/usr/bin/env bash
# AM235 STAGING UI-only: one Vendor Master combobox with preserved vendor_id POST.
# No financial saves, migrations, ledger edits or controller/service modifications.
set -Eeuo pipefail
APP="${1:?STAGING_LARAVEL_ROOT}"
APP="$(cd "$APP" && pwd -P)"
case "$APP" in *staging*) ;; *) echo AM235_STOP_NON_STAGING_ROOT; exit 3;; esac
cd "$APP"
test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
VIEW='resources/views/payments/create.blade.php'
test -f "$VIEW"
python3 -m py_compile "$HERE/apply.py"
if command -v node >/dev/null 2>&1; then node --check "$HERE/vendor-combobox.js"; fi
python3 "$HERE/apply.py" check "$APP"
if grep -Fq 'AM235_SINGLE_VENDOR_MASTER_COMBOBOX' "$VIEW"; then
 echo AM235_ALREADY_APPLIED_NO_ADDITIONAL_WRITES
 exit 0
fi
EXTERNAL_BACKUP="$APP/storage/app/am-backups/AM235_GH_$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p "$EXTERNAL_BACKUP"
cp -p "$VIEW" "$EXTERNAL_BACKUP/create.blade.php"
ROLLBACK_REQUIRED=1
restore(){
 code="$?"
 trap - ERR
 if test "$ROLLBACK_REQUIRED" = 1; then
   cp -p "$EXTERNAL_BACKUP/create.blade.php" "$APP/$VIEW" || true
   php artisan view:clear >/dev/null 2>&1 || true
   php artisan view:cache >/dev/null 2>&1 || true
 fi
 echo "AM235_ROLLBACK_COMPLETED_OR_ATTEMPTED_EXIT=$code"
 exit "$code"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
grep -Fq 'AM235_SINGLE_VENDOR_MASTER_COMBOBOX' "$VIEW"
grep -Fq 'am235-vendor-options' "$VIEW"
grep -Fq 'name="vendor_id"' "$VIEW"
grep -Fq 'AM234_UNIVERSAL_PAYMENT_FEATURES' "$VIEW"
HTTP="$(curl -sSL -o /dev/null -w '%{http_code}' --max-time 25 https://staging.assammotors.com/erp/login)"
echo "AM235_STAGING_LOGIN_HTTP=$HTTP"
test "$HTTP" = 200
ROLLBACK_REQUIRED=0
trap - ERR
echo AM235_SINGLE_VENDOR_MASTER_SEARCH_STAGING_DEPLOY_PASS
echo AM235_EXISTING_VENDOR_ID_AND_BILL_PAYMENT_PRESERVED
echo AM235_NO_ACCOUNTING_DATA_OR_SERVICE_MODIFIED
