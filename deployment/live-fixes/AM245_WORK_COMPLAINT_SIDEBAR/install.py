#!/usr/bin/env python3
"""AM245: Add Work Complaint Register into existing LIVE WORKSHOP/JOB CARDS sidebar.
Usage: python3 install.py check|apply|rollback STAGING_ROOT.
Only one shared Blade menu file may change. No database/route/controller edits.
"""
from pathlib import Path
from datetime import datetime
import hashlib, json, os, re, shutil, subprocess, sys, tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("USAGE_AM245 install.py check|apply|rollback STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("STOP_AM245_NOT_STAGING")
backup_root=root/"storage/app/am-backups"
pointer=backup_root/"AM245_LAST_BACKUP.txt"
marker="AM245_WORK_COMPLAINT_SIDEBAR"
candidates=[
    "resources/views/layouts/erp.blade.php",
    "resources/views/layouts/partials/sidebar.blade.php",
    "resources/views/layouts/sidebar.blade.php",
    "resources/views/partials/sidebar.blade.php",
    "resources/views/admin/partials/sidebar.blade.php",
]
def read(p):
    return p.read_text("utf-8",errors="surrogateescape")
def enc(s):
    return s.encode("utf-8",errors="surrogateescape")
def sha(b):
    return hashlib.sha256(b).hexdigest()
def run(args,timeout=60):
    return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(p,data):
    fd,tmp=tempfile.mkstemp(prefix=".am245_",dir=p.parent)
    try:
        with os.fdopen(fd,"wb") as f:f.write(data)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def blade_check(data,label):
    with tempfile.TemporaryDirectory(prefix="am245blade_") as d:
        raw=Path(d)/"candidate.blade.php"
        compiled=Path(d)/"compiled.php"
        raw.write_bytes(data)
        script=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        result=run(["php","-r",script,str(raw),str(compiled)],40)
        if result.returncode or not compiled.is_file():
            raise SystemExit("STOP_AM245_BLADE_COMPILE_"+label+" "+(result.stdout+result.stderr)[-800:])
        result=run(["php","-d","display_errors=1","-d","log_errors=0","-l",str(compiled)],25)
        if result.returncode:
            raise SystemExit("STOP_AM245_BLADE_PHP_SYNTAX_"+label+" "+(result.stdout+result.stderr)[-800:])
def route_check():
    script=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$route=\Illuminate\Support\Facades\Route::getRoutes()->getByName('erp.rot-work-complaints.register');
if (!$route || !in_array('GET',$route->methods(),true)) {fwrite(STDERR,'AM245_COMPLAINT_REGISTER_GET_ROUTE_MISSING');exit(17);}
echo 'AM245_REGISTER_ROUTE_PASS';"""
    result=run(["php","-r",script],35)
    if result.returncode or "AM245_REGISTER_ROUTE_PASS" not in result.stdout:
        raise SystemExit("STOP_AM245_COMPLAINT_ROUTE_NOT_INSTALLED "+result.stderr[-400:])
    print("AM245_REGISTER_ROUTE_PASS")

if mode=="rollback":
    if not pointer.is_file():
        raise SystemExit("STOP_AM245_NO_ROLLBACK_POINTER")
    backup=Path(pointer.read_text().strip()).resolve()
    if backup_root.resolve() not in backup.parents or not backup.name.startswith("AM245_WORK_COMPLAINT_MENU_"):
        raise SystemExit("STOP_AM245_INVALID_BACKUP_PATH")
    manifest=backup/"manifest.json"
    before=backup/"menu.before.blade.php"
    if not manifest.is_file() or not before.is_file():
        raise SystemExit("STOP_AM245_BACKUP_MISSING")
    data=json.loads(manifest.read_text())
    relative=data["relative"]
    if relative not in candidates:
        raise SystemExit("STOP_AM245_UNSAFE_TARGET")
    target=root/relative
    if not target.is_file() or sha(target.read_bytes())!=data["installed_sha"]:
        raise SystemExit("STOP_AM245_MENU_CHANGED_AFTER_INSTALL")
    atomic(target,before.read_bytes())
    result=run(["php","artisan","view:clear"],65)
    if result.returncode:
        raise SystemExit("AM245_MENU_RESTORED_BUT_VIEW_CLEAR_FAILED")
    print("AM245_ORIGINAL_SIDEBAR_RESTORED")
    print("AM245_NO_BUSINESS_RECORDS_TOUCHED")
    sys.exit(0)

route_check()
a_pat=re.compile(r"<a\b[^>]{0,1800}>[\s\S]{0,1400}?</a>",re.I)
found=[]
already=[]
for rel in candidates:
    path=root/rel
    if not path.is_file():continue
    src=read(path)
    if marker in src:
        already.append(rel)
    # Locate a unique authenticated ERP sidebar navigation section;
    # never edit arbitrary body links or menu entries outside nav.
    for nav in re.finditer(r"<nav\b[^>]{0,800}\bclass\s*=\s*['\"][^'\"]*\bsidebar-nav\b[^'\"]*['\"][^>]*>[\s\S]*?</nav\s*>",src,re.I):
        nav_markup=nav.group(0)
        for anchor in a_pat.finditer(nav_markup):
            markup=anchor.group(0)
            first=markup.split(">",1)[0]+">"
            body=markup[len(first):-4]
            visible=re.sub(r"<[^>]*>","",body)
            if not re.search(r"\bjob\s*cards\b",visible,re.I):continue
            if not ("job-cards.index" in first or re.search(r"/job-cards\b",first,re.I)):continue
            found.append((rel,nav.start()+anchor.start(),nav.start()+anchor.end(),markup,first,body))
if already:
    raise SystemExit("STOP_AM245_ALREADY_INSTALLED_IN_"+",".join(already))
if len(found)!=1:
    print("AM245_SIDEBAR_CANDIDATES",[(rel, (root/rel).is_file()) for rel in candidates])
    raise SystemExit("STOP_AM245_JOB_CARDS_NAV_ANCHOR_COUNT_"+str(len(found))+" (read-only; never guess location)")
rel,start,end,existing,opening,body=found[0]
source=read(root/rel)
if len(source)>2000000:
    raise SystemExit("STOP_AM245_LAYOUT_UNEXPECTEDLY_LARGE")
if source[start:end]!=existing:
    raise SystemExit("STOP_AM245_ANCHOR_CHANGED")
if any(x in existing for x in ("@foreach","@endforeach","@endfor","@section")):
    raise SystemExit("STOP_AM245_COMPLEX_ANCHOR")
if len(re.findall(r"\bjob\s*cards\b",body,re.I))!=1:
    raise SystemExit("STOP_AM245_LABEL_NOT_UNIQUE")
if not re.search(r'\bhref\s*=\s*"[^"]+"',opening):
    raise SystemExit("STOP_AM245_HREF_ATTRIBUTE_UNEXPECTED")
opening_new=re.sub(
    r'\bhref\s*=\s*"[^"]+"',
    lambda m:'href="{{ route(\'erp.rot-work-complaints.register\') }}"',
    opening,count=1
)
# Keep inherited CSS/icon, only replace visible text, navigation target and active-route check.
body_new=re.sub(r"\bjob\s*cards\b","WORK COMPLAINT REGISTER",body,count=1,flags=re.I)
newlink=opening_new+body_new+"</a>"
newlink=newlink.replace("erp.job-cards.*","erp.rot-work-complaints.*")
newlink=newlink.replace("erp.job-cards.index","erp.rot-work-complaints.register")
if "erp.rot-work-complaints.register" not in newlink:
    raise SystemExit("STOP_AM245_LINK_ROUTE_UNEXPECTED")
# Insert at the end of the Job Cards anchor: stays inside its existing LIVE WORKSHOP navigation group.
addition="\n{{-- "+marker+": original job card anchor untouched --}}\n"+newlink
patched=source[:end]+addition+source[end:]
if patched.count(marker)!=1 or patched.count("WORK COMPLAINT REGISTER")!=1:
    raise SystemExit("STOP_AM245_MENU_INJECTION_INTEGRITY")
if source.count(existing)!=1:
    raise SystemExit("STOP_AM245_JOB_CARDS_ANCHOR_NOT_UNIQUE")
blade_check(enc(source),"ORIGINAL")
blade_check(enc(patched),"UPDATED")
print("AM245_NAV_FILE",rel)
print("AM245_INSERT_POSITION immediately after existing JOB CARDS")
print("AM245_LINK route('erp.rot-work-complaints.register')")
print("AM245_BLADE_BASELINE_AND_UPDATED_PASS")
print("AM245_NO_CONTROLLER_ROUTE_DATABASE_CHANGES")
print("AM245_PREFLIGHT_PASS")
if mode=="check":
    print("AM245_CHECK_READ_ONLY")
    sys.exit(0)

path=root/rel
original=path.read_bytes()
if original!=enc(source):
    raise SystemExit("STOP_AM245_LAYOUT_CHANGED_DURING_CHECK")
backup=backup_root/("AM245_WORK_COMPLAINT_MENU_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False)
shutil.copy2(path,backup/"menu.before.blade.php")
try:
    atomic(path,enc(patched))
    cleared=run(["php","artisan","view:clear"],65)
    if cleared.returncode:
        raise RuntimeError("VIEW_CLEAR_FAILED "+(cleared.stdout+cleared.stderr)[-350:])
    compiled=run(["php","artisan","view:cache"],115)
    if compiled.returncode:
        raise RuntimeError("VIEW_CACHE_FAILED "+(compiled.stdout+compiled.stderr)[-650:])
except Exception as error:
    atomic(path,original)
    run(["php","artisan","view:clear"],65)
    raise SystemExit("STOP_AM245_APPLY_FAILED_RESTORED_"+str(error))
(backup/"manifest.json").write_text(json.dumps({"relative":rel,"installed_sha":sha(enc(patched))},indent=2))
pointer.write_text(str(backup)+"\n")
print("AM245_ADMIN_SIDEBAR_COMPLAINT_MENU_INSTALLED")
print("BACKUP",backup)
print("BROWSER_CHECK_REQUIRED: reload Admin ERP, look below JOB CARDS")
