#!/usr/bin/env python3
"""Emergency AM126-only view rollback from verified staging backup (no DB writes)."""
import os,sys,shutil,subprocess,tempfile
from pathlib import Path
if len(sys.argv)!=2:raise SystemExit("Use: ROLLBACK_STAGING.py STAGING_ROOT")
root=Path(sys.argv[1]).resolve()
view=root/"resources/views/dashboard/partials/rot-live-clock.blade.php"
if not (root/"artisan").is_file() or not view.is_file():
    raise SystemExit("AM126_ROLLBACK_STOP_WRONG_ROOT")
text=view.read_text("utf8",errors="surrogateescape")
marker="AM126_COMPLETED_TAT_FROM_CORRECTED_START_END"
if marker not in text:
    print("AM126_ROLLBACK_NOT_NEEDED_ALREADY_BASELINE")
    sys.exit(0)
bk=root/"storage/app/am-backups"
candidates=sorted(
    list(bk.glob("AM126_rot-live-clock_*.blade.php"))+
    list(bk.glob("AM126_GH_STAGING_PREAPPLY_*.blade.php")),
    key=lambda p:p.stat().st_mtime,reverse=True
)
safe=[]
for p in candidates:
    candidate=p.read_text("utf8",errors="surrogateescape")
    if (marker not in candidate
        and "@php($rotClock = \\App\\Support\\RotLiveClock::snapshot($session))" in candidate
        and "data-am-clock" in candidate):
        safe.append(p)
if not safe:raise SystemExit("AM126_ROLLBACK_STOP_NO_VERIFIED_PREPATCH_BACKUP")
backup=safe[0]
print("AM126_RESTORE_VERIFIED_BACKUP="+str(backup))
bad=view.with_name(view.name+".AM126_FAILED_COPY")
shutil.copy2(view,bad)
fd,temp=tempfile.mkstemp(prefix=".am126_restore_",dir=view.parent)
try:
    os.close(fd)
    shutil.copy2(backup,temp)
    os.replace(temp,view)
finally:
    if os.path.exists(temp):os.unlink(temp)
for cmd in (["php","artisan","view:clear"],["php","artisan","view:cache"]):
    r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
    if r.returncode:raise SystemExit("AM126_ROLLBACK_CACHE_STEP_FAILED="+r.stderr[-350:])
if marker in view.read_text("utf8",errors="replace"):
    raise SystemExit("AM126_ROLLBACK_MARKER_REMAINS")
print("AM126_ROLLBACK_VERIFIED_BASELINE_VIEW_RESTORED")
print("AM126_DATABASE_UNCHANGED")
