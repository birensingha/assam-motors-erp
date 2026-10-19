#!/usr/bin/env python3
"""FIX52: guarded exact replacements; no writes on --check."""
import json, os, sys, tempfile
from pathlib import Path

if len(sys.argv)!=4 or sys.argv[1] not in ("--check","--apply"):
    raise SystemExit("usage: apply.py --check|--apply ROOT MANIFEST")
mode,root,manifest=sys.argv[1:]
root=Path(root).resolve()
spec=json.loads(Path(manifest).read_text())
assert spec["fix"]=="FIX52_CUSTOMER_GSTIN"
allow={"AdminNativeModuleController.php","customers.blade.php","customer-detail.blade.php","print.blade.php","show.blade.php"}
pending=[]
for change in spec["changes"]:
    rel=Path(change["path"])
    assert not rel.is_absolute() and ".." not in rel.parts and rel.name in allow
    target=(root/rel).resolve()
    assert target.is_relative_to(root) and target.is_file()
    original=target.read_bytes()
    crlf=b"\r\n" in original
    content=original.decode("utf-8").replace("\r\n","\n")
    for item in change["replacements"]:
        old=item["old"].replace("\r\n","\n")
        new=item["new"].replace("\r\n","\n")
        n=content.count(old)
        print("EXACT_ANCHOR",rel.name,n)
        if n!=1:
            raise SystemExit("FIX52_ABORT: unexpected source anchor; no changes")
        content=content.replace(old,new,1)
    output=content.replace("\n","\r\n") if crlf else content
    pending.append((target,output.encode("utf-8")))
if len(pending)!=5:raise SystemExit("Expected five exact source files")
if mode=="--apply":
    for target,data in pending:
        fd,temp=tempfile.mkstemp(dir=target.parent,prefix=".fix52.")
        try:
            with os.fdopen(fd,"wb") as f:f.write(data)
            os.chmod(temp,target.stat().st_mode & 0o777)
            os.replace(temp,target)
            print("UPDATED",target.name)
        finally:
            if os.path.exists(temp):os.unlink(temp)
else:print("FIX52_DRY_RUN_PASS_NO_WRITES")
