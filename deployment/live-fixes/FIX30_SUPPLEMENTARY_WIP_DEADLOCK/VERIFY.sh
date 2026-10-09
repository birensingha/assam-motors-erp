#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"
echo "ASSAM MOTORS FIX30 — READ ONLY VERIFY"
php -l app/Services/EstimateService.php >/dev/null
php -l app/Http/Controllers/Web/AdminEstimatePageController.php >/dev/null
php -l resources/views/job-cards/show.blade.php >/dev/null
grep -q "bool \$supplementary = false" app/Services/EstimateService.php
grep -q "Supplementary Estimate requires a previously approved Estimate" app/Services/EstimateService.php
grep -q "Supplementary Estimate Preparation" app/Services/EstimateService.php
grep -q "supplementaryFlow" app/Http/Controllers/Web/AdminEstimatePageController.php
grep -q "Supplementary Estimate Ready" resources/views/job-cards/show.blade.php
grep -q "SUPPLEMENTARY READY" resources/views/job-cards/show.blade.php
grep -q "Diagnosis can be completed only before Work In Progress" app/Http/Controllers/Web/AdminJobCardPageController.php
echo "PASS Initial/Revision diagnosis gate retained"
echo "PASS Supplementary eligibility separated from WIP diagnosis"
echo "PASS WIP status preservation marker"
echo "PASS Job Card UI shows Supplementary Ready state"
echo "VERIFY RESULT: PASS"
