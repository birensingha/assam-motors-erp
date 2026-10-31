#!/usr/bin/env python3
"""AM226 READ ONLY inventory: mandatory alert generation, dedupe, resolution.
Source code only and INFORMATION_SCHEMA table names/column metadata. No records.
"""
from pathlib import Path
import re,sys,subprocess,tempfile
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM226_STOP_NOT_STAGING')
print("AM226_AUDIT_READ_ONLY_START")
sources=[
 root/'app/Services/MandatoryNotificationService.php',
 root/'app/Services/StaffDashboardService.php',
 root/'app/Services/AttendanceService.php',
 root/'app/Console/Commands/DispatchMandatoryNotifications.php',
 root/'app/Console/Commands/GenerateStaffReminders.php',
 root/'app/Console/Kernel.php',
 root/'routes/console.php',
]
for p in sources:
 if not p.is_file():continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM226_FILE',str(p.relative_to(root)),'LINES',len(lines))
 if p.name=='AttendanceService.php':
  needles=[i for i,s in enumerate(lines) if re.search(r'(?i)(checkout|checkIn|previous|block)',s)]
  ix=set(j for i in needles[:28] for j in range(max(0,i-2),min(len(lines),i+3)))
 else: ix=range(min(480,len(lines)))
 for i in sorted(ix):
  line=lines[i]
  if re.search(r'(?i)(password|secret|bearer|authorization|private_key|DB_PASS|dotenv|\.env|API_TOKEN|cookie)',line):
   line='[SENSITIVE_SOURCE_REDACTED]'
  print('AM226_LINE',p.name,i+1,line.rstrip()[:950])
for p in sorted((root/'app/Console/Commands').glob('*.php')) if (root/'app/Console/Commands').is_dir() else []:
 if re.search(r'(?i)notification|remind|alert|attendance|dispatch',p.name):
  print('AM226_COMMAND',p.name)
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
foreach(['staff_mandatory_alerts','staff_alerts','staff_reminders','staff_notifications','attendance','rot_sessions'] as $table){
 if(!Schema::hasTable($table))continue;
 echo "AM226_TABLE ".$table." COLUMNS ".implode(',',Schema::getColumnListing($table))."\n";
}
"""
with tempfile.TemporaryDirectory(prefix='am226audit_') as d:
 f=Path(d)/'metadata.php';f.write_text(php,encoding='utf8')
 r=subprocess.run(['php',str(f),str(root)],cwd=root,text=True,capture_output=True,timeout=35)
 if r.returncode:print("AM226_SCHEMA_SCAN_UNAVAILABLE")
 else:print(r.stdout)
print("AM226_AUDIT_READ_ONLY_COMPLETE")
