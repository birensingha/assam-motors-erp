#!/usr/bin/env bash
# STAGING only: inspect attendance check-in/out rollover and automatic midnight close.
# READ-ONLY: file/scheduler inspection, no data mutation.
set -Eeuo pipefail
ROOT="$1"; cd "$ROOT"; test -f artisan || exit 2
echo "MISSED_CHECKOUT_AUDIT_BEGIN"
for path in app/Services/AttendanceService.php app/Http/Controllers/Api/V1/AttendanceController.php app/Http/Controllers/Api/V1/AuthController.php; do
  echo "===== FILE $path ====="
  if ! test -f "$path";then echo MISSING;continue;fi
  if [[ "$path" == *AttendanceService.php ]];then
     echo "LINES=$(wc -l < "$path")"
     sed -n '1,320p' "$path" | cut -c 1-230
  else
     grep -nEi -m 42 'checkIn|checkOut|logout|checkout|attendance|staff_login_events|staff_logout_events' "$path" | cut -c 1-230 || true
  fi
done
echo "===== SCHEDULED / CRON / CONSOLE CHECKOUT REFERENCES ====="
for folder in app/Console app/Jobs app/Services routes;do
  if test -d "$folder";then
   grep -RniE -m 3 --include='*.php' 'auto.?check.?out|missed.?check.?out|CHECKOUT_PENDING|check.?out_at|checkout_at|check_out_at|attendance.*midnight|attendance.*auto.?close|attendance.*rollover' "$folder" 2>/dev/null | head -70 | cut -c1-245 || true
  fi
done
echo "===== SCHEDULE LIST ATTENDANCE KEYWORDS ====="
php artisan schedule:list --no-ansi 2>&1 | grep -Ei -m 32 'attendance|checkout|check-out|cron|reminder|staff' | cut -c1-220 || true
echo "MISSED_CHECKOUT_AUDIT_COMPLETE_NO_WRITES"
