#!/usr/bin/env python3
import json,sys,os,tempfile
from pathlib import Path
mode,root,manifest=sys.argv[1:]
assert mode in ("check","apply")
root=Path(root).resolve()
spec=json.loads(Path(manifest).read_text())
assert spec["fix"]=="FIX58_APP_GPS_DASHBOARD"
assert len(spec["changes"])==1
item=spec["changes"][0]
assert item["path"]=="app/Http/Controllers/Api/V1/AdminMobileController.php"
dest=(root/item["path"]).resolve()
assert dest.is_file() and dest.is_relative_to(root)
raw=dest.read_bytes()
crlf=b"\r\n" in raw
text=raw.decode("utf8","surrogateescape").replace("\r\n","\n")
assert text.count(item["old"])==1,"FIX58 exact API anchor missing or duplicated"
new=text.replace(item["old"],item["new"],1)
if crlf:new=new.replace("\n","\r\n")
data=new.encode("utf8","surrogateescape")
if mode=="apply":
    fd,tmp=tempfile.mkstemp(prefix=".fix58.",dir=dest.parent)
    try:
        with os.fdopen(fd,"wb") as f:f.write(data)
        os.chmod(tmp,dest.stat().st_mode & 0o777)
        os.replace(tmp,dest)
        print("FIX58_AUTHENTICATED_ADMIN_MOBILE_GPS_API_UPDATED")
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
else:
    print("FIX58_API_EXACT_ANCHOR_PREFLIGHT_PASS")
