#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="${1:?Staging Laravel root}"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"; test -f artisan
python3 -m py_compile "$HERE/patch.py"
python3 "$HERE/patch.py" check "$ROOT"
ROLLBACK=0
restore(){
 code="$?"
 if [ "$ROLLBACK" = 1 ];then
  B="$ROOT/storage/app/am-backups/AM276_VEHICLE_VARIANT"
  test -f "$B/customer.before" && cp -p "$B/customer.before" "$ROOT/resources/views/admin/native/customer-detail.blade.php" || true
  test -f "$B/vehicle.before" && cp -p "$B/vehicle.before" "$ROOT/resources/views/admin/native/vehicles.blade.php" || true
  php artisan view:clear >/dev/null 2>&1 || true
  echo AM276_SOURCE_ROLLBACK_AFTER_FAILURE
 fi
 exit "$code"
}
trap restore EXIT
python3 "$HERE/patch.py" apply "$ROOT"
ROLLBACK=1
php artisan view:clear
php artisan view:cache
grep -q AM276_SCOPED_VARIANTS resources/views/admin/native/customer-detail.blade.php
grep -q am276-vehicle-master-parts-style resources/views/admin/native/vehicles.blade.php
CODE="$(curl -sSL -o /dev/null -w '%{http_code}' --max-time 30 https://staging.assammotors.com/erp/login)"
test "$CODE" = 200
ROLLBACK=0;trap - EXIT
echo AM276_STAGING_VEHICLE_CASCADE_AND_UI_DEPLOY_PASS
