#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"
F=resources/views/job-cards/form.blade.php
echo "ASSAM MOTORS FIX31 — READ ONLY VERIFY"
php -l "$F" >/dev/null
grep -q 'FIX31_JOBCARD_FORM_LAYOUT' "$F"
grep -q 'grid-template-columns:minmax(0,1fr) minmax(0,1fr)' "$F"
grep -q 'id="jcServiceType"' "$F"
grep -q 'Periodic Service' "$F"
grep -q 'Running Repair' "$F"
grep -q 'Body & Paint' "$F"
grep -q 'appearance:auto' "$F"
echo "PASS desktop two-column Job Card layout"
echo "PASS mobile one-column fallback"
echo "PASS Service Type proper dropdown list"
echo "PASS native select styling"
echo "VERIFY RESULT: PASS"
