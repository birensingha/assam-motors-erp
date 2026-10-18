#!/usr/bin/env python3
"""Staging-only ROT policy: staff must Check-In for any manual Pause,
and Paused ROTs may coexist with a NEW Assigned/Running ROT.
Only ONE new Assigned/Running ROT may be held at a time.
No attendance, stock, labour, customer, invoice or payroll data is modified.
"""
import argparse,subprocess,datetime,shutil,os,stat,tempfile
from pathlib import Path
ap=argparse.ArgumentParser()
ap.add_argument('--root',required=True)
ap.add_argument('--apply',action='store_true')
args=ap.parse_args()
root=Path(args.root).resolve()
assert (root/'artisan').is_file(),'SAFE_STOP: wrong staging Laravel root'
a=root/'app/Services/RotAssignmentService.php'
w=root/'app/Services/RotWorkflowService.php'
files={'assignment':a,'workflow':w}
for p in files.values():assert p.is_file(),'SAFE_STOP: missing source '+p.name
sources={k:p.read_text(encoding='utf-8') for k,p in files.items()}
s=sources['assignment'];t=sources['workflow']
marker='AM_ROT_PAUSE_FREES_STAFF_V2'
if marker in s and marker in t:
    print('ROT_V2_ALREADY_APPLIED')
    raise SystemExit(0)
if marker in s or marker in t:raise SystemExit('SAFE_STOP: Partial patch markers detected')
def once(text,old,new,name):
    c=text.count(old)
    if c!=1:raise SystemExit('SAFE_STOP: '+name+' anchor count='+str(c))
    return text.replace(old,new,1)
s=once(s,"            $busy = RotSession::query()->where('staff_id', $staffId)\n                ->whereIn('status', ['Assigned','Running','Paused'])->lockForUpdate()->exists();",
"""            // AM_ROT_PAUSE_FREES_STAFF_V2: Paused jobs are held, not actively engaged.
            // New assignments require Check-In and no other Assigned/Running ROT.
            $busy = RotSession::query()->where('staff_id', $staffId)
                ->whereIn('status', ['Assigned','Running'])->lockForUpdate()->exists();""",
'assign busy filter')
s=once(s,"Staff already has an Assigned, Running or Paused ROT. Complete or reassign the existing ROT first.",
"Staff already has an Assigned or Running ROT. Pause the running ROT before assigning another.",
'assign busy message')
old="""            $rot = $this->lockedRot($sessionId, $restrictedStaffId);
            if ($rot->status !== 'Running') {"""
new="""            $rot = $this->lockedRot($sessionId, $restrictedStaffId);
            // AM_ROT_PAUSE_FREES_STAFF_V2: even Admin manually pausing another
            // mechanic's ROT requires that mechanic to be actively checked in.
            // Trusted automatic Check-Out may still pause a running ROT.
            if (!$trustedAutoCheckout) {
                $this->assertStaffCheckedIn((int)$rot->staff_id);
            }
            if ($rot->status !== 'Running') {"""
t=once(t,old,new,'staff Check-In enforced for manual Admin Pause')
methods=[
 ('public function reopenResetEndTime(', 'public function changeMechanic(', 'ROT reopen'),
 ('public function changeMechanic(', 'public function history(', 'ROT mechanic reassignment'),
 ('private function assertNoOtherRunningRot(', 'private function ensureCurrentSegment(', 'ROT start/resume conflict'),
]
for left,right,label in methods:
    start=t.find(left);end=t.find(right,start+len(left))
    if start<0 or end<0:raise SystemExit('SAFE_STOP: missing '+label)
    section=t[start:end]
    if section.count("->whereIn('status', ['Assigned','Running','Paused'])")!=1:
        raise SystemExit('SAFE_STOP: expected busy status once in '+label)
    section=section.replace("->whereIn('status', ['Assigned','Running','Paused'])","->whereIn('status', ['Assigned','Running'])",1)
    section=section.replace('Assigned, Running or Paused','Assigned or Running')
    t=t[:start]+section+t[end:]
assert t.count(marker)==1
assert s.count(marker)==1
assert "if (!$trustedAutoCheckout)" in t
assert t.count("->whereIn('status', ['Assigned','Running'])")>=3
assert "->whereIn('status', ['Assigned','Running'])->lockForUpdate()->exists()" in s
for k,source in {'assignment':s,'workflow':t}.items():
    proc=subprocess.run(['php','-l'],input=source,text=True,capture_output=True)
    if proc.returncode != 0:
        raise SystemExit('SAFE_STOP: php -l candidate failed '+k+': '+proc.stderr[:260])
print('ROT_V2_POLICY_PHP_LINT=PASS')
if not args.apply:
    print('ROT_V2_DRY_RUN=PASS')
    raise SystemExit(0)
date=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup=root/'storage/app/patch-backups'/('rot-pause-v2-'+date)
backup.mkdir(mode=0o700,parents=True,exist_ok=False)
for k,p in files.items():
    b=backup/(p.name+'.bak')
    shutil.copy2(p,b);b.chmod(0o600)
try:
    for k,body in [('assignment',s),('workflow',t)]:
        p=files[k]
        fd,tmp=tempfile.mkstemp(prefix='.rot-v2-',suffix='.php',dir=p.parent)
        with os.fdopen(fd,'w',encoding='utf-8') as h:
            h.write(body);h.flush();os.fsync(h.fileno())
        os.chmod(tmp,stat.S_IMODE(p.stat().st_mode))
        os.replace(tmp,p)
    for p in files.values():
        proc=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
        if proc.returncode: raise ValueError('Post-write PHP syntax failure '+p.name)
except Exception:
    for k,p in files.items():shutil.copy2(backup/(p.name+'.bak'),p)
    print('ROT_V2_PATCH=ROLLED_BACK')
    raise
print('ROT_V2_PATCH=APPLIED')
print('ROT_V2_BACKUP='+backup.name)
print('POLICY_CHECKIN_REQUIRED_FOR_MANUAL_PAUSE=YES')
print('POLICY_PAUSED_RELEASES_STAFF=YES')
print('POLICY_ONE_ASSIGNED_OR_RUNNING_ROT=YES')
