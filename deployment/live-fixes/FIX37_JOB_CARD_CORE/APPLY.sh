#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$DIR/CHECK.sh" "$APP_ROOT"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$(dirname "$APP_ROOT")/ASSAM_MOTORS_FIX37_BACKUP_$STAMP"
FILES=(app/Http/Controllers/Web/AdminJobCardPageController.php resources/views/job-cards/show.blade.php routes/web.php)
for f in "${FILES[@]}"; do mkdir -p "$BACKUP/$(dirname "$f")"; cp -p "$APP_ROOT/$f" "$BACKUP/$f"; done
echo "BACKUP: $BACKUP"
rollback_on_error() {
  rc=$?
  if [[ $rc -ne 0 ]]; then
    echo "FIX37 APPLY ERROR — restoring backup..."
    for f in "${FILES[@]}"; do cp -p "$BACKUP/$f" "$APP_ROOT/$f"; done
    cd "$APP_ROOT"
    php artisan route:clear >/dev/null 2>&1 || true
    php artisan view:clear >/dev/null 2>&1 || true
    echo "FIX37 AUTO-ROLLBACK COMPLETE"
  fi
  exit $rc
}
trap rollback_on_error ERR
php "$DIR/PATCH_FIX37.php" "$APP_ROOT"
cd "$APP_ROOT"
php -l app/Http/Controllers/Web/AdminJobCardPageController.php
php -l routes/web.php
php -l resources/views/job-cards/show.blade.php
php artisan route:clear >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true
bash "$DIR/VERIFY.sh" "$APP_ROOT"
trap - ERR
echo "FIX37 APPLY COMPLETE"
echo "Backup: $BACKUP"
echo "No DB migration was run."
