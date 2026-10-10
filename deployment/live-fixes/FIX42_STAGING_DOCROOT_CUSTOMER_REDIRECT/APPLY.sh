#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
DOCROOT="$HOME/domains/assammotors.com/public_html/staging"
HTACCESS="$DOCROOT/.htaccess"
BASE_URL="https://staging.assammotors.com"

HERE="$(cd "$(dirname "$0")" && pwd)"
bash "$HERE/CHECK.sh" "$APP_ROOT"

if grep -q 'FIX42_STAGING_DOCROOT_CUSTOMER_REDIRECT' "$HTACCESS"; then
  echo "FIX42 already installed"
  bash "$HERE/VERIFY.sh" "$APP_ROOT"
  exit 0
fi

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$HOME/domains/assammotors.com/ASSAM_MOTORS_FIX42_BACKUP_$STAMP"
mkdir -p "$BACKUP"
cp -a "$HTACCESS" "$BACKUP/staging.htaccess"
echo "BACKUP: $BACKUP"

TMP="$(mktemp)"
trap 'rm -f "$TMP"' EXIT
cat > "$TMP" <<'RULES'
# FIX42_STAGING_DOCROOT_CUSTOMER_REDIRECT
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

cd "$APP_ROOT"
php artisan optimize:clear >/dev/null 2>&1 || true

if ! bash "$HERE/VERIFY.sh" "$APP_ROOT"; then
  echo "VERIFY failed — rolling back .htaccess"
  cp "$BACKUP/staging.htaccess" "$HTACCESS"
  exit 1
fi

echo "FIX42 APPLY COMPLETE"
echo "Backup: $BACKUP"
echo "No DB migration was run."
