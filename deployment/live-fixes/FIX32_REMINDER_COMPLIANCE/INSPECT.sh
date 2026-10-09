#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"
echo "ASSAM MOTORS FIX32 — REMINDER COMPLIANCE INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

FILES=(
  app/Services/StaffReminderEngine.php
  app/Services/MandatoryNotificationService.php
  app/Http/Controllers/Api/V1/ReminderController.php
  public/legacy/api/staff-reminders.php
  app/Services/FirebaseFcmService.php
)

echo "== FILE INVENTORY / HASHES =="
for f in "${FILES[@]}"; do
  if [[ -f "$f" ]]; then
    sha256sum "$f"
  else
    echo "MISSING $f"
  fi
done
echo

echo "== ROUTES =="
php artisan route:list 2>&1 | grep -Ei 'reminder|notification|staff.*alert|alert.*staff' | head -n 120 || true
echo

echo "== STAFF REMINDER ENGINE MARKERS =="
if [[ -f app/Services/StaffReminderEngine.php ]]; then
  grep -nEi -A15 -B6 'postpone|snooze|max_|reminder|escalat|resolved|next_reminder|staff_reminder_state|target_key|alert_type'     app/Services/StaffReminderEngine.php | head -n 320 || true
fi
echo

echo "== MANDATORY NOTIFICATION MARKERS =="
if [[ -f app/Services/MandatoryNotificationService.php ]]; then
  grep -nEi -A15 -B6 'postpone|snooze|max_|reminder|escalat|resolved|outbox|push|target_key|alert_type'     app/Services/MandatoryNotificationService.php | head -n 260 || true
fi
echo

echo "== LEGACY STAFF REMINDER API =="
if [[ -f public/legacy/api/staff-reminders.php ]]; then
  grep -nEi -A18 -B8 'action|snooze|postpone|resolve|escalat|max_|minutes|next_reminder|reminder_state|status'     public/legacy/api/staff-reminders.php | head -n 360 || true
fi
echo

echo "== FCM PAYLOAD MARKERS =="
if [[ -f app/Services/FirebaseFcmService.php ]]; then
  grep -nEi -A12 -B6 'reminder_state_id|outbox_id|postpone_count|deep_link|title|body|message|push'     app/Services/FirebaseFcmService.php | head -n 260 || true
fi
echo

echo "== SCHEMA: staff_reminder_state =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
  $cols=DB::select("SHOW COLUMNS FROM staff_reminder_state");
  foreach($cols as $c){ echo $c->Field." | ".$c->Type." | ".$c->Null." | ".($c->Default??"NULL").PHP_EOL; }
} catch(Throwable $e) { echo "SCHEMA ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "== READ-ONLY STATE SAMPLE =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
  $rows=DB::table("staff_reminder_state")->orderByDesc("id")->limit(10)->get();
  echo "rows=".count($rows).PHP_EOL;
  foreach($rows as $r){
    $a=(array)$r;
    foreach(["id","staff_id","alert_type","target_type","target_key","status","postpone_count","reminder_count","max_postponements","next_reminder_at","escalated_at","resolved_at","updated_at"] as $k){
      if(array_key_exists($k,$a)) echo $k."=".($a[$k]===null?"NULL":$a[$k])." ";
    }
    echo PHP_EOL;
  }
} catch(Throwable $e) { echo "STATE ERROR: ".$e->getMessage().PHP_EOL; }
'
echo

echo "INSPECT COMPLETE — READ ONLY"
