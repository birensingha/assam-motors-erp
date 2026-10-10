#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
DOCROOT="$HOME/domains/assammotors.com/public_html/staging"
HTACCESS="$DOCROOT/.htaccess"

echo "ASSAM MOTORS FIX42 — READ ONLY CHECK"
test -n "$APP_ROOT" && test -f "$APP_ROOT/artisan" || { echo "NO-GO: invalid Laravel app root"; exit 1; }
test -d "$DOCROOT" || { echo "NO-GO: staging docroot missing"; exit 1; }
test -f "$HTACCESS" || { echo "NO-GO: staging .htaccess missing"; exit 1; }

echo "PASS Laravel app root"
echo "PASS staging docroot"
echo "PASS staging .htaccess"
if grep -q 'FIX42_STAGING_DOCROOT_CUSTOMER_REDIRECT' "$HTACCESS"; then
  echo "FIX42 already installed"
else
  echo "GO: FIX42 can be applied"
fi
