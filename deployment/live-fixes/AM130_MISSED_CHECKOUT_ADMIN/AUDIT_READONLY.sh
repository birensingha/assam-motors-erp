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
echo AM130_SOURCE_COMPLETE_NO_WRITES
