#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }

DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_ROOT"
F=public/legacy/api/staff-rot-v3.php

echo "ASSAM MOTORS FIX28 — READ ONLY CHECK"
test -f "$F" || { echo "NO-GO: missing $F"; exit 3; }

php -l "$F" >/dev/null || { echo "NO-GO: PHP syntax error in $F"; exit 3; }
php -l "$DIR/PATCH_FIX28.php" >/dev/null || { echo "NO-GO: PATCH_FIX28.php syntax error"; exit 3; }
echo "PASS ROT endpoint syntax"
echo "PASS FIX28 patcher syntax"

grep -q "pause_reason" "$F" || { echo "NO-GO: FIX20 pause metadata support missing"; exit 4; }
grep -q "function s3public" "$F" || { echo "NO-GO: s3public function missing"; exit 4; }
echo "PASS baseline: pause metadata"
echo "PASS baseline: s3public"

if grep -q "near_standard_limit" "$F"; then
  echo "FIX28 already installed"
  exit 5
fi

echo "== DRY-RUN AGAINST CURRENT LIVE FILE =="
TMP="$(mktemp -d)"
cleanup(){ rm -rf "$TMP"; }
trap cleanup EXIT
mkdir -p "$TMP/public/legacy/api"
cp -p "$F" "$TMP/public/legacy/api/staff-rot-v3.php"

if ! php "$DIR/PATCH_FIX28.php" "$TMP"; then
  echo "NO-GO: FIX28 patcher does not match current live baseline."
  echo "Live source was NOT modified."
  exit 6
fi

php -l "$TMP/public/legacy/api/staff-rot-v3.php" >/dev/null || {
  echo "NO-GO: dry-run produced invalid PHP"
  exit 6
}

for m in standard_seconds remaining_seconds overtime_seconds progress_percent near_standard_limit target_end_at pause_reason pause_note; do
  grep -q "$m" "$TMP/public/legacy/api/staff-rot-v3.php" || {
    echo "NO-GO: dry-run missing $m"
    exit 6
  }
done

echo "PASS dry-run patch against CURRENT live file"
echo "CHECK RESULT: GO"
