#!/usr/bin/env bash
set -euo pipefail
# FIX47 applies ONLY incremental Web QC->ROT linkage. No DB / invoice / ledger writes.
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
PKG_DIR="$(cd "$(dirname "$0")" && pwd)"
test -f "$APP_ROOT/artisan"
test -f "$APP_ROOT/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"
test -f "$APP_ROOT/resources/views/job-cards/lifecycle-panel.blade.php"
php -l "$PKG_DIR/PATCH_FIX47.php" >/dev/null
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$APP_ROOT/storage/app/fix47_qc_rot_backup_$STAMP"
mkdir -p "$BACKUP"
C="$APP_ROOT/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"
V="$APP_ROOT/resources/views/job-cards/lifecycle-panel.blade.php"
cp "$C" "$BACKUP/controller.php"
cp "$V" "$BACKUP/panel.blade.php"
rollback(){
  echo "FIX47 source rollback (NO billing actions)"
  cp "$BACKUP/controller.php" "$C"
  cp "$BACKUP/panel.blade.php" "$V"
  (cd "$APP_ROOT" && php artisan view:clear >/dev/null 2>&1) || true
}
trap rollback ERR
php "$PKG_DIR/PATCH_FIX47.php" "$APP_ROOT"
php -l "$C"
grep -q 'FIX47_QC_FAILED_ROT_SESSION' "$C"
grep -q 'FIX47_QC_FAILED_ROT_SESSION' "$V"
grep -q "where('job_card_id',\$job)" "$C"
grep -q 'failed_rot_session_id' "$V"
cd "$APP_ROOT"
php artisan view:cache >/dev/null
php artisan view:clear >/dev/null
trap - ERR
echo "FIX47 APPLY+VERIFY: PASS; backup=$BACKUP"
