#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash INSPECT_EXISTING.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_ROOT"

echo "ASSAM MOTORS FIX36B — EXISTING LEGACY INSPECTION API INSPECT (READ ONLY)"
for name in staff-inspections.php staff-inspection.php; do
  live="public/legacy/api/$name"
  pkg="$DIR/files/public/legacy/api/$name"
  echo
  echo "== $name =="
  if [[ ! -f "$live" ]]; then
    echo "LIVE: MISSING"
    continue
  fi
  php -l "$live" || true
  echo "LIVE SHA256:    $(sha256sum "$live" | awk '{print $1}')"
  echo "PACKAGE SHA256: $(sha256sum "$pkg" | awk '{print $1}')"
  if cmp -s "$live" "$pkg"; then
    echo "RESULT: EXACT SAME"
  else
    echo "RESULT: DIFFERENT"
    echo "-- LIVE KEY MARKERS --"
    grep -nEi 'technician_id|inspection_status|inspection_submitted_by|job_card_inspection|staffId|action|POST|GET' "$live" | head -n 120 || true
    echo "-- PACKAGE KEY MARKERS --"
    grep -nEi 'technician_id|inspection_status|inspection_submitted_by|job_card_inspection|staffId|action|POST|GET' "$pkg" | head -n 120 || true
  fi
done
echo
echo "INSPECT COMPLETE — NO SOURCE OR DB CHANGES"
