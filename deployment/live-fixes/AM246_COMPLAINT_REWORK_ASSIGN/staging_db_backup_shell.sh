#!/usr/bin/env bash
# AM246 Hostinger SSH backup: no PHP proc_open required.
# Produces private, integrity-checked complete SQL export before migration.
set -euo pipefail
umask 077

if [ "$#" -ne 1 ]; then
  echo "USAGE_AM246_SHELL_BACKUP STAGING_ROOT" >&2
  exit 2
fi
ROOT="$(cd -- "$1" && pwd -P)"
if [ "$(basename "$ROOT")" != "assam-erp-staging" ] || [ ! -f "$ROOT/artisan" ]; then
  echo "STOP_AM246_SHELL_BACKUP_NOT_STAGING" >&2
  exit 2
fi
PACKAGE="$(cd -- "$(dirname -- "$0")" && pwd -P)"
HELPER="$PACKAGE/staging_dump_config.php"
if [ ! -f "$HELPER" ]; then
  echo "STOP_AM246_SHELL_BACKUP_MISSING_HELPER" >&2
  exit 2
fi
if ! command -v php >/dev/null 2>&1; then
  echo "STOP_AM246_SHELL_BACKUP_PHP_UNAVAILABLE" >&2
  exit 2
fi
DUMP_BIN=""
for candidate in mariadb-dump mysqldump /usr/bin/mariadb-dump /usr/bin/mysqldump /usr/local/bin/mariadb-dump /usr/local/bin/mysqldump; do
  if command -v "$candidate" >/dev/null 2>&1; then
    DUMP_BIN="$(command -v "$candidate")"
    break
  fi
done
if [ -z "$DUMP_BIN" ]; then
  echo "STOP_AM246_SHELL_BACKUP_NO_MARIADB_DUMP_BINARY" >&2
  exit 2
fi
BASE="$ROOT/storage/app/am-backups/AM246_REWORK_SQL_SNAPSHOT"
mkdir -p -- "$BASE"
chmod 700 -- "$BASE"
TMP="$(mktemp -d "$BASE/.am246_inprogress_XXXXXXXX")"
chmod 700 -- "$TMP"
cleanup() {
  rm -f -- "$TMP/.client.cnf" "$TMP/database_name" "$TMP/expected_tables" "$TMP/dump.sql.partial" "$TMP/dump.stderr"
  rmdir -- "$TMP" 2>/dev/null || true
}
trap cleanup EXIT

php "$HELPER" "$ROOT" "$TMP"
DATABASE="$(cat "$TMP/database_name")"
EXPECTED="$(cat "$TMP/expected_tables")"
if ! [[ "$DATABASE" =~ ^[a-zA-Z0-9_]+$ ]] || ! [[ "$EXPECTED" =~ ^[0-9]+$ ]]; then
  echo "STOP_AM246_SHELL_BACKUP_PRIVATE_METADATA_INVALID" >&2
  exit 2
fi

# Do not pass DB password in arguments or stdout:
# mysql reads it from the 0600 private defaults file.
if "$DUMP_BIN" \
   --defaults-extra-file="$TMP/.client.cnf" \
   --single-transaction --quick --skip-lock-tables \
   --default-character-set=utf8mb4 --triggers \
   "$DATABASE" > "$TMP/dump.sql.partial" 2>"$TMP/dump.stderr"; then
  :
else
  status="$?"
  echo "STOP_AM246_SHELL_BACKUP_DUMP_EXIT_$status" >&2
  exit 2
fi
chmod 600 -- "$TMP/dump.sql.partial"
BYTES="$(wc -c < "$TMP/dump.sql.partial" | tr -d '[:space:]')"
FOUND="$(grep -c '^CREATE TABLE ' "$TMP/dump.sql.partial" || true)"
if ! [[ "$FOUND" =~ ^[0-9]+$ ]] || ! [[ "$BYTES" =~ ^[0-9]+$ ]] ||
   [ "$BYTES" -lt 4096 ] || [ "$FOUND" -lt "$EXPECTED" ]; then
  echo "STOP_AM246_SHELL_BACKUP_DUMP_SCHEMA_INCOMPLETE expected=$EXPECTED found=$FOUND bytes=$BYTES" >&2
  exit 2
fi
if ! command -v sha256sum >/dev/null 2>&1; then
  echo "STOP_AM246_SHELL_BACKUP_SHA256SUM_MISSING" >&2
  exit 2
fi
STAMP="$(date -u +%Y%m%d_%H%M%S)"
DEST="$BASE/staging_before_am246_$STAMP"_$$.sql
if [ -e "$DEST" ]; then
  echo "STOP_AM246_SHELL_BACKUP_FILENAME_COLLISION" >&2
  exit 2
fi
mv -- "$TMP/dump.sql.partial" "$DEST"
chmod 600 -- "$DEST"
sha256sum -- "$DEST" > "$DEST.sha256"
chmod 600 -- "$DEST.sha256"
echo "AM246_FRESH_STAGING_DB_BACKUP_EXPORT_VERIFIED"
echo "AM246_SHELL_DUMP_BASE_TABLES=$FOUND EXPECTED=$EXPECTED"
echo "AM246_BACKUP_BYTES=$BYTES"
echo "AM246_BACKUP_PRIVATE_FILE=$DEST"
echo "AM246_BACKUP_RESTORE_NOT_TESTED"
echo "AM246_BACKUP_NO_DATABASE_CHANGES"
