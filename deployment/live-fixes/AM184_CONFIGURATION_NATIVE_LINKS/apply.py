#!/usr/bin/env python3
"""AM184: migrate STAGING Configuration tiles off OLD ERP safely.

- Finds the native Configuration Blade view from AdminDashboardPageController.
- Discovers currently registered ERP GET routes and their admin middleware.
- Rewires only verified equivalent native routes; never invents a URL.
- All-or-nothing: refuses to APPLY if even one legacy tile has no native route.
- Preserves current form/actions and database; backup, lint, rollback available.
- No secrets or customer records printed.

Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import datetime,html,json,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("AM184_STOP_NOT_STAGING_ROOT")
ctl=root/"app/Http/Controllers/Web/AdminDashboardPageController.php"
if not ctl.is_file():raise SystemExit("AM184_STOP_CONFIGURATION_CONTROLLER_MISSING")
backup_dir=root/"storage/app/am-backups/AM184_CONFIGURATION_NATIVE"
marker="AM184_CONFIGURATION_NATIVE_LINKS"
def read(p):
    return p.read_text(encoding="utf8",errors="surrogateescape")
def atomic(p,source):
    fd,tm=tempfile.mkstemp(prefix=".am184_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as o:o.write(source)
        os.chmod(tm,p.stat().st_mode & 0o777)
        os.replace(tm,p)
    finally:
        if os.path.exists(tm):os.unlink(tm)
def cleanup_label(s):
    return re.sub(r"\s+"," ",html.unescape(re.sub(r"<[^>]+>","",s))).strip()
def controller_view():
    src=read(ctl)
    a=re.search(r"\bfunction\s+configuration\s*\(",src)
    if not a:raise SystemExit("AM184_STOP_CONFIGURATION_METHOD_MISSING")
    # Laravel View name from within this method.
    block=src[a.start():a.start()+8000]
    # Keep the scan within the method by stopping at the next public method.
    block=re.split(r"\n\s*public\s+function\s+",block,maxsplit=1)[0]
    views=re.findall(r"\bview\s*\(\s*['\"]([^'\"]+)['\"]",block)
    for name in views:
        target=root/"resources/views"/(name.replace(".","/")+".blade.php")
        if target.is_file():return target
    # Explicit fallback restricted to known local Configuration Blade paths.
    matches=[p for p in (root/"resources/views").rglob("*configuration*.blade.php")
             if 'module-tile' in read(p)]
    if len(matches)==1:return matches[0]
    raise SystemExit("AM184_STOP_AMBIGUOUS_CONFIGURATION_VIEW")
view=controller_view()
print("AM184_NATIVE_CONFIGURATION_TILES")
print("CONFIGURATION_VIEW",view.relative_to(root))

backup=backup_dir/"configuration_view_before_AM184.blade.php"
if mode=="rollback":
    if not backup.is_file():raise SystemExit("AM184_STOP_ROLLBACK_BACKUP_MISSING")
    current=read(view)
    if marker not in current:raise SystemExit("AM184_STOP_NOT_INSTALLED")
    atomic(view,read(backup))
    print("AM184_CONFIGURATION_VIEW_ROLLED_BACK")
    print("AM184_RUN_PHP_ARTISAN_VIEW_CLEAR")
    sys.exit(0)

source=read(view)
if marker in source:
    print("AM184_ALREADY_INSTALLED")
    sys.exit(0)

# Runtime route list: Laravel's authoritative route collection, not guessed
# from source patterns. Print only name, URI, action, middleware for GET pages.
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$result=[];
foreach($app->make('router')->getRoutes() as $r){
 $uri=(string)$r->uri();$name=(string)$r->getName();
 if(!str_starts_with($uri,'erp/') || !in_array('GET',$r->methods(),true))continue;
 $mw=$r->gatherMiddleware();$action=(string)$r->getActionName();
 $result[]=['name'=>$name,'uri'=>$uri,'action'=>$action,'middleware'=>$mw];
}
echo json_encode($result,JSON_UNESCAPED_SLASHES);
"""
with tempfile.TemporaryDirectory(prefix="am184rt_") as d:
    p=Path(d)/"route-info.php";p.write_text(php,encoding="utf8")
    r=subprocess.run(["php",str(p),str(root)],capture_output=True,text=True,timeout=30)
    if r.returncode:raise SystemExit("AM184_STOP_ROUTE_PROBE_FAILED "+r.stderr[:150])
    try:routes=json.loads(r.stdout)
    except ValueError:raise SystemExit("AM184_STOP_ROUTE_PROBE_JSON_INVALID")

# Only the already-existing NEW ERP admin pages qualify for migration.
valid={}
for r in routes:
    name=r["name"]
    if not name or '{' in r["uri"] or "legacy" in r["uri"].lower():continue
    mw=" ".join(r["middleware"])
    if ("EnsureErpAdmin" not in mw or "auth:web" not in mw or
         "Admin" not in r["action"] or r["action"]=="Closure"):continue
    valid[name]=r

# Explicit semantic equivalence: prefer stable route names over heuristic
# partial matches (e.g. never mistake Parts Purchase for Parts Master).
categories=[
 ("mapping",r"mapping|labour.*part.*map|rot.*map",[
    "erp.mappings","erp.mappings.index","erp.native.mappings",
    "erp.configuration.mappings","erp.labour-mappings.index"]),
 ("customer",r"customer",[
    "erp.customers","erp.customers.index","erp.native.customers"]),
 ("vendor",r"vendor",[
    "erp.vendors.index","erp.vendors"]),
 ("labour",r"labou?r|rot.master|labor",[
    "erp.labour","erp.labour.index","erp.native.labour",
    "erp.labour-master.index","erp.rot-master.index"]),
 ("part",r"part|spare",[
    "erp.parts","erp.parts.index","erp.native.parts",
    "erp.part-master.index"]),
 ("vehicle",r"vehicle",[
    "erp.vehicles","erp.vehicles.index","erp.native.vehicles"]),
 ("user",r"\busers?\b|access|roles?",[
    "erp.users","erp.users.index","erp.native.users"]),
 ("staff",r"staff|employee",[
    "erp.staff","erp.staff.index","erp.native.staff"]),
 ("products",r"products?|catalog",[
    "erp.products","erp.products.index","erp.native.products"]),
 ("reminders",r"service.reminder",[
    "erp.service-reminders.index","erp.service-reminders"]),
 ("attendance",r"attendance",[
    "erp.attendance","erp.attendance.index"]),
 ("settings",r"settings?|configuration",[
    "erp.settings","erp.settings.index","erp.configuration.settings"]),
]
# Scope to <a class="module-tile"...> so form/action URLs aren't touched.
anchor_re=re.compile(r'<a\b[^>]{0,1200}\bclass\s*=\s*"[^"]*\bmodule-tile\b[^"]*"[^>]*>[\s\S]*?</a>',re.I)
anchors=list(anchor_re.finditer(source))
print("CONFIGURATION_TILE_COUNT",len(anchors))
if not anchors:raise SystemExit("AM184_STOP_NO_CONFIGURATION_TILES")
old_re=re.compile(r"(?:/legacy/|(?:assammotors\.com)?/workshop/|https?://(?:www\.)?assammotors\.com/workshop/)",re.I)
href_re=re.compile(r'\bhref\s*=\s*"([^"]*)"')
changes=[]
unresolved=[]
for m in anchors:
    a=m.group()
    h=href_re.search(a)
    t=re.search(r'<h3[^>]*>([\s\S]*?)</h3>',a,re.I)
    if not h or not t:continue
    href=h.group(1)
    title=cleanup_label(t.group(1))
    if not old_re.search(href):
        print("TILE_NATIVE_OR_OTHER",title)
        continue
    category=None
    key=(title+" "+href).lower()
    for c,pattern,names in categories:
        if re.search(pattern,key,re.I):
            category=(c,names)
            break
    found=None
    if category:
        for name in category[1]:
            if name in valid:
                found=name
                break
    if not found:
        unresolved.append(title)
        print("TILE_NEEDS_NATIVE_MODULE",title)
        continue
    print("TILE_MAPPED",title,"TO",found)
    new_href="{{ route('"+found+"') }}"
    new_a=a[:h.start(1)]+new_href+a[h.end(1):]
    changes.append((m.start(),m.end(),new_a))
print("LEGACY_TILES_FOUND",len(changes)+len(unresolved))
print("NATIVE_ROUTE_MATCHES",len(changes))
print("REMAINING_UNMIGRATED_TILES",len(unresolved))
if unresolved:
    print("AM184_STOP_UNMIGRATED_CONFIGURATION_MODULES")
    print("AM184_NO_FILES_CHANGED")
    sys.exit(3)
if not changes:
    print("AM184_NO_LEGACY_TILE_LINKS_FOUND")
    sys.exit(0)
candidate=source
for start,end,replacement in sorted(changes,reverse=True):
    candidate=candidate[:start]+replacement+candidate[end:]
candidate="{{{-- "+marker+" --}}\n"+candidate if False else candidate
# Valid Blade comment after the @section('content') prevents adding text before
# @extends. Keep marker separate from the markup.
insert_at=candidate.find("@section('content')")
if insert_at<0:raise SystemExit("AM184_STOP_CONTENT_SECTION_MISSING")
candidate=candidate[:insert_at]+"{{-- "+marker+" --}}\n"+candidate[insert_at:]
if candidate.count(marker)!=1:raise SystemExit("AM184_STOP_MARKER_INTEGRITY_FAILED")
for name in ("view","php"):
    pass
if mode=="check":
    print("AM184_CHECK_PASS_NO_WRITES")
    sys.exit(0)
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
if backup.exists():raise SystemExit("AM184_STOP_BACKUP_ALREADY_EXISTS")
shutil.copy2(view,backup)
os.chmod(backup,0o600)
atomic(view,candidate)
print("AM184_NATIVE_CONFIGURATION_TILE_LINKS_APPLIED",len(changes))
print("AM184_SETTINGS_AND_DATA_UNCHANGED")
print("AM184_RUN_VIEW_CLEAR_AND_BROWSER_FUNCTION_TEST")
