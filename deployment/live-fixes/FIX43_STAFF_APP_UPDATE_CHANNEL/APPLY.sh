#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
HERE="$(cd "$(dirname "$0")" && pwd)"
PAYLOAD="$HERE/payload"
DOCROOT="$HOME/domains/assammotors.com/public_html/staging"
API_DIR="$DOCROOT/legacy/api"
DIST_DIR="$DOCROOT/staff-app"
APK="Assam-Motors-Staff-v6.0.21-FRESH-PRODUCTION.apk"

bash "$HERE/CHECK.sh" "$APP_ROOT"

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$HOME/domains/assammotors.com/ASSAM_MOTORS_FIX43_BACKUP_$STAMP"
mkdir -p "$BACKUP"

if [ -f "$API_DIR/staff-app-update.php" ]; then
  cp -a "$API_DIR/staff-app-update.php" "$BACKUP/staff-app-update.php"
fi
if [ -d "$DIST_DIR" ]; then
  cp -a "$DIST_DIR" "$BACKUP/staff-app"
fi

echo "BACKUP: $BACKUP"

mkdir -p "$DIST_DIR"
cp "$PAYLOAD/staff-app-update.php" "$API_DIR/staff-app-update.php"
cp "$PAYLOAD/latest.json" "$DIST_DIR/latest.json"
cp "$PAYLOAD/$APK" "$DIST_DIR/$APK"
chmod 0644 "$API_DIR/staff-app-update.php" "$DIST_DIR/latest.json" "$DIST_DIR/$APK"

cd "$APP_ROOT"
php artisan optimize:clear >/dev/null 2>&1 || true

bash "$HERE/VERIFY.sh" "$APP_ROOT"

echo "FIX43 APPLY COMPLETE"
echo "Backup: $BACKUP"
echo "No DB migration was run."
