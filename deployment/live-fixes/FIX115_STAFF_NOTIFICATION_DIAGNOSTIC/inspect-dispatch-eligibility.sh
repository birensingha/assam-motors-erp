#!/usr/bin/env bash
# FIX115-D. READ ONLY: inspect notification dispatcher eligibility and scheduler.
# Never call schedule:run, dispatch-notifications, or fcm-smoke-test here.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan
test -f vendor/autoload.php

echo "FIX115_D_DISPATCH_ELIGIBILITY_READ_ONLY"
echo "PHP_BINARY=$(command -v php)"
echo "SCHEDULE_SCRIPT=$(test -f routes/console.php && echo present || echo absent)"
echo "=== Source-filtered eligibility lines (no tokens or secrets) ==="
python3 - <<'PY'
import re
from pathlib import Path
files=[
    'app/Console/Commands/DispatchNotificationOutbox.php',
    'app/Console/Commands/SyncStaffReminders.php',
    'app/Services/StaffReminderEngine.php',
    'app/Services/FirebaseFcmService.php',
    'routes/console.php',
]
include=re.compile(r'\b(STATUS|PENDING|POLLING|available_at|attempts|send\(|dispatch|where\(|orWhere\(|limit\(|active|notification_permission|fcm_token|staff_device_id|skip|return|withoutOverlapping|everyMinute|eligible|invalidated|schedule|signature)\b',re.I)
hide=re.compile(r'(secret|password|private.?key|access.?token|authorization|bearer|service_account|credential|\.env|oauth)',re.I)
for path in files:
    f=Path(path)
    print('SOURCE',path,'present='+('yes' if f.is_file() else 'no'))
    if not f.is_file(): continue
    entries=[]
    for n,line in enumerate(f.read_text(encoding='utf-8',errors='replace').splitlines(),1):
        if not include.search(line) or hide.search(line):
            continue
        line=line.strip()
        if len(line)>190: line=line[:190]+'...'
        entries.append((n,line))
    print('FILTERED_MATCHES',len(entries),'SHOWING',min(90,len(entries)))
    for n,line in entries[:90]:
        print(str(n)+': '+line)
PY
echo "=== Outbox real eligibility aggregates ==="
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$t='notification_outbox';
if (!Schema::hasTable($t) || !Schema::hasTable('staff_devices')) {
 echo "REQUIRED_TABLE_MISSING\n"; exit(0);
}
$now=\Carbon\CarbonImmutable::now('Asia/Kolkata')->format('Y-m-d H:i:s');
$cols=Schema::getColumnListing($t);
$dc=Schema::getColumnListing('staff_devices');
echo "TIME_IST=$now\n";
foreach (DB::table($t)->select('status')->selectRaw('COUNT(*) AS n')->groupBy('status')->get() as $r) {
    $s=(string)$r->status;
    echo "OUTBOX_STATUS=" . preg_replace('/[^A-Za-z0-9_-]/','',$s) . " COUNT=" . (int)$r->n . "\n";
}
if (in_array('staff_device_id',$cols,true) && in_array('fcm_token',$dc,true)) {
  $q=DB::table($t.' as o')->leftJoin('staff_devices as d','o.staff_device_id','=','d.id');
  $expr=[
   'DUE'=>static fn($z)=>$z->where('o.available_at','<=',$now),
   'LINKED_TOKEN'=>static fn($z)=>$z->whereNotNull('d.fcm_token')->where('d.fcm_token','<>',''),
   'LINKED_TOKEN_PERMISSION'=>static fn($z)=>$z->whereNotNull('d.fcm_token')->where('d.fcm_token','<>','')->where('d.notification_permission',1),
   'LINKED_TOKEN_PERMISSION_ACTIVE'=>static fn($z)=>$z->whereNotNull('d.fcm_token')->where('d.fcm_token','<>','')->where('d.notification_permission',1)->where('d.active',1),
   'NULL_DEVICE'=>static fn($z)=>$z->whereNull('o.staff_device_id'),
  ];
  foreach(['PENDING','POLLING'] as $status) {
    $b=(clone $q)->where('o.status',$status);
    echo "GROUP=$status TOTAL=".$b->count()."\n";
    foreach ($expr as $label=>$filter) {
      echo "  $label=".$filter(clone $b)->count()."\n";
    }
    if (in_array('attempts',$cols,true)) {
      echo "  ATTEMPT_ZERO=".(clone $b)->where('o.attempts',0)->count()."\n";
    }
  }
  if (in_array('staff_id',$cols,true) && in_array('staff_id',$dc,true)) {
   echo "DEVICE_STAFF_ID_MISMATCH=".(clone $q)->whereNotNull('o.staff_device_id')->whereColumn('o.staff_id','<>','d.staff_id')->count()."\n";
  }
}
try {
 $table=(new \App\Models\StaffReminderState)->getTable();
 echo "REMINDER_ACTUAL_TABLE=".preg_replace('/[^A-Za-z0-9_]/','',$table)."\n";
 if (Schema::hasTable($table)) {
  echo "REMINDER_ROW_COUNT=".DB::table($table)->count()."\n";
 }
} catch (Throwable $e) { echo "REMINDER_MODEL_ERROR_CLASS=".get_class($e)."\n"; }
echo "DB_READ_ONLY=YES\n";
PHP
echo "FIX115_D_COMPLETE_NO_WRITES"
