#!/usr/bin/env bash
set -euo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
REPO="$HOME/assam-motors-deploy"
PREFIX="deployment/live-fixes/AM189_INSPECTION_QC_GUARD/files"
test -f "$ROOT/artisan" && test -d "$REPO/.git" || { echo "STOP: staging root / checkout unavailable"; exit 1; }
cd "$REPO"
git rev-parse --verify "FETCH_HEAD^{commit}" >/dev/null
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
paths=( app/Services/InspectionQcGuard.php app/Http/Controllers/Web/InspectionQcDefectController.php resources/views/inspection-defects/index.blade.php database/migrations/2026_11_05_173000_add_customer_decline_to_inspection_defects.php )
for rel in "${paths[@]}"; do mkdir -p "$TMP/$(dirname "$rel")"; git show "FETCH_HEAD:$PREFIX/$rel" > "$TMP/$rel"; done
grep -q "reviewerStaffId" "$ROOT/app/Services/InspectionQcGuard.php" || { echo "STOP: independent reviewer fix not present"; exit 1; }
grep -q "Independent Reviewer" "$ROOT/resources/views/inspection-defects/index.blade.php" || { echo "STOP: reviewer UI not present"; exit 1; }
grep -q "function verify(" "$ROOT/app/Http/Controllers/Web/InspectionQcDefectController.php" || exit 1
for rel in "${paths[@]}"; do if [[ "$rel" == *.php ]]; then php -l "$TMP/$rel"; fi; done
python3 - "$ROOT/routes/web.php" "$TMP/routes.web.php" <<'PY'
import pathlib,sys
src=pathlib.Path(sys.argv[1]).read_text()
marker="erp.job-cards.inspection-defects.decline"
if marker in src: raise SystemExit("STOP: customer decline routes already present; review existing installation")
lines=src.splitlines(keepends=True)
find=[i for i,l in enumerate(lines) if "Route::post('/inspection-defects/{defect}/verify'" in l]
if len(find)!=1: raise SystemExit("STOP: expected single AM189 verify route; no writes")
new=[r"    Route::post('/inspection-defects/{defect}/decline', [\App\Http\Controllers\Web\InspectionQcDefectController::class,'decline'])->whereNumber('defect')->name('erp.job-cards.inspection-defects.decline');" + "\n",r"    Route::post('/inspection-defects/{defect}/reopen-declined', [\App\Http\Controllers\Web\InspectionQcDefectController::class,'reopenDeclined'])->whereNumber('defect')->name('erp.job-cards.inspection-defects.reopen-declined');" + "\n"]
lines[find[0]+1:find[0]+1]=new
pathlib.Path(sys.argv[2]).write_text("".join(lines))
PY
php -l "$TMP/routes.web.php"
BACKUP="$ROOT/storage/app/am-backups/AM_QC_CUSTOMER_DECLINE_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP"
for rel in "${paths[@]}" routes/web.php; do if [ -e "$ROOT/$rel" ]; then mkdir -p "$BACKUP/$(dirname "$rel")"; cp -p "$ROOT/$rel" "$BACKUP/$rel"; fi; done
echo "AM_QC_DECLINE_PREFLIGHT_PASS"
echo "BACKUP: $BACKUP"
for rel in "${paths[@]}"; do mkdir -p "$ROOT/$(dirname "$rel")"; cp "$TMP/$rel" "$ROOT/$rel"; done
cp "$TMP/routes.web.php" "$ROOT/routes/web.php"
cd "$ROOT"
if ! php artisan migrate --path=database/migrations/2026_11_05_173000_add_customer_decline_to_inspection_defects.php --force; then
  for rel in "${paths[@]}" routes/web.php; do if [ -f "$BACKUP/$rel" ]; then cp -p "$BACKUP/$rel" "$ROOT/$rel"; else rm -f "$ROOT/$rel"; fi; done
  echo "STOP: migration failed; application files restored (inspect schema before retry)"; exit 1
fi
php artisan optimize:clear
php artisan route:list --path=inspection-defects --no-ansi | grep -E "decline|reopen-declined"
php -l app/Services/InspectionQcGuard.php
php -l app/Http/Controllers/Web/InspectionQcDefectController.php
echo "AM_QC_CUSTOMER_DECLINE_STAGING_INSTALLED"
echo "NO EXISTING DEFECT / QC STATUS CHANGED"
echo "DECLINED IS NOT VERIFIED. NO FULL QC PASS BYPASS."
