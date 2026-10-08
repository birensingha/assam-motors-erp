#!/usr/bin/env bash
set -u

# Assam Motors live-source discovery helper.
# READ ONLY. Does not change source, caches, DB, or permissions.

APP_ROOT="${1:-}"

if [[ -z "$APP_ROOT" || ! -d "$APP_ROOT" ]]; then
  echo "Usage: $0 /path/to/staging/app"
  exit 2
fi

cd "$APP_ROOT" || exit 2

OUT="${2:-/tmp/assam-motors-source-map}"
mkdir -p "$OUT"

echo "Writing read-only source discovery to: $OUT"

if [[ -f artisan ]] && command -v php >/dev/null 2>&1; then
  php artisan route:list > "$OUT/routes.txt" 2>&1 || true
else
  echo "artisan unavailable" > "$OUT/routes.txt"
fi

for root in routes app resources/views public legacy config database; do
  if [[ -e "$root" ]]; then
    find "$root" -type f 2>/dev/null | sort > "$OUT/files-${root//\//-}.txt"
  fi
done

PATTERN='customer|vendor|part|labour|vehicle|payment|voucher|job.?card|invoice|estimate|purchase|rot|reminder|alert|staff|location|booking|application'

grep -RniE "$PATTERN" routes app resources/views public legacy config database 2>/dev/null   > "$OUT/keyword-hits.txt" || true

if [[ -f composer.json ]]; then
  cp composer.json "$OUT/composer.json"
fi

if [[ -f package.json ]]; then
  cp package.json "$OUT/package.json"
fi

echo
echo "Useful targeted reports:"

grep -Ei 'customer|vendor|part|labour|vehicle' "$OUT/routes.txt"   > "$OUT/routes-masters.txt" || true

grep -Ei 'payment|voucher' "$OUT/routes.txt"   > "$OUT/routes-payment.txt" || true

grep -Ei 'job.?card|invoice|estimate|purchase' "$OUT/routes.txt"   > "$OUT/routes-workshop.txt" || true

grep -Ei 'rot|reminder|alert|staff' "$OUT/routes.txt"   > "$OUT/routes-staff.txt" || true

echo "Discovery complete."
echo "No source files were modified."
