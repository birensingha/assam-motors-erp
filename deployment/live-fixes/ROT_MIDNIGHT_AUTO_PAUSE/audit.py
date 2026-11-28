#!/usr/bin/env python3
"""Read-only inspection of midnight ROT pause integration. No business data."""
from pathlib import Path
import sys,re,subprocess,shutil
root=Path(sys.argv[1]).resolve()
if not (root/'artisan').is_file() or not root.name.endswith('staging'):
 raise SystemExit('STOP: staging Laravel root required')
paths=[root/p for p in ['app/Services/RotWorkflowService.php','app/Services/AttendanceService.php','app/Models/RotSession.php','app/Models/RotSegment.php','routes/console.php','app/Console/Kernel.php','bootstrap/app.php']]
paths+=list((root/'app/Console/Commands').glob('*Rot*.php'))
for p in paths:
 if not p.is_file():continue
 s=p.read_text(errors='replace')
 if 'Console/Commands' in str(p) and not re.search(r'rot|attendance|midnight',s,re.I):continue
 if p.name=='bootstrap/app.php':
  lines=s.splitlines()
  indices=set()
  for i,l in enumerate(lines):
   if re.search(r'schedule|commands|withRouting',l,re.I):indices.update(range(max(0,i-5),min(len(lines),i+10)))
  s='\n'.join(lines[i] for i in sorted(indices))
 print('FILE',p.relative_to(root),flush=True)
 print(s,flush=True)
print('SCHEDULE_LIST_READONLY',flush=True)
subprocess.run(['php','artisan','schedule:list'],cwd=root,check=True,timeout=40)
# Report whether a Laravel scheduler entry exists, without exposing cron commands.
if shutil.which('crontab'):
 r=subprocess.run(['crontab','-l'],capture_output=True,text=True)
 print('CRONTAB_READABLE',r.returncode==0)
 print('CRON_SCHEDULE_RUN_PRESENT',any('schedule:run' in line and not line.lstrip().startswith('#') for line in r.stdout.splitlines()))
else:print('HOST_CRON_NOT_INSPECTABLE_USE_HPanel')
print('ROT_MIDNIGHT_SOURCE_AUDIT_PASS')
