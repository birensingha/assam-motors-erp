#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; BACKUP="${2:-}"; [[ -n "$APP_ROOT" && -d "$BACKUP" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/backup"; exit 2; }
FILES=(app/Http/Controllers/Web/AdminPurchasePageController.php app/Services/PurchaseService.php resources/views/purchases/create.blade.php public/js/purchase-entry.js)
for f in "${FILES[@]}"; do cp -p "$BACKUP/$f" "$APP_ROOT/$f"; done
cd "$APP_ROOT"; php artisan view:clear >/dev/null 2>&1 || true
echo "FIX26 ROLLBACK COMPLETE"
