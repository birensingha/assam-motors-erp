#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
F=resources/views/job-cards/form.blade.php
echo "ASSAM MOTORS FIX31 — READ ONLY CHECK"
test -f "$F" || { echo "MISSING $F"; exit 3; }
grep -q 'class="classic-form-shell"' "$F" || { echo "NO-GO classic Job Card form missing"; exit 4; }
grep -q 'Service Type<input name="service_type"' "$F" || { echo "NO-GO expected Service Type text input baseline missing"; exit 4; }
grep -q 'form-grid four-col' "$F" || { echo "NO-GO form grid baseline missing"; exit 4; }
if grep -q 'FIX31_JOBCARD_FORM_LAYOUT' "$F"; then echo "FIX31 already installed"; exit 5; fi
php -l "$F" >/dev/null
echo "PASS Job Card form baseline"
echo "PASS Service Type is currently text input"
echo "CHECK RESULT: GO"
