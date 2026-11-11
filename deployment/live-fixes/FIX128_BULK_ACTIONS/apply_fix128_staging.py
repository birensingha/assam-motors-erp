#!/usr/bin/env python3
"""FIX128: recover compact two-button Issue All/Fitted All in Parts heading ONLY.
No schema/data changes. Backup & integrity guard on both existing Blade and routes.
check: entirely read-only. apply: only UI/route source files.
"""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,os,subprocess,sys,tempfile

LIVE_VIEW_SHA="0907e40afeac137438e572fa149dfb4c3bbaceb4501b7cc8155c7a3f51365253"
LIVE_ROUTES_SHA="84a777e6584a42ae86f892e881e264c90fb4314bab7063badcc97f7dc04a8da6"
VIEW=Path("resources/views/job-cards/show.blade.php")
ROUTES=Path("routes/web.php")
CONTROLLER=Path("app/Http/Controllers/Web/AdminJobCardPartsBatchController.php")
PARTIAL=Path("resources/views/job-cards/parts-bulk-actions.blade.php")
PARTIAL_URL="https://raw.githubusercontent.com/birensingha/assam-motors-erp/45229d58d2b82504d4049ace76e6326f3b30dc03/deployment/live-fixes/FIX128_BULK_ACTIONS/parts-bulk-actions.blade.php"
HEADER='<h2 class="section-title">Parts</h2><span class="am87-inline-status" id="am87-parts-status" role="status" aria-live="polite"></span>'
PART_ACTION="@include('job-cards.part-stock-control', ['p' => $p])"
ROUTE_ANCHOR="// P34_ROT_BILLING_CONTROL"
MARKER="FIX128_PARTS_BULK_SAFE"
ROUTES_ADD=r"""    // FIX128_PARTS_BULK_SAFE: audited batch controls at Parts heading.
    Route::post('/job-cards/{job}/parts/issue-all', [\App\Http\Controllers\Web\AdminJobCardPartsBatchController::class,'issueAll'])
        ->whereNumber('job')->name('erp.job-cards.parts.issue-all');
    Route::post('/job-cards/{job}/parts/fitted-all', [\App\Http\Controllers\Web\AdminJobCardPartsBatchController::class,'fittedAll'])
        ->whereNumber('job')->name('erp.job-cards.parts.fitted-all');
"""
def sha(data):return hashlib.sha256(data).hexdigest()
def fail(reason):
 print("FIX128_ABORT="+reason,flush=True);raise SystemExit(2)
def run(cmd,root,limit=800,timeout=45):
 p=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
 if p.returncode!=0:
  print("FIX128_ERROR_OUTPUT="+(p.stdout+"\n"+p.stderr)[-limit:],flush=True)
  fail("COMMAND_FAILED="+str(cmd[:3]))
 return p.stdout.strip()
def compile_blade(root,source):
 with tempfile.TemporaryDirectory(prefix="fix128_blade_lint_") as d:
  out=Path(d)/"compiled.php"
  php="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();file_put_contents($argv[2],\\Illuminate\\Support\\Facades\\Blade::compileString(file_get_contents($argv[1])));"
  run(["php","-r",php,str(source),str(out)],root)
  run(["php","-l",str(out)],root)
 print("FIX128_BLADE_COMPILED_LINT=PASS")
def stage_replace(dest,blob):
 temp=dest.with_name("."+dest.name+".fix128.tmp")
 if temp.exists():raise RuntimeError("TEMP_EXISTS")
 temp.write_bytes(blob)
 os.chmod(temp,(dest.stat().st_mode & 0o777) if dest.exists() else 0o644)
 os.replace(temp,dest)
def main():
 if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):fail("USAGE")
 mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
 if not (root/"artisan").is_file() or not (root/"vendor/autoload.php").is_file():fail("NOT_LARAVEL_ROOT")
 view,route,controller,partial=[root/p for p in (VIEW,ROUTES,CONTROLLER,PARTIAL)]
 for p in (view,route,controller):
  if not p.is_file():fail("FILE_MISSING_"+str(p.relative_to(root)))
 if partial.exists():fail("PARTIAL_ALREADY_EXISTS_STOP")
 vraw,rraw=view.read_bytes(),route.read_bytes()
 print("FIX128_MODE="+mode)
 print("FIX128_VIEW_SHA="+sha(vraw))
 print("FIX128_ROUTES_SHA="+sha(rraw))
 if sha(vraw)!=LIVE_VIEW_SHA or sha(rraw)!=LIVE_ROUTES_SHA:fail("UNKNOWN_LIVE_REVISION_NO_CHANGES")
 vc=vraw.decode("utf-8","surrogateescape")
 rc=rraw.decode("utf-8","surrogateescape")
 if any((vc.count(HEADER)!=1,vc.count(PART_ACTION)!=1,rc.count(ROUTE_ANCHOR)!=1)):
  fail("ANCHOR_AMBIGUOUS")
 if "fix128-parts-stock-actions" in vc or MARKER in rc:fail("ALREADY_APPLIED")
 if vc.index(HEADER)>vc.index(PART_ACTION):fail("ANCHOR_ORDER")
 pview=vc.replace(HEADER,HEADER+"\n      @include('job-cards.parts-bulk-actions')",1)
 pview=pview.replace(PART_ACTION,"{{-- FIX128: per-row stock requisition / issue / fitted hidden; two bulk actions at Parts heading. --}}",1)
 if pview.count("@include('job-cards.parts-bulk-actions')")!=1 or pview.count(PART_ACTION)!=0:
  fail("VIEW_ASSERTION_FAILURE")
 proutes=rc.replace(ROUTE_ANCHOR,ROUTES_ADD+"    "+ROUTE_ANCHOR,1)
 for name in ("erp.job-cards.parts.issue-all","erp.job-cards.parts.fitted-all"):
  if proutes.count(name)!=1:fail("ROUTE_DUPLICATE")
 if mode=="check":
  print("FIX128_TARGET_UI_SHA="+sha(pview.encode("utf-8","surrogateescape")))
  print("FIX128_PARTS_PANEL_HEADER_ONLY=YES")
  print("FIX128_INSPECTION_HTML_UNCHANGED=YES")
  print("FIX128_CHECK_PASS_NO_WRITES=YES")
  return
 # Staging app already holds controller from FIX126. Verify signatures before reactivating.
 source=controller.read_text(encoding="utf-8")
 if not all(x in source for x in ("function issueAll(", "function fittedAll(", "->lockForUpdate()", "DB::transaction", "ValidationException", "isAdmin()")):
  fail("BATCH_CONTROLLER_SAFETY_GUARD_NOT_FOUND")
 run(["php","-l",str(controller)],root)
 with tempfile.TemporaryDirectory(prefix="fix128_stage_") as d:
  q=Path(d)
  # No untrusted source from the current workspace: download immutable pinned raw file.
  f=q/"parts-bulk-actions.blade.php"
  run(["curl","-fsSL","--retry","2",PARTIAL_URL,"-o",str(f)],root)
  if MARKER not in f.read_text(encoding="utf-8"):fail("PINNED_PARTIAL_MARKER_MISSING")
  tmpview=q/"show.blade.php";tmpview.write_bytes(pview.encode("utf-8","surrogateescape"))
  tmproute=q/"web.php";tmproute.write_bytes(proutes.encode("utf-8","surrogateescape"))
  compile_blade(root,tmpview)
  compile_blade(root,f)
  run(["php","-l",str(tmproute)],root)
  print("FIX128_ALL_SYNTAX_CHECKS=PASS")
  back_dir=root/"storage/app/am-backups";back_dir.mkdir(parents=True,exist_ok=True)
  t=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
  backups=[]
  for source_path,blob in ((view,vraw),(route,rraw)):
   out=back_dir/("FIX128_"+source_path.name+"_"+t+".bak")
   with os.fdopen(os.open(out,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as fd:
    fd.write(blob);fd.flush();os.fsync(fd.fileno())
   if sha(out.read_bytes())!=sha(blob):fail("BACKUP_HASH_FAILED")
   backups.append(out)
  print("FIX128_BACKUPS_VERIFIED=YES")
  touched=[]
  try:
   for dest,blob in [(partial,f.read_bytes()),(route,tmproute.read_bytes()),(view,tmpview.read_bytes())]:
    stage_replace(dest,blob)
    touched.append(dest)
   if sha(view.read_bytes())!=sha(tmpview.read_bytes()) or sha(route.read_bytes())!=sha(tmproute.read_bytes()):
    raise RuntimeError("POST_WRITE_HASH_FAILED")
   run(["php","artisan","route:clear"],root)
   run(["php","artisan","view:clear"],root)
   code=("require 'vendor/autoload.php';$app=require 'bootstrap/app.php';"
         "$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();"
         "foreach(['erp.job-cards.parts.issue-all','erp.job-cards.parts.fitted-all'] as $n)"
         "{if(!\\Illuminate\\Support\\Facades\\Route::has($n)){exit(17);}echo $n.'=YES'.PHP_EOL;}")
   result=run(["php","-r",code],root)
   if result.count("=YES")!=2:raise RuntimeError("BATCH_ROUTE_REGISTRATION")
   print("FIX128_ROUTES="+result.replace("\n",";"))
   print("FIX128_STAGING_DEPLOYED=YES")
   print("FIX128_UI_LOCATION=PARTS_HEADING")
   print("FIX128_STOCK_AND_BILLING_UNCHANGED=YES")
   for b in backups:print("FIX128_BACKUP="+str(b))
  except BaseException as exc:
   print("FIX128_RESTORE_REASON="+str(exc))
   if view in touched:view.write_bytes(vraw)
   if route in touched:route.write_bytes(rraw)
   if partial in touched:partial.unlink(missing_ok=True)
   subprocess.run(["php","artisan","route:clear"],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
   subprocess.run(["php","artisan","view:clear"],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
   fail("ROLLBACK_RESTORED_OLD_FILES")
if __name__=="__main__":main()
