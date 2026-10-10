#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
DOCROOT="$HOME/domains/assammotors.com/public_html/staging"
BASE_URL="https://staging.assammotors.com"
APK="Assam-Motors-Staff-v6.0.21-FRESH-PRODUCTION.apk"

test -f "$DOCROOT/legacy/api/staff-app-update.php" || { echo "VERIFY FAIL: update endpoint missing"; exit 1; }
test -f "$DOCROOT/staff-app/latest.json" || { echo "VERIFY FAIL: metadata missing"; exit 1; }
test -f "$DOCROOT/staff-app/$APK" || { echo "VERIFY FAIL: APK missing"; exit 1; }

php -l "$DOCROOT/legacy/api/staff-app-update.php" >/dev/null

EXPECTED="$(php -r '$m=json_decode(file_get_contents($argv[1]),true); echo strtolower((string)($m["sha256"]??""));' "$DOCROOT/staff-app/latest.json")"
ACTUAL="$(sha256sum "$DOCROOT/staff-app/$APK" | awk '{print $1}')"
test "$EXPECTED" = "$ACTUAL" || { echo "VERIFY FAIL: published APK checksum mismatch"; exit 1; }

APK_CODE="$(curl -sS -o /dev/null -w '%{http_code}' "$BASE_URL/staff-app/$APK" || true)"
test "$APK_CODE" = "200" || { echo "VERIFY FAIL: public APK HTTP $APK_CODE"; exit 1; }

API_CODE="$(curl -sS -o /tmp/am_fix43_api -w '%{http_code}' "$BASE_URL/legacy/api/staff-app-update.php?platform=android&version_code=60020" || true)"
test "$API_CODE" = "401" || { echo "VERIFY FAIL: unauthenticated update API expected 401, got $API_CODE"; cat /tmp/am_fix43_api || true; exit 1; }
rm -f /tmp/am_fix43_api

echo "VERIFY RESULT: PASS"
echo "Public APK HTTP 200"
echo "Authenticated update API is installed"
echo "Published version: 6.0.21 / 60021"
echo "No DB migration was run."
