#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
F=public/legacy/api/staff-rot-v3.php
echo "ASSAM MOTORS FIX28 — READ ONLY CHECK"
test -f "$F" || { echo "MISSING $F"; exit 3; }
php -l "$F" >/dev/null
grep -q "pause_reason" "$F" || { echo "NO-GO: FIX20 pause metadata support missing"; exit 4; }
grep -q "function s3public" "$F" || exit 4
if grep -q "near_standard_limit" "$F"; then echo "FIX28 already installed"; exit 5; fi
echo "PASS ROT legacy endpoint baseline"
echo "CHECK RESULT: GO"
