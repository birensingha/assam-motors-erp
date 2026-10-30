#!/usr/bin/env python3
"""AM210 STAGING Live Duty Location user-entered GPS interval.

check: read-only source, DB, route and PHP/JS preflight
prepare: copy ONLY non-destructive sample_seconds migration into STAGING
apply: after DB backup + migration, install/upgrade existing panel and
       Admin-only controller/routes with rollback on syntax/auth failure

No check-in, ROT, location history, consent, geofence or GPS log mutation.
"""
from pathlib import Path
from datetime import datetime,timezone
import argparse,os,re,shutil,subprocess,tempfile,json
p=argparse.ArgumentParser()
p.add_argument("mode",choices=["check","prepare","apply"])
p.add_argument("root")
args=p.parse_args()
root=Path(args.root).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM210_STOP_NOT_STAGING_LARAVEL_ROOT")

routes=root/"routes/web.php"
view=root/"resources/views/admin/native/staff-live-location.blade.php"
controller=root/"app/Http/Controllers/Web/Am210LiveGpsReportingController.php"
source_controller=pkg/"files/app/Http/Controllers/Web/Am210LiveGpsReportingController.php"
panel_path=pkg/"panel.blade.php"
migration_rel="database/migrations/2026_10_30_180000_add_am210_sample_seconds_to_staff_location_settings.php"
migration_source=pkg/"files"/migration_rel
migration_dest=root/migration_rel
for path in (routes,view,source_controller,panel_path,migration_source):
    if not path.is_file():raise SystemExit("AM210_STOP_REQUIRED_FILE_MISSING "+str(path.name))

def lint_php(source,label):
    fd,tmp=tempfile.mkstemp(prefix=".am210_php_",suffix=".php")
    try:
        with os.fdopen(fd,"w",encoding="utf8") as f:f.write(source)
        x=subprocess.run(["php","-l",tmp],capture_output=True,text=True,timeout=35)
        if x.returncode:raise SystemExit("AM210_PHP_LINT_FAILED "+label+" "+x.stderr[-500:])
    finally:os.unlink(tmp)

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
in_view=marker in view_text
in_route=route_marker in route_text
in_controller=controller.is_file()
if len({in_view,in_route,in_controller})!=1:
    raise SystemExit("AM210_STOP_PARTIAL_OLD_INSTALLATION_REVIEW_REQUIRED")
already_in=in_view
if not all(t in view_text for t in
           ('id="slMap"','id="slList"','function drawStaff()','function geofence(p)')):
    raise SystemExit("AM210_STOP_ORIGINAL_DASHBOARD_ANCHORS_MISSING")
if not already_in and (
    view_text.count('<div class="sl-foot">')!=1 or route_text.count(route_anchor)!=1
):
    raise SystemExit("AM210_STOP_FRESH_INSTALL_ANCHOR_MISMATCH")
if already_in:
    if view_text.count(marker)!=1 or route_text.count(route_marker)!=1:
        raise SystemExit("AM210_STOP_AMBIGUOUS_EXISTING_PANEL")
    start=view_text.index('{{-- '+marker)
    end=view_text.find('</script>',start)
    if start<0 or end<0 or end-start>14000:
        raise SystemExit("AM210_STOP_EXISTING_PANEL_END_UNKNOWN")
    old_panel=view_text[start:end+len('</script>')]
    if ('id="am210IntervalNumber"' not in old_panel and
        'id="am210Interval"' not in old_panel):
        raise SystemExit("AM210_STOP_EXISTING_AM210_PANEL_UNKNOWN")
    view_new=view_text[:start]+panel+view_text[end+len('</script>'):]
    route_new=route_text
else:
    view_new=view_text.replace('<div class="sl-foot">',panel+'\n <div class="sl-foot">',1)
    route_new=route_text.replace(route_anchor,route_anchor+route_add,1)

# Presentation only: avoid false STALE at slow cadence, but never treat old
# fixes as fresh geofence verification. Existing check-in validation untouched.
quality_pattern=r"const quality=p=>\{\n.*?\n\};"
matches=list(re.finditer(quality_pattern,view_new,re.S))
if len(matches)!=1:raise SystemExit("AM210_STOP_QUALITY_FUNCTION_AMBIGUOUS")
original_quality=matches[0].group(0)
if "ageMs(p)" not in original_quality or "return 'live'" not in original_quality:
    raise SystemExit("AM210_STOP_QUALITY_LOGIC_UNEXPECTED")
new_quality="""const quality=p=>{
  if(!p)return 'none';
  const age=ageMs(p);
  const seconds=Number(window.am210ActiveSampleSeconds)||60;
  const expected=Math.min(900,Math.max(30,seconds))*1000;
  if(age>1800000)return 'offline';
  if(age>Math.max(420000,expected*2+120000))return 'stale';
  if(age<=120000)return 'live'; // only fresh <=2m is GPS-verified
  if(expected>120000 && age<=expected+90000)return 'scheduled';
  return 'delayed';
};"""
view_new=view_new[:matches[0].start()]+new_quality+view_new[matches[0].end():]
# Insert a scheduled filter only for a fresh panel. Prior AM210 versions
# already have one; never produce two duplicate filter options.
if '<option value="scheduled">' not in view_new:
    if view_new.count('<option value="live">Live</option>')!=1:
        raise SystemExit("AM210_STOP_LIVE_STATUS_FILTER_UNEXPECTED")
    view_new=view_new.replace('<option value="live">Live</option>',
        '<option value="live">Live</option><option value="scheduled">On Schedule</option>',1)
for old,new in [
    ('<option value="scheduled">On Schedule (5 min)</option>',
     '<option value="scheduled">On Schedule</option>'),
    ("setText('slLive',labels.filter(x=>x==='live').length);",
     "setText('slLive',labels.filter(x=>x==='live'||x==='scheduled').length);"),
    ('<span class="sl-label">FRESH GPS</span>',
     '<span class="sl-label">REPORTING OK</span>'),
    ('<span class="sl-sub">Latest sample ≤2 min</span>',
     '<span class="sl-sub">Fresh or within Admin reporting schedule</span>'),
    ("const priority={live:0,delayed:1,stale:2,offline:3,none:4};",
     "const priority={live:0,scheduled:1,delayed:2,stale:3,offline:4,none:5};"),
    ('Admin-selected 5-minute reporting cadence',
     'Admin-selected reporting cadence'),
]:
    if old in view_new:
        if view_new.count(old)!=1:raise SystemExit("AM210_STOP_AMBIGUOUS_EXISTING_VIEW_TEXT")
        view_new=view_new.replace(old,new,1)

foot_old="<strong>LIVE</strong> means a genuine GPS fix within 2 minutes; <strong>DELAYED</strong> 2–7 minutes; <strong>STALE</strong> 7–30 minutes; <strong>OFFLINE</strong> over 30 minutes."
foot_new="<strong>LIVE</strong> means a GPS fix within 2 minutes; <strong>ON SCHEDULE</strong> is a last-known point within the Admin reporting cadence, not verified Check-In; <strong>DELAYED/STALE</strong> follow that cadence; <strong>OFFLINE</strong> means over 30 minutes."
if foot_old in view_new:view_new=view_new.replace(foot_old,foot_new,1)
if view_new.count('<option value="scheduled">')!=1:
    raise SystemExit("AM210_STOP_DUPLICATE_SCHEDULED_FILTER")
if view_new.count(marker)!=1 or route_new.count(route_marker)!=1:
    raise SystemExit("AM210_STOP_PATCH_MARKER_ERROR")
if not all(x in controller_text for x in ("sample_seconds","sample_minutes","interval_seconds","isAdmin()")):
    raise SystemExit("AM210_STOP_CONTROLLER_CONTRACT_CHANGED")

lint_php(route_new,"routes/web.php")
lint_php(controller_text,"AM210 controller")
lint_php(migration_source.read_text(encoding="utf8"),"sample_seconds migration")
scripts=re.findall(r"<script>\s*(.*?)\s*</script>",panel,re.S)
if len(scripts)!=1:raise SystemExit("AM210_STOP_PANEL_SCRIPT_INVALID")
js=(scripts[0].replace("@json(route('erp.staff-live-location.reporting-interval'))",'"http://localhost/erp/staff-live-location/reporting-interval"')
    .replace("@json(csrf_token())",'"test-csrf"'))
if "@json" in js:raise SystemExit("AM210_STOP_BLADE_JS_TOKENS")
if shutil.which("node"):
    fd,tmp=tempfile.mkstemp(prefix=".am210_js_",suffix=".js")
    try:
        with os.fdopen(fd,"w",encoding="utf8") as f:f.write(js)
        check=subprocess.run(["node","--check",tmp],capture_output=True,text=True,timeout=25)
        if check.returncode:raise SystemExit("AM210_JS_LINT_FAIL "+check.stderr[-500:])
    finally:os.unlink(tmp)

# Only read scalar settings, not GPS logs, tokens, staff or coordinates.
probe=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=\Illuminate\Support\Facades\Schema::class;
if (!$schema::hasTable('staff_location_settings') ||
    !$schema::hasColumn('staff_location_settings','sample_minutes')) {
    fwrite(STDERR,'AM210_STAFF_SETTINGS_MISSING');exit(3);
}
$has=$schema::hasColumn('staff_location_settings','sample_seconds');
$row=\Illuminate\Support\Facades\DB::table('staff_location_settings')->where('id',1)->first(['sample_minutes']);
if(!$row || !is_numeric($row->sample_minutes)) {fwrite(STDERR,'AM210_STAFF_SETTINGS_ROW_MISSING');exit(4);}
$minutes=(int)$row->sample_minutes;
if($minutes<1 || $minutes>15){fwrite(STDERR,'AM210_LEGACY_INTERVAL_OUTSIDE_1_15_MIN');exit(5);}
echo json_encode(['legacy_sample_minutes'=>$minutes,'sample_seconds_column'=>$has]);
"""
check=subprocess.run(["php","-r",probe],cwd=root,capture_output=True,text=True,timeout=50)
if check.returncode:
    raise SystemExit("AM210_DB_READ_ONLY_CHECK_FAILED "+check.stderr[-400:])
state=json.loads(check.stdout)
has_seconds=state['sample_seconds_column']
print("AM210_PHP_JS_PREFLIGHT_PASS")
print("AM210_PANEL_POSITION=BELOW_LIVE_DUTY_LOCATION")
print("AM210_INTERVAL=ADMIN_CUSTOM_30_TO_900_SECONDS")
print("AM210_MIGRATION_COLUMN="+("PRESENT" if has_seconds else "MISSING"))
print("AM210_EXISTING_PANEL="+("YES" if already_in else "NO"))
if args.mode=="check":
    print("AM210_CHECK_COMPLETE_NO_WRITES")
    raise SystemExit(0)
if args.mode=="prepare":
    if migration_dest.is_file():
        if migration_dest.read_bytes()!=migration_source.read_bytes():
            raise SystemExit("AM210_STOP_EXISTING_MIGRATION_CONFLICT")
        print("AM210_MIGRATION_ALREADY_PREPARED")
    else:
        migration_dest.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(migration_source,migration_dest)
        print("AM210_MIGRATION_PREPARED_ONLY_NO_DB_WRITES")
    print("AM210_NEXT=BACKUP_DB_THEN_php_artisan_migrate_path_FORCE")
    raise SystemExit(0)
if os.environ.get("AM210_STAGING_APPROVED")!="YES":
    raise SystemExit("AM210_STOP_EXPLICIT_APPLY_APPROVAL_REQUIRED")
if not has_seconds:
    raise SystemExit("AM210_STOP_RUN_NON_DESTRUCTIVE_SAMPLE_SECONDS_MIGRATION_FIRST")
if already_in and route_text==route_new and view_text==view_new and controller.read_text(encoding="utf8")==controller_text:
    print("AM210_CUSTOM_INTERVAL_ALREADY_INSTALLED_NO_WRITES")
    raise SystemExit(0)

backup=root/"storage/app/am-backups"/(
    "AM210_CUSTOM_GPS_INTERVAL_"+datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for f,rel in [(routes,"routes/web.php"),(view,"resources/views/admin/native/staff-live-location.blade.php")]:
    dest=backup/rel;dest.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(f,dest);dest.chmod(0o600)
if controller.is_file():
    dest=backup/"app/Http/Controllers/Web/Am210LiveGpsReportingController.php"
    dest.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(controller,dest);dest.chmod(0o600)
written=[]
def atomic(path,content):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix=".am210_",dir=path.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8") as f:f.write(content)
        os.chmod(tmp,path.stat().st_mode&0o777 if path.exists() else 0o644)
        os.replace(tmp,path);written.append(path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
try:
    atomic(controller,controller_text)
    if routes.read_text(encoding="utf8")!=route_new:atomic(routes,route_new)
    if view.read_text(encoding="utf8")!=view_new:atomic(view,view_new)
    for cmd in (["php","artisan","route:clear"],
                ["php","artisan","view:clear"],
                ["php","artisan","view:cache"],
                ["php","artisan","route:list","--json"]):
        result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=110)
        if result.returncode:
            raise RuntimeError("AM210_LARAVEL_VALIDATION "+str(cmd)+" "+result.stderr[-500:])
        if cmd[-1]=="--json":
            registered=json.loads(result.stdout)
            for name in ("erp.staff-live-location.reporting-interval","erp.staff-live-location.reporting-interval.update"):
                match=[x for x in registered if x.get("name")==name]
                if len(match)!=1 or not any("EnsureErpAdmin" in str(m) for m in match[0].get("middleware",[])):
                    raise RuntimeError("AM210_ROUTE_AUTH_REQUIRED "+name)
    print("AM210_CUSTOM_GPS_INTERVAL_STAGING_APPLIED")
    print("AM210_ADMIN_AUTH_VERIFIED")
    print("AM210_BACKUP="+str(backup))
except Exception as e:
    for f,rel in [(routes,"routes/web.php"),(view,"resources/views/admin/native/staff-live-location.blade.php")]:
        if f in written:shutil.copy2(backup/rel,f)
    if controller in written:
        old=backup/"app/Http/Controllers/Web/Am210LiveGpsReportingController.php"
        if old.is_file():shutil.copy2(old,controller)
        else:controller.unlink(missing_ok=True)
    for cmd in (["php","artisan","route:clear"],["php","artisan","view:clear"]):
        subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=45)
    raise SystemExit("AM210_FAILED_FILES_RESTORED_DB_COLUMN_RETAINED "+str(e))
