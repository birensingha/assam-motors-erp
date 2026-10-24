#!/usr/bin/env python3
"""AM122: backup-first STAGING-ONLY view patch for compact Void ROT toolbar.
Usage: python3 deploy.py check|apply /path/to/staging-laravel
No controller/route/DB mutation. Existing AM121 form and V647 timer remain.
"""
import datetime
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ("check","apply"):
    raise SystemExit("Usage: python3 deploy.py check|apply STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
payload=Path(__file__).resolve().with_name("toolbar-fragment.blade.php")
partial=root/"resources/views/job-cards/partials/completed-rot-void.blade.php"
show=root/"resources/views/job-cards/show.blade.php"
time_editor=root/"resources/views/job-cards/rot-time-editor.blade.php"
if not (root/"artisan").is_file():
    raise SystemExit("AM122_STOP_NOT_LARAVEL_ROOT")
for p in (payload,partial,show,time_editor):
    if not p.is_file():raise SystemExit("AM122_STOP_MISSING="+str(p))
src=partial.read_text("utf8",errors="surrogateescape")
v=show.read_text("utf8",errors="surrogateescape")
time=time_editor.read_text("utf8",errors="surrogateescape")
fragment=payload.read_text("utf8",errors="surrogateescape")
marker="AM122_VOID_INLINE_ROT_ACTIONS"
if marker in src:
    if src.count(marker)==1:
        print("AM122_ALREADY_APPLIED");sys.exit(0)
    raise SystemExit("AM122_STOP_DUPLICATE_MARKER")
assertions=[
 ("AM121_VOID_PANEL","class=\"am121-void-panel\"" in src),
 ("AM121_VOID_ACTION","erp.job-cards.labour.void-completed" in src),
 ("AM121_IN_MAIN_JC","@include('job-cards.partials.completed-rot-void'" in v),
 ("AM121_EXISTING_WIP_ADMIN_GUARD","$am121Admin && $am121Wip && $am121Completed" in src),
 ("V647_TIME_EDITOR","class=\"am-v647-time-editor\"" in time),
 ("V647_EDITOR_INCLUDE","rot-time-editor" in v),
 ("FIX90_BILLING_BUTTON","data-am90-edit" in v),
 ("ROT_LINE_ID","data-am90-labour-id" in v),
 ("FRAGMENT_MARKER",fragment.count(marker)==1),
 ("FRAGMENT_BLADE_ONCE","@once" in fragment and "@endonce" in fragment),
 ("FRAGMENT_MOVES_ORIGINAL_FORM","bar.appendChild(voidPanel)" in fragment),
 ("FRAGMENT_MOVES_EXISTING_TIME_EDITOR","bar.appendChild(target)" in fragment),
]
for k,ok in assertions:
    print("AM122_CHECK_"+k+"="+("PASS" if ok else "FAIL"))
if not all(x[1] for x in assertions):
    raise SystemExit("AM122_STOP_VIEW_STRUCTURE_MISMATCH_NO_CHANGES")
candidate=src.rstrip()+"\n"+fragment
if candidate.count(marker)!=1:
    raise SystemExit("AM122_STOP_CANDIDATE_INVALID")
if mode=="check":
    print("AM122_READ_ONLY_PREFLIGHT_PASS_NO_WRITES")
    sys.exit(0)

backup_dir=root/"storage/app/am-backups"
backup_dir.mkdir(parents=True,exist_ok=True)
backup=backup_dir/("completed-rot-void_AM122_"+datetime.datetime.now().strftime("%Y%m%d_%H%M%S")+"_"+str(os.getpid())+".blade.php")
shutil.copy2(partial,backup)
print("AM122_BACKUP="+str(backup))

def rollback(reason):
    shutil.copy2(backup,partial)
    subprocess.run(["php","artisan","view:clear"],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    print("AM122_ROLLBACK_RESTORED="+str(backup))
    raise SystemExit("AM122_DEPLOY_FAILED="+reason)

try:
    fd,tmp=tempfile.mkstemp(prefix=".am122_",dir=partial.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(candidate)
        os.chmod(tmp,partial.stat().st_mode & 0o777)
        os.replace(tmp,partial)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    for command in (["php","artisan","view:clear"],["php","artisan","view:cache"]):
        result=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=100)
        if result.returncode:
            rollback("BLADE_CACHE_"+str(result.returncode)+"_"+result.stderr[-250:])
        print((result.stdout or result.stderr)[-270:])
    compiled_files=[
        p for p in (root/"storage/framework/views").glob("*.php")
        if 'id="AM122_VOID_INLINE_ROT_ACTIONS"' in p.read_text("utf8",errors="replace")
    ]
    if len(compiled_files)!=1:
        rollback("COMPILED_AM122_MARKER_MISSING_OR_DUPLICATE_"+str(len(compiled_files)))
    result=subprocess.run(["php","-l",str(compiled_files[0])],
                          cwd=root,capture_output=True,text=True,timeout=20)
    if result.returncode:
        rollback("COMPILED_BLADE_PHP_LINT_FAILED_"+result.stderr[-240:])
    print(result.stdout.strip())
except SystemExit:
    raise
except BaseException as exc:
    rollback(str(exc))
print("AM122_COMPILED_INLINE_TOOLBAR_PASS")
print("AM122_SOURCE_AND_BLADE_DEPLOY_PASS")
print("AM122_DATABASE_ROUTES_CONTROLLERS_UNCHANGED")
print("AM122_BROWSER_PLACEMENT_NOT_YET_VERIFIED")
