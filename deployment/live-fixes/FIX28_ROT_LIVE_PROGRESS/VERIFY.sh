#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"
F=public/legacy/api/staff-rot-v3.php
echo "ASSAM MOTORS FIX28 — READ ONLY VERIFY"
php -l "$F" >/dev/null
for m in standard_seconds remaining_seconds overtime_seconds progress_percent near_standard_limit target_end_at pause_reason pause_note; do
  grep -q "$m" "$F" || { echo "FAIL missing $m"; exit 3; }
done
echo "PASS live ROT timing fields"
echo "PASS latest pause metadata fields"
echo "VERIFY RESULT: PASS"
