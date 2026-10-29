#!/usr/bin/env bash
# AM130 guarded STAGING-only Admin Missed Check-Out Register, no data mutation.
set -Eeuo pipefail
ROOT="${1:?STAGING_LARAVEL_ROOT required}"
cd "$ROOT";test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
CONTROLLER="$ROOT/app/Http/Controllers/Web/AdminNativeModuleController.php"
VIEW="$ROOT/resources/views/admin/native/attendance.blade.php"
PARTIAL="$ROOT/resources/views/admin/native/partials/am130-missed-checkout.blade.php"
python3 -m py_compile "$HERE/apply.py"
php -l "$HERE/verify.php"
python3 "$HERE/apply.py" check "$ROOT"
mkdir -p "$ROOT/storage/app/am-backups"
STAMP="$(date +%Y%m%d_%H%M%S)_$$"
OLD_CONTROLLER="$ROOT/storage/app/am-backups/AM130_WORKFLOW_CTRL_$STAMP.php"
OLD_VIEW="$ROOT/storage/app/am-backups/AM130_WORKFLOW_VIEW_$STAMP.blade.php"
cp -p "$CONTROLLER" "$OLD_CONTROLLER"
cp -p "$VIEW" "$OLD_VIEW"
if test -f "$PARTIAL";then
 echo "AM130_PARTIAL_PREEXISTED_ABORT"
 exit 3
fi
restore(){
 code="$?"
 trap - ERR
 cp -p "$OLD_CONTROLLER" "$CONTROLLER" || true
 cp -p "$OLD_VIEW" "$VIEW" || true
 rm -f "$PARTIAL"
 php artisan view:clear >/dev/null 2>&1 || true
 php artisan view:cache >/dev/null 2>&1 || true
 echo "AM130_FAILED_SAFE_RESTORED_PREVIOUS_ADMIN_PAGE=$code"
 exit "$code"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$ROOT"
QA="$(php "$HERE/verify.php" 2>&1 || true)"
printf '%s\n' "$QA"
printf '%s\n' "$QA" | grep -Fxq "AM130_VERIFICATION_COMPLETE_NO_ATTENDANCE_DATA_WRITES"
CODE="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' 'https://staging.assammotors.com/erp/login')"
echo "AM130_STAGING_LOGIN_HTTP=$CODE"
test "$CODE" = 200
trap - ERR
echo "AM130_ADMIN_MISSED_CHECKOUT_REGISTER_STAGING_VERIFIED"
echo "AM130_HISTORICAL_ATTENDANCE_ROWS_NOT_CHANGED"
