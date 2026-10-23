#!/usr/bin/env bash
# FIX112, run on Hostinger STAGING only. Never run in old ERP/production.
set -Eeuo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
file='resources/views/job-cards/show.blade.php'
oldsha='4fd49ba66df4bcf9dc7f5c7b636b4cabdbc702c78ebd5395ad1a9a36d7977515'
newsha='e2a272d22e65d29705d6f80b1c800fdafecbd15b57ad857620f5d46acf755d71'
patchsha='92ef413a4cdc10915b38424e2371f1e1a2a7f721c3b5ce8c3d8314df668d2da9'
for exe in curl patch sha256sum php; do command -v "$exe" >/dev/null || { echo "STOP: $exe not available"; exit 1; }; done
[ -f "$file" ] || { echo 'STOP: Job Card view missing'; exit 1; }
actual=$(sha256sum "$file" | awk '{print $1}')
if [ "$actual" != "$oldsha" ]; then
  echo "STOP: Server file changed since FIX111. Not modifying any files."
  echo "Current: $actual"; echo "Expected: $oldsha"; exit 1
fi
mkdir -p storage/app/am-backups
backup="storage/app/am-backups/show_FIX112_$(date +%Y%m%d_%H%M%S).blade.php"
cp -p "$file" "$backup"
tmp=$(mktemp)
changed=0
cleanup() {
  status=$?
  trap - EXIT
  if [ "$status" -ne 0 ] && [ "$changed" -eq 1 ]; then
    cp -p "$backup" "$file"
    php artisan view:clear || true
    echo 'FIX112 validation failed, original file restored.'
  fi
  rm -f "$tmp"
  exit "$status"
}
trap cleanup EXIT
curl -fsSL --max-time 30 'https://raw.githubusercontent.com/birensingha/assam-motors-erp/1d35354afdf6d9a5496f4460e769c08974a264e2/patches/FIX112_SINGLE_MECHANIC_PICKER_AND_NATIVE_SUBMIT.patch' -o "$tmp"
got=$(sha256sum "$tmp" | awk '{print $1}')
if [ "$got" != "$patchsha" ]; then echo "STOP: Patch integrity mismatch: $got"; exit 1; fi
patch --batch --dry-run -p1 < "$tmp"
changed=1
patch --batch -p1 < "$tmp"
actual=$(sha256sum "$file" | awk '{print $1}')
if [ "$actual" != "$newsha" ]; then echo "STOP: Resulting source hash mismatch: $actual"; exit 1; fi
php artisan view:clear
php artisan view:cache
compiled=$(grep -l 'FIX112' storage/framework/views/*.php 2>/dev/null | head -n 1 || true)
if [ -z "$compiled" ]; then echo 'STOP: FIX112 compiled Blade view was not found'; exit 1; fi
php -l "$compiled"
echo 'FIX112 CODE/VIEW VALIDATION PASSED'
echo "Backup: $backup"
echo 'NEXT: Browser check real mechanic save; backend success NOT yet verified.'
