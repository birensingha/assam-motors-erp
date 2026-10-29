#!/usr/bin/env python3
"""AM129: Staging only. Block next-day Check-In if any earlier Check-Out missing.
Works through shared AttendanceService for Native API and staff web. 
Backup-first and source+PHP syntax guards; no migrations, no staff data writes.
"""
import sys,os,shutil,tempfile,subprocess,datetime
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):
    raise SystemExit("Use python3 apply.py check|apply STAGING_ROOT")
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
file=root/"app/Services/AttendanceService.php"
if not (root/"artisan").is_file() or not file.is_file():
    raise SystemExit("AM129_STOP_STAGING_LARAVEL_REQUIRED")
old=file.read_text("utf8",errors="surrogateescape")
marker="AM129_PREVIOUS_DAY_MISSED_CHECKOUT_BLOCK"
if marker in old:
    print("AM129_ALREADY_INSTALLED");sys.exit(0)
anchor="""            $now = CarbonImmutable::now('Asia/Kolkata');
            $geo = $this->geoFence->verify("""
if old.count(anchor)!=2:
    raise SystemExit("AM129_STOP_EXPECTED_CHECKIN_AND_CHECKOUT_ANCHORS")
for name,needle in [
    ("CHECKIN_SERVICE","public function checkIn(int $staffId, array $location): Attendance"),
    ("CHECKOUT_SERVICE","public function checkOut(int $staffId, array $location): Attendance"),
    ("TODAY_QUERY","->whereDate('date', $now->toDateString())"),
    ("GEOGUARD","true // Strict Check-In: independent accuracy setting")
]:
    ok=needle in old;print("AM129_PREFLIGHT_"+name+"="+("PASS" if ok else "FAIL"))
    if not ok:raise SystemExit("AM129_STOP_SOURCE_DRIFT_"+name)
replacement="""            $now = CarbonImmutable::now('Asia/Kolkata');

            // AM129_PREVIOUS_DAY_MISSED_CHECKOUT_BLOCK:
            // Any earlier Check-In without Check-Out blocks a NEW day's
            // Check-In on all Staff App/web clients. Only an authorised
            // Admin/HR historical correction can clear this condition.
            // Never auto-generate Check-Out or worked hours.
            // This check runs BEFORE GPS so the staff sees the real reason.
            $priorOpen = Attendance::query()
                ->where('staff_id', $staffId)
                ->where('date', '<', $now->toDateString())
                ->whereNotNull('check_in')
                ->whereNull('check_out')
                ->orderBy('date')
                ->lockForUpdate()
                ->first();
            if ($priorOpen) {
                $missingDate = CarbonImmutable::parse(
                    (string) $priorOpen->date, 'Asia/Kolkata'
                )->format('d-m-Y');
                throw ValidationException::withMessages([
                    'attendance' => 'CHECK-IN BLOCKED: Check-Out missing for '
                        .$missingDate.'. Contact Admin to correct your previous '
                        .'attendance. Staff cannot back-date Check-Out. '
                        .'Today\\'s Check-In is not recorded until Admin resolves it.',
                ]);
            }

            $geo = $this->geoFence->verify("""
candidate=old.replace(anchor,replacement,1)
if candidate.count(marker)!=1 or candidate.count("whereNull('check_out')")!=1:
    raise SystemExit("AM129_CANDIDATE_VALIDATION_FAILED")
with tempfile.TemporaryDirectory(prefix="am129_preflight_") as dirname:
    phpfile=Path(dirname)/"AttendanceService.php"
    phpfile.write_text(candidate,"utf8",errors="surrogateescape")
    p=subprocess.run(["php","-l",str(phpfile)],cwd=root,capture_output=True,text=True,timeout=25)
    if p.returncode:raise SystemExit("AM129_SOURCE_PHP_LINT_FAIL "+p.stderr[-350:])
print("AM129_CANDIDATE_PHP_LINT_PASS")
if mode=="check":
    print("AM129_PREFLIGHT_PASS_NO_WRITES");sys.exit(0)
backup=root/"storage/app/am-backups"/("AM129_AttendanceService_"+datetime.datetime.now().strftime("%Y%m%d_%H%M%S")+"_"+str(os.getpid())+".php")
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(file,backup)
print("AM129_BACKUP="+str(backup))
try:
    fd,tmp=tempfile.mkstemp(prefix=".am129_",dir=file.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(candidate)
        os.chmod(tmp,file.stat().st_mode & 0o777)
        os.replace(tmp,file)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    lint=subprocess.run(["php","-l",str(file)],cwd=root,capture_output=True,text=True,timeout=25)
    if lint.returncode:raise RuntimeError("Deployed PHP lint failed: "+lint.stderr[-350:])
    print(lint.stdout.strip())
except BaseException as ex:
    shutil.copy2(backup,file)
    raise SystemExit("AM129_ROLLBACK_SERVICE_RESTORED="+str(ex))
print("AM129_STAGING_API_WEB_CHECKIN_GUARD_APPLIED")
print("AM129_NO_ATTENDANCE_DATA_WRITES")
print("AM129_NO_MIDNIGHT_FAKE_CHECKOUT")
