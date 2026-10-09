#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
BASE_URL="${2:-https://staging.assammotors.com}"
DOMAIN_ROOT="$HOME/domains/assammotors.com"

test -d "$DOMAIN_ROOT" || { echo "Domain root missing"; exit 1; }
test -f "$APP_ROOT/artisan" || { echo "Laravel APP_ROOT invalid"; exit 1; }

mapfile -t CANDIDATES < <(find -L "$DOMAIN_ROOT" -type f -path '*/legacy/workshop/customers.php' -print 2>/dev/null | head -30)
(("${#CANDIDATES[@]}" > 0)) || { echo "No legacy customers.php candidates found"; exit 1; }

PROBE="am-staging-served-probe-$$.txt"
declare -a PROBES=()
declare -A TOKEN_TO_FILE=()

cleanup() {
  for p in "${PROBES[@]:-}"; do rm -f "$p" 2>/dev/null || true; done
}
trap cleanup EXIT

idx=0
for file in "${CANDIDATES[@]}"; do
  idx=$((idx+1))
  dir="$(dirname "$file")"
  [ -w "$dir" ] || continue
  token="AM_STAGING_CANDIDATE_${idx}_$$"
  probe="$dir/$PROBE"
  printf '%s' "$token" > "$probe"
  PROBES+=("$probe")
  TOKEN_TO_FILE["$token"]="$file"
done

served_token="$(curl -fsS --max-time 15 "$BASE_URL/legacy/workshop/$PROBE" 2>/dev/null || true)"
served_file="${TOKEN_TO_FILE[$served_token]:-}"

if [ -z "$served_file" ] || [ ! -f "$served_file" ]; then
  echo "Unable to identify the file actually served by staging URL"
  exit 1
fi

echo "Identified staging-served legacy customer endpoint."
echo "Served file is inside domain root: yes"

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$DOMAIN_ROOT/ASSAM_MOTORS_FIX41_BACKUP_$STAMP"
mkdir -p "$BACKUP"
cp -a "$served_file" "$BACKUP/customers.php"
printf '%s\n' "${served_file#"$DOMAIN_ROOT/"}" > "$BACKUP/SOURCE_RELATIVE_PATH.txt"
echo "Backup created: $BACKUP"

if ! grep -q 'FIX41_STAGING_SERVED_CUSTOMER_REDIRECT' "$served_file"; then
  TARGET_FILE="$served_file" php <<'PHP'
<?php
declare(strict_types=1);

$file=(string)getenv('TARGET_FILE');
if($file==='' || !is_file($file)){
    fwrite(STDERR,"Target missing\n");
    exit(2);
}
$c=file_get_contents($file);
if($c===false){
    fwrite(STDERR,"Read failed\n");
    exit(2);
}
$open='<?php';
$pos=strpos($c,$open);
if($pos===false){
    fwrite(STDERR,"PHP opening tag missing\n");
    exit(2);
}

$insert=<<<'GUARD'

/* FIX41_STAGING_SERVED_CUSTOMER_REDIRECT
 * Direct browser navigation belongs to Laravel Customer Master.
 * Legacy action requests remain available to legacy JS/API callers.
 */
$__amFix41Method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$__amFix41Action = trim((string)($_GET['action'] ?? ''));
if (in_array($__amFix41Method, ['GET','HEAD'], true) && $__amFix41Action === '') {
    header('Cache-Control: no-store, private');
    header('Location: /erp/customers', true, 302);
    exit;
}
unset($__amFix41Method, $__amFix41Action);

GUARD;

$c=substr($c,0,$pos+strlen($open)).$insert.substr($c,$pos+strlen($open));
if(file_put_contents($file,$c)===false){
    fwrite(STDERR,"Write failed\n");
    exit(2);
}
echo "PASS served legacy customer redirect patched\n";
PHP
else
  echo "FIX41 already present on served file"
fi

touch "$served_file"
php -l "$served_file"

cd "$APP_ROOT"
php artisan optimize:clear >/dev/null

headers="$(mktemp)"
body="$(mktemp)"
trap 'cleanup; rm -f "$headers" "$body"' EXIT
code="$(curl -sS --max-redirs 0 -D "$headers" -o "$body" -w '%{http_code}' "$BASE_URL/legacy/workshop/customers.php" || true)"
location="$(awk 'BEGIN{IGNORECASE=1} /^Location:/{gsub(/\r/,""); sub(/^Location:[[:space:]]*/,""); print; exit}' "$headers")"

if [ "$code" != "302" ]; then
  echo "Expected HTTP 302, got $code"
  exit 1
fi

case "$location" in
  /erp/customers|"$BASE_URL/erp/customers") ;;
  *)
    echo "Unexpected redirect location"
    exit 1
    ;;
esac

if grep -qi 'Admin login required' "$body"; then
  echo "Admin login error still visible"
  exit 1
fi

echo "VERIFY PASS: staging legacy customer URL redirects to /erp/customers"
echo "No DB migration was run."
