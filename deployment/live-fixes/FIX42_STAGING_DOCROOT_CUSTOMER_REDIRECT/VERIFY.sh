#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
BASE_URL="https://staging.assammotors.com"

test -n "$APP_ROOT" && test -f "$APP_ROOT/artisan" || { echo "VERIFY NO-GO: invalid Laravel app root"; exit 1; }

HEADERS="$(mktemp)"
BODY="$(mktemp)"
ACTION_HEADERS="$(mktemp)"
trap 'rm -f "$HEADERS" "$BODY" "$ACTION_HEADERS"' EXIT

CODE="$(curl -sS --max-redirs 0 -D "$HEADERS" -o "$BODY" -w '%{http_code}' "$BASE_URL/legacy/workshop/customers.php" || true)"
LOCATION="$(awk 'BEGIN{IGNORECASE=1} /^Location:/{gsub(/\r/,""); sub(/^Location:[[:space:]]*/,""); print; exit}' "$HEADERS")"

echo "DIRECT HTTP: $CODE"
echo "DIRECT LOCATION: ${LOCATION:-none}"

test "$CODE" = "302" || { echo "VERIFY FAIL: direct URL is not 302"; exit 1; }
case "$LOCATION" in
  /erp/customers|"$BASE_URL/erp/customers") ;;
  *) echo "VERIFY FAIL: wrong redirect target"; exit 1 ;;
esac

ACTION_CODE="$(curl -sS --max-redirs 0 -D "$ACTION_HEADERS" -o /dev/null -w '%{http_code}' "$BASE_URL/legacy/workshop/customers.php?action=__fix42_probe__" || true)"
ACTION_LOCATION="$(awk 'BEGIN{IGNORECASE=1} /^Location:/{gsub(/\r/,""); sub(/^Location:[[:space:]]*/,""); print; exit}' "$ACTION_HEADERS")"

echo "ACTION HTTP: $ACTION_CODE"
echo "ACTION LOCATION: ${ACTION_LOCATION:-none}"

if [ "$ACTION_LOCATION" = "/erp/customers" ] || [ "$ACTION_LOCATION" = "$BASE_URL/erp/customers" ]; then
  echo "VERIFY FAIL: legacy action API was redirected"
  exit 1
fi

echo "VERIFY RESULT: PASS"
echo "Direct browser URL -> /erp/customers"
echo "Legacy ?action= API preserved"
