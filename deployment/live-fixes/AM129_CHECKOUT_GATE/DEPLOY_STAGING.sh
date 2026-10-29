#!/usr/bin/env bash
# AM129 - staging only backend AttendanceService Check-In gate, auditable backup.
# Fail closed; if QA is not explicitly successful, restore previous PHP service.
set -Eeuo pipefail
APP="${1:?STAGING_ROOT required}"; cd "$APP";test -f artisan
SCRIPTS="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
SERVICE="$APP/app/Services/AttendanceService.php"
python3 -m py_compile "$SCRIPTS/apply.py"
php -l "$SCRIPTS/verify.php"
python3 "$SCRIPTS/apply.py" check "$APP"
mkdir -p "$APP/storage/app/am-backups"
BACKUP="$APP/storage/app/am-backups/AM129_GH_WORKFLOW_$(date +%Y%m%d_%H%M%S)_$$.php"
cp -p "$SERVICE" "$BACKUP"
echo "AM129_WORKFLOW_BACKUP=$BACKUP"
rollback(){
 code="$?"
 trap - ERR
 echo "AM129_QA_FAILED_ROLLBACK=$code"
 cp -p "$BACKUP" "$SERVICE" || true
 php -l "$SERVICE" || true
 echo "AM129_SERVICE_PREVIOUS_VERSION_RESTORED"
 exit "$code"
}
trap rollback ERR
python3 "$SCRIPTS/apply.py" apply "$APP"
QA_RESULT="$(php "$SCRIPTS/verify.php" 2>&1)"
printf '%s\n' "$QA_RESULT"
printf '%s\n' "$QA_RESULT" | grep -Fxq 'AM129_VERIFICATION_COMPLETE_NO_DATA_WRITES'
STATUS="$(curl -sS -L --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM129_ERP_LOGIN_HTTP=$STATUS"
test "$STATUS" = 200
trap - ERR
echo "AM129_STAGING_NEXTDAY_CHECKIN_BLOCK_VERIFIED"
echo "AM129_NO_ATTENDANCE_DB_MUTATIONS"
