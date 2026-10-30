#!/usr/bin/env python3
"""AM210 STAGING-only: put Admin reporting-interval control BELOW Live Duty Location.

check: read-only preflight of exact current ERP files + PHP/JS candidates
apply: backup, add one new controller + two Admin-only routes + bottom-page UI.
       Laravel syntax, route auth and view compilation verified, rollback on fail.

No migration, no JWT/API changes, no existing location/attendance/ROT writes.
"""
from pathlib import Path
from datetime import datetime,timezone
import argparse,os,re,shutil,subprocess,tempfile,json

p=argparse.ArgumentParser()
p.add_argument("mode",choices=["check","apply"])
p.add_argument("root")
args=p.parse_args()
root=Path(args.root).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!="assam-erp-staging" or not (root/"artisan").is_file() or not (root/"routes/web.php").is_file():
    raise SystemExit("AM210_STOP_NOT_STAGING_LARAVEL_ROOT")

routes=root/"routes/web.php"
view=root/"resources/views/admin/native/staff-live-location.blade.php"
controller=root/"app/Http/Controllers/Web/Am210LiveGpsReportingController.php"
source_controller=pkg/"files/app/Http/Controllers/Web/Am210LiveGpsReportingController.php"
panel_path=pkg/"panel.blade.php"
if not view.is_file() or not source_controller.is_file() or not panel_path.is_file():
    raise SystemExit("AM210_STOP_LIVE_VIEW_OR_BUNDLE_MISSING")

route_text=routes.read_text(encoding="utf8")
view_text=view.read_text(encoding="utf8")
controller_text=source_controller.read_text(encoding="utf8")
panel=panel_path.read_text(encoding="utf8")

marker="AM210_LIVE_DUTY_GPS_REPORTING_INTERVAL"
route_marker="AM210_LIVE_GPS_INTERVAL_ADMIN_ROUTES"
route_anchor=(
    r"Route::get('/staff-live-location/history', [\App\Http\Controllers\Web\StaffLiveLocationController::class, 'history'])"
    "->name('erp.staff-live-location.history');"
)
route_add=(
    "\n    // "+route_marker+" — secured inside existing ERP Admin route group\n"
    "    Route::get('/staff-live-location/reporting-interval', "
    r"[\App\Http\Controllers\Web\Am210LiveGpsReportingController::class, 'show'])"
    "->name('erp.staff-live-location.reporting-interval');\n"
    "    Route::post('/staff-live-location/reporting-interval', "
    r"[\App\Http\Controllers\Web\Am210LiveGpsReportingController::class, 'update'])"
    "->name('erp.staff-live-location.reporting-interval.update');"
)
if route_text.count(route_anchor)!=1:
    raise SystemExit("AM210_STOP_EXISTING_ADMIN_ROUTE_ANCHOR_CHANGED")
if view_text.count('<div class="sl-foot">')!=1:
    raise SystemExit("AM210_STOP_EXISTING_LIVE_PANEL_FOOTER_ANCHOR_CHANGED")
if 'id="slMap"' not in view_text or 'id="slList"' not in view_text:
    raise SystemExit("AM210_STOP_EXISTING_LIVE_LOCATION_UI_UNEXPECTED")
if not all(x in view_text for x in ["function drawStaff()", "const quality=p=>{", "function geofence(p)"]):
    raise SystemExit("AM210_STOP_DASHBOARD_STATUS_FUNCTIONS_UNEXPECTED")
if "gpsFix" in panel or "gps_token" in panel:
    raise SystemExit("AM210_STOP_UNEXPECTED_PANEL_CONTENT")

installed_view=marker in view_text
installed_route=route_marker in route_text
installed_controller=controller.is_file()
if len({installed_view,installed_route,installed_controller})!=1:
    raise SystemExit("AM210_STOP_PARTIAL_OLD_DEPLOYMENT_REVIEW_REQUIRED")
installed=installed_view
if installed:
    if controller.read_text(encoding="utf8")!=controller_text:
        raise SystemExit("AM210_STOP_EXISTING_CONTROLLER_DIFFERENT")
    print("AM210_ALREADY_INSTALLED_NO_CHANGES")
    raise SystemExit(0)

if ("/staff-live-location/reporting-interval" in route_text or
        "erp.staff-live-location.reporting-interval" in route_text):
    raise SystemExit("AM210_STOP_ROUTE_COLLISION")
if "sample_minutes" not in controller_text:
    raise SystemExit("AM210_STOP_CONTROLLER_MISSING_GLOBAL_SAMPLE_MINUTES")

# The anchor is a protected route inside the original ERP Admin group.
route_new=route_text.replace(route_anchor,route_anchor+route_add,1)
view_new=view_text.replace('<div class="sl-foot">',panel+'\n <div class="sl-foot">',1)

# At a 5-minute setting, 2+ minutes without a new fix is expected, not a
# server failure. Keep LIVE strictly <=2 min; add SCHEDULED for on-cadence
# last-known locations. Never pass SCHEDULED to strict geofence verification.
quality_old="""const quality=p=>{
  if(!p)return 'none';
  const age=ageMs(p);
  if(age>1800000)return 'offline';
  if(age>420000)return 'stale';
  if(age>120000)return 'delayed';
  return 'live';
};"""
quality_new="""// AM210 cadence-aware presentation only; no change to GPS authorization.
const quality=p=>{
  if(!p)return 'none';
  const age=ageMs(p);
  const minutes=Number(window.am210ActiveSampleMinutes)||1;
  const expected=Math.min(15,Math.max(1,minutes))*60000;
  if(age>1800000)return 'offline';
  if(age>Math.max(420000,expected*2+120000))return 'stale';
  if(age<=120000)return 'live'; // only fresh <2m may enter geofence checks
  if(expected>60000 && age<=expected+90000)return 'scheduled';
  return 'delayed';
};"""
if view_new.count(quality_old)!=1:
    raise SystemExit("AM210_STOP_GPS_FRESHNESS_FUNCTION_CHANGED")
view_new=view_new.replace(quality_old,quality_new,1)

# Make On-Schedule visible in status filter and counts, not falsely STALE.
filter_anchor='<option value="live">Live</option>'
if view_new.count(filter_anchor)!=1:
    raise SystemExit("AM210_STOP_GPS_FILTER_ANCHOR_CHANGED")
view_new=view_new.replace(filter_anchor,
    '<option value="live">Live</option><option value="scheduled">On Schedule (5 min)</option>',1)
count_old="setText('slLive',labels.filter(x=>x==='live').length);"
count_new="setText('slLive',labels.filter(x=>x==='live'||x==='scheduled').length);"
if view_new.count(count_old)!=1:
    raise SystemExit("AM210_STOP_GPS_METRIC_ANCHOR_CHANGED")
view_new=view_new.replace(count_old,count_new,1)

for old,new,label in [
    ("<span class=\"sl-label\">FRESH GPS</span>",
     "<span class=\"sl-label\">REPORTING OK</span>","metric label"),
    ("<span class=\"sl-sub\">Latest sample ≤2 min</span>",
     "<span class=\"sl-sub\">Fresh or within Admin reporting schedule</span>","metric explanation"),
    ("const priority={live:0,delayed:1,stale:2,offline:3,none:4};",
     "const priority={live:0,scheduled:1,delayed:2,stale:3,offline:4,none:5};","staff status priority"),
]:
    if view_new.count(old)!=1:
        raise SystemExit("AM210_STOP_"+label.upper().replace(" ","_")+"_ANCHOR_CHANGED")
    view_new=view_new.replace(old,new,1)

# Footnote is below the bottom control. Preserve safety explanation:
# ON SCHEDULE is not equivalent to a physically verified Check-In.
foot_old="<strong>LIVE</strong> means a genuine GPS fix within 2 minutes; <strong>DELAYED</strong> 2–7 minutes; <strong>STALE</strong> 7–30 minutes; <strong>OFFLINE</strong> over 30 minutes."
foot_new=("<strong>LIVE</strong> is a genuine fix within 2 minutes; "
          "<strong>ON SCHEDULE</strong> is a last-known position within the Admin-selected 5-minute reporting cadence, not a fresh Check-In verification; "
          "<strong>DELAYED/STALE</strong> follow the configured interval plus a tolerance; <strong>OFFLINE</strong> means over 30 minutes without a fix.")
if view_new.count(foot_old)!=1:
    raise SystemExit("AM210_STOP_GPS_FOOTNOTE_CHANGED")
view_new=view_new.replace(foot_old,foot_new,1)
if view_new.count(marker)!=1 or route_new.count(route_marker)!=1:
    raise SystemExit("AM210_STOP_PATCH_MARKER_INVALID")

def lint_php(data,label):
    with tempfile.NamedTemporaryFile(mode="w",suffix=".php",encoding="utf8",delete=False) as tmp:
        tmp.write(data);location=Path(tmp.name)
    try:
        result=subprocess.run(["php","-l",str(location)],capture_output=True,text=True,timeout=40)
        if result.returncode:raise SystemExit("AM210_PHP_LINT_FAIL "+label+" "+result.stderr[-500:])
    finally:location.unlink(missing_ok=True)

lint_php(route_new,"routes/web.php")
lint_php(controller_text,"new controller")
# Lint only the new inline JS. Existing scripts contain Blade-specific
# @json bindings, so do not misinterpret them as JavaScript syntax.
inline=re.search(r"<script>\s*\(function\(\)\{\s*'use strict';\s*const endpoint=@json\(route\('erp.staff-live-location.reporting-interval'\)\);.*?</script>",
    panel,re.S)
if not inline:raise SystemExit("AM210_STOP_PANEL_JS_NOT_FOUND")
js=inline.group().replace("<script>","").replace("</script>","")
js=js.replace("@json(route('erp.staff-live-location.reporting-interval'))",'"http://localhost/erp/staff-live-location/reporting-interval"')
js=js.replace("@json(csrf_token())",'"test-token"')
if "@json" in js:raise SystemExit("AM210_STOP_BLADE_JS_TOKEN")
if shutil.which("node"):
    with tempfile.NamedTemporaryFile(mode="w",suffix=".js",encoding="utf8",delete=False) as tmp:
        tmp.write(js);test=Path(tmp.name)
    try:
        result=subprocess.run(["node","--check",str(test)],capture_output=True,text=True,timeout=30)
        if result.returncode:raise SystemExit("AM210_JS_LINT_FAIL "+result.stderr[-500:])
    finally:test.unlink(missing_ok=True)

# Confirm existing ERP GPS settings row is usable. This is read-only;
# never create/overwrite workshop geofence settings in a patch preflight.
probe = r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=\Illuminate\Support\Facades\Schema::class;
if (!$schema::hasTable('staff_location_settings') ||
    !$schema::hasColumn('staff_location_settings','sample_minutes')) {
    fwrite(STDERR,"AM210_SAMPLE_MINUTES_SCHEMA_MISSING");exit(3);
}
$row=\Illuminate\Support\Facades\DB::table('staff_location_settings')->where('id',1)->first(['sample_minutes']);
if (!$row || !is_numeric($row->sample_minutes) ||
    (int)$row->sample_minutes<1 || (int)$row->sample_minutes>15) {
    fwrite(STDERR,"AM210_SAMPLE_MINUTES_INVALID_OR_ROW_MISSING");exit(4);
}
echo "AM210_EXISTING_GPS_SETTING_READONLY_PASS\n";
"""
dbcheck=subprocess.run(["php","-r",probe],cwd=root,capture_output=True,text=True,timeout=45)
if dbcheck.returncode:
    raise SystemExit("AM210_DB_POLICY_PREFLIGHT_FAILED "+dbcheck.stderr[-300:])
print(dbcheck.stdout.strip())

print("AM210_PRECHECK_PASS_PHP_JS")
print("AM210_PANEL_PLACEMENT=BELOW_STAFF_DETAILS_ABOVE_EXISTING_FOOTNOTE")
print("AM210_SAVES_ONLY=staff_location_settings.sample_minutes")
print("AM210_60SEC_OR_5MIN_WITH_CONSERVATIVE_LIVE_STALE_INDICATORS")
print("AM210_APP_6_3_20_REMAINS_FIXED_60SEC_UNTIL_NEW_APK")
if args.mode=="check":
    print("AM210_DRY_RUN_COMPLETE_NO_STAGING_WRITES")
    raise SystemExit(0)

if os.environ.get("AM210_STAGING_APPROVED")!="YES":
    raise SystemExit("AM210_STOP_SET_AM210_STAGING_APPROVED=YES_FOR_APPLY")

backup=root/"storage/app/am-backups"/(
    "AM210_LIVE_DUTY_INTERVAL_"+datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for source,rel in [(routes,"routes/web.php"),
                   (view,"resources/views/admin/native/staff-live-location.blade.php")]:
    dest=backup/rel
    dest.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(source,dest)
    dest.chmod(0o600)
written=[]
def atomic(path,text):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix=".am210_",dir=path.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8") as file:file.write(text)
        os.chmod(tmp,path.stat().st_mode & 0o777 if path.exists() else 0o644)
        os.replace(tmp,path)
        written.append(path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

try:
    atomic(controller,controller_text)
    atomic(routes,route_new)
    atomic(view,view_new)
    for cmd in (["php","artisan","route:clear"],
                ["php","artisan","view:clear"],
                ["php","artisan","view:cache"],
                ["php","artisan","route:list","--json"]):
        result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=100)
        if result.returncode:
            raise RuntimeError("Laravel validation failed: "+" ".join(cmd)+" "+result.stderr[-400:])
        if cmd[-1]=="--json":
            entries=json.loads(result.stdout)
            for name in ("erp.staff-live-location.reporting-interval",
                         "erp.staff-live-location.reporting-interval.update"):
                route=[x for x in entries if x.get("name")==name]
                if len(route)!=1 or not any(
                    "EnsureErpAdmin" in str(m) for m in route[0].get("middleware",[])
                ):
                    raise RuntimeError("AM210_PROTECTED_ROUTE_AUTH_FAILED "+name)
    print("AM210_APPLIED_STAGING_LIVE_DUTY_INTERVAL_PANEL=PASS")
    print("AM210_ADMIN_MIDDLEWARE=VERIFIED")
    print("AM210_BACKUP="+str(backup))
except Exception as err:
    if routes in written:shutil.copy2(backup/"routes/web.php",routes)
    if view in written:shutil.copy2(
        backup/"resources/views/admin/native/staff-live-location.blade.php",view)
    if controller in written:controller.unlink(missing_ok=True)
    for cmd in (["php","artisan","route:clear"],["php","artisan","view:clear"]):
        subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=45)
    raise SystemExit("AM210_APPLY_FAILED_BACKUP_RESTORED "+str(err))
