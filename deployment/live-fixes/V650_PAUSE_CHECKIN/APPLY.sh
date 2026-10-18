#!/usr/bin/env bash
# V650 — server-side Staff Check-In rule for manual ROT Pause.
# Code-only deployment; no ROT/attendance/invoice data modifications.
set -euo pipefail
ROOT="$1"
test "$ROOT" = "/home/u956497103/domains/assammotors.com/assam-erp-staging"
test -f "$ROOT/artisan"
export V650_ROOT="$ROOT"
python3 - <<'PY'
import os,subprocess
from pathlib import Path
from datetime import datetime,timezone
root=Path(os.environ['V650_ROOT'])
files={
 'rot':'app/Services/RotWorkflowService.php',
 'staff_checkout':'app/Services/AttendanceService.php',
 'admin_checkout':'app/Http/Controllers/Web/AdminSelfAttendanceController.php'
}
prior={}
for k,p in files.items():
 f=root/p
 if not f.is_file():raise RuntimeError('Required Staging source absent: '+p)
 prior[k]=f.read_bytes()
code=prior['rot'].decode('utf-8','surrogateescape')
if 'V650_STAFF_MANUAL_PAUSE_ATTENDANCE_GUARD' not in code:
 old="""        ?int $actorStaffId = null,
        ?int $restrictedStaffId = null
    ): RotSession {
        $pauseReason = trim($pauseReason);"""
 new="""        ?int $actorStaffId = null,
        ?int $restrictedStaffId = null,
        bool $trustedAutoCheckout = false
    ): RotSession {
        $pauseReason = trim($pauseReason);"""
 if code.count(old)!=1:raise RuntimeError('pause signature anchor not unique')
 code=code.replace(old,new,1)
 old="""        if ($pauseReason === '') {
            throw ValidationException::withMessages(['pause_reason' => 'Pause reason is required.']);
        }

        return DB::transaction(function () use ($sessionId, $pauseReason, $pauseNote, $actor, $actorStaffId, $restrictedStaffId) {
            $rot = $this->lockedRot($sessionId, $restrictedStaffId);"""
 new="""        if ($pauseReason === '') {
            throw ValidationException::withMessages(['pause_reason' => 'Pause reason is required.']);
        }
        // V650_STAFF_MANUAL_PAUSE_ATTENDANCE_GUARD:
        // No app/web client may impersonate the internal Attendance Check-Out reason.
        if (($pauseReason === 'AUTO_CHECK_OUT') !== $trustedAutoCheckout) {
            throw ValidationException::withMessages(['pause_reason' =>
                'AUTO_CHECK_OUT is reserved for automatic Attendance Check-Out.']);
        }

        return DB::transaction(function () use ($sessionId, $pauseReason, $pauseNote, $actor, $actorStaffId, $restrictedStaffId, $trustedAutoCheckout) {
            // Lock Attendance BEFORE the ROT, same order as AttendanceService::checkOut.
            // A concurrent Check-Out therefore cannot race an unauthorized manual Pause.
            // Admin supervisor override remains available to safely stop orphan timers.
            if (!$trustedAutoCheckout && strtolower(trim($actor)) !== 'admin') {
                $actingStaffId = (int)($restrictedStaffId ?: $actorStaffId);
                if ($actingStaffId <= 0) {
                    throw ValidationException::withMessages(['attendance' =>
                        'Active staff identity and Check-In required before ROT Pause.']);
                }
                $today = CarbonImmutable::now('Asia/Kolkata')->toDateString();
                $attendance = Attendance::query()
                    ->where('staff_id', $actingStaffId)
                    ->whereDate('date', $today)
                    ->whereNotNull('check_in')
                    ->whereNull('check_out')
                    ->lockForUpdate()
                    ->first();
                if (!$attendance) {
                    throw ValidationException::withMessages(['attendance' =>
                        'ROT Pause denied: staff is Not Check-In / already Checked-Out. Check-In first.']);
                }
            }
            $rot = $this->lockedRot($sessionId, $restrictedStaffId);"""
 if code.count(old)!=1:raise RuntimeError('pause validation/transaction anchor not unique')
 code=code.replace(old,new,1)
new={'rot':code.encode('utf-8','surrogateescape')}
sc=prior['staff_checkout'].decode('utf-8','surrogateescape')
if 'V650_AUTO_CHECKOUT_TRUSTED_CALL' not in sc:
 needle="'staff',$staffId,$staffId);"
 if sc.count(needle)!=1:raise RuntimeError('Staff Check-Out automated pause call not uniquely located')
 sc=sc.replace(needle,"'staff',$staffId,$staffId,true); // V650_AUTO_CHECKOUT_TRUSTED_CALL",1)
new['staff_checkout']=sc.encode('utf-8','surrogateescape')
ac=prior['admin_checkout'].decode('utf-8','surrogateescape')
if 'V650_AUTO_CHECKOUT_TRUSTED_CALL' not in ac:
 needle="'admin',$staffId,null);"
 if ac.count(needle)!=1:raise RuntimeError('Admin Check-Out automated pause call not uniquely located')
 ac=ac.replace(needle,"'admin',$staffId,null,true); // V650_AUTO_CHECKOUT_TRUSTED_CALL",1)
new['admin_checkout']=ac.encode('utf-8','surrogateescape')
for k in files:
 p=subprocess.run(['php','-l'],input=new[k],capture_output=True)
 if p.returncode:raise RuntimeError('PHP preflight syntax '+k+': '+(p.stdout+p.stderr).decode('utf-8','replace'))
if b'->lockForUpdate()' not in new['rot'] or b"whereNull('check_out')" not in new['rot'] or b"trustedAutoCheckout" not in new['rot']:
 raise RuntimeError('Required attendance lock or internal check-out flag missing')
backup=root.parent/('BACKUP_V650_ROT_PAUSE_CHECKIN_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700)
changed=[]
try:
 for k,path in files.items():
  dest=root/path
  if prior[k]==new[k]:continue
  b=backup/path
  b.parent.mkdir(parents=True,exist_ok=True);b.write_bytes(prior[k]);b.chmod(0o600)
  tmp=dest.with_name('.'+dest.name+'.v650tmp')
  tmp.write_bytes(new[k]);tmp.chmod(dest.stat().st_mode&0o777)
  os.replace(tmp,dest);changed.append(k)
 for k in files:
  p=subprocess.run(['php','-l',str(root/files[k])],capture_output=True,text=True)
  if p.returncode:raise RuntimeError('PHP staging lint '+k+': '+p.stdout+p.stderr)
 p=subprocess.run(['php','artisan','route:list','--json'],cwd=root,capture_output=True,text=True,timeout=80)
 if p.returncode:raise RuntimeError('Laravel route bootstrap failed: '+p.stdout+p.stderr)
 print('V650_PHP_LINT_AND_LARAVEL_ROUTES=PASS')
except BaseException:
 for k in reversed(changed):
  (root/files[k]).write_bytes(prior[k])
 print('V650_DEPLOY_ROLLBACK=RESTORED')
 raise
print('V650_BACKUP='+str(backup))
print('V650_CHANGED='+','.join(changed))
print('V650_STAFF_MANUAL_ROT_PAUSE_CHECKIN_GUARD=DEPLOYED')
PY
