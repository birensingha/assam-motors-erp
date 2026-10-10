#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
HERE="$(cd "$(dirname "$0")" && pwd)"
PAYLOAD="$HERE/payload"
DOCROOT="$HOME/domains/assammotors.com/public_html/staging"
API_DIR="$DOCROOT/legacy/api"
DIST_DIR="$DOCROOT/staff-app"
PRIVATE_DIR="$DIST_DIR/private"
APK="Assam-Motors-Staff-v6.0.21-FRESH-PRODUCTION.apk"

bash "$HERE/CHECK.sh" "$APP_ROOT"

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$HOME/domains/assammotors.com/ASSAM_MOTORS_FIX43_BACKUP_$STAMP"
mkdir -p "$BACKUP"

for f in staff-app-update.php staff-app-download.php; do
  if [ -f "$API_DIR/$f" ]; then
    cp -a "$API_DIR/$f" "$BACKUP/$f"
  fi
done
if [ -d "$DIST_DIR" ]; then
  cp -a "$DIST_DIR" "$BACKUP/staff-app"
fi

echo "BACKUP: $BACKUP"

mkdir -p "$PRIVATE_DIR"
cp "$PAYLOAD/staff-app-update.php" "$API_DIR/staff-app-update.php"
cp "$PAYLOAD/staff-app-download.php" "$API_DIR/staff-app-download.php"
cp "$PAYLOAD/latest.json" "$DIST_DIR/latest.json"
cp "$PAYLOAD/private.htaccess" "$PRIVATE_DIR/.htaccess"
cp "$PAYLOAD/$APK" "$PRIVATE_DIR/$APK"
chmod 0644 "$API_DIR/staff-app-update.php" "$API_DIR/staff-app-download.php" "$DIST_DIR/latest.json" "$PRIVATE_DIR/.htaccess" "$PRIVATE_DIR/$APK"

cd "$APP_ROOT"
php artisan optimize:clear >/dev/null 2>&1 || true

bash "$HERE/VERIFY.sh" "$APP_ROOT"

echo "FIX43 APPLY COMPLETE"
echo "Backup: $BACKUP"
echo "No DB migration was run."
