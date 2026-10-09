#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$DIR/CHECK.sh" "$APP_ROOT"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$(dirname "$APP_ROOT")/ASSAM_MOTORS_FIX29_BACKUP_$STAMP"
mkdir -p "$BACKUP/resources/views/job-cards"
cp -p "$APP_ROOT/resources/views/job-cards/show.blade.php" "$BACKUP/resources/views/job-cards/show.blade.php"
echo "BACKUP: $BACKUP"
php "$DIR/PATCH_FIX29.php" "$APP_ROOT"
cd "$APP_ROOT"
php artisan view:clear >/dev/null 2>&1 || true
bash "$DIR/VERIFY.sh" "$APP_ROOT"
echo "FIX29 APPLY COMPLETE"
echo "Backup: $BACKUP"
