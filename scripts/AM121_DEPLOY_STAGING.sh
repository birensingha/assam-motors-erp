#!/usr/bin/env bash
# AM121 / ASSAM MOTORS STAGING ONLY. Read-only preflight precedes source writes.
# This does NOT void any ROT itself. Actual void requires explicit Admin form.
set -Eeuo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
cd "$ROOT"
test -f artisan
BASE="https://raw.githubusercontent.com/birensingha/assam-motors-erp/feature/am121-completed-rot-void-20261024/deployment/live-fixes/AM121_COMPLETED_ROT_VOID"
DEST=/tmp/assam-am121
mkdir -p "$DEST"
for f in preflight.php deploy.py AdminCompletedRotVoidController.php completed-rot-void.blade.php; do
  curl --connect-timeout 15 --max-time 50 -fsSL "$BASE/$f" -o "$DEST/$f"
  test -s "$DEST/$f"
done
php -l "$DEST/preflight.php"
php -l "$DEST/AdminCompletedRotVoidController.php"
python3 -m py_compile "$DEST/deploy.py"
echo AM121_READONLY_PREFLIGHT
php "$DEST/preflight.php" | tee /tmp/am121_preflight.log
grep -Fxq "AM121_PREFLIGHT_RESULT=READY_FOR_STAGING_CODE_REVIEW" /tmp/am121_preflight.log || {
  echo "AM121_SAFE_STOP: schema/source mismatch; nothing installed"; exit 2;
}
python3 "$DEST/deploy.py" check "$PWD"
echo AM121_DEPLOYING_TO_STAGING_ONLY
python3 "$DEST/deploy.py" apply "$PWD"
echo "AM121_CLEAR_STAGING_ROUTE_CACHE"
php artisan route:clear
echo "AM121_VERIFY_ROUTE"
php artisan route:list --path=job-cards | grep 'completed-void' || {
  echo "AM121_ROUTE_NOT_VISIBLE: check route cache and staging log; do not use Void until verified"; exit 3;
}
echo "AM121_INSTALL_FINISHED_NOT_A_BROWSER_TEST"
echo "NEXT: WIP / uninvoiced completed ROT in Main Job Card, test form; do not void customer data without verifying expected totals first."
