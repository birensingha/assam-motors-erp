#!/usr/bin/env python3
"""AM185: safely wire four native Configuration master pages into STAGING ERP.

Connects existing native Parts, Labour/ROT, Vehicle and Mapping controllers
and views to missing admin-protected routes. Never points tiles at OLD ERP.
Guarded: stops if any controller/view is absent, contains legacy navigation,
or the view needs missing action routes. Does NOT fabricate CRUD handlers.

check: no changes. apply: web routes only. rollback: restore backed-up routes.
Run: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import os,re,json,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("AM185_STOP_NOT_STAGING_ROOT")
web=root/"routes/web.php"
controller=root/"app/Http/Controllers/Web/AdminNativeModuleController.php"
backup=root/"storage/app/am-backups/AM185_CONFIG_MASTERS/web.php.before_AM185"
if not web.is_file() or not controller.is_file():
    raise SystemExit("AM185_STOP_REQUIRED_FILES_MISSING")
marker="AM185_NATIVE_CONFIGURATION_MASTERS"
# Name / URL pairs must match AM184's exact route-name whitelist.
modules=[
 ("Parts Master","parts","erp.parts","parts",
  ("parts","partMaster","partsMaster")),
 ("ROT / Labour Master","labour","erp.labour","labour",
  ("labour","labourMaster","labourRotMaster")),
 ("Vehicle Master","vehicles","erp.vehicles","vehicles",
  ("vehicles","vehicleMaster","vehiclesMaster")),
 ("Labour / Part Mapping","mappings","erp.mappings","mappings",
  ("mappings","labourPartMapping","labourPartsMapping")),
]
def read(p):return p.read_text(encoding="utf8",errors="surrogateescape")
def write_atomic(p,contents):
    fd,t=tempfile.mkstemp(prefix=".am185_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:
            f.write(contents)
        os.chmod(t,p.stat().st_mode & 0o777)
        os.replace(t,p)
    finally:
        if os.path.exists(t):os.unlink(t)
def php_lint(s):
    with tempfile.TemporaryDirectory(prefix="am185lint_") as d:
        p=Path(d)/"web.php";p.write_text(s,encoding="utf8",errors="surrogateescape")
        r=subprocess.run(["php","-l",str(p)],text=True,capture_output=True,timeout=30)
        if r.returncode:raise SystemExit("AM185_STOP_PHP_SYNTAX "+(r.stdout+r.stderr)[-350:])
def registered_routes():
    php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$result=[];
foreach($app->make('router')->getRoutes() as $r){
  $result[]=['name'=>(string)$r->getName(),'uri'=>(string)$r->uri(),
   'methods'=>$r->methods(),'action'=>(string)$r->getActionName()];
}
echo json_encode($result);
"""
    with tempfile.TemporaryDirectory(prefix="am185rt_") as d:
        f=Path(d)/"routes.php";f.write_text(php,encoding="utf8")
        r=subprocess.run(["php",str(f),str(root)],capture_output=True,text=True,timeout=30)
        if r.returncode:raise SystemExit("AM185_STOP_ROUTE_PROBE_ERROR")
        try:return json.loads(r.stdout)
        except ValueError:raise SystemExit("AM185_STOP_ROUTE_PROBE_INVALID")
def method_info(source,name):
    m=re.search(r"\bpublic\s+function\s+"+re.escape(name)+r"\s*\(",source)
    if not m:return None
    # Preview only current action until next public/protected method.
    section=re.split(r"\n\s*(?:public|private|protected)\s+function\s+",
                     source[m.start():m.start()+18000],maxsplit=1)[0]
    return section
source=read(web)
if mode=="rollback":
    if not backup.is_file() or marker not in source:
        raise SystemExit("AM185_STOP_BACKUP_OR_MARKER_MISSING")
    before=read(backup)
    php_lint(before)
    write_atomic(web,before)
    print("AM185_ROUTES_ROLLED_BACK")
    print("AM185_RUN_PHP_ARTISAN_ROUTE_CLEAR")
    sys.exit(0)
if marker in source:
    print("AM185_NATIVE_CONFIG_ROUTES_ALREADY_INSTALLED")
    sys.exit(0)
if not source.lstrip().startswith("<?php") or source.rstrip().endswith("?>"):
    raise SystemExit("AM185_STOP_WEB_ROUTES_UNEXPECTED_PHP")
existing=registered_routes()
names={r["name"] for r in existing if r["name"]}
uris={r["uri"] for r in existing if r["uri"]}
ctl_src=read(controller)
methodnames=re.findall(r"\bpublic\s+function\s+(\w+)\s*\(",ctl_src)
print("AM185_NATIVE_CONFIG_MASTERS_PREFLIGHT")
print("EXISTING_RELEVANT_CONTROLLER_METHODS",
      ",".join(n for n in methodnames if re.search("part|labour|labor|rot|vehicle|mapping",n,re.I)) or "NONE")
planned=[]
errors=[]
for label,slug,route,viewname,methods in modules:
    view=root/"resources/views/admin/native"/(viewname+".blade.php")
    method=next((name for name in methods if method_info(ctl_src,name)),None)
    print("MODULE",label,"VIEW",view.is_file(),
          "METHOD",method or "MISSING","ROUTE_NAME",route)
    if route in names or "erp/"+slug in uris:
        print(" STOP_ALREADY_ROUTED",route)
        errors.append(label+": EXISTING_ROUTE_CONFLICT")
        continue
    if not view.is_file():
        errors.append(label+": NATIVE_VIEW_MISSING")
        continue
    if not method:
        errors.append(label+": NATIVE_CONTROLLER_METHOD_MISSING")
        continue
    action=method_info(ctl_src,method)
    if re.search(r"/legacy/|(?<![\w])legacy/workshop/|assammotors\.com/workshop/",action,re.I):
        errors.append(label+": CONTROLLER_STILL_USES_OLD_ERP")
    v=read(view)
    # The native display must not send applicants/masters back to OLD ERP.
    if re.search(r"(?i)(href|action)\s*=\s*[\"'][^\"']*(/legacy/|/workshop/)",v):
        errors.append(label+": VIEW_HAS_LEGACY_ACTION")
    refs=sorted(set(re.findall(r"\broute\s*\(\s*['\"]([^'\"]+)['\"]",v)))
    missing=[n for n in refs if n not in names and n!=route]
    if missing:
        errors.append(label+": MISSING_FORM_OR_LINK_ROUTES:"+",".join(missing[:15]))
    # Any JS/API endpoint pointing to legacy is not considered a completed
    # native master: do not silently migrate only its page.
    if re.search(r"/legacy/api/|/legacy/workshop/|/workshop/api/",v,re.I):
        errors.append(label+": LEGACY_API_DEPENDENCY")
    forms=len(re.findall(r"<form\b",v,re.I))
    print(" VIEW_FORM_COUNT",forms,"REFERENCED_NAMED_ROUTES",
          ",".join(refs) if refs else "NONE")
    if not forms:
        print(" VIEW_NO_NATIVE_FORM_CHECK_OPERABILITY_IN_BROWSER",label)
    planned.append((slug,route,method))
if errors:
    print("AM185_MISSING_REQUIREMENTS",len(errors))
    for error in errors:print(" BLOCKER",error)
    print("AM185_NO_FILES_CHANGED")
    sys.exit(3)
if len(planned)!=4:raise SystemExit("AM185_STOP_INCOMPLETE_MODULE_PLAN")
lines=[
"\n/* AM185_NATIVE_CONFIGURATION_MASTERS",
" * Four fully authenticated NEW ERP configuration master GET routes.",
" * Existing native controller/views remain the operational source.",
" */",
r"\Illuminate\Support\Facades\Route::middleware([",
r"    'web','auth:web',\App\Http\Middleware\EnsureErpAdmin::class",
r"])->group(function () {",
]
for slug,route,method in planned:
    lines.append(
      r"    \Illuminate\Support\Facades\Route::get('/erp/"+slug+"', "+
      r"[\App\Http\Controllers\Web\AdminNativeModuleController::class, '"+
      method+"'])->name('"+route+"');"
    )
lines+=["});",""]
candidate=source.rstrip()+"\n"+"\n".join(lines)
php_lint(candidate)
print("AM185_PHP_SYNTAX_PASS")
print("AM185_PLANNED_NATIVE_ROUTES",len(planned))
if mode=="check":
    print("AM185_CHECK_PASS_NO_WRITES")
    sys.exit(0)
backup.parent.mkdir(parents=True,exist_ok=True)
os.chmod(backup.parent,0o700)
if backup.exists():raise SystemExit("AM185_STOP_BACKUP_EXISTS")
shutil.copy2(web,backup);os.chmod(backup,0o600)
write_atomic(web,candidate)
print("AM185_NATIVE_CONFIGURATION_MASTER_ROUTES_INSTALLED")
print("AM185_NO_DATABASE_OR_MASTER_DATA_CHANGED")
print("AM185_NEXT_RUN_ARTISAN_ROUTE_CLEAR_THEN_AM184_CHECK_APPLY")
