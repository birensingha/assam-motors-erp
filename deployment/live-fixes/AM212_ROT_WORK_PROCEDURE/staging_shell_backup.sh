#!/usr/bin/env bash
# STAGING-only private MariaDB table data+schema backup.
# Uses SSH shell mysqldump because Hostinger PHP proc_open is disabled.
set -Eeuo pipefail
umask 077
ROOT="${1:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ ! -f "$ROOT/artisan" || "$(basename "$ROOT")" != "assam-erp-staging" ]]; then
  echo "AM212_BACKUP_STOP_NOT_STAGING"; exit 2
fi
DIR="$ROOT/storage/app/am-backups/AM212_ROT_WORK_PROCEDURE"
mkdir -p "$DIR"
chmod 700 "$DIR"
if command -v mariadb-dump >/dev/null 2>&1; then DUMPER="$(command -v mariadb-dump)"
elif command -v mysqldump >/dev/null 2>&1; then DUMPER="$(command -v mysqldump)"
else echo "AM212_BACKUP_STOP_MYSQLDUMP_NOT_AVAILABLE"; exit 2; fi
BASE="$DIR/staging_before_AM212_$(date -u +%Y%m%d_%H%M%S)_$$"
CONFIG="$BASE.cnf"
PARTIAL="$BASE.sql.partial"
SQL="$BASE.sql"
CHECKSUM="$SQL.sha256"
cleanup(){
  rm -f "$CONFIG"
  if [[ -f "$PARTIAL" ]]; then rm -f "$PARTIAL"; fi
}
trap cleanup EXIT INT TERM
test ! -e "$CONFIG"
test ! -e "$PARTIAL"
settings="$(php "$SCRIPT_DIR/staging_dump_settings.php" "$ROOT" "$CONFIG")"
TABLE_COUNT="$(printf '%s\n' "$settings" | sed -n 's/^AM212_BACKUP_TABLE_COUNT=//p')"
DB_NAME="$(printf '%s\n' "$settings" | sed -n 's/^AM212_BACKUP_DB_NAME=//p')"
if [[ -z "$TABLE_COUNT" || -z "$DB_NAME" || ! "$TABLE_COUNT" =~ ^[0-9]+$ ]]; then
  echo "AM212_BACKUP_STOP_INVALID_METADATA"; exit 2
fi
test -f "$CONFIG"
test "$(stat -c %a "$CONFIG")" = "600"
echo "AM212_BACKUP_SHELL_DUMP_STARTED"
if ! "$DUMPER" --defaults-extra-file="$CONFIG" \
  --single-transaction --quick --skip-lock-tables --hex-blob \
  --default-character-set=utf8mb4 "$DB_NAME" > "$PARTIAL" 2>"$BASE.stderr"; then
  rm -f "$BASE.stderr"
  echo "AM212_BACKUP_STOP_SQL_DUMP_COMMAND_FAILED"
  exit 2
fi
rm -f "$BASE.stderr"
if [[ ! -s "$PARTIAL" ]] || (( $(wc -c < "$PARTIAL") < 4096 )); then
  echo "AM212_BACKUP_STOP_SQL_DUMP_TOO_SMALL"; exit 2
fi
COUNT="$(grep -c '^CREATE TABLE ' "$PARTIAL" || true)"
if (( COUNT < TABLE_COUNT )); then
  echo "AM212_BACKUP_STOP_SCHEMA_TABLE_COUNT_MISMATCH"
  exit 2
fi
if ! grep -Eq -- 'Dump completed|dump completed|MariaDB dump' "$PARTIAL"; then
  echo "AM212_BACKUP_STOP_SQL_DUMP_FOOTER_MISSING"; exit 2
fi
mv "$PARTIAL" "$SQL"
chmod 600 "$SQL"
(
  cd "$DIR"
  sha256sum "$(basename "$SQL")" > "$(basename "$CHECKSUM")"
  chmod 600 "$(basename "$CHECKSUM")"
  sha256sum -c "$(basename "$CHECKSUM")" >/dev/null
)
echo "AM212_FRESH_STAGING_DB_BACKUP_EXPORT_VERIFIED"
echo "AM212_BACKUP_TABLES $COUNT"
echo "AM212_BACKUP_BYTES $(wc -c < "$SQL")"
echo "AM212_BACKUP_PRIVATE_FILE $SQL"
echo "AM212_BACKUP_RESTORE_NOT_TESTED"
echo "AM212_BACKUP_NO_DB_CHANGES"
