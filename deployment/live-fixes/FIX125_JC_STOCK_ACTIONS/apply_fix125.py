#!/usr/bin/env python3
"""FIX125 - Restore existing P33 stock requisition/issue/fitted controls into JC Parts rows.

Changes only one Blade Job Card show view. Existing routes, permissions, DB transactions,
stock guards, audit requirements, invoice/amount/GST calculations are left unchanged.
"""
from pathlib import Path
from datetime import datetime,timezone
import hashlib, os, subprocess, sys, tempfile
BASELINE="4eae23ec80f50e029c5c298a46a5d2c1fe1c5eedca51d93d4be5c922a0b9bf23"
PARTIAL_SHA="3663b61f8b19f3ab9426a92672c3657faa166d65e53d5f7924e80804db6e0db6"
MARKER="FIX125_PART_STOCK_ACTIONS_IN_ROWS"
FILE=Path("resources/views/job-cards/show.blade.php")
PARTIAL=Path("resources/views/job-cards/part-stock-control.blade.php")
def sha(x):return hashlib.sha256(x).hexdigest()
def abort(msg):
 print("FIX125_ABORT="+msg,flush=True)
 sys.exit(2)
def patch(txt):
 start=txt.find("@forelse($parts as $p)")
 if start<0 or txt.count("@forelse($parts as $p)")!=1:abort("PART_FORELSE_ANCHOR_MISSING")
 end=txt.find("@empty",start)
 if end<0:abort("PART_EMPTY_BOUNDARY_MISSING")
 before,block,after=txt[:start],txt[start:end],txt[end:]
 label='<div class="muted">{{ $p->part_code }} {{ $p->part_number }}</div>'
 if block.count(label)!=1:abort("PART_LABEL_ANCHOR_MISMATCH")
 block=block.replace(label,label+'\n           {{-- '+MARKER+': billing selection and physical fitting are distinct. --}}\n           <div class="muted" style="font-size:10px;margin-top:2px">Stock: <strong>{{ strtoupper(trim((string)($p->issue_status ?? \'PLANNED\'))) }}</strong></div>',1)
 # The last action-cell @endif in the Parts loop closes the Edit/Delete controls.
 # Match structurally to avoid whitespace variations from legacy Blade revisions.
 marker=block.rfind("@endif")
 if marker<0:abort("LAST_ACTION_ENDIF_MISSING")
 tail=block[marker+len("@endif"):]
 import re
 if not re.fullmatch(r"\s*</td>\s*</tr>\s*",tail):
  abort("LAST_ACTION_ENDIF_NOT_PARTS_ACTION_CELL")
 block=block[:marker]+"{{-- FIX125: audited stock controls; no automatic stock movement. --}}\n           @include('job-cards.part-stock-control', ['p' => $p])\n           "+block[marker:]
 out=before+block+after
 if out.count(MARKER)!=1 or out.count("           @include('job-cards.part-stock-control', ['p' => $p])")!=1:abort("INTEGRITY_FAILURE")
 if out.count("{{ $p->total+$amJcPartsTax")!=txt.count("{{ $p->total+$amJcPartsTax"): abort("PRICING_CHANGED")
 return out
def compile_blade(root,p):
 with tempfile.TemporaryDirectory(prefix="fix125_") as d:
  dest=Path(d)/"compiled.php"
  php="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();file_put_contents($argv[2],\\Illuminate\\Support\\Facades\\Blade::compileString(file_get_contents($argv[1])));"
  x=subprocess.run(["php","-r",php,str(p),str(dest)],cwd=root,capture_output=True,text=True,timeout=60)
  if x.returncode:abort("BLADE_COMPILATION_FAILED")
  y=subprocess.run(["php","-l",str(dest)],cwd=root,capture_output=True,text=True,timeout=30)
  if y.returncode:abort("PHP_LINT_FAILED")
 print("FIX125_BLADE_COMPILE_PHP_LINT=PASS")
def main():
 if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):abort("USAGE")
 mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
 if not (root/"artisan").is_file() or not (root/"vendor/autoload.php").is_file():abort("NOT_LARAVEL_ROOT")
 view,part=root/FILE,root/PARTIAL
 if not view.is_file() or not part.is_file():abort("MISSING_VIEW_OR_STOCK_PARTIAL")
 raw=view.read_bytes()
 print("FIX125_MODE="+mode)
 print("FIX125_SHOW_SHA="+sha(raw))
 if MARKER in raw.decode("utf-8","surrogateescape"):
  print("FIX125_ALREADY_APPLIED=YES");return
 if sha(raw)!=BASELINE:abort("UNEXPECTED_SHOW_SOURCE_SHA")
 if sha(part.read_bytes())!=PARTIAL_SHA:abort("STOCK_PARTIAL_SOURCE_CHANGED")
 src=patch(raw.decode("utf-8","surrogateescape"))
 print("FIX125_RESTORED_STOCK_ACTIONS=REQUISITION_ISSUE_FITTED")
 print("FIX125_AUTOMATIC_STOCK_ISSUE=NO")
 print("FIX125_CONTROLLERS_ROUTES_DB_UNCHANGED=YES")
 print("FIX125_NEW_SHOW_SHA="+sha(src.encode("utf-8","surrogateescape")))
 if mode=="check":
  print("FIX125_CHECK_PASS_NO_WRITES");return
 backups=root/"storage/app/am-backups";backups.mkdir(parents=True,exist_ok=True)
 back=backups/("FIX125_show_"+datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")+".blade.php.bak")
 with os.fdopen(os.open(back,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as f:
  f.write(raw);f.flush();os.fsync(f.fileno())
 if sha(back.read_bytes())!=BASELINE:abort("BACKUP_INTEGRITY_FAILED")
 print("FIX125_BACKUP="+str(back))
 tmp=view.with_name(".show.fix125.tmp.blade.php")
 if tmp.exists():abort("TEMP_ALREADY_EXISTS")
 try:
  tmp.write_bytes(src.encode("utf-8","surrogateescape"))
  os.chmod(tmp,view.stat().st_mode&0o777)
  compile_blade(root,tmp)
  if sha(view.read_bytes())!=BASELINE:abort("VIEW_CHANGED_CONCURRENTLY")
  os.replace(tmp,view)
  if sha(view.read_bytes())!=sha(src.encode("utf-8","surrogateescape")):
   view.write_bytes(raw)
   abort("POST_WRITE_MISMATCH_ROLLED_BACK")
  print("FIX125_STAGING_PARTS_UI_APPLIED=YES")
  print("FIX125_STOCK_TOTALS_UNCHANGED=YES")
 finally:tmp.unlink(missing_ok=True)
if __name__=="__main__":main()
