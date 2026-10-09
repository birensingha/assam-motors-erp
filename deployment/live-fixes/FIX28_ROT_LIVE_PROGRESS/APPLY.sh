#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$DIR/CHECK.sh" "$APP_ROOT"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$(dirname "$APP_ROOT")/ASSAM_MOTORS_FIX28_BACKUP_$STAMP"
mkdir -p "$BACKUP/public/legacy/api"
cp -p "$APP_ROOT/public/legacy/api/staff-rot-v3.php" "$BACKUP/public/legacy/api/staff-rot-v3.php"
echo "BACKUP: $BACKUP"
php "$DIR/PATCH_FIX28.php" "$APP_ROOT"
php -l "$APP_ROOT/public/legacy/api/staff-rot-v3.php"
bash "$DIR/VERIFY.sh" "$APP_ROOT"
echo "FIX28 APPLY COMPLETE"
echo "Backup: $BACKUP"
