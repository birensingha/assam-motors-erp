#!/usr/bin/env bash
# Deploy native authenticated in-app update metadata without changing current
# Staff updater, signing key, APK, license, database or existing sessions.
set -euo pipefail
APP="${1:?Provide absolute staging Laravel root}"
DIR="$(cd "$(dirname "$0")" && pwd)"
ROUTES="$APP/routes/api.php"
TARGET="$APP/app/Http/Controllers/Api/V1/NativeAppUpdateController.php"
test -f "$ROUTES" && test -f "$APP/artisan"
test -f "$DIR/NativeAppUpdateController.php"
php -l "$DIR/NativeAppUpdateController.php"
php -l "$ROUTES"
STAMP="$(date +%Y%m%dT%H%M%S)"
BACKUP="$APP/../BACKUP_V637_NATIVE_UPDATE_$STAMP"
mkdir -p "$BACKUP"
cp -p "$ROUTES" "$BACKUP/api.php"
if [[ -f "$TARGET" ]]; then cp -p "$TARGET" "$BACKUP/NativeAppUpdateController.php"; fi
export V637_APP="$APP" V637_SOURCE="$DIR/NativeAppUpdateController.php"
python3 - <<'PY'
from pathlib import Path
import os
route=Path(os.environ['V637_APP'])/'routes/api.php'
source=Path(os.environ['V637_SOURCE'])
target=Path(os.environ['V637_APP'])/'app/Http/Controllers/Api/V1/NativeAppUpdateController.php'
marker="        Route::get('/app-update', [\\App\\Http\\Controllers\\Api\\V1\\NativeAppUpdateController::class, 'index']);"
src=route.read_text()
anchor="        Route::post('/auth/logout', [AuthController::class, 'logout']);"
if marker not in src:
    assert src.count(anchor)==1,'STOP: unexpected protected auth route marker'
    src=src.replace(anchor,anchor+"\n"+marker)
    route.write_text(src)
controller=source.read_text()
if target.is_file() and target.read_text()!=controller:
    raise RuntimeError('STOP: existing NativeAppUpdateController differs; manual review needed')
if not target.exists():target.write_text(controller)
print('V637_NATIVE_AUTHENTICATED_UPDATE_ROUTE=PREPARED')
PY
if ! php -l "$TARGET" || ! php -l "$ROUTES"; then
  cp -p "$BACKUP/api.php" "$ROUTES"
  if [[ -f "$BACKUP/NativeAppUpdateController.php" ]]; then
    cp -p "$BACKUP/NativeAppUpdateController.php" "$TARGET"
  else
    rm -f "$TARGET"
  fi
  echo 'V637_ROLLBACK=PHP_LINT_FAILED'
  exit 1
fi
cd "$APP"
php artisan route:clear
RESULT="$(php artisan route:list --path=api/v1/app-update --json)"
printf '%s' "$RESULT" | python3 -c 'import json,sys; r=json.load(sys.stdin); assert any("api/v1/app-update"==v.get("uri") and "GET" in v.get("method","") and "NativeAppUpdateController" in v.get("action","") for v in r); print("V637_NATIVE_SIGNED_UPDATE_ROUTE=REGISTERED")'
echo "V637_ROUTES_BACKED_UP_AT=$BACKUP"
echo 'V637_NATIVE_APP_UPDATE_DEPLOY=READY'
