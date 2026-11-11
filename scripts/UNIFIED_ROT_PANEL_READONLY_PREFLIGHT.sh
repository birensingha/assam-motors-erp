#!/usr/bin/env bash
# ASSAM MOTORS — LOW LOAD, READ ONLY, staging ROT panel inventory.
# Only known small set of files: NO recursive content scans, artisan boot,
# DB queries, web calls, cache clears, or application modifications.
set -u
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
if ! cd "$ROOT"; then echo "STOP: staging directory unavailable"; exit 1; fi
if [ ! -f artisan ]; then echo "STOP: not a Laravel root"; exit 1; fi
echo "AM_ROT_QUICK_PREFLIGHT_START"
echo "ROOT_OK"
check_file() {
  f="$1"
  if [ ! -f "$f" ]; then printf '\nFILE_MISSING %s\n' "$f"; return; fi
  printf '\nFILE %s\n' "$f"
  wc -c < "$f" | awk '{print "BYTES " $1}'
  # Limited, literal source matching: never print token values or credentials.
  grep -nEi -m 10 'rot.alteration|alter.rot|Lock / Alter|Assign ROT|Change Mechanic|am90-edit|labour.delete-admin|planned-rot-delete|amount.editor|standard.hours|rot.assign|rot-assignment' "$f" | cut -c 1-200 || true
}
check_file "resources/views/job-cards/show.blade.php"
check_file "resources/views/job-cards/rot-alteration.blade.php"
check_file "resources/views/job-cards/partials/planned-rot-delete.blade.php"
check_file "routes/web.php"
check_file "resources/views/layouts/app.blade.php"
check_file "resources/views/layouts/erp.blade.php"
check_file "resources/views/partials/sidebar.blade.php"
check_file "resources/views/components/sidebar.blade.php"
printf '\nROT_RELATED_VIEW_FILENAMES\n'
find resources/views/job-cards -maxdepth 2 -type f \( -iname '*rot*' -o -iname '*alter*' \) -print 2>/dev/null | head -30 || true
echo "AM_ROT_QUICK_PREFLIGHT_COMPLETE_NO_CHANGES"
