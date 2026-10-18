#!/usr/bin/env python3
"""Safe staging-only patch: Login GPS independent of Check-In; strict ROT assignment.
No attendance, ROT, customer or GPS database records are changed here.
Run --dry-run before --apply; backup + PHP syntax validation + rollback on failure.
"""
import argparse, datetime, os, pathlib, shutil, subprocess, sys, tempfile

parser=argparse.ArgumentParser()
parser.add_argument("--root", required=True)
parser.add_argument("--apply", action="store_true")
args=parser.parse_args()
root=pathlib.Path(args.root).resolve()
assert (root/"artisan").is_file(), "Expected staging Laravel ERP root"
files={
 "assign":root/"app/Services/RotAssignmentService.php",
 "workflow":root/"app/Services/RotWorkflowService.php",
 "gps":root/"app/Http/Controllers/Api/V1/StaffLocationController.php",
}
for k,p in files.items():
 assert p.is_file(), f"Missing expected staging source: {k}"

def once(s,a,b,tag):
 n=s.count(a)
 if n!=1: raise ValueError(f"UNSAFE ABORT: {tag}, expected one anchor, got {n}")
 return s.replace(a,b,1)

def change_section(s,beg,end,transform,tag):
 a=s.index(beg);b=s.index(end,a+len(beg))
 old=s[a:b];new=transform(old)
 if old==new:raise ValueError(f"UNSAFE ABORT: no change to {tag}")
 return s[:a]+new+s[b:]

original={k:p.read_text() for k,p in files.items()}
edited=dict(original)

a=edited["assign"]
a=once(a, '''$staff = Staff::query()->whereKey($staffId)->whereRaw("LOWER(status)='active'")->first();''',
 '''$staff = Staff::query()->whereKey($staffId)->whereRaw("LOWER(status)='active'")->lockForUpdate()->first();''',"ROT assignment staff row lock")
a=once(a,'''            $id = 'ROT'.CarbonImmutable::now('Asia/Kolkata')->format('ymdHis').Str::upper(Str::random(5));''',
'''            // FIX V6312: assigning an active ROT requires a real Check-In,
            // and staff cannot carry another Assigned, Running or Paused ROT.
            // The Staff master row is locked above to serialize two admin assignments.
            $onDuty = DB::table('attendance')
                ->where('staff_id', $staffId)
                ->whereDate('date', CarbonImmutable::now('Asia/Kolkata')->toDateString())
                ->whereNotNull('check_in')->whereNull('check_out')
                ->lockForUpdate()->exists();
            if (!$onDuty) {
                throw ValidationException::withMessages(['mechanic' =>
                    'Check-In required before assigning ROT. Please Check-In staff first.']);
            }
            $busy = RotSession::query()->where('staff_id', $staffId)
                ->whereIn('status', ['Assigned','Running','Paused'])->lockForUpdate()->exists();
            if ($busy) {
                throw ValidationException::withMessages(['mechanic' =>
                    'Staff already has an Assigned, Running or Paused ROT. Complete or reassign the existing ROT first.']);
            }
            $id = 'ROT'.CarbonImmutable::now('Asia/Kolkata')->format('ymdHis').Str::upper(Str::random(5));''',"ROT new assignment Check-In and single-active gate")
edited["assign"]=a

w=edited["workflow"]
def change_complete(t):
 return once(t,'''            $now = CarbonImmutable::now('Asia/Kolkata');''',
 '''            // FIX V6312: completing a ROT without an active staff Check-In is forbidden.
            $this->assertStaffCheckedIn((int)$rot->staff_id);
            $now = CarbonImmutable::now('Asia/Kolkata');''',"ROT Complete check-in")
w=change_section(w,"public function complete(","public function reopenResetEndTime(",change_complete,"ROT complete")
def change_reopen(t):
 return once(t,'''            $old = $rot->toArray();''',
 '''            // FIX V6312: reopening makes ROT active again; enforce the same
            // Check-In and single-active requirement as new assignment.
            \App\Models\Staff::query()->whereKey((int)$rot->staff_id)->lockForUpdate()->firstOrFail();
            $this->assertStaffCheckedIn((int)$rot->staff_id);
            if (RotSession::query()->where('staff_id', $rot->staff_id)
                ->whereIn('status', ['Assigned','Running','Paused'])
                ->where('id','<>',$rot->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['rot' =>
                    'Cannot reopen ROT while another Assigned, Running or Paused ROT is active for this staff.']);
            }
            $old = $rot->toArray();''',"ROT reopen Check-In and single-active gate")
w=change_section(w,"public function reopenResetEndTime(","public function changeMechanic(",change_reopen,"ROT reopen")
def change_mechanic(t):
 t=once(t,'''                ->whereRaw("LOWER(status) = 'active'")
                ->first();''',
 '''                ->whereRaw("LOWER(status) = 'active'")
                ->lockForUpdate()->first();''',"reassign staff lock")
 t=once(t,'''            // Assignment/change is allowed even before duty Check-In. The new mechanic
            // must have an active Check-In when they actually Start/Resume the ROT.''',
 '''            // FIX V6312: Reassignment is a new assignment and requires Check-In.
            $this->assertStaffCheckedIn($newStaffId);''',"remove old unchecked reassignment rule")
 t=once(t,'''                ->where('status', 'Running')
                ->where('id', '<>', $rot->id)''',
 '''                ->whereIn('status', ['Assigned','Running','Paused'])
                ->where('id', '<>', $rot->id)''',"reassign busy states")
 t=once(t,'''New mechanic already has another Running ROT.''',
 '''New mechanic already has an Assigned, Running or Paused ROT.''',"reassign busy error")
 return t
w=change_section(w,"public function changeMechanic(","public function history(",change_mechanic,"ROT mechanic change")
def change_active(t):
 t=once(t,'''->where('status', 'Running')''',
 '''->whereIn('status', ['Assigned','Running','Paused'])''',"conflict status states")
 t=once(t,'''This mechanic already has another Running ROT.''',
 '''This mechanic already has another Assigned, Running or Paused ROT.''',"conflict message")
 return t
w=change_section(w,"private function assertNoOtherRunningRot(","private function ensureCurrentSegment(",change_active,"ROT start/resume single active")
edited["workflow"]=w

g=edited["gps"]
g=once(g,"            'source' => ['nullable', 'string', 'max:40'],",
"""            'source' => ['nullable', 'string', 'max:40'],
            'login_tracking_consented' => ['nullable', 'boolean'],""","allow explicit login GPS field")
g=once(g,'''        $staffId = (int)$user->staff_id;''',
'''        $staffId = (int)$user->staff_id;
        // FIX V6312: regular Staff may submit explicitly consented native
        // Login-to-Logout GPS even before Check-In or after Check-Out. This
        // does not change attendance or authorize ANY ROT action.
        $loginGps = $request->header('X-Assam-Motors-App') === 'Android/6.3.12'
            && ($data['source'] ?? '') === 'ANDROID_LOGIN_SESSION_60S'
            && !empty($data['login_tracking_consented']);''',"native consented GPS flag")
g=once(g,"if (!$attendance && !$privileged) {",
         "if (!$attendance && !$privileged && !$loginGps) {","GPS acceptance independent of check-in only for v6.3.12 opt-in")
g=once(g,'''        $recordedAt = CarbonImmutable::createFromTimestamp((int)floor(((int)$data['location_time_ms']) / 1000), 'Asia/Kolkata');''',
'''        $recordedAt = CarbonImmutable::createFromTimestamp((int)floor(((int)$data['location_time_ms']) / 1000), 'Asia/Kolkata');
        // Login-session GPS must be truly live, never a replayed cached position.
        if ($loginGps && abs(CarbonImmutable::now('Asia/Kolkata')->getTimestamp()
                - $recordedAt->getTimestamp()) > 180) {
            return response()->json(['message'=>'Fresh native GPS fix required.',
                'reason'=>'STALE_GPS_FIX'],422);
        }''',"fresh GPS for un-checked-in staff")
edited["gps"]=g

changes={k:v for k,v in edited.items() if v!=original[k]}
assert len(changes)==3
# Check syntax of every candidate before touching the live files.
temps={}
try:
 for key,source in changes.items():
  fd,name=tempfile.mkstemp(prefix=f".v6312-{key}-",suffix=".php",dir=files[key].parent)
  os.close(fd);pathlib.Path(name).write_text(source);temps[key]=pathlib.Path(name)
  p=subprocess.run(["php","-l",name],capture_output=True,text=True)
  if p.returncode!=0:raise ValueError(f"PHP syntax check failed for {key}: {p.stderr.strip()}")
 print("V6312_PATCH_CANDIDATE_PHP_LINT=PASS")
 print("V6312_STAGING_PATCH_DRY_RUN=PASS")
 if args.apply:
  backup=root/"storage/app/patch-backups"/("v6312-"+datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ"))
  backup.mkdir(parents=True,mode=0o700)
  for k,src in original.items():
   dst=backup/(k+".php");dst.write_text(src);dst.chmod(0o600)
  print("V6312_STAGING_BACKUP_DIR="+str(backup))
  try:
   for k,p in files.items():
    shutil.copyfile(temps[k],p)
   for k,p in files.items():
    proc=subprocess.run(["php","-l",str(p)],capture_output=True,text=True)
    if proc.returncode!=0:raise ValueError(f"Live PHP syntax failed: {k}")
  except Exception:
   for k,p in files.items():shutil.copyfile(backup/(k+".php"),p)
   print("V6312_STAGING_PATCH=ROLLED_BACK")
   raise
  print("V6312_STAGING_PATCH=APPLIED")
  print("V6312_ROT_GUARDS=STRICT_CHECKIN_SINGLE_ACTIVE")
  print("V6312_GPS=CONSENTED_LOGIN_SESSION_NO_ATTENDANCE_BYPASS_FOR_ROT")
finally:
 for p in temps.values():
  if p.exists():p.unlink()
