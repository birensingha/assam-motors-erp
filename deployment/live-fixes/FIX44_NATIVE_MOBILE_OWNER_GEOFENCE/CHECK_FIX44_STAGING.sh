#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
test -f "$APP_ROOT/artisan"
test -f "$APP_ROOT/app/Services/GeoFenceService.php"
test -f "$APP_ROOT/app/Services/AttendanceService.php"
test -f "$APP_ROOT/app/Models/StaffLocationSetting.php"
test -f "$APP_ROOT/app/Models/StaffLocationLog.php"
grep -q "geofence_checkin_enabled" "$APP_ROOT/app/Services/GeoFenceService.php"
grep -q "GeoFenceService" "$APP_ROOT/app/Services/AttendanceService.php"
echo "FIX44 CHECK: PASS"
