#!/usr/bin/env python3
"""AM172: one-Job-Card manual Sync Customer Name button (read-check first).

Writes only a new Admin controller, one scoped POST route, and the JC show view.
A sync click later updates ONLY the matching non-invoiced JC name under a DB
transaction, with identity-check + audit. No DB writes on deployment.
"""
from pathlib import Path
import datetime,os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit("Usage: apply.py check|apply LARAVEL_STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
project=Path(__file__).resolve().parent
view=root/"resources/views/job-cards/show.blade.php"
routes=root/"routes/web.php"
controller=root/"app/Http/Controllers/Web/AdminJobCardCustomerNameSyncController.php"
payload=project/"AdminJobCardCustomerNameSyncController.php"
if not (root/"artisan").is_file() or not view.is_file() or not routes.is_file() or not payload.is_file():
    raise SystemExit("AM172_STOP_REQUIRED_FILES_MISSING")
origview=view.read_text(encoding="utf8",errors="surrogateescape")
origroute=routes.read_text(encoding="utf8",errors="surrogateescape")
mark="AM172_JC_CUSTOMER_NAME_SYNC"
route_name="erp.job-cards.customer-name-sync"
if mark in origview or route_name in origroute or controller.exists():
    if mark in origview and route_name in origroute and controller.exists():
        print("AM172_ALREADY_APPLIED");sys.exit(0)
    raise SystemExit("AM172_STOP_PARTIAL_INSTALL_REVIEW_BEFORE_MODIFY")
if origview.count("@section('content')")!=1:
    raise SystemExit("AM172_STOP_VIEW_CONTENT_ANCHOR_CHANGED")
lines=origroute.splitlines(keepends=True)
idx=[i for i,l in enumerate(lines) if "name('erp.job-cards.show')" in l and "Route::get(" in l]
if len(idx)!=1:
    raise SystemExit("AM172_STOP_ROUTE_ANCHOR_CHANGED")
if not all(x in origview for x in ["$job", "FIX92_LABOUR_MECHANICS_DELETE_DIALOG"]):
    raise SystemExit("AM172_STOP_UNEXPECTED_JC_VIEW")
addon=r"""
{{-- AM172_JC_CUSTOMER_NAME_SYNC: one-JC manual correction, master identity verified server-side. --}}
<style id="AM172_JC_CUSTOMER_NAME_SYNC">
.am172-name-bar{display:flex;align-items:center;gap:9px;flex-wrap:wrap;
 padding:8px 11px;margin:8px 0 12px;border:1px solid #b9d4ed;
 border-radius:7px;background:#f3f8fe;color:#244565;font-size:11px}
.am172-name-bar strong{font-weight:800}
.am172-name-bar form{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin:0}
.am172-name-bar input{background:white;border:1px solid #bbd1e3;border-radius:5px;
 padding:7px 9px;min-width:210px;max-width:100%;font-size:11px}
.am172-name-bar button{border:1px solid #15528d;background:#15528d;color:white;
 font-size:11px;font-weight:800;padding:8px 10px;border-radius:5px;cursor:pointer}
</style>
@if((int)($job->customer_id ?? 0)>0 && empty($job->invoice_id) &&
 !in_array(strtolower((string)($job->status??'')),['invoiced','cancelled','canceled'],true))
<div class="am172-name-bar">
 <strong>Customer Name Correction</strong>
 <span>Sync from ERP Customer Master (Admin · verified phone/email · audited)</span>
 <form method="POST" action="{{ route('erp.job-cards.customer-name-sync',$job->id) }}"
   onsubmit="return confirm('Sync the verified Customer Master name into this Job Card? An audit entry will be kept.');">
   @csrf
   <input type="text" name="reason" required minlength="10" maxlength="500"
     aria-label="Customer name correction reason" placeholder="Correction reason (10+ characters)">
   <button type="submit">Sync Customer Name</button>
 </form>
</div>
@endif
"""
updated=origview.replace("@section('content')","@section('content')\n"+addon,1)
route_line=r"    Route::post('/job-cards/{job}/sync-customer-name', [\App\Http\Controllers\Web\AdminJobCardCustomerNameSyncController::class,'post'])->whereNumber('job')->name('erp.job-cards.customer-name-sync');"+"\n"
lines.insert(idx[0], "    // AM172_JC_CUSTOMER_NAME_SYNC Admin only, audit on each changed JC\n"+route_line)
newroutes="".join(lines)
if updated.count('id="'+mark+'"')!=1 or newroutes.count(route_name)!=1:
    raise SystemExit("AM172_STOP_RESULT_VALIDATION")

with tempfile.TemporaryDirectory(prefix="am172_preflight_") as tmpdir:
    temp=Path(tmpdir)
    compiled=temp/"compiled.php"
    candidate=temp/"candidate.blade.php"
    candidate.write_text(updated,encoding="utf8",errors="surrogateescape")
    validator=temp/"compile.php"
    validator.write_text("""<?php
require $argv[1] . '/vendor/autoload.php';
$app=require $argv[1] . '/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$blade=$app->make('blade.compiler');
file_put_contents($argv[3],$blade->compileString(file_get_contents($argv[2])));
""",encoding="utf8")
    cmd=subprocess.run(["php",str(validator),str(root),str(candidate),str(compiled)],
        text=True,capture_output=True)
    if cmd.returncode or not compiled.exists():
        raise SystemExit("AM172_STOP_BLADE_COMPILE "+cmd.stderr[-1400:])
    testfiles=[(compiled,"compiled JC Blade"),(payload,"new Controller")]
    for file,label in testfiles:
        lint=subprocess.run(["php","-l",str(file)],text=True,capture_output=True)
        if lint.returncode:
            raise SystemExit("AM172_STOP_PHP_LINT "+label+" "+(lint.stdout+lint.stderr)[-1300:])
    route_candidate=temp/"routes.php"
    route_candidate.write_text(newroutes,encoding="utf8",errors="surrogateescape")
    lint=subprocess.run(["php","-l",str(route_candidate)],text=True,capture_output=True)
    if lint.returncode:
        raise SystemExit("AM172_STOP_ROUTES_PHP_LINT "+(lint.stdout+lint.stderr)[-1200:])
    print("AM172_VIEW_CONTROLLER_ROUTES_SYNTAX_PASS")

if mode=="check":
    print("AM172_PREFLIGHT_PASS_NO_WRITES")
    sys.exit(0)

backups=[]
created=False
def atomic(path,data,perm=0o644):
    fd,tempname=tempfile.mkstemp(prefix=".am172_",dir=path.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(data)
        os.chmod(tempname,perm)
        os.replace(tempname,path)
    finally:
        if os.path.exists(tempname):os.unlink(tempname)
try:
    stamp=datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
    for p in [view,routes]:
        bk=p.with_name(p.name+".AM172_"+stamp+".bak")
        shutil.copy2(p,bk)
        backups.append((p,bk))
    if controller.exists():raise RuntimeError("Controller unexpectedly exists")
    atomic(controller,payload.read_text(encoding="utf8"),0o644)
    created=True
    atomic(routes,newroutes,routes.stat().st_mode & 0o777)
    atomic(view,updated,view.stat().st_mode & 0o777)
except Exception:
    for p,bk in backups:
        if bk.exists():shutil.copy2(bk,p)
    if created and controller.exists():controller.unlink()
    raise
print("AM172_INSTALLED_UI_AND_BACKEND_NO_DB_WRITES")
for _,bk in backups:print("BACKUP",bk)
print("AM172_NEXT php artisan view:clear; browser verify on a not-invoiced Job Card")
