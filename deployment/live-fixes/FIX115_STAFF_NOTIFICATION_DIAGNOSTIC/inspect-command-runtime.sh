#!/usr/bin/env bash
# FIX115-G: Non-mutating diagnosis of Artisan dispatch runtime.
# Do not execute notification commands, invoke FCM, or change cache/database.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan && test -f vendor/autoload.php

echo 'FIX115_G_DISPATCH_RUNTIME_READ_ONLY'
echo '=== Safe Laravel command registration ==='
php artisan help assam:dispatch-notifications 2>&1 | head -30
echo '=== CLI execution environment (no secrets) ==='
php -r '
printf("PHP_VERSION=%s\n", PHP_VERSION);
foreach (["proc_open","exec","shell_exec"] as $f) {
  printf("%s_available=%s\n", strtoupper($f), function_exists($f)?"yes":"no");
}
'
echo '=== Laravel event execution context (no event run) ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Console\Scheduling\Schedule;
try {
  echo 'ENVIRONMENT='.preg_replace('/[^A-Za-z0-9_-]/','',(string)$app->environment())."\n";
  echo 'APP_MAINTENANCE_MODE='.($app->isDownForMaintenance()?'yes':'no')."\n";
  foreach(app(Schedule::class)->events() as $event) {
    $cmd=(string)($event->command??'');
    if (!str_contains($cmd,'assam:dispatch-notifications') && !str_contains($cmd,'assam:staff-reminders')) continue;
    $name=str_contains($cmd,'dispatch-notifications')?'DISPATCH':'REMINDERS';
    echo "EVENT=$name"
      ." DUE_NOW=".($event->isDue($app)?'yes':'no')
      ." BACKGROUND=".($event->runInBackground?'yes':'no')
      ." OVERLAP_GUARD=".($event->withoutOverlapping?'yes':'no')."\n";
  }
} catch(Throwable $e) {
  echo 'CONTEXT_INSPECTION_ERROR='.get_class($e)."\n";
}
echo "LARAVEL_CONTEXT_READ_ONLY=YES\n";
PHP
echo '=== Recent log ERROR counts (no log messages printed) ==='
python3 - <<'PY'
from pathlib import Path
import re
files=sorted(Path('storage/logs').glob('laravel*.log'),key=lambda p:p.stat().st_mtime,reverse=True)[:3]
print('LOG_FILES_FOUND',len(files))
for path in files:
    try:
        with path.open('rb') as handle:
            size=path.stat().st_size
            handle.seek(max(0,size-2500000))
            lines=handle.read().decode('utf-8','replace').splitlines()
        stamps=['2026-10-23 23:20','2026-10-23 23:21','2026-10-23 17:50','2026-10-23 17:51']
        error_prefix=re.compile(r'^\[(2026-10-23 \d\d:\d\d):\d\d\].*\.(?:ERROR|CRITICAL|ALERT|EMERGENCY):')
        counts={s:0 for s in stamps}
        errors=0
        for line in lines:
            match=error_prefix.search(line)
            if not match: continue
            errors+=1
            if match.group(1) in counts:counts[match.group(1)]+=1
        print('LOG_RECENT_ERROR_HEADER_COUNT',errors)
        for s,n in counts.items():
            print('ERRORS_AT_'+s.replace(' ','T').replace(':','')+'='+str(n))
    except Exception as e:
        print('LOG_READ_ERROR_CLASS='+type(e).__name__)
PY
echo 'FIX115_G_AUDIT_COMPLETE_NO_CHANGES'
