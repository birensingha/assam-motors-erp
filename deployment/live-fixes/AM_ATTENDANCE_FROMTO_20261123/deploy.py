#!/usr/bin/env python3
"""AM Attendance Date Range STAGING installer, idempotent and rollback-safe.
Only changes a new admin report controller+view, routes/web.php, and the
Attendance page navigation link. Does not edit DB or financial records.
"""
import argparse
import datetime
import hashlib
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

MARKER = "AM_ATTENDANCE_FROMTO_20261123"
ROUTE_ANCHOR = "Route::get('/attendance', [AdminNativeModuleController::class,'attendance'])->name('erp.attendance');"
NEW_ROUTE = ("    // "+MARKER+": read-only dated attendance and indicative salary preview.\n"
             "    Route::get('/attendance/date-range', "
             "[\\App\\Http\\Controllers\\Web\\AdminAttendanceRangeController::class,'index'])"
             "->name('erp.attendance.range');")
LINK = ("""{{-- AM_ATTENDANCE_FROMTO_20261123: date-wise historical report; monthly matrix unchanged --}}\n"""
        """<div style="margin:9px 0;padding:9px;background:#edf5ff;border:1px solid #c8dcf5;border-radius:9px">"""
        """<a href="{{ route('erp.attendance.range') }}" """
        """style="color:#154e89;font-weight:800;text-decoration:underline">"""
        """📅 From Date–To Date Attendance Report · CSV / Print · Salary Preview</a></div>\n""")

def run(command, cwd, check=True, capture=False):
    return subprocess.run(command, cwd=cwd, check=check, text=True,
                          stdout=subprocess.PIPE if capture else None,
                          stderr=subprocess.STDOUT if capture else None,
                          timeout=90)

def sha(data):
    return hashlib.sha256(data.encode('utf-8')).hexdigest()[:16]

def main():
    ap=argparse.ArgumentParser()
    ap.add_argument("--root",required=True)
    ap.add_argument("--payload",required=True)
    ap.add_argument("--apply",action="store_true")
    args=ap.parse_args()
    root=Path(args.root).expanduser().resolve()
    payload=Path(args.payload).expanduser().resolve()
    if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
        raise RuntimeError("Not verified STAGING Laravel root; refusing deployment")
    if not (root/"vendor/autoload.php").exists():
        raise RuntimeError("Laravel vendor/autoload missing")
    if not (payload/"app/Http/Controllers/Web/AdminAttendanceRangeController.php").is_file():
        raise RuntimeError("Missing controller payload")
    if not (payload/"resources/views/admin/native/attendance-range.blade.php").is_file():
        raise RuntimeError("Missing Blade payload")
    routes=root/"routes/web.php"
    attend=root/"resources/views/admin/native/attendance.blade.php"
    if not routes.is_file() or not attend.is_file():
        raise RuntimeError("Existing ERP routes or Attendance view missing")

    oldRoutes=routes.read_text(encoding="utf-8")
    if oldRoutes.count(ROUTE_ANCHOR)!=1:
        raise RuntimeError("Attendance route anchor differs from inspected STAGING source")
    if "erp.attendance.range" in oldRoutes and MARKER not in oldRoutes:
        raise RuntimeError("Another date-range report route already exists; manual review needed")
    newRoutes=(oldRoutes if MARKER in oldRoutes else
               oldRoutes.replace(ROUTE_ANCHOR, ROUTE_ANCHOR+"\n"+NEW_ROUTE))
    oldView=attend.read_text(encoding="utf-8")
    if oldView.count("@section('content')") != 1:
        raise RuntimeError("Existing Attendance view unexpected content section")
    newView=(oldView if MARKER in oldView else
             oldView.replace("@section('content')","@section('content')\n"+LINK))

    ctlPath=root/"app/Http/Controllers/Web/AdminAttendanceRangeController.php"
    viewPath=root/"resources/views/admin/native/attendance-range.blade.php"
    controller=(payload/"app/Http/Controllers/Web/AdminAttendanceRangeController.php").read_text(encoding="utf-8")
    newBlade=(payload/"resources/views/admin/native/attendance-range.blade.php").read_text(encoding="utf-8")

    if ctlPath.exists() and "final class AdminAttendanceRangeController" not in ctlPath.read_text(encoding="utf-8"):
        raise RuntimeError("Conflicting existing controller; stop")
    if viewPath.exists() and "Date Range Attendance Report" not in viewPath.read_text(encoding="utf-8"):
        raise RuntimeError("Conflicting existing date-range view; stop")

    updates = {routes:newRoutes, attend:newView, ctlPath:controller, viewPath:newBlade}
    with tempfile.TemporaryDirectory(prefix="amatt-preflight-") as tmp:
        td=Path(tmp)
        for name,data in (("routes.php",newRoutes),("controller.php",controller)):
            p=td/name
            p.write_text(data,encoding="utf-8")
            result=run(["php","-l",str(p)],root,capture=True)
            print("PHP_LINT_OK",name,result.stdout.strip()[-120:])
        # Compile both Blade views without writing changes into ERP, then php -l.
        # Laravel's Blade facade is bootstrapped under existing staging app.
        blade_compile = r'''
            require 'vendor/autoload.php';
            $app=require 'bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            echo \Illuminate\Support\Facades\Blade::compileString(file_get_contents($argv[1]));
        '''
        for i,(name,source) in enumerate((("existing-attendance",newView),("new-date-range",newBlade))):
            raw=td/(name+".blade.php")
            raw.write_text(source,encoding="utf-8")
            compiled=run(["php","-r",blade_compile,str(raw)],root,capture=True)
            compiled_file=td/(name+".compiled.php")
            compiled_file.write_text(compiled.stdout,encoding="utf-8")
            run(["php","-l",str(compiled_file)],root,capture=True)
            print("BLADE_COMPILE_AND_PHP_LINT_OK",name)

    print("STAGING_ROOT_OK")
    for file,data in updates.items():
        print("CANDIDATE",file.relative_to(root),"sha",sha(data),"changed",not file.exists() or file.read_text(encoding="utf-8")!=data)

    if not args.apply:
        print("AM_ATTENDANCE_DRY_RUN_OK_NO_STAGING_FILES_CHANGED")
        return

    stamp=datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backup=root/"storage/app/attendance_range_deploy_backups"/stamp
    backup.mkdir(parents=True,exist_ok=False)
    touched=[]
    try:
        for target,data in updates.items():
            rel=target.relative_to(root)
            if target.exists():
                b=backup/rel
                b.parent.mkdir(parents=True,exist_ok=True)
                shutil.copy2(target,b)
                print("BACKUP",rel)
            else:
                print("NEW_FILE",rel)
            touched.append((target,target.exists()))
            target.parent.mkdir(parents=True,exist_ok=True)
            with tempfile.NamedTemporaryFile(mode="w",encoding="utf-8",dir=str(target.parent),delete=False) as f:
                f.write(data)
                pending=Path(f.name)
            if target.exists():
                os.chmod(pending,target.stat().st_mode & 0o777)
            else:
                os.chmod(pending,0o644)
            os.replace(pending,target)

        run(["php","artisan","route:clear"],root,capture=True)
        listed=run(["php","artisan","route:list","--path=erp/attendance"],root,capture=True).stdout
        if "attendance/date-range" not in listed:
            raise RuntimeError("New attendance date-range route missing after deploy")
        run(["php","-l",str(routes)],root,capture=True)
        run(["php","-l",str(ctlPath)],root,capture=True)
        print("ROUTE_REGISTRATION_OK")
        print("STAGING_ATTENDANCE_DATE_RANGE_DEPLOYED")
        print("BACKUP_DIR",backup)
    except BaseException:
        for target,existed in reversed(touched):
            if existed:
                origin=backup/target.relative_to(root)
                if origin.exists():
                    shutil.copy2(origin,target)
            elif target.exists():
                target.unlink()
        try:
            run(["php","artisan","route:clear"],root,capture=True)
        except BaseException:
            pass
        print("STAGING_ROLLED_BACK",file=sys.stderr)
        raise

if __name__=="__main__":
    try:
        main()
    except BaseException as e:
        print("DEPLOY_ABORTED",type(e).__name__,str(e)[:500],file=sys.stderr)
        sys.exit(1)
