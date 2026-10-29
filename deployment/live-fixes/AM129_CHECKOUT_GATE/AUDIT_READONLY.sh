#!/usr/bin/env bash
# AM129 staging-only READONLY source/status/permissions check.
set -Eeuo pipefail
cd "$1"; test -f artisan
echo "AM129_AUDIT_START"
for path in app/Services/AttendanceService.php app/Models/Attendance.php; do
 echo "=== $path ===";test -f "$path" && sed -n '1,240p' "$path" | cut -c1-220 || echo NOT_FOUND
done
echo "=== ATTENDANCE ROUTES ==="
php artisan route:list --path=attendance --no-ansi 2>&1 | head -65 | cut -c1-245 || true
echo "=== ADMIN ATTENDANCE CONTROLLER/PERMISSIONS ==="
find app/Http/Controllers -type f -iname '*Attendance*' | head -25 | while read -r path; do
 echo "--- $path ---"
 grep -nEi -C 2 -m 20 'update|correct|check_out|checkOut|status|audit|role|authorize|permission|salary' "$path" | head -140 | cut -c1-240 || true
done
echo "=== ATTENDANCE MODEL / MIGRATION CHECKS ==="
find database/migrations -type f -iname '*attend*' | head -15 | while read -r path;do echo "--- $path";grep -nEi -m 25 'table|check_in|check_out|worked_hours|status|date' "$path" | head -40 | cut -c1-200 || true;done
echo "=== STAFF ATTENDANCE CONTROLLER ENTRYPOINTS ==="
grep -RniE -m 5 --include='*.php' --exclude='AttendanceService.php' '->checkIn\(|->checkOut\(|AttendanceService::class|Attendance::query\(\)' app/Http/Controllers | head -80 | cut -c1-240 || true
echo "=== ADMIN/HR CORRECTION AND WEB STAFF CHECK-IN SOURCES ==="
for item in app/Http/Controllers/Web/AdminNativeModuleController.php app/Http/Controllers/Web/StaffPortalController.php app/Http/Controllers/Api/V1/HrOperationsApiController.php app/Http/Controllers/Api/V1/OwnerMobileAttendanceCorrectionController.php; do
 echo "--- $item ---"
 if ! test -f "$item";then echo MISSING;continue;fi
 grep -nEi -C 5 -m 36 -- 'updateAttendance|resetAttendance|attendance.*update|function.*attendance|function.*checkIn|function.*checkOut|check_out|check_in|worked_hours|->checkIn\(|->checkOut\(|audit|admin_note|reason' "$item" | head -200 | cut -c1-225 || true
done
echo "=== ATTENDANCE UNFINISHED PREVIOUS-DAY AGGREGATE ==="
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$today=now('Asia/Kolkata')->toDateString();
echo 'TODAY_IST='.$today."\n";
echo 'OLD_OPEN_ATTENDANCE_COUNT='.DB::table('attendance')->where('date','<',$today)->whereNotNull('check_in')->whereNull('check_out')->count()."\n";
echo 'STAFF_COUNT_OLD_OPEN='.DB::table('attendance')->where('date','<',$today)->whereNotNull('check_in')->whereNull('check_out')->distinct()->count('staff_id')."\n";
PHP
echo "AM129_AUDIT_COMPLETE_NO_DATA_WRITES"
