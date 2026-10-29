#!/usr/bin/env python3
"""AM202 STAGING-only historical inspection view compatibility label.
No DB writes. Only 2 AM189 presentation files. Fails on changed live files.

Usage: python3 install.py check|apply STAGING_ROOT
"""
from pathlib import Path
from datetime import datetime
import os, shutil, subprocess, sys, tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):
    raise SystemExit("USAGE install.py check|apply STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent.parent/"AM189_INSPECTION_QC_GUARD"/"files"
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM202_STOP_NOT_STAGING")
controller="app/Http/Controllers/Web/InspectionQcDefectController.php"
view="resources/views/inspection-defects/index.blade.php"

updated_controller=(package/controller).read_bytes()
updated_view=(package/view).read_bytes()
if updated_controller.count(b"'inspectionMeta'=>$this->inspectionMeta($job),")!=1:
    raise SystemExit("AM202_STOP_CONTROLLER_PACKAGE_INCOMPLETE")
if updated_view.count(b"AM202_INSPECTION_HISTORY_AWARENESS")!=1:
    raise SystemExit("AM202_STOP_VIEW_PACKAGE_INCOMPLETE")

def remove_new_controller(data):
    meta=b"            'inspectionMeta'=>$this->inspectionMeta($job),\n"
    if data.count(meta)!=1:raise SystemExit("AM202_STOP_META_ANCHOR")
    data=data.replace(meta,b"",1)
    begin=b"    /** Original saved point-count and missing-observation details; no mutation. */"
    after=b"    /** Read-only header data, compatible with older and staging JC field names. */"
    a=data.index(begin);b=data.index(after,a)
    return data[:a]+data[b:]

def remove_new_view(data):
    begin=b"{{-- AM202_INSPECTION_HISTORY_AWARENESS"
    after=b'<a class="btn secondary" href="{{ route(\'erp.job-cards.show\',$job->id) }}">'
    a=data.index(begin);b=data.index(after,a)
    return data[:a]+data[b:]

pairs=[
    (controller,remove_new_controller(updated_controller),updated_controller),
    (view,remove_new_view(updated_view),updated_view),
]
status=[]
for rel,old,new in pairs:
    actual=(root/rel).read_bytes()
    if actual==new:
        status.append("installed")
    elif actual==old:
        status.append("needs_patch")
    else:
        raise SystemExit("AM202_STOP_LIVE_FILE_CHANGED_REVIEW_REQUIRED "+rel)
print("AM202_CONTROLLER",status[0])
print("AM202_VIEW",status[1])
if status[0]!=status[1]:
    raise SystemExit("AM202_STOP_PARTIAL_INSTALL_REVIEW_REQUIRED")
if status[0]=="installed":
    print("AM202_ALREADY_INSTALLED")
    sys.exit(0)
if mode=="check":
    print("AM202_PREFLIGHT_PASS_NO_WRITES")
    print("AM202_ONLY_PRESENTATION_CHANGED")
    sys.exit(0)
backupdir=root/"storage/app/am-backups"/("AM202_INSPECTION_HISTORY_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backupdir.mkdir(parents=True,exist_ok=False)
for rel,old,_ in pairs:
    dst=backupdir/rel
    dst.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(root/rel,dst)

try:
    for rel,_,new in pairs:
        path=root/rel
        fd,temp=tempfile.mkstemp(prefix=".am202_",dir=path.parent)
        try:
            with os.fdopen(fd,"wb") as output:
                output.write(new)
            shutil.copymode(path,temp)
            os.replace(temp,path)
        finally:
            if os.path.exists(temp):os.unlink(temp)
    checks=[["php","-l",controller],["php","artisan","view:cache"]]
    for check in checks:
        result=subprocess.run(check,cwd=root,text=True,capture_output=True)
        if result.returncode:
            raise RuntimeError("AM202_VALIDATION_FAILED "+
                (result.stderr or result.stdout)[-600:])
    print("AM202_INSTALLED_LEGACY_FORMAT_AND_OBSERVATION_WARNINGS")
    print("AM202_BLADE_AND_PHP_VALIDATION_PASS")
    print("AM202_ORIGINAL_BACKUP",backupdir)
    print("AM202_NO_DB_QC_OR_INSPECTION_STATUS_CHANGE")
except Exception as e:
    for rel,_,_ in pairs: shutil.copy2(backupdir/rel,root/rel)
    subprocess.run(["php","artisan","view:clear"],cwd=root,text=True,capture_output=True)
    raise SystemExit("AM202_ROLLBACK_ORIGINALS_RESTORED "+str(e))
