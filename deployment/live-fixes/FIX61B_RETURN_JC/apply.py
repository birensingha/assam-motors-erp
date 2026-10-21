#!/usr/bin/env python3
"""FIX61B STAGING-only guarded install for one-button Return to JC (unpaid).
Changes only 2 existing PHP/Blade files and 2 new files. No schema/data changes.
"""
import os,sys,tempfile
from pathlib import Path
if len(sys.argv)!=4 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: apply.py check|apply STAGING_APP_ROOT PATCH_DIR')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
pkg=Path(sys.argv[3]).resolve()
if not (root/'artisan').is_file() or not (root/'routes/web.php').is_file():
    raise SystemExit('FIX61B_ABORT_NOT_LARAVEL_APP')
routes=root/'routes/web.php'
show=root/'resources/views/job-cards/show.blade.php'
controller=root/'app/Http/Controllers/Web/AdminJobCardReturnController.php'
partial=root/'resources/views/job-cards/return-to-jc.blade.php'
need={'AdminJobCardReturnController.php':controller,'return-to-jc.blade.php':partial}
marker='FIX61B_ONE_BUTTON_RETURN_JC_V1'
route_anchor='    // FIX56: protected billing correction page, credit note and original print.'
view_anchor='<div class="summary-grid job-summary">'
route_line="""    // FIX61B_ONE_BUTTON_RETURN_JC_V1: single audited unpaid invoice return.
    Route::post('/job-cards/{job}/return-to-jc',
        [\\App\\Http\\Controllers\\Web\\AdminJobCardReturnController::class,'store'])
        ->whereNumber('job')->name('erp.job-cards.return-to-jc');
"""
view_line="{{-- FIX61B_ONE_BUTTON_RETURN_JC_V1 --}}\n@include('job-cards.return-to-jc')\n"
old_routes=routes.read_text(encoding='utf8')
old_show=show.read_text(encoding='utf8')
if (marker in old_routes or marker in old_show or any(f.exists() for f in need.values())):
    if (marker in old_routes and marker in old_show and
        all(f.is_file() and f.read_text(encoding='utf8')==(pkg/name).read_text(encoding='utf8')
            for name,f in need.items())):
        print('FIX61B_ALREADY_APPLIED');sys.exit(0)
    raise SystemExit('FIX61B_CONFLICT_EXISTING_CODE_STOP')
if old_routes.count(route_anchor)!=1 or old_show.count(view_anchor)!=1:
    raise SystemExit('FIX61B_SOURCE_ANCHOR_MISMATCH')
if not all((pkg/name).is_file() for name in need):
    raise SystemExit('FIX61B_PACKAGE_INCOMPLETE')
new_routes=old_routes.replace(route_anchor,route_line+route_anchor,1)
new_show=old_show.replace(view_anchor,view_line+view_anchor,1)
assert old_routes!=new_routes and old_show!=new_show
if mode=='check':
    print('FIX61B_PREFLIGHT_PASS_UNPAID_ONLY')
    sys.exit(0)
def atomic(path,content,mode):
    fd,tmp=tempfile.mkstemp(prefix='.fix61b-',dir=path.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8') as fp:fp.write(content)
        os.chmod(tmp,mode)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
for name,dest in need.items():
    if dest.exists():raise SystemExit('FIX61B_FILE_COLLISION:'+name)
for name,dest in need.items():
    atomic(dest,(pkg/name).read_text(encoding='utf8'),0o644)
atomic(routes,new_routes,routes.stat().st_mode & 0o777)
atomic(show,new_show,show.stat().st_mode & 0o777)
print('FIX61B_APPLIED_NO_DATABASE_WRITES')
