#!/usr/bin/env bash
# AM212 explicit STAGING deployment with fresh private DB export FIRST.
set -Eeuo pipefail
ROOT="${1:-}"
PKG="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ ! -f "$ROOT/artisan" || "$(basename "$ROOT")" != "assam-erp-staging" ]]; then
  echo "AM212_DEPLOY_STOP_INVALID_STAGING_ROOT"; exit 2
fi
test -f "$PKG/files/database/migrations/2026_10_30_190000_add_work_procedure_to_labour_master.php"
AM214_DIR="$(dirname "$PKG")/AM214_NATIVE_MASTER_MODAL_SELECT"
test -f "$AM214_DIR/install.py"
python3 -m py_compile "$PKG/install.py" "$PKG/approved_ui.py" "$AM214_DIR/install.py"
php -l "$PKG/staging_db_backup.php"
php -l "$PKG/staging_verify.php"
php -l "$PKG/files/database/migrations/2026_10_30_190000_add_work_procedure_to_labour_master.php"
python3 "$PKG/install.py" check "$ROOT"
echo "AM212_DEPLOY_PRECHECK_PASSED"
php "$PKG/staging_db_backup.php" "$ROOT"
echo "AM212_DEPLOY_BACKUP_GATE_PASSED"
python3 "$PKG/install.py" prepare "$ROOT"
(
  cd "$ROOT"
  php artisan migrate --path=database/migrations/2026_10_30_190000_add_work_procedure_to_labour_master.php --force
)
echo "AM212_DEPLOY_MIGRATION_EXECUTED"
python3 "$PKG/install.py" apply "$ROOT"
python3 "$PKG/approved_ui.py" check "$ROOT"
python3 "$PKG/approved_ui.py" apply "$ROOT"
php "$PKG/staging_verify.php" "$ROOT"
echo "AM212_DEPLOY_SOURCE_AND_DB_VERIFIED"
if python3 "$AM214_DIR/install.py" check "$ROOT"; then
    python3 "$AM214_DIR/install.py" apply "$ROOT"
    echo "AM214_DEPLOY_NATIVE_OLD_STYLE_MASTER_MODALS_APPLIED"
else
    echo "AM214_DEPLOY_BLOCKED_AT_PREFLIGHT_NO_MASTER_DATA_WRITE"
fi
echo "AM212_DEPLOY_COMPLETED_BROWSER_SAVE_REFRESH_STILL_REQUIRED"
