#!/usr/bin/env python3
"""FIX124 toolbar HTML close correction, exact SHA guarded, print view only."""
from pathlib import Path
import hashlib,os,sys,subprocess,tempfile
from datetime import datetime,timezone
OLD_SHA="0a2f65c8db67e5a968274fa050cb8249a36ae142207c0b80a115a14f227e8f2e"
ANCHOR='</button></span></div>\n<main class="page">'
NEW='</button></span></div></div>\n<main class="page">'
def hashb(b):return hashlib.sha256(b).hexdigest()
def bail(x):print("FIX124_TB_ABORT="+x);raise SystemExit(2)
def main():
 if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):bail("USAGE")
 mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
 if not (root/"artisan").is_file():bail("NOT_LARAVEL")
 v=root/"resources/views/job-cards/summary-print.blade.php"
 data=v.read_bytes();s=data.decode("utf-8")
 print("FIX124_TOOLBAR_MODE="+mode)
 print("FIX124_TOOLBAR_SHA="+hashb(data))
 if 'FIX124_TOOLBAR_BALANCED_V2' in s:
  print("FIX124_TOOLBAR_ALREADY_APPLIED");return
 if hashb(data)!=OLD_SHA:bail("BASELINE_SHA_MISMATCH")
 if s.count(ANCHOR)!=1 or s.count('class="am124-modes"')!=1:bail("ANCHOR_MISSING")
 patched=s.replace(ANCHOR,'<!-- FIX124_TOOLBAR_BALANCED_V2 -->\n'+NEW,1)
 print("FIX124_TOOLBAR_HTML_CLOSE_CHECK=PASS")
 if mode=="check":
  print("FIX124_TOOLBAR_CHECK_PASS_NO_WRITES");return
 folder=root/"storage/app/am-backups";folder.mkdir(parents=True,exist_ok=True)
 back=folder/("FIX124_toolbar_"+datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")+".blade.php.bak")
 with os.fdopen(os.open(back,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as out:out.write(data)
 if hashb(back.read_bytes())!=OLD_SHA:bail("BACKUP_BAD")
 temp=v.with_name(".summary-print.fix124-toolbar.tmp.blade.php")
 if temp.exists():bail("TEMP_EXISTS")
 try:
  temp.write_text(patched,encoding="utf-8")
  os.chmod(temp,v.stat().st_mode&0o777)
  with tempfile.TemporaryDirectory() as d:
   php="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();file_put_contents($argv[2],\\Illuminate\\Support\\Facades\\Blade::compileString(file_get_contents($argv[1])));"
   out=Path(d)/"compiled.php"
   x=subprocess.run(["php","-r",php,str(temp),str(out)],cwd=root,capture_output=True,text=True,timeout=40)
   if x.returncode:bail("BLADE_COMPILE")
   x=subprocess.run(["php","-l",str(out)],cwd=root,capture_output=True,text=True,timeout=30)
   if x.returncode:bail("PHP_LINT")
  if hashb(v.read_bytes())!=OLD_SHA:bail("CONCURRENT_CHANGE")
  os.replace(temp,v)
  print("FIX124_TOOLBAR_APPLIED=YES")
  print("FIX124_TOOLBAR_NEW_SHA="+hashb(v.read_bytes()))
  print("FIX124_TOOLBAR_BACKUP="+str(back))
  print("FIX124_TOOLBAR_NO_BACKEND_CHANGES=YES")
 finally:
  temp.unlink(missing_ok=True)
if __name__=="__main__":
 main()
