#!/usr/bin/env bash
set -euo pipefail

# Assam Motors live staging handoff collector.
# READ ONLY against the application and DB.
# It does not copy .env or application source code.

APP_ROOT="${1:-}"
OUT_BASE="${2:-/tmp}"
RUN_DB_SCHEMA="${RUN_DB_SCHEMA:-0}"

if [[ -z "$APP_ROOT" || ! -d "$APP_ROOT" ]]; then
  echo "Usage: RUN_DB_SCHEMA=1 $0 /path/to/staging/app [/output/base]"
  exit 2
fi

if [[ ! -f "$APP_ROOT/artisan" ]]; then
  echo "ERROR: artisan not found under APP_ROOT: $APP_ROOT"
  exit 2
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAMP="$(date +%Y%m%d_%H%M%S)"
OUT="$OUT_BASE/assam-motors-live-handoff-$STAMP"
ARCHIVE="$OUT_BASE/assam-motors-live-handoff-$STAMP.tar.gz"

mkdir -p "$OUT"

say(){ printf '%s\n' "$*"; }

say "Assam Motors live staging handoff"
say "APP_ROOT: $APP_ROOT"
say "OUTPUT:   $OUT"
say "MODE:     read-only"

{
  echo "collected_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "app_root_basename=$(basename "$APP_ROOT")"
  echo "db_schema_requested=$RUN_DB_SCHEMA"
} > "$OUT/collection-meta.txt"

(
  cd "$APP_ROOT"

  {
    echo "PHP:"
    php -v 2>/dev/null | head -3 || true
    echo
    echo "Artisan:"
    php artisan --version 2>/dev/null || true
    echo
    echo "Composer:"
    composer --version 2>/dev/null || true
    echo
    echo "Node:"
    node --version 2>/dev/null || true
    echo
    echo "NPM:"
    npm --version 2>/dev/null || true
  } > "$OUT/runtime.txt"

  if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    {
      echo "branch=$(git branch --show-current 2>/dev/null || true)"
      echo "commit=$(git rev-parse HEAD 2>/dev/null || true)"
      echo "status:"
      git status --short 2>/dev/null || true
    } > "$OUT/git-state.txt"
  else
    echo "not-a-git-worktree" > "$OUT/git-state.txt"
  fi

  php artisan route:list > "$OUT/routes.txt" 2>&1 || true

  grep -Ei 'customer|vendor|part|labour|labor|vehicle' "$OUT/routes.txt"     > "$OUT/routes-masters.txt" || true
  grep -Ei 'payment|voucher|account' "$OUT/routes.txt"     > "$OUT/routes-payment.txt" || true
  grep -Ei 'job.?card|invoice|estimate|purchase|workshop' "$OUT/routes.txt"     > "$OUT/routes-workshop.txt" || true
  grep -Ei 'rot|reminder|alert|staff|attendance|location' "$OUT/routes.txt"     > "$OUT/routes-staff.txt" || true

  {
    for root in routes app resources/views public legacy config database; do
      if [[ -e "$root" ]]; then
        find "$root" -type f           ! -name '.env'           ! -name '.env.*'           ! -path '*/vendor/*'           ! -path '*/node_modules/*'           ! -path '*/storage/logs/*'           2>/dev/null
      fi
    done
  } | sort -u > "$OUT/source-files.txt"

  PATTERN='customer|vendor|part|labour|labor|vehicle|payment|voucher|job.?card|invoice|estimate|purchase|rot|reminder|alert|staff|attendance|location|booking|application|product|user'

  # File paths only: no matched source lines are collected.
  while IFS= read -r root; do
    [[ -e "$root" ]] || continue
    grep -RIlE "$PATTERN" "$root" \
      --exclude='.env' \
      --exclude='.env.*' \
      --exclude-dir=vendor \
      --exclude-dir=node_modules \
      --exclude-dir=storage \
      2>/dev/null || true
  done > "$OUT/keyword-files.txt" <<'ROOTS'
routes
app
resources/views
public
legacy
config
database
ROOTS

  if [[ -f composer.json ]]; then
    cp composer.json "$OUT/composer.json"
  fi

  if command -v composer >/dev/null 2>&1 && [[ -f composer.json ]]; then
    composer show --direct --no-ansi > "$OUT/composer-direct.txt" 2>&1 || true
  fi

  if [[ -f package.json ]]; then
    cp package.json "$OUT/package.json"
  fi

  {
    for p in storage bootstrap/cache; do
      if [[ -e "$p" ]]; then
        if stat --version >/dev/null 2>&1; then
          stat -c '%A %U:%G %n' "$p" 2>/dev/null || true
        else
          ls -ld "$p" 2>/dev/null || true
        fi
      fi
    done
  } > "$OUT/permissions.txt"

  if php artisan about --help >/dev/null 2>&1; then
    php artisan about > "$OUT/artisan-about.txt" 2>&1 || true
  fi
)

if [[ "$RUN_DB_SCHEMA" == "1" ]]; then
  if [[ ! -f "$SCRIPT_DIR/db-schema-probe.php" ]]; then
    echo "ERROR: $SCRIPT_DIR/db-schema-probe.php not found"
    exit 2
  fi

  php "$SCRIPT_DIR/db-schema-probe.php" "$APP_ROOT" > "$OUT/db-schema.json"
fi

# Refuse to package obvious secret material if it somehow entered collected text.
SECRET_HITS="$OUT/secret-scan.txt"
: > "$SECRET_HITS"

grep -RInE -- \
  '-----BEGIN [A-Z ]*PRIVATE KEY-----|APP_KEY=base64:|DB_PASSWORD=|AIza[0-9A-Za-z_-]{30,}|"private_key"[[:space:]]*:' \
  "$OUT" \
  --exclude='secret-scan.txt' \
  > "$SECRET_HITS" 2>/dev/null || true

if [[ -s "$SECRET_HITS" ]]; then
  echo
  echo "REFUSING TO CREATE ARCHIVE: possible secret material detected."
  echo "Inspect: $SECRET_HITS"
  echo "Output directory retained for local inspection: $OUT"
  exit 3
fi

rm -f "$SECRET_HITS"

tar -C "$OUT_BASE" -czf "$ARCHIVE" "$(basename "$OUT")"
sha256sum "$ARCHIVE" > "$ARCHIVE.sha256" 2>/dev/null || true

echo
echo "HANDOFF READY"
echo "Archive: $ARCHIVE"
if [[ -f "$ARCHIVE.sha256" ]]; then
  echo "SHA256:  $ARCHIVE.sha256"
fi
echo
echo "Upload only the .tar.gz archive to continue source mapping."
