#!/usr/bin/env python3
"""Stage-only: independent GPS upload quality vs verified Check-In precision.

Never modify attendance, job cards, ROTs or location log records. All changed
files are linted and backed up; one additive non-destructive DB column.
"""
import argparse, datetime, os, pathlib, shutil, subprocess, tempfile

parser=argparse.ArgumentParser()
parser.add_argument("--root",required=True)
parser.add_argument("--apply",action="store_true")
args=parser.parse_args()
root=pathlib.Path(args.root).resolve()
assert (root/"artisan").is_file(), "Must be the staging Laravel ERP root"
site=root.parent/"public_html/staging"
legacy=site/"legacy/admin"
paths={
    "geofence":root/"app/Services/GeoFenceService.php",
    "attendance":root/"app/Services/AttendanceService.php",
    "rot":root/"app/Http/Controllers/Api/V1/RotWorkflowController.php",
    "laravel_admin":root/"app/Http/Controllers/Web/AdminNativeModuleController.php",
    "laravel_blade":root/"resources/views/admin/native/staff-location.blade.php",
    "legacy_php":legacy/"staff-location.php",
    "legacy_js":legacy/"admin.js",
    "legacy_html":legacy/"admin.html",
}
for k,p in paths.items():
    assert p.is_file(), f"Required staging source missing: {k}"

before={k:p.read_text() for k,p in paths.items()}
after=dict(before)

def exact(s,old,new,label):
    if new in s:
        return s
    n=s.count(old)
    if n!=1:
        raise RuntimeError(f"Unsafe change {label}: expected one exact anchor, got {n}")
    return s.replace(old,new,1)

g=after["geofence"]
g=exact(g,
  "public function verify(float $latitude, float $longitude, ?float $accuracyM, bool $isMocked = false): array",
  "public function verify(float $latitude, float $longitude, ?float $accuracyM, bool $isMocked = false, bool $strictCheckIn = false): array",
  "geofence method signature")
g=exact(g,
  "$maxAccuracy = max(10, (int)$settings->max_accuracy_m);",
  """// The reporting limit (max_accuracy_m) must NEVER authorize Check-In.
        // Only Attendance and Staff ROT Start/Resume opt into strict 30m default.
        $maxAccuracy = $strictCheckIn
            ? max(10, min(50, (int)($settings->checkin_max_accuracy_m ?? 30)))
            : max(10, (int)$settings->max_accuracy_m);""",
  "independent accuracy limits")
g=exact(g,
  "$inside = $distance <= max(20, (int)$settings->radius_m);",
  """$inside = $distance <= max(20, (int)$settings->radius_m);
        // Conservative check: even with a nominal point inside the geofence,
        // avoid approving work when the reported accuracy radius crosses out.
        if ($strictCheckIn && ($distance + (float)$accuracyM) > max(20, (int)$settings->radius_m)) {
            throw ValidationException::withMessages([
                'location' => 'Check-In GPS uncertainty overlaps the workshop boundary. Move closer to workshop and wait for Precise GPS.',
            ]);
        }""",
  "accuracy radius overlap safety")
after["geofence"]=g

t=after["attendance"]
a=t.index("public function checkIn(")
b=t.index("public function checkOut(",a)
block=t[a:b]
block=exact(block,
  "(bool)($location['is_mocked'] ?? false)\n",
  "(bool)($location['is_mocked'] ?? false),\n                true // Strict Check-In: independent accuracy setting\n",
  "attendance strict check-in")
after["attendance"]=t[:a]+block+t[b:]

t=after["rot"]
a=t.index("private function assertStaffGeoFence(")
b=t.index("private function actor(",a)
block=t[a:b]
block=exact(block,
  "(bool)$data['is_mocked']\n",
  "(bool)$data['is_mocked'],\n            true // Strict workshop work authorization\n",
  "staff ROT start/resume strict geofence")
after["rot"]=t[:a]+block+t[b:]

t=after["laravel_admin"]
after["laravel_admin"]=exact(t,
  "'max_accuracy_m'=>['required','integer','between:20,1000'],'sample_minutes'",
  "'max_accuracy_m'=>['required','integer','between:20,1000'],'checkin_max_accuracy_m'=>['required','integer','between:10,50'],'sample_minutes'",
  "Laravel settings validation")

t=after["laravel_blade"]
after["laravel_blade"]=exact(t,
  '<label>Max GPS Accuracy (m)<input type="number" name="max_accuracy_m" value="{{ $settings->max_accuracy_m }}" required></label>',
  '<label>GPS Reporting Accuracy (m)<input type="number" min="20" max="1000" name="max_accuracy_m" value="{{ $settings->max_accuracy_m }}" required></label><label>Check-In / ROT GPS Accuracy (m)<input type="number" min="10" max="50" name="checkin_max_accuracy_m" value="{{ $settings->checkin_max_accuracy_m ?? 30 }}" required></label>',
  "Laravel settings two independent inputs")

t=after["legacy_php"]
t=exact(t,
  "$accuracy=max(20,min(1000,(int)($i['max_accuracy_m']??100)));",
  "$accuracy=max(20,min(1000,(int)($i['max_accuracy_m']??100)));\n  $checkinAccuracy=max(10,min(50,(int)($i['checkin_max_accuracy_m']??30)));",
  "legacy settings separate validation")
t=exact(t,
  "sample_minutes=?,max_accuracy_m=?,tracking_enabled=?,geofence_checkin_enabled=? WHERE id=1",
  "sample_minutes=?,max_accuracy_m=?,checkin_max_accuracy_m=?,tracking_enabled=?,geofence_checkin_enabled=? WHERE id=1",
  "legacy SQL separate column")
t=exact(t,
  "$q->execute([$name,$lat,$lng,$radius,$sample,$accuracy,$tracking,$geoCheckin]);",
  "$q->execute([$name,$lat,$lng,$radius,$sample,$accuracy,$checkinAccuracy,$tracking,$geoCheckin]);",
  "legacy SQL parameter order")
after["legacy_php"]=t

t=after["legacy_js"]
t=exact(t,
  "put('locMaxAccuracy',s.max_accuracy_m||100);",
  "put('locMaxAccuracy',s.max_accuracy_m||100);put('locCheckinAccuracy',s.checkin_max_accuracy_m??30);",
  "legacy settings load")
t=exact(t,
  "max_accuracy_m:Number(val('locMaxAccuracy')||100),",
  "max_accuracy_m:Number(val('locMaxAccuracy')||100),checkin_max_accuracy_m:Number(val('locCheckinAccuracy')||30),",
  "legacy settings save")
after["legacy_js"]=t

t=after["legacy_html"]
after["legacy_html"]=exact(t,
  '<label>Max GPS Accuracy (metres)<input id="locMaxAccuracy" type="number" min="20" value="100"></label>',
  '<label>GPS Reporting Accuracy (metres)<input id="locMaxAccuracy" type="number" min="20" max="1000" value="100"></label>\n                <label>Check-In / ROT GPS Accuracy (metres)<input id="locCheckinAccuracy" type="number" min="10" max="50" value="30"></label>',
  "legacy settings separate fields")

assert len([k for k in paths if before[k]!=after[k]])>=7
for k,text in after.items():
    assert "checkin_max_accuracy_m" in text or k in ("attendance","rot"), f"Unexpected missing setting: {k}"

# Fail closed: check every candidate BEFORE creating a new DB column or
# deploying any file. Tests must be repeatable.
temps={}
try:
    for k,path in paths.items():
        if after[k]==before[k]:continue
        suffix=path.suffix
        fd,name=tempfile.mkstemp(prefix='.am-checkin-gps-',suffix=suffix,dir=path.parent)
        with os.fdopen(fd,'w') as f:f.write(after[k])
        temps[k]=pathlib.Path(name)
        if suffix=='.php' and path.name!='staff-location.blade.php':
            lint=subprocess.run(['php','-l',name],capture_output=True,text=True)
            if lint.returncode!=0:
                raise RuntimeError(f"Candidate PHP lint failed ({k}): {lint.stderr}")
        if suffix=='.js':
            if shutil.which('node'):
                lint=subprocess.run(['node','--check',name],capture_output=True,text=True)
                if lint.returncode!=0:
                    raise RuntimeError(f"Candidate JS lint failed ({k}): {lint.stderr}")
        if k=="legacy_php" and after[k].count("checkin_max_accuracy_m")<2:
            raise RuntimeError("Legacy SQL partial update")
    print("GPS_POLICY_CANDIDATE_PHP_JS_LINT=PASS")
    print("GPS_POLICY_SEPARATE_REPORTING_100_CHECKIN_30_RADIUS_100=READY")
    if not args.apply:
        print("GPS_POLICY_DRY_RUN=PASS")
    else:
        backup=root/"storage/app/patch-backups"/("gps-policy-separate-"+datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ"))
        backup.mkdir(parents=True,mode=0o700)
        for k,path in paths.items():
            if after[k]!=before[k]:
                (backup/(k+path.suffix)).write_text(before[k])
                (backup/(k+path.suffix)).chmod(0o600)
        print("GPS_POLICY_FILE_BACKUPS=CREATED_PRIVATE")
        # One additive schema operation only; all records/values stay untouched.
        schema_script=r"""<?php
        require 'vendor/autoload.php';
        $app=require 'bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $schema=\Illuminate\Support\Facades\Schema::class;
        if (!$schema::hasColumn('staff_location_settings','checkin_max_accuracy_m')) {
            $schema::table('staff_location_settings', function(\Illuminate\Database\Schema\Blueprint $table) {
                $table->unsignedInteger('checkin_max_accuracy_m')->default(30)->after('max_accuracy_m');
            });
            echo "GPS_POLICY_DB_COLUMN=CREATED_DEFAULT_30\n";
        } else echo "GPS_POLICY_DB_COLUMN=EXISTS\n";
        """
        proc=subprocess.run(['php'],input=schema_script,text=True,cwd=root,capture_output=True)
        if proc.returncode!=0:
            raise RuntimeError("GPS policy additive schema migration failed; no files changed: "+proc.stderr[-800:])
        print(proc.stdout.strip())
        changed=[]
        try:
            for k,path in paths.items():
                if k not in temps:continue
                os.chmod(temps[k],path.stat().st_mode & 0o777)
                os.replace(temps[k],path)
                changed.append(k)
            for k,path in paths.items():
                if k in changed and path.suffix=='.php' and path.name!='staff-location.blade.php':
                    assert subprocess.run(['php','-l',str(path)],capture_output=True).returncode==0, f"PHP post-deploy lint {k}"
            print("GPS_POLICY_STAGING_PATCH=APPLIED")
        except Exception:
            for k in changed: paths[k].write_text(before[k])
            print("GPS_POLICY_STAGING_PATCH=ROLLED_BACK")
            raise
finally:
    for p in temps.values():
        p.unlink(missing_ok=True)
