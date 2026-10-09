#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -d "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: $0 /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"

echo "Assam Motors — THREE PAGE 500 DIAGNOSTIC (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

echo "== ROUTES =="
php artisan route:list --path=erp/payments/create 2>&1 || true
php artisan route:list --path=erp/customers 2>&1 || true
php artisan route:list --path=erp/job-cards/create 2>&1 || true
echo

LOG="$(ls -1t storage/logs/*.log 2>/dev/null | head -1 || true)"
if [[ -z "$LOG" || ! -f "$LOG" ]]; then
  echo "No Laravel log file found under storage/logs/"
  exit 3
fi

echo "== LOG FILE =="
echo "$LOG"
echo

TMP="/tmp/assam-three-page-500-$$.log"
tail -n 1800 "$LOG" > "$TMP"

echo "== RECENT ERROR HEADERS =="
grep -nE '\.(ERROR|CRITICAL|ALERT|EMERGENCY):|production.ERROR|local.ERROR|staging.ERROR' "$TMP" | tail -n 20 || true
echo

echo "== RELEVANT EXCEPTION EXCERPTS =="
# Print context around common Laravel/PHP/SQL exception signals.
grep -nE -B4 -A24   'SQLSTATE\[|QueryException|ViewException|ErrorException|TypeError|Undefined variable|Undefined property|Call to undefined|Route \[|does not exist|Unknown column|isn.t in GROUP BY|Malformed UTF-8|payments/create|erp/customers|job-cards/create'   "$TMP" | tail -n 420 |   sed -E \
    -e 's/(Authorization: Bearer )[A-Za-z0-9._~+\/-]+/\1<redacted>/g' \
    -e 's/(DB_PASSWORD[=:][[:space:]]*)[^[:space:]]+/\1<redacted>/g' \
    -e 's/(APP_KEY[=:][[:space:]]*)[^[:space:]]+/\1<redacted>/g' || true

rm -f "$TMP"

echo
echo "DIAGNOSTIC COMPLETE — READ ONLY"
echo "No source file, database row, cache or config was modified."
