#!/usr/bin/env python3
"""AM226 generator exact code and reminder table metadata, READ ONLY."""
from pathlib import Path
import re,sys,subprocess,tempfile
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM226_GEN_STOP_NOT_STAGING')
files=[
 root/'app/Console/Commands/SyncStaffReminders.php',
 root/'app/Services/StaffReminderEngine.php',
 root/'app/Models/StaffReminderState.php',
 root/'app/Models/NotificationOutbox.php',
 root/'app/Models/Attendance.php',
]
for p in files:
 if not p.is_file():continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM226_GEN_FILE',str(p.relative_to(root)),'LINES',len(lines))
 for i,line in enumerate(lines[:560]):
  if re.search(r'(?i)(password|secret|bearer|authorization|private.key|DB_PASS|\\.env|api.?token|cookie)',line):
   line='[REDACTED]'
  print('AM226_GEN_CODE',p.name,i+1,line.rstrip()[:700])
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
foreach(['staff_reminder_state','staff_reminder_states','notification_outbox','staff_devices','attendance','staff','hr_audit_logs'] as $table){
 if(Schema::hasTable($table)){
  echo "AM226_GEN_TABLE ".$table." COLUMNS ".implode(',',Schema::getColumnListing($table))."\n";
  $indexes=Schema::getIndexes($table);
  foreach($indexes as $index){
   echo "AM226_GEN_INDEX ".$table." ".($index['name']??'')." ".implode(',',$index['columns']??[])." UNIQUE ".(!empty($index['unique'])?'yes':'no')."\n";
  }
 }
}
"""
with tempfile.TemporaryDirectory(prefix='am226gen_') as d:
 p=Path(d)/'meta.php';p.write_text(php,encoding='utf8')
 r=subprocess.run(['php',str(p),str(root)],cwd=root,text=True,capture_output=True,timeout=40)
 if r.returncode:print('AM226_GEN_TABLE_METADATA_UNAVAILABLE')
 else:print(r.stdout)
print('AM226_GEN_AUDIT_READ_ONLY_COMPLETE')
