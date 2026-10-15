#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/staging"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
if bash "$DIR/CHECK.sh" "$APP_ROOT";then :;else
  rc=$?
  if [ "$rc" -eq 5 ];then echo "FIX48 already installed; no action."; exit 0;fi
  exit "$rc"
fi
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$(dirname "$APP_ROOT")/ASSAM_FIX48_BACKUP_$STAMP"
mkdir -p "$BACKUP/routes" "$BACKUP/resources/views/job-cards" "$BACKUP/resources/views/payments" "$BACKUP/app/Http/Controllers/Web"
cp -p "$APP_ROOT/routes/web.php" "$BACKUP/routes/web.php"
cp -p "$APP_ROOT/resources/views/job-cards/show.blade.php" "$BACKUP/resources/views/job-cards/show.blade.php"
cp -p "$APP_ROOT/resources/views/payments/index.blade.php" "$BACKUP/resources/views/payments/index.blade.php"
touch "$BACKUP/CONTROLLER_WAS_MISSING"
rollback(){
  echo "FIX48 apply failed; restoring original source"
  cp -p "$BACKUP/routes/web.php" "$APP_ROOT/routes/web.php" || true
  cp -p "$BACKUP/resources/views/job-cards/show.blade.php" "$APP_ROOT/resources/views/job-cards/show.blade.php" || true
  cp -p "$BACKUP/resources/views/payments/index.blade.php" "$APP_ROOT/resources/views/payments/index.blade.php" || true
  if [[ -f "$BACKUP/CONTROLLER_WAS_MISSING" ]];then rm -f "$APP_ROOT/app/Http/Controllers/Web/AdminWorkshopControlPageController.php";else
   cp -p "$BACKUP/app/Http/Controllers/Web/AdminWorkshopControlPageController.php" "$APP_ROOT/app/Http/Controllers/Web/" || true;fi
  rm -f "$APP_ROOT/app/Http/Controllers/Web/AdminPaymentVoucherEditController.php" || true
  rm -f "$APP_ROOT/database/migrations/2026_10_15_220000_create_workshop_payment_edit_audits.php" || true
  rm -rf "$APP_ROOT/resources/views/workshop-controls" || true
  (cd "$APP_ROOT" && php artisan optimize:clear >/dev/null 2>&1) || true
}
trap rollback ERR
mkdir -p "$APP_ROOT/app/Http/Controllers/Web" "$APP_ROOT/resources/views/workshop-controls"
cp "$DIR/payload/app/Http/Controllers/Web/AdminWorkshopControlPageController.php" "$APP_ROOT/app/Http/Controllers/Web/"
cp "$DIR/payload/app/Http/Controllers/Web/AdminPaymentVoucherEditController.php" "$APP_ROOT/app/Http/Controllers/Web/"
cp "$DIR/payload/database/migrations/2026_10_15_220000_create_workshop_payment_edit_audits.php" "$APP_ROOT/database/migrations/"
cp "$DIR"/payload/resources/views/workshop-controls/*.blade.php "$APP_ROOT/resources/views/workshop-controls/"
php "$DIR/PATCH_FIX48.php" "$APP_ROOT"
cd "$APP_ROOT"
php artisan migrate --path=database/migrations/2026_10_15_220000_create_workshop_payment_edit_audits.php --force
bash "$DIR/VERIFY.sh" "$APP_ROOT"
trap - ERR
echo "FIX49 SOURCE INSTALL COMPLETE — Backup $BACKUP"
echo 'Audit migration only; no historical finance records auto-edited. Authenticated browser/save tests still required.'