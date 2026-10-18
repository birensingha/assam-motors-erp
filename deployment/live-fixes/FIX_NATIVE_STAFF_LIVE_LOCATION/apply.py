#!/usr/bin/env python3
"""Safely deploy new native ERP Staff Live Location view and read-only feed.

This script modifies ONLY two view files, nav layouts and the Laravel routes.
It does not alter staff, GPS logs, attendance, ROTs, credentials or DB schema.
Always invoke once with --dry-run before --apply. Atomic backups and rollback.
"""
import argparse,datetime,os,pathlib,re,shutil,subprocess,tempfile
a=argparse.ArgumentParser()
a.add_argument("--root",required=True)
a.add_argument("--bundle",required=True)
a.add_argument("--apply",action="store_true")
opts=a.parse_args()
root=pathlib.Path(opts.root).resolve()
bundle=pathlib.Path(opts.bundle).resolve()
assert (root/"artisan").is_file(),"Expected staging ERP Laravel root"
assert bundle.is_dir(),"Expected uploaded read-only module payload"
controller=root/"app/Http/Controllers/Web/StaffLiveLocationController.php"
view=root/"resources/views/admin/native/staff-live-location.blade.php"
routes=root/"routes/web.php"
nav=root/"resources/views/layouts/erp.blade.php"
adminnav=root/"resources/views/layouts/new-admin.blade.php"
existing=root/"resources/views/admin/native/staff-location.blade.php"
files={controller,view,routes,nav,adminnav,existing}
original={p:p.read_text() if p.is_file() else None for p in files}
modified=dict(original)
modified[controller]=(bundle/"StaffLiveLocationController.php").read_text()
modified[view]=(bundle/"staff-live-location.blade.php").read_text()

routeMarker="// AM_NATIVE_STAFF_LIVE_LOCATION_V1"
links=r"""Route::get('/staff-live-location', [\App\Http\Controllers\Web\StaffLiveLocationController::class, 'index'])->name('erp.staff-live-location');
Route::get('/staff-live-location/feed', [\App\Http\Controllers\Web\StaffLiveLocationController::class, 'feed'])->name('erp.staff-live-location.feed');
Route::get('/staff-live-location/history', [\App\Http\Controllers\Web\StaffLiveLocationController::class, 'history'])->name('erp.staff-live-location.history');"""
if routeMarker not in modified[routes]:
    mark="Route::middleware(['auth:web', EnsureErpAdmin::class])->prefix('erp')->group(function () {"
    assert modified[routes].count(mark)==1,"Required protected ERP admin route group not found exactly once"
    modified[routes]=modified[routes].replace(mark,mark+"\n"+routeMarker+"\n"+links+"\n",1)

link="""<a href="{{ route('erp.staff-live-location') }}" class="{{ request()->routeIs('erp.staff-live-location*')?'active':'' }}"><span>📡</span> Live Duty Location</a>"""
if link not in modified[nav]:
    old='<a href="{{ url(\'/legacy/admin/admin.html?page=staff-location\') }}"><span>📍</span> Staff Location</a>'
    assert modified[nav].count(old)==1,"ERP navigation legacy staff link missing"
    modified[nav]=modified[nav].replace(old,link,1)

if link not in modified[adminnav]:
    needle='<a href="{{ route(\'erp.attendance\') }}"><span>▣</span> Staff Attendance</a>'
    assert modified[adminnav].count(needle)==1,"NEW ERP admin sidebar anchor missing"
    modified[adminnav]=modified[adminnav].replace(needle,needle+"\n    "+link,1)

cta='<div style="padding:10px 0"><a class="btn primary" href="{{ route(\'erp.staff-live-location\') }}">📡 Open NEW Live Duty Location Map →</a></div>'
if cta not in modified[existing]:
    needle="@section('content')"
    assert modified[existing].count(needle)==1,"Existing native staff-location content section missing"
    modified[existing]=modified[existing].replace(needle,needle+"\n"+cta,1)

assert routeMarker in modified[routes]
assert "StaffLiveLocationController" in modified[controller]
assert "Live Duty Location" in modified[nav]
assert '@json(route(' in modified[view]
assert 'setInterval(()=>poll(),1000)' in modified[view]
assert 'gpsAccuracy' in modified[view]
assert 'Staff ROT' not in modified[controller]

temps={}
try:
    for target,new in modified.items():
        if original[target]==new:continue
        target.parent.mkdir(parents=True,exist_ok=True)
        fd,n=tempfile.mkstemp(prefix='.staff-live-',suffix=target.suffix,dir=target.parent)
        with os.fdopen(fd,'w') as f:f.write(new)
        temps[target]=pathlib.Path(n)
        if target in (routes,controller):
            result=subprocess.run(['php','-l',str(n)],capture_output=True,text=True)
            assert result.returncode==0, f"PHP syntax preflight: {target}: {result.stderr}"
    # Validate the embedded JS using the runner's Node if installed.
    javascript=re.search(r'<script>\s*(\(function\(\)\{.*?\}\)\(\);)\s*</script>',modified[view],re.S)
    assert javascript, "New dashboard script not found"
    js=javascript.group(1)
    js=js.replace("@json(route('erp.staff-live-location.feed'))", '"/erp/staff-live-location/feed"')
    js=js.replace("@json(route('erp.staff-live-location.history'))", '"/erp/staff-live-location/history"')
    js=js.replace('@json($mapSettings)', '{}')
    assert '@json(' not in js, "Unprocessed Blade data binding in JavaScript"
    fd,n=tempfile.mkstemp(prefix='staff-live-ui-',suffix='.js')
    with os.fdopen(fd,'w') as f:f.write(js)
    try:
        if shutil.which('node'):
            check=subprocess.run(['node','--check',n],capture_output=True,text=True)
            assert check.returncode==0, "Dashboard JavaScript syntax error: "+check.stderr
    finally:
        os.unlink(n)
    print("STAFF_LIVE_MODULE_PHP_JAVASCRIPT_LINT=PASS")
    print("STAFF_LIVE_MODULE_CHANGED_FILES="+str(sum(x!=original[k] for k,x in modified.items())))
    if not opts.apply:
        print("STAFF_LIVE_MODULE_DRY_RUN=PASS")
    else:
        stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
        backup=root/"storage/app/patch-backups"/("native-staff-live-"+stamp)
        backup.mkdir(mode=0o700,parents=True)
        for target,old in original.items():
            if modified[target]!=old and old is not None:
                copy=backup/(target.name.replace('.blade.php','.blade')+'.original')
                if copy.exists():copy=backup/(target.parent.name+'-'+target.name+'.original')
                copy.write_text(old);copy.chmod(0o600)
        deployed=[]
        try:
            for target,tmp in temps.items():
                if target in (controller,view):
                    os.chmod(tmp,0o644)
                else:
                    os.chmod(tmp,target.stat().st_mode & 0o777)
                os.replace(tmp,target)
                deployed.append(target)
            for target in (routes,controller):
                result=subprocess.run(['php','-l',str(target)],capture_output=True,text=True)
                assert result.returncode==0, f"post deploy syntax failure: {target}"
            cmd=subprocess.run(['php','artisan','route:clear'],cwd=root,capture_output=True,text=True)
            assert cmd.returncode==0,'Route cache invalidation failed'
            cmd=subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,text=True)
            assert cmd.returncode==0,'View cache invalidation failed'
            print('STAFF_LIVE_MODULE_STAGING_DEPLOYED=PASS')
            print('STAFF_LIVE_MODULE_PRIVATE_BACKUP='+str(backup))
        except Exception:
            for target in deployed:
                old=original[target]
                if old is None:target.unlink(missing_ok=True)
                else:target.write_text(old)
            print('STAFF_LIVE_MODULE_STAGING_ROLLBACK=COMPLETE')
            raise
finally:
    for path in temps.values():
        path.unlink(missing_ok=True)
