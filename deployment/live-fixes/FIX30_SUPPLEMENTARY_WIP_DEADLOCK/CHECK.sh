#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
echo "ASSAM MOTORS FIX30 — READ ONLY CHECK"
for f in app/Services/EstimateService.php app/Http/Controllers/Web/AdminEstimatePageController.php resources/views/job-cards/show.blade.php; do
  test -f "$f" || { echo "MISSING $f"; exit 3; }
  echo "PASS file: $f"
done
grep -q "Final Diagnosis is not marked COMPLETED" app/Services/EstimateService.php
grep -q "Diagnosis can be completed only before Work In Progress" app/Http/Controllers/Web/AdminJobCardPageController.php
grep -q "WORKSHOP_PROTOCOL_WIP_SUPPLEMENTARY" app/Services/EstimateService.php
if grep -q "Supplementary Estimate Ready" resources/views/job-cards/show.blade.php; then
  echo "FIX30 already appears installed"; exit 4
fi
php -l app/Services/EstimateService.php >/dev/null
php -l app/Http/Controllers/Web/AdminEstimatePageController.php >/dev/null
echo "PASS strict initial Diagnosis gate present"
echo "PASS WIP Diagnosis protection present"
echo "CHECK RESULT: GO"
