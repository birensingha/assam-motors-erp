#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
echo "ASSAM MOTORS FIX27 — READ ONLY CHECK"
for f in resources/views/purchases/create.blade.php resources/views/estimates/create.blade.php; do
  test -f "$f" || { echo "MISSING $f"; exit 3; }
  echo "PASS file: $f"
done
grep -q "Parts Purchase Entry" resources/views/purchases/create.blade.php
grep -q 'id="purchase-form"' resources/views/purchases/create.blade.php
grep -q 'id="estimate-form"' resources/views/estimates/create.blade.php
grep -q "Estimate Lines" resources/views/estimates/create.blade.php
if grep -q "FIX27_READABILITY" resources/views/purchases/create.blade.php || grep -q "FIX27_READABILITY" resources/views/estimates/create.blade.php; then
  echo "FIX27 already appears installed"; exit 4
fi
echo "CHECK RESULT: GO"
