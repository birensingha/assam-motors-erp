#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: $0 /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"
DOMAIN_ROOT="$(dirname "$APP_ROOT")"
WEBROOT="$DOMAIN_ROOT/public_html/staging"

echo "ASSAM MOTORS — CUSTOMER + JOB CARD UI DIAGNOSTIC (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo "WEBROOT:  $WEBROOT"
echo

echo "== ROUTES =="
php artisan route:list --path=erp/customers 2>&1 || true
php artisan route:list --path=erp/job-cards 2>&1 || true
echo

echo "== CUSTOMER REFERENCES IN ERP VIEWS / ROUTES =="
grep -RInE --exclude='*.map'   'legacy/workshop/customers\.php|erp\.customers|/erp/customers'   routes resources/views app/Http/Controllers 2>/dev/null | head -n 220 || true
echo

echo "== JOB CARD CREATE REFERENCES IN ERP VIEWS =="
grep -RInE --exclude='*.map'   'erp\.job-cards\.create|/erp/job-cards/create|Create Job Card|New Job Card|Job Cards'   resources/views routes 2>/dev/null | head -n 260 || true
echo

echo "== ERP LAYOUT NAVIGATION EXCERPT =="
if [[ -f resources/views/layouts/erp.blade.php ]]; then
  grep -nE -B3 -A5     'customer|job.card|job-card|legacy/workshop/customers|erp\.customers|erp\.job-cards'     resources/views/layouts/erp.blade.php 2>/dev/null | head -n 260 || true
else
  echo "MISSING resources/views/layouts/erp.blade.php"
fi
echo

echo "== JOB CARD INDEX VIEW =="
if [[ -f resources/views/job-cards/index.blade.php ]]; then
  sed -n '1,260p' resources/views/job-cards/index.blade.php
else
  echo "MISSING resources/views/job-cards/index.blade.php"
fi
echo

echo "== CUSTOMER NATIVE VIEW FIRST 80 LINES =="
if [[ -f resources/views/admin/native/customers.blade.php ]]; then
  sed -n '1,80p' resources/views/admin/native/customers.blade.php
else
  echo "MISSING resources/views/admin/native/customers.blade.php"
fi
echo

echo "== LEGACY CUSTOMER ENTRY FIRST 35 LINES =="
if [[ -f public/legacy/workshop/customers.php ]]; then
  sed -n '1,35p' public/legacy/workshop/customers.php
else
  echo "MISSING public/legacy/workshop/customers.php"
fi
echo

echo "== WEBSERVER REWRITE REFERENCES =="
for f in "$WEBROOT/.htaccess" "$WEBROOT/index.php" "$DOMAIN_ROOT/public_html/.htaccess"; do
  if [[ -f "$f" ]]; then
    echo "--- $f ---"
    grep -nE 'Rewrite|erp|legacy|customers|staging|index\.php' "$f" 2>/dev/null | head -n 180 || true
  fi
done
echo

echo "== UNAUTHENTICATED HTTP HEADERS (NO COOKIE) =="
for u in   "https://staging.assammotors.com/erp/customers"   "https://staging.assammotors.com/erp/job-cards/create"   "https://staging.assammotors.com/legacy/workshop/customers.php"; do
  echo "--- $u ---"
  curl -ksS -o /dev/null -D - --max-time 12 "$u" 2>&1 |     grep -Ei '^(HTTP/|location:|content-type:|server:)' || true
done
echo

echo "DIAGNOSTIC COMPLETE — READ ONLY"
echo "No source, database, cache or config was modified."
