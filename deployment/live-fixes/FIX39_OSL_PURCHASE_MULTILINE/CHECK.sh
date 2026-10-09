#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_ROOT"

echo "ASSAM MOTORS FIX39 — OSL PURCHASE MULTI-LINE CHECK (READ ONLY)"
VIEW=resources/views/purchases/osl-create.blade.php
SERVICE=app/Services/PurchaseService.php
CTRL=app/Http/Controllers/Web/AdminPurchasePageController.php
ROUTES=routes/web.php

for f in "$VIEW" "$SERVICE" "$CTRL" "$ROUTES"; do
  [[ -f "$f" ]] || { echo "NO-GO: missing $f"; exit 3; }
  echo "PASS file: $f"
done

php -l "$DIR/PATCH_FIX39.php" >/dev/null || { echo "NO-GO: PATCH_FIX39.php syntax error"; exit 3; }
echo "PASS patcher syntax"

grep -q "function saveOslBatch" "$SERVICE" || { echo "NO-GO: saveOslBatch missing"; exit 4; }
grep -q "Maximum 50 OSL lines" "$SERVICE" || { echo "NO-GO: OSL 50-line validation missing"; exit 4; }
grep -q "workshop_purchase_batches" "$SERVICE" || { echo "NO-GO: OSL batch storage missing"; exit 4; }
grep -q 'id="add-osl-line"' "$VIEW" || { echo "NO-GO: OSL Add Line UI missing"; exit 4; }
grep -q 'id="osl-lines"' "$VIEW" || { echo "NO-GO: OSL line table missing"; exit 4; }
grep -q "erp.purchases.osl.store" "$VIEW" || { echo "NO-GO: OSL store route binding missing"; exit 4; }
echo "PASS base multi-line OSL backend/UI"

if grep -q "FIX39_OSL_PARTS_STYLE" "$VIEW"; then
  echo "FIX39 already installed"
  exit 5
fi

echo "== DRY-RUN AGAINST CURRENT LIVE FILE =="
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/resources/views/purchases"
cp -p "$VIEW" "$TMP/resources/views/purchases/osl-create.blade.php"
touch "$TMP/artisan"
php "$DIR/PATCH_FIX39.php" "$TMP"

for marker in FIX39_OSL_PARTS_STYLE job-filter am-readable-115 am_erp_osl_view_scale; do
  grep -q "$marker" "$TMP/resources/views/purchases/osl-create.blade.php" || { echo "NO-GO: dry-run missing $marker"; exit 6; }
done

echo "PASS dry-run patch against CURRENT live OSL view"
echo "CHECK RESULT: GO"
