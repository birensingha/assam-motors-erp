#!/usr/bin/env bash
set -Eeuo pipefail
cd "$1"; test -f artisan
echo AM130_SOURCE_BEGIN
file=app/Http/Controllers/Web/AdminNativeModuleController.php
echo '=== ADMIN ATTENDANCE CONTROLLER 230..420 ==='
sed -n '230,420p' "$file" | cut -c1-250
echo '=== ADMIN PERMISSION SOURCE ==='
grep -nE -A 18 -B 4 'function admin\(|function updateAttendance\(|function existing\(' "$file" | cut -c1-250 | head -120 || true
echo '=== ATTENDANCE VIEW REFERENCE ==='
grep -n -C 4 "return view(.*attendance\|'erp.attendance'\|'attendance'" "$file" | head -125 | cut -c1-250 || true
echo '=== VIEW FILES ==='
find resources/views -type f -iname '*attendance*' | head -45
echo '=== ATTENDANCE BLADE MAIN ==='
for f in $(find resources/views -type f \( -iname '*attendance*' -o -iname '*native-module*' \) | head -18);do
 echo "--- $f"
 wc -l "$f"
 grep -nEi -m 28 -C 3 'update|check.?out|attendance|notes|status|reason|edit|reset' "$f" | head -145 | cut -c1-235 || true
done
echo '=== AUDIT TABLES / LOGGING SOURCE ==='
grep -RniE -m 3 --include='*.php' 'attendance.*audit|audit.*attendance|HrOperationsService|correctAttendance' app/Services app/Http/Controllers | head -80 | cut -c1-235 || true

echo '=== AUDIT TABLE WRITE SIGNATURE ==='
sed -n '127,178p' app/Services/HrOperationsService.php | cut -c1-250
sed -n '1180,1215p' app/Http/Controllers/Api/V1/OwnerWorkshopController.php | cut -c1-250
echo '=== ATTENDANCE BLADE FIRST 210 ==='
sed -n '1,210p' resources/views/admin/native/attendance.blade.php | cut -c1-270
echo '=== ATTENDANCE VIEW EDIT FORMS ==='
grep -n -C 8 "route('erp.attendance.update" resources/views/admin/native/attendance.blade.php | head -105 | cut -c1-270 || true
echo '=== AUDIT TABLE SCHEMA ==='
php <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['hr_audit_logs','attendance_change_audits','job_card_change_audits'] as $name) {
 if(\Illuminate\Support\Facades\Schema::hasTable($name)){
    echo $name.' COLUMNS='.implode(',',\Illuminate\Support\Facades\Schema::getColumnListing($name))."\n";
 }else echo $name.' MISSING'."\n";
}
PHP

echo AM130_SOURCE_COMPLETE_NO_WRITES
