#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
DOCROOT="$HOME/domains/assammotors.com/public_html/staging"
PAYLOAD="$(cd "$(dirname "$0")/payload" && pwd)"

echo "ASSAM MOTORS FIX43 — STAFF APP UPDATE CHANNEL CHECK"

test -n "$APP_ROOT" && test -f "$APP_ROOT/artisan" || { echo "NO-GO: invalid Laravel app root"; exit 1; }
test -d "$DOCROOT/legacy/api" || { echo "NO-GO: staging legacy API directory missing"; exit 1; }
test -f "$DOCROOT/legacy/api/config.php" || { echo "NO-GO: legacy API config.php missing"; exit 1; }

for f in staff-app-update.php staff-app-download.php latest.json private.htaccess Assam-Motors-Staff-v6.0.21-FRESH-PRODUCTION.apk; do
  test -f "$PAYLOAD/$f" || { echo "NO-GO: payload missing $f"; exit 1; }
done

php -l "$PAYLOAD/staff-app-update.php" >/dev/null
php -l "$PAYLOAD/staff-app-download.php" >/dev/null

EXPECTED="$(php -r '$m=json_decode(file_get_contents($argv[1]),true); echo strtolower((string)($m["sha256"]??""));' "$PAYLOAD/latest.json")"
ACTUAL="$(sha256sum "$PAYLOAD/Assam-Motors-Staff-v6.0.21-FRESH-PRODUCTION.apk" | awk '{print $1}')"

test -n "$EXPECTED" || { echo "NO-GO: latest.json sha256 missing"; exit 1; }
test "$EXPECTED" = "$ACTUAL" || { echo "NO-GO: APK SHA256 mismatch"; exit 1; }

echo "PASS staging docroot"
echo "PASS update API/download payload syntax"
echo "PASS APK checksum matches metadata"
echo "CHECK RESULT: GO"
