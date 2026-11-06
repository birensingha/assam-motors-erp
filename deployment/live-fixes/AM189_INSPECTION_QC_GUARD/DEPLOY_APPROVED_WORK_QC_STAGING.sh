#!/usr/bin/env bash
set -euo pipefail
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
REPO="$HOME/assam-motors-deploy"
PREFIX="deployment/live-fixes/AM189_INSPECTION_QC_GUARD/files"
test -f "$ROOT/artisan" && test -d "$REPO/.git" || { echo "STOP: STAGING root / deploy checkout missing"; exit 1; }
cd "$REPO"
git rev-parse --verify "FETCH_HEAD^{commit}" >/dev/null
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
paths=( app/Http/Controllers/Web/InspectionQcDefectController.php resources/views/inspection-defects/index.blade.php database/migrations/2026_11_06_170000_create_qc_conditional_reviews.php )
for rel in "${paths[@]}"; do mkdir -p "$TMP/$(dirname "$rel")"; git show "FETCH_HEAD:$PREFIX/$rel" > "$TMP/$rel"; done
grep -q "function decline(" "$ROOT/app/Http/Controllers/Web/InspectionQcDefectController.php" || { echo "STOP: customer decline feature not installed"; exit 1; }
grep -q "independent_attestation" "$ROOT/app/Http/Controllers/Web/InspectionQcDefectController.php" || exit 1
grep -q "Customer Declined Repair" "$ROOT/resources/views/inspection-defects/index.blade.php" || exit 1
grep -q "function assertQcReady" "$ROOT/app/Services/InspectionQcGuard.php" || exit 1
grep -q "Route::post('/inspection-defects/{defect}/decline'" "$ROOT/routes/web.php" || { echo "STOP: original AM189 routes missing"; exit 1; }
for rel in "${paths[@]}"; do if [[ "$rel" == *.php ]]; then php -l "$TMP/$rel"; fi; done
python3 - "$ROOT/routes/web.php" "$TMP/routes.web.php" <<'PY'
import pathlib,sys
src=pathlib.Path(sys.argv[1]).read_text()
if "erp.job-cards.inspection-defects.approved-work-qc" in src: raise SystemExit("STOP: route already installed; review before rerun")
lines=src.splitlines(keepends=True)
found=[i for i,l in enumerate(lines) if "Route::post('/inspection-defects/{defect}/reopen-declined'" in l]
if len(found)!=1: raise SystemExit("STOP: customer-decline route anchor mismatch; no writes")
new=r"    Route::post('/job-cards/{job}/inspection-defects/approved-work-qc', [\App\Http\Controllers\Web\InspectionQcDefectController::class,'approvedWorkQc'])->whereNumber('job')->name('erp.job-cards.inspection-defects.approved-work-qc');" + "\n"
lines.insert(found[0]+1,new)
pathlib.Path(sys.argv[2]).write_text("".join(lines))
PY
php -l "$TMP/routes.web.php"
BACKUP="$ROOT/storage/app/am-backups/AM_QC_APPROVED_WORK_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP"
for rel in "${paths[@]}" routes/web.php; do if [ -e "$ROOT/$rel" ]; then mkdir -p "$BACKUP/$(dirname "$rel")"; cp -p "$ROOT/$rel" "$BACKUP/$rel"; fi; done
echo "AM_QC_APPROVED_WORK_PREFLIGHT_PASS"
echo "BACKUP: $BACKUP"
for rel in "${paths[@]}"; do mkdir -p "$ROOT/$(dirname "$rel")"; cp "$TMP/$rel" "$ROOT/$rel"; done
cp "$TMP/routes.web.php" "$ROOT/routes/web.php"
cd "$ROOT"
if ! php artisan migrate --path=database/migrations/2026_11_06_170000_create_qc_conditional_reviews.php --force; then
  for rel in "${paths[@]}" routes/web.php; do if [ -f "$BACKUP/$rel" ]; then cp -p "$BACKUP/$rel" "$ROOT/$rel"; else rm -f "$ROOT/$rel"; fi; done
  echo "STOP: migration failed; files restored; schema needs inspection"; exit 1
fi
php artisan optimize:clear
php -l app/Http/Controllers/Web/InspectionQcDefectController.php
php artisan route:list --path=inspection-defects --no-ansi | grep "approved-work-qc"
echo "AM_QC_APPROVED_WORK_STAGING_CODE_INSTALLED"
echo "No existing job card/QC/delivery records modified. Full QC PASS guard unchanged."
