#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
PKG_DIR="$(cd "$(dirname "$0")" && pwd)"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$APP_ROOT/storage/app/fix45_backup_$STAMP"

bash "$PKG_DIR/CHECK_FIX45_STAGING.sh" "$APP_ROOT"

mkdir -p "$BACKUP/app/Http/Controllers/Api/V1" "$BACKUP/routes"
cp "$APP_ROOT/routes/api.php" "$BACKUP/routes/api.php"
if [[ -f "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php" ]]; then
  cp "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php" "$BACKUP/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
fi

restore_source() {
  echo "FIX45 source rollback triggered"
  cp "$BACKUP/routes/api.php" "$APP_ROOT/routes/api.php" || true
  if [[ -f "$BACKUP/app/Http/Controllers/Api/V1/OwnerWorkshopController.php" ]]; then
    cp "$BACKUP/app/Http/Controllers/Api/V1/OwnerWorkshopController.php" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php" || true
  else
    rm -f "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
  fi
  php "$APP_ROOT/artisan" optimize:clear >/dev/null 2>&1 || true
}
trap restore_source ERR

cp "$PKG_DIR/payload/OwnerWorkshopController.php" "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
php "$PKG_DIR/PATCH_ROUTES.php" "$APP_ROOT"
php "$PKG_DIR/MIGRATE_FIX45.php" "$APP_ROOT"

php -l "$APP_ROOT/app/Http/Controllers/Api/V1/OwnerWorkshopController.php"
php -l "$APP_ROOT/routes/api.php"
cd "$APP_ROOT"
php artisan optimize:clear >/dev/null

bash "$PKG_DIR/VERIFY_FIX45_STAGING.sh" "$APP_ROOT"
trap - ERR

echo "FIX45 APPLY: PASS"
echo "Backup: $BACKUP"
echo "NOTE: FIX45 DB changes are additive tables/column only."
