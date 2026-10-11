#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
PKG_DIR="$(cd "$(dirname "$0")" && pwd)"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$APP_ROOT/storage/app/fix44_backup_$STAMP"

test -f "$APP_ROOT/artisan"
test -f "$APP_ROOT/routes/api.php"
test -f "$PKG_DIR/payload/AuthController.php"
test -f "$PKG_DIR/payload/AttendanceController.php"
test -f "$PKG_DIR/payload/RotWorkflowController.php"
test -f "$PKG_DIR/payload/AdminMobileController.php"
test -f "$PKG_DIR/payload/StaffLocationController.php"

mkdir -p "$BACKUP/app/Http/Controllers/Api/V1" "$BACKUP/routes"

cp "$APP_ROOT/routes/api.php" "$BACKUP/routes/api.php"
for f in AuthController.php AttendanceController.php RotWorkflowController.php; do
  cp "$APP_ROOT/app/Http/Controllers/Api/V1/$f" "$BACKUP/app/Http/Controllers/Api/V1/$f"
done

restore() {
  echo "FIX44 rollback triggered"
  cp "$BACKUP/routes/api.php" "$APP_ROOT/routes/api.php" || true
  for f in AuthController.php AttendanceController.php RotWorkflowController.php; do
    cp "$BACKUP/app/Http/Controllers/Api/V1/$f" "$APP_ROOT/app/Http/Controllers/Api/V1/$f" || true
  done
  rm -f "$APP_ROOT/app/Http/Controllers/Api/V1/AdminMobileController.php"         "$APP_ROOT/app/Http/Controllers/Api/V1/StaffLocationController.php"
  php "$APP_ROOT/artisan" optimize:clear >/dev/null 2>&1 || true
}
trap restore ERR

cp "$PKG_DIR/payload/AuthController.php" "$APP_ROOT/app/Http/Controllers/Api/V1/AuthController.php"
cp "$PKG_DIR/payload/AttendanceController.php" "$APP_ROOT/app/Http/Controllers/Api/V1/AttendanceController.php"
cp "$PKG_DIR/payload/RotWorkflowController.php" "$APP_ROOT/app/Http/Controllers/Api/V1/RotWorkflowController.php"
cp "$PKG_DIR/payload/AdminMobileController.php" "$APP_ROOT/app/Http/Controllers/Api/V1/AdminMobileController.php"
cp "$PKG_DIR/payload/StaffLocationController.php" "$APP_ROOT/app/Http/Controllers/Api/V1/StaffLocationController.php"

php "$PKG_DIR/PATCH_ROUTES.php" "$APP_ROOT"

for f in   "$APP_ROOT/app/Http/Controllers/Api/V1/AuthController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/AttendanceController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/RotWorkflowController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/AdminMobileController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/StaffLocationController.php"   "$APP_ROOT/routes/api.php"
do
  php -l "$f"
done

# Fail closed if workshop coordinates are not already configured.
cd "$APP_ROOT"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s=App\Models\StaffLocationSetting::query()->whereKey(1)->first();
if(!$s){
    fwrite(STDERR,"FIX44: staff_location_settings id=1 missing\n");
    exit(10);
}
if($s->latitude===null || $s->longitude===null){
    fwrite(STDERR,"FIX44: workshop coordinates are not configured; refusing to enable geofence\n");
    exit(11);
}
$s->radius_m=100;
$s->max_accuracy_m=max(20,min(100,(int)($s->max_accuracy_m ?: 100)));
$s->tracking_enabled=true;
$s->geofence_checkin_enabled=true;
$s->save();
echo "FIX44 geofence enabled: radius=100m, max_accuracy=".$s->max_accuracy_m."m\n";
PHP

php artisan optimize:clear >/dev/null
php artisan route:list --path=api/v1 | grep -E 'admin/mobile|staff/location|attendance/check-in|auth/login' >/tmp/fix44_routes.txt
cat /tmp/fix44_routes.txt

trap - ERR
echo "FIX44 APPLY: PASS"
echo "Backup: $BACKUP"
