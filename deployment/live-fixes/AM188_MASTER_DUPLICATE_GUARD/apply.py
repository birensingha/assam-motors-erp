#!/usr/bin/env python3
"""AM188 — Guard NEW ERP native Vendor/Customer create routes against duplicates.

Read-only preflight discovers actual CREATE routes and master table shapes.
Only existing POST create routes are protected, not edit/ledger/transaction routes.
Installs a common Laravel middleware on exact create route (URI+action) matches;
no changes to existing master rows, schemas or financial records.

check:   python3 apply.py check STAGING_ROOT
apply:   python3 apply.py apply STAGING_ROOT
rollback:python3 apply.py rollback STAGING_ROOT
"""
from pathlib import Path
import json,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: apply.py check|apply|rollback STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if not (root/"artisan").is_file():raise SystemExit("AM188_STOP_NOT_STAGING_ROOT")
routes=[root/"routes/web.php",root/"routes/api.php"]
middleware=root/"app/Http/Middleware/PreventDuplicateMaster.php"
source=pkg/"PreventDuplicateMaster.php"
backup=root/"storage/app/am-backups/AM188_MASTER_DUPLICATE_GUARD"
marker="AM188_PREVENT_MASTER_DUPLICATES"
def read(p):return p.read_text(encoding="utf8",errors="surrogateescape")
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix=".am188_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777 if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(s):
    with tempfile.TemporaryDirectory(prefix="am188lint_") as d:
        f=Path(d)/"test.php";f.write_text(s,encoding="utf8",errors="surrogateescape")
        p=subprocess.run(["php","-l",str(f)],capture_output=True,text=True,timeout=30)
        if p.returncode:raise SystemExit("AM188_STOP_PHP_SYNTAX "+(p.stderr+p.stdout)[-260:])
def php_probe():
    code=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$items=[];
foreach($app->make('router')->getRoutes() as $r){
 $items[]=['uri'=>(string)$r->uri(),'action'=>(string)$r->getActionName(),
   'name'=>(string)$r->getName(),'methods'=>$r->methods(),
   'middleware'=>$r->gatherMiddleware()];
}
$tables=[];
foreach(['workshop_vendors','am_erp_customers','customer_master','customers'] as $t){
 if(Illuminate\Support\Facades\Schema::hasTable($t))
   $tables[$t]=Illuminate\Support\Facades\Schema::getColumnListing($t);
}
echo json_encode(['routes'=>$items,'tables'=>$tables]);
"""
    with tempfile.TemporaryDirectory(prefix="am188probe_") as d:
        f=Path(d)/"probe.php";f.write_text(code,encoding="utf8")
        p=subprocess.run(["php",str(f),str(root)],capture_output=True,text=True,timeout=45)
        if p.returncode:raise SystemExit("AM188_STOP_APP_ROUTE_SCHEMA_PROBE_FAILED "+p.stderr[:100])
        try:return json.loads(p.stdout)
        except ValueError:raise SystemExit("AM188_STOP_PROBE_INVALID_JSON")

if mode=="rollback":
    if not backup.is_dir():raise SystemExit("AM188_STOP_ROLLBACK_BACKUP_MISSING")
    for r in routes:
        old=backup/(r.name+".before_AM188")
        if not old.exists():continue
        if marker not in read(r):raise SystemExit("AM188_STOP_CURRENT_ROUTES_CHANGED_"+r.name)
        lint(read(old))
        atomic(r,read(old))
    if middleware.is_file() and source.is_file() and read(middleware)==read(source):
        middleware.unlink()
    print("AM188_ROUTE_GUARDS_ROLLED_BACK")
    print("AM188_EXISTING_MASTER_RECORDS_UNCHANGED")
    print("AM188_CLEAR_ROUTE_CACHE")
    sys.exit(0)

if not source.is_file():raise SystemExit("AM188_STOP_GUARD_SOURCE_MISSING")
lint(read(source))
if middleware.exists():raise SystemExit("AM188_STOP_MIDDLEWARE_ALREADY_EXISTS")
for r in routes:
    if not r.is_file():raise SystemExit("AM188_STOP_ROUTE_FILE_MISSING_"+r.name)
    if marker in read(r):raise SystemExit("AM188_STOP_ALREADY_INSTALLED_"+r.name)
    if not read(r).lstrip().startswith("<?php") or read(r).rstrip().endswith("?>"):
        raise SystemExit("AM188_STOP_ROUTE_FORMAT_UNEXPECTED_"+r.name)
probe=php_probe()
tables=probe["tables"]
names={'vendor':['vendor_name','name','supplier_name'],
       'customer':['customer_name','name','full_name']}
phones=['mobile','phone','phone_no','mobile_no','phone_number','contact_no']
gst=['gstin','gst_no','gst_number']
vendor_tables=[t for t in ['workshop_vendors'] if t in tables]
customer_tables=[t for t in ['am_erp_customers','customer_master','customers'] if t in tables]
if not vendor_tables or not customer_tables:
    raise SystemExit("AM188_STOP_VENDOR_OR_CUSTOMER_TABLE_MISSING")
for kind,targets in [('vendor',vendor_tables),('customer',customer_tables)]:
    if not any(any(c in tables[t] for c in names[kind]) for t in targets):
        raise SystemExit("AM188_STOP_MASTER_NAME_SCHEMA_MISSING_"+kind)
    print("AM188_"+kind.upper()+"_TABLES",",".join(targets))
    for t in targets:
        print(" MASTER_SCHEMA",t,"NAME",int(any(c in tables[t] for c in names[kind])),
              "PHONE",int(any(c in tables[t] for c in phones)),
              "GST",int(any(c in tables[t] for c in gst)))

def classify(r):
    if 'POST' not in r["methods"]:return None
    u=r["uri"].lower().strip("/")
    a=r["action"].lower()
    n=r["name"].lower()
    if '{' in u:return None
    if not (u.startswith("erp/") or u.startswith("api/")):return None
    parts=u.split("/")
    # Exact master create paths. Do not block purchases, invoice, customer
    # job-card assignment, payment, OSL or activity endpoints.
    vendor=any(x in parts for x in ("vendors","vendor-master","vendor_master","suppliers"))
    customer=any(x in parts for x in ("customers","customer-master","customer_master"))
    if not vendor and not customer:return None
    if any(x in parts for x in ("payments","ledger","status","toggle",
        "delete","void","archive","merge","update","contact","import",
        "upload","activate","deactivate","assign","search")):return None
    if not any(word in a for word in ("@save","@store","@create","@add","@insert",
                                      "createcustomer","savecustomer","storecustomer")):
        return None
    if vendor and ("vendor" in a or "supplier" in a or u=="erp/vendors"):
        return "vendor"
    if customer and ("customer" in a or u.endswith("/customers")):
        return "customer"
    return None
targets=[]
for r in probe["routes"]:
    group=classify(r)
    if group:
        # Must already require authentication (API tokens or web login).
        if not any(('auth' in x.lower() or "ensureerpadmin" in x.lower())
                   for x in r["middleware"]):
            raise SystemExit("AM188_STOP_UNAUTHENTICATED_CREATE_ROUTE_"+r["uri"])
        targets.append((r,group))
        print("AM188_WILL_PROTECT",group,r["uri"],r["action"])
counts={c:sum(k==c for _,k in targets) for c in ("vendor","customer")}
print("AM188_CREATE_ROUTE_COUNTS",json.dumps(counts))
if not counts['vendor'] or not counts['customer']:
    print("AM188_STOP_CREATE_ROUTE_NOT_IDENTIFIED_FOR_BOTH_MASTERS")
    print("AM188_NO_FILES_CHANGED")
    sys.exit(3)
# Laravel route collections are populated by this point. Mutate only exact
# route objects in the collection that match observed runtime signatures.
snippet=r"""

/* AM188_PREVENT_MASTER_DUPLICATES — guard existing native CREATE routes */
$am188Targets = __TARGETS__;
foreach (\Illuminate\Support\Facades\Route::getRoutes() as $am188Route) {
    $am188Key = (string)$am188Route->uri().'|'.(string)$am188Route->getActionName();
    if (isset($am188Targets[$am188Key]) && in_array('POST', $am188Route->methods(), true)) {
        $am188Guard = \App\Http\Middleware\PreventDuplicateMaster::class.':'.$am188Targets[$am188Key];
        if (!in_array($am188Guard, $am188Route->gatherMiddleware(), true)) {
            $am188Route->middleware($am188Guard);
        }
    }
}
"""
# Each route file sees all routes already registered at that point. We append
# to both web.php and api.php and skip duplicate registrations if encountered.
targetmap={r["uri"]+"|"+r["action"]:kind for r,kind in targets}
serialized=json.dumps(targetmap,ensure_ascii=True)
php_map="["+",".join("'"+key.replace("\\","\\\\").replace("'","\\'")+
                  "'=>'"+value+"'" for key,value in targetmap.items())+"]"
modified={}
for route in routes:
    old=read(route)
    changed=old.rstrip()+"\n"+snippet.replace("__TARGETS__",php_map)+"\n"
    lint(changed)
    modified[route]=changed
print("AM188_PHP_LINT_PASS")
print("AM188_NO_DATABASE_OR_EXISTING_CUSTOMER_VENDOR_WRITES")
if mode=="check":
    print("AM188_CHECK_PASS_NO_WRITES")
    sys.exit(0)
if backup.exists():raise SystemExit("AM188_STOP_BACKUP_EXISTS")
backup.mkdir(parents=True)
os.chmod(backup,0o700)
for r in routes:
    old=backup/(r.name+".before_AM188")
    shutil.copy2(r,old);os.chmod(old,0o600)
shutil.copy2(source,middleware)
for r,changed in modified.items():atomic(r,changed)
print("AM188_VENDOR_CUSTOMER_DUPLICATE_PREVENTION_INSTALLED")
print("AM188_CLEAR_ROUTE_CACHE_AND_VERIFY_CREATE_ROUTES")
