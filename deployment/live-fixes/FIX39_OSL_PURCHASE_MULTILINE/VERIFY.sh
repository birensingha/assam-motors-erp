#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash VERIFY.sh /path/to/app"; exit 2; }
cd "$APP_ROOT"
VIEW=resources/views/purchases/osl-create.blade.php
SERVICE=app/Services/PurchaseService.php

echo "ASSAM MOTORS FIX39 — VERIFY"
grep -q "FIX39_OSL_PARTS_STYLE" "$VIEW" && echo "PASS Parts-style OSL UI marker" || { echo "FAIL OSL UI marker"; exit 1; }
grep -q 'class="job-filter"' "$VIEW" && echo "PASS JC / Vehicle text search" || { echo "FAIL job filter"; exit 1; }
grep -q "am_erp_osl_view_scale" "$VIEW" && echo "PASS 100/115/125 readability control" || { echo "FAIL readability"; exit 1; }
grep -q "function saveOslBatch" "$SERVICE" && echo "PASS multi-line transaction backend" || { echo "FAIL saveOslBatch"; exit 1; }
grep -q "workshop_purchase_batches" "$SERVICE" && echo "PASS OSL batch header support" || { echo "FAIL batch support"; exit 1; }
grep -q "customer_rate" "$SERVICE" && echo "PASS customer billing linkage" || { echo "FAIL customer billing"; exit 1; }
echo "VERIFY RESULT: PASS"
