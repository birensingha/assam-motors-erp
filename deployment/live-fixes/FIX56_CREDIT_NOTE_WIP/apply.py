#!/usr/bin/env python3
"""FIX56 - fail-closed exact source patcher for staging only. No DB writes."""
import json,sys,os,tempfile
from pathlib import Path
if len(sys.argv)!=4 or sys.argv[1] not in ("--check","--apply"):
    raise SystemExit("Usage: patcher.py --check|--apply STAGING_ROOT PACKAGE_DIR")
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();pkg=Path(sys.argv[3]).resolve()
allow={
  "routes/web.php",
  "resources/views/job-cards/show.blade.php",
  "app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php",
}
manifests=["routes_patch.json","freeze_print_patch.json","jobcard_button_patch.json"]
todo=[]
for name in manifests:
    meta=json.loads((pkg/name).read_text(encoding="utf-8"))
    if meta.get("fix")!="FIX56_CREDIT_NOTE_WIP":raise SystemExit("FIX56 invalid manifest")
    for row in meta["changes"]:
        rel=row["path"]
        if rel not in allow:raise SystemExit("Unexpected patch file")
        target=(root/rel).resolve()
        if not target.is_file() or not target.is_relative_to(root):
            raise SystemExit("Missing target "+rel)
        raw=target.read_bytes(); crlf=b"\r\n" in raw
        source=raw.decode("utf8","surrogateescape").replace("\r\n","\n")
        old=row["old"];new=row["new"]
        n=source.count(old)
        print("FIX56_EXACT_ANCHOR",rel,n)
        if n!=1:raise SystemExit("FIX56_PRECHECK_FAILED "+rel)
        after=source.replace(old,new,1)
        if crlf:after=after.replace("\n","\r\n")
        todo.append((target,after.encode("utf8","surrogateescape")))
if len(todo)!=3 or len({str(t) for t,_ in todo})!=3:
    raise SystemExit("FIX56 expected three unique file targets")
if mode=="--check":
    print("FIX56_SOURCE_PREFLIGHT_PASS_NO_WRITES")
    sys.exit(0)
for target,data in todo:
    fd,tmp=tempfile.mkstemp(prefix=".fix56.",dir=target.parent)
    try:
        with os.fdopen(fd,"wb") as out:out.write(data)
        os.chmod(tmp,target.stat().st_mode & 0o777)
        os.replace(tmp,target)
        print("FIX56_PATCHED",target)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
