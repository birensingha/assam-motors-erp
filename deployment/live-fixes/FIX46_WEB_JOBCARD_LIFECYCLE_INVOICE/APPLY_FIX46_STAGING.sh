#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
PKG_DIR="$(cd "$(dirname "$0")" && pwd)"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$APP_ROOT/storage/app/fix46_backup_$STAMP"

bash "$PKG_DIR/CHECK_FIX46_STAGING.sh" "$APP_ROOT"

mkdir -p "$BACKUP/app/Http/Controllers/Web" "$BACKUP/resources/views/job-cards" "$BACKUP/resources/views/invoices" "$BACKUP/routes"
cp "$APP_ROOT/routes/web.php" "$BACKUP/routes/web.php"
cp "$APP_ROOT/resources/views/job-cards/show.blade.php" "$BACKUP/resources/views/job-cards/show.blade.php"
for f in   "$APP_ROOT/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"   "$APP_ROOT/resources/views/job-cards/lifecycle-panel.blade.php"   "$APP_ROOT/resources/views/invoices/show.blade.php"   "$APP_ROOT/resources/views/invoices/print.blade.php"
do
  if [[ -f "$f" ]]; then
    rel="${f#$APP_ROOT/}"
    mkdir -p "$BACKUP/$(dirname "$rel")"
    cp "$f" "$BACKUP/$rel"
  fi
done

rollback(){
  echo "FIX46 source rollback triggered"
  cp "$BACKUP/routes/web.php" "$APP_ROOT/routes/web.php" || true
  cp "$BACKUP/resources/views/job-cards/show.blade.php" "$APP_ROOT/resources/views/job-cards/show.blade.php" || true
  for rel in     app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php     resources/views/job-cards/lifecycle-panel.blade.php     resources/views/invoices/show.blade.php     resources/views/invoices/print.blade.php
  do
    if [[ -f "$BACKUP/$rel" ]]; then
      mkdir -p "$APP_ROOT/$(dirname "$rel")"
      cp "$BACKUP/$rel" "$APP_ROOT/$rel" || true
    else
      rm -f "$APP_ROOT/$rel"
    fi
  done
  cd "$APP_ROOT" && php artisan optimize:clear >/dev/null 2>&1 || true
}
trap rollback ERR

mkdir -p "$APP_ROOT/app/Http/Controllers/Web" "$APP_ROOT/resources/views/job-cards" "$APP_ROOT/resources/views/invoices"
cp "$PKG_DIR/payload/AdminJobCardLifecyclePageController.php" "$APP_ROOT/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"
cp "$PKG_DIR/payload/lifecycle-panel.blade.php" "$APP_ROOT/resources/views/job-cards/lifecycle-panel.blade.php"
cp "$PKG_DIR/payload/invoice-show.blade.php" "$APP_ROOT/resources/views/invoices/show.blade.php"
cp "$PKG_DIR/payload/invoice-print.blade.php" "$APP_ROOT/resources/views/invoices/print.blade.php"

php "$PKG_DIR/PATCH_FIX46.php" "$APP_ROOT"
php "$PKG_DIR/MIGRATE_FIX46.php" "$APP_ROOT"

php -l "$APP_ROOT/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"
php -l "$APP_ROOT/routes/web.php"
cd "$APP_ROOT"
php artisan optimize:clear >/dev/null
bash "$PKG_DIR/VERIFY_FIX46_STAGING.sh" "$APP_ROOT"
trap - ERR

echo "FIX46 APPLY: PASS"
echo "Backup: $BACKUP"
