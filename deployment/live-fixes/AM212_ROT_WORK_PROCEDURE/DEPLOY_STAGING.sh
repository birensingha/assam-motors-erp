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
AM216_DIR="$(dirname "$PKG")/AM216_PARTS_VEHICLE_FIELDS"
test -f "$AM214_DIR/install.py"
test -f "$AM216_DIR/install.py"
python3 -m py_compile "$PKG/install.py" "$PKG/approved_ui.py" "$AM214_DIR/install.py" "$AM216_DIR/install.py"
php -l "$PKG/staging_dump_settings.php"
bash -n "$PKG/staging_shell_backup.sh"
php -l "$PKG/staging_verify.php"
php -l "$PKG/files/database/migrations/2026_10_30_190000_add_work_procedure_to_labour_master.php"
python3 "$PKG/install.py" check "$ROOT"
echo "AM212_DEPLOY_PRECHECK_PASSED"
BACKUP_DIR="$ROOT/storage/app/am-backups/AM212_ROT_WORK_PROCEDURE"
LATEST_CHECKSUM="$(find "$BACKUP_DIR" -maxdepth 1 -name 'staging_before_AM212_*.sql.sha256' -type f -mtime -1 2>/dev/null | sort | tail -1)"
if [[ -n "$LATEST_CHECKSUM" ]] &&
   (cd "$BACKUP_DIR" && sha256sum -c "$(basename "$LATEST_CHECKSUM")" >/dev/null) &&
   php "$PKG/staging_verify.php" "$ROOT" >/dev/null; then
    echo "AM212_EXISTING_BACKUP_AND_INSTALLED_UI_VERIFIED_NO_REPEAT_MIGRATION"
else
    bash "$PKG/staging_shell_backup.sh" "$ROOT"
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
fi
python3 "$AM216_DIR/install.py" check "$ROOT"
python3 "$AM216_DIR/install.py" apply "$ROOT"
echo "AM216_DEPLOY_PARTS_MAKE_MODEL_FIELDS_READY"
python3 "$AM214_DIR/install.py" check "$ROOT"
python3 "$AM214_DIR/install.py" apply "$ROOT"
echo "AM214_DEPLOY_NATIVE_OLD_STYLE_MASTER_MODALS_APPLIED"
echo "AM212_DEPLOY_COMPLETED_BROWSER_SAVE_REFRESH_STILL_REQUIRED"
