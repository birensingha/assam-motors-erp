#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
BASE_URL="${2:-https://staging.assammotors.com}"
HTACCESS="$APP_ROOT/public/.htaccess"

test -f "$APP_ROOT/artisan" || { echo "ERROR: invalid Laravel APP_ROOT"; exit 2; }
test -f "$HTACCESS" || { echo "ERROR: public/.htaccess missing"; exit 2; }

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$APP_ROOT/storage/app/fix41-backups/$STAMP"
mkdir -p "$BACKUP"
cp -a "$HTACCESS" "$BACKUP/.htaccess"
echo "BACKUP_CREATED=yes"

if ! grep -q 'FIX41_LEGACY_CUSTOMER_WEBROOT_REDIRECT' "$HTACCESS"; then
  TMP="$(mktemp)"
  cat > "$TMP" <<'RULES'
# FIX41_LEGACY_CUSTOMER_WEBROOT_REDIRECT
# Direct browser navigation belongs to Laravel Customer Master.
# Preserve legacy API calls carrying ?action=...
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{QUERY_STRING} !(^|&)action= [NC]
    RewriteRule ^legacy/workshop/customers\.php$ /erp/customers [R=302,L,NE]
</IfModule>

RULES
  cat "$HTACCESS" >> "$TMP"
  cp "$TMP" "$HTACCESS"
  rm -f "$TMP"
  echo "PATCH_APPLIED=yes"
else
  echo "PATCH_APPLIED=already_present"
fi

cd "$APP_ROOT"
php artisan optimize:clear >/dev/null 2>&1 || true

HEADERS="$(mktemp)"
BODY="$(mktemp)"
cleanup(){ rm -f "$HEADERS" "$BODY"; }
trap cleanup EXIT

CODE="$(curl -sS --max-redirs 0 -D "$HEADERS" -o "$BODY" -w '%{http_code}' "$BASE_URL/legacy/workshop/customers.php" || true)"
LOCATION="$(awk 'BEGIN{IGNORECASE=1} /^Location:/{gsub(/\r/,""); sub(/^Location:[[:space:]]*/,""); print; exit}' "$HEADERS")"

echo "DIRECT_HTTP_CODE=$CODE"
echo "DIRECT_LOCATION=${LOCATION:-none}"

if [ "$CODE" != "302" ]; then
  cp "$BACKUP/.htaccess" "$HTACCESS"
  echo "ROLLBACK=yes"
  echo "ERROR: direct legacy customer URL did not return 302"
  exit 1
fi

case "$LOCATION" in
  /erp/customers|"$BASE_URL/erp/customers")
    ;;
  *)
    cp "$BACKUP/.htaccess" "$HTACCESS"
    echo "ROLLBACK=yes"
    echo "ERROR: unexpected redirect target"
    exit 1
    ;;
esac

# API preservation smoke: only verify that action requests are not redirected by FIX41.
API_HEADERS="$(mktemp)"
trap 'cleanup; rm -f "$API_HEADERS"' EXIT
API_CODE="$(curl -sS --max-redirs 0 -D "$API_HEADERS" -o /dev/null -w '%{http_code}' "$BASE_URL/legacy/workshop/customers.php?action=__fix41_probe__" || true)"
API_LOCATION="$(awk 'BEGIN{IGNORECASE=1} /^Location:/{gsub(/\r/,""); sub(/^Location:[[:space:]]*/,""); print; exit}' "$API_HEADERS")"
rm -f "$API_HEADERS"

echo "ACTION_HTTP_CODE=$API_CODE"
echo "ACTION_LOCATION=${API_LOCATION:-none}"

if [ "$API_LOCATION" = "/erp/customers" ] || [ "$API_LOCATION" = "$BASE_URL/erp/customers" ]; then
  cp "$BACKUP/.htaccess" "$HTACCESS"
  echo "ROLLBACK=yes"
  echo "ERROR: action API was incorrectly redirected"
  exit 1
fi

echo "VERIFY_PASS=yes"
echo "DB_MIGRATION=none"
