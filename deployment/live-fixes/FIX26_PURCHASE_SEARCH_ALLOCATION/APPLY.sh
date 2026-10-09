#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"; bash "$DIR/CHECK.sh" "$APP_ROOT"
STAMP="$(date +%Y%m%d_%H%M%S)"; BACKUP="$(dirname "$APP_ROOT")/ASSAM_MOTORS_FIX26_BACKUP_$STAMP"; mkdir -p "$BACKUP"
cd "$APP_ROOT"
FILES=(app/Http/Controllers/Web/AdminPurchasePageController.php app/Services/PurchaseService.php resources/views/purchases/create.blade.php public/js/purchase-entry.js)
for f in "${FILES[@]}"; do mkdir -p "$BACKUP/$(dirname "$f")"; cp -p "$f" "$BACKUP/$f"; done
echo "BACKUP: $BACKUP"
php "$DIR/PATCH_FIX26.php" "$APP_ROOT"
php -l app/Http/Controllers/Web/AdminPurchasePageController.php
php -l app/Services/PurchaseService.php
if command -v node >/dev/null 2>&1; then node --check public/js/purchase-entry.js; fi
php artisan view:clear >/dev/null 2>&1 || true
bash "$DIR/VERIFY.sh" "$APP_ROOT"
echo "FIX26 APPLY COMPLETE"
echo "Backup: $BACKUP"
