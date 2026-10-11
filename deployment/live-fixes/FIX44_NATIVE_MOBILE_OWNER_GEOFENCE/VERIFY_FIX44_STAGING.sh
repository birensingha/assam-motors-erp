#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
for f in   "$APP_ROOT/app/Http/Controllers/Api/V1/AuthController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/AttendanceController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/RotWorkflowController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/AdminMobileController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/StaffLocationController.php"   "$APP_ROOT/app/Http/Controllers/Api/V1/StaffNotificationController.php"   "$APP_ROOT/routes/api.php"
do
  php -l "$f" >/dev/null
done
grep -q "AM_NATIVE_STAFF_LOGIN_GEOFENCE_FIX44" "$APP_ROOT/app/Http/Controllers/Api/V1/AuthController.php"
grep -q "admin/mobile/dashboard" "$APP_ROOT/routes/api.php"
grep -q "staff/location" "$APP_ROOT/routes/api.php"
grep -q "polling_fallback" "$APP_ROOT/app/Http/Controllers/Api/V1/AdminMobileController.php"
grep -q "action_resolved_at" "$APP_ROOT/app/Http/Controllers/Api/V1/StaffNotificationController.php"
cd "$APP_ROOT"
php artisan route:list --path=api/v1 | grep -q "admin/mobile/dashboard"
php artisan route:list --path=api/v1 | grep -q "staff/location"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s=App\Models\StaffLocationSetting::query()->whereKey(1)->first();
if(!$s || !$s->geofence_checkin_enabled || (int)$s->radius_m!==100 || $s->latitude===null || $s->longitude===null){
    fwrite(STDERR,"FIX44 geofence verification failed\n"); exit(20);
}
echo "FIX44 geofence verification: PASS\n";
PHP
echo "FIX44 VERIFY: PASS"
