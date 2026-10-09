#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_ROOT"

echo "ASSAM MOTORS FIX40 — CUSTOMER NATIVE AUTH CHECK (READ ONLY)"
LEG=public/legacy/workshop/customers.php

[[ -f "$LEG" ]] || { echo "NO-GO: missing $LEG"; exit 3; }
echo "PASS legacy customers.php exists"

php -l "$LEG" >/dev/null || { echo "NO-GO: legacy customers.php syntax error"; exit 3; }
php -l "$DIR/PATCH_FIX40.php" >/dev/null || { echo "NO-GO: PATCH_FIX40.php syntax error"; exit 3; }
echo "PASS syntax"

php artisan route:list 2>&1 | grep -qE 'GET\|HEAD[[:space:]]+erp/customers[[:space:]]' || {
  echo "NO-GO: native GET /erp/customers route missing"
  exit 4
}
echo "PASS native /erp/customers route"

grep -q "customers.php?action=" public/legacy/workshop/customers.js || {
  echo "NO-GO: legacy customers.js API usage not detected"
  exit 4
}
echo "PASS legacy action API usage detected — must be preserved"

if grep -q "FIX40_DIRECT_BROWSER_REDIRECT" "$LEG"; then
  echo "FIX40 already installed"
  exit 5
fi

echo "== DRY-RUN AGAINST CURRENT LIVE FILE =="
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/public/legacy/workshop"
cp -p "$LEG" "$TMP/public/legacy/workshop/customers.php"
touch "$TMP/artisan"

php "$DIR/PATCH_FIX40.php" "$TMP"
php -l "$TMP/public/legacy/workshop/customers.php" >/dev/null

grep -q "FIX40_DIRECT_BROWSER_REDIRECT" "$TMP/public/legacy/workshop/customers.php" || {
  echo "NO-GO: dry-run marker missing"; exit 6;
}
grep -q "Location: /erp/customers" "$TMP/public/legacy/workshop/customers.php" || {
  echo "NO-GO: dry-run redirect missing"; exit 6;
}
grep -q "\$_GET\['action'\]" "$TMP/public/legacy/workshop/customers.php" || {
  echo "NO-GO: action-preservation condition missing"; exit 6;
}

echo "PASS dry-run direct browser redirect"
echo "PASS legacy ?action=... requests remain available"
echo "CHECK RESULT: GO"
