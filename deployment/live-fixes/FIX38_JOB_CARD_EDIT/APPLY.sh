#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"

set +e
bash "$DIR/CHECK.sh" "$APP_ROOT"
RC=$?
set -e
if [[ $RC -eq 5 ]]; then echo "FIX38 already appears installed"; exit 0; fi
[[ $RC -eq 0 ]] || exit $RC

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$(dirname "$APP_ROOT")/ASSAM_MOTORS_FIX38_BACKUP_$STAMP"
mkdir -p "$BACKUP/resources/views/job-cards"
cp -p "$APP_ROOT/resources/views/job-cards/form.blade.php" "$BACKUP/resources/views/job-cards/form.blade.php"
echo "BACKUP: $BACKUP"

php "$DIR/PATCH_FIX38.php" "$APP_ROOT"
php -l "$APP_ROOT/resources/views/job-cards/form.blade.php"
bash "$DIR/VERIFY.sh" "$APP_ROOT"

echo "FIX38 APPLY COMPLETE"
echo "Backup: $BACKUP"
echo "No DB migration was run."
