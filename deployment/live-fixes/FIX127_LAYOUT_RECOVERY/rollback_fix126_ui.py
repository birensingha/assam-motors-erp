#!/usr/bin/env python3
"""FIX127: restore verified pre-FIX126 STAGING UI view and routes only.
No database/stock updates. Leaves orphaned batch controller file untouched.
"""
from pathlib import Path
import hashlib, os, sys, subprocess
from datetime import datetime,timezone
ROOT=Path(sys.argv[2]).resolve() if len(sys.argv)==3 else None
MODE=sys.argv[1] if len(sys.argv)==3 else ""
V=Path("resources/views/job-cards/show.blade.php")
R=Path("routes/web.php")
ORIG={
 V:("067806194ed2ce5c406e43b6d12fff30e3e7c6df5453c09a80ab9c1e467af608",
    "0907e40afeac137438e572fa149dfb4c3bbaceb4501b7cc8155c7a3f51365253",
    "FIX126_show_20261025T042007Z.blade.php.bak"),
 R:("6c187e37c4e8ae40d959b2d10b8ed228bcb91d43b823f9eebfb9ca57c552dcf4",
    "84a777e6584a42ae86f892e881e264c90fb4314bab7063badcc97f7dc04a8da6",
    "FIX126_routes_20261025T042007Z.php.bak")
}
def h(v):return hashlib.sha256(v).hexdigest()
def fail(m):print("FIX127_ABORT="+m,flush=True);sys.exit(2)
if MODE not in ('check','apply') or ROOT is None:fail("USAGE")
if not (ROOT/"artisan").is_file():fail("NOT_LARAVEL")
d={}
for rel,(nowsha,oldsha,back) in ORIG.items():
 live=ROOT/rel
 previous=ROOT/"storage/app/am-backups"/back
 if not live.is_file() or not previous.is_file():fail("BACKUP_OR_VIEW_MISSING")
 original=live.read_bytes();recovery=previous.read_bytes()
 if h(original)!=nowsha or h(recovery)!=oldsha:fail("SHA_MISMATCH_"+str(rel))
 d[rel]=(original,recovery)
 print("FIX127_VERIFIED="+str(rel),flush=True)
if MODE=="check":
 print("FIX127_CHECK_PASS_NO_CHANGES");sys.exit(0)
store=ROOT/"storage/app/am-backups"
tag=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
latest=[]
for rel,(cur,prev) in d.items():
 b=store/("FIX127_pre_restore_"+rel.name+"_"+tag+".bak")
 with os.fdopen(os.open(b,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as f:
  f.write(cur);f.flush();os.fsync(f.fileno())
 if h(b.read_bytes())!=h(cur):fail("SAFETY_BACKUP_FAILED")
 latest.append(str(b))
 print("FIX127_SAFETY_BACKUP="+str(b),flush=True)
changed=[]
try:
 for rel,(cur,prev) in d.items():
  target=ROOT/rel
  temp=target.with_name("."+target.name+".fix127restore.tmp")
  if temp.exists():raise RuntimeError("TEMP_ALREADY_EXISTS")
  temp.write_bytes(prev)
  os.chmod(temp,target.stat().st_mode&0o777)
  os.replace(temp,target)
  changed.append(rel)
 for rel,(_,prev) in d.items():
  if h((ROOT/rel).read_bytes())!=h(prev):raise RuntimeError("VERIFY_RESTORE_FAILED")
 for name in ["route:clear","view:clear"]:
  o=subprocess.run(["php","artisan",name],cwd=ROOT,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,timeout=40)
  if o.returncode!=0:raise RuntimeError("CACHE_CLEAR_FAILED_"+name)
 print("FIX127_RECOVERY_APPLIED=YES")
 print("FIX127_UI_PREVIOUS_LAYOUT_RESTORED=YES")
 print("FIX127_DATABASE_STOCK_INVOICE_UNCHANGED=YES")
except Exception as e:
 print("FIX127_ROLLBACK_REASON="+str(e))
 for rel in changed:(ROOT/rel).write_bytes(d[rel][0])
 subprocess.run(["php","artisan","route:clear"],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=40)
 subprocess.run(["php","artisan","view:clear"],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,timeout=40)
 fail("RESTORE_FAILED_ORIGINAL_FIX126_RETAINED")
