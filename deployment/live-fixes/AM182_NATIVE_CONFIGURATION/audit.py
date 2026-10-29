#!/usr/bin/env python3
"""AM182 READ-ONLY NEW ERP Configuration migration audit.

Finds the Configuration sidebar destination, native routes/controllers/views,
legacy Configuration feature handlers, and database table names (no records).
Never prints environment/config file contents, credentials, staff/customer data.
No writes.  Usage: python3 audit.py LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import os,re,subprocess,sys,tempfile

if len(sys.argv)!=2:
    raise SystemExit("Usage: python3 audit.py LARAVEL_STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():
    raise SystemExit("AM182_STOP_NOT_LARAVEL_STAGING")
domain=root.parent
print("AM182_CONFIGURATION_MIGRATION_READ_ONLY")
sensitive=re.compile(r'(?i)\b(password|secret|token|api[_-]?key|db[_-]?(pass|user|host)|authorization|bearer|private_key|client_secret)\b')
hint=re.compile(r'(?i)configuration|configurations|settings|preferences|shop.?setup|system.?setup|workshop.?setup|rot.?master|labor.?rate')
def safe(s):
    if sensitive.search(s):return "[SECRET_RELATED_LINE_REDACTED]"
    return s.strip()[:235]
def read(p):
    try:return p.read_text(encoding="utf-8",errors="replace").splitlines()
    except (OSError,UnicodeError):return []
def rel(p):
    try:return str(p.relative_to(domain))
    except ValueError:return str(p.relative_to(root)) if p.is_relative_to(root) else p.name
def scan_nav(p):
    lines=read(p)
    hits=[i for i,l in enumerate(lines) if re.search(r'configuration',l,re.I)]
    if not hits:return
    print("NAV_FILE",rel(p),"CONFIGURATION_MATCHES",len(hits))
    for i in hits[:10]:
        print(" NAV_CONTEXT",i+1)
        for j in range(max(0,i-2),min(len(lines),i+3)):
            print(" NAV_LINE",j+1,safe(lines[j]))
def scan_file(p):
    if not p.is_file():return
    if p.stat().st_size>400_000:return
    lines=read(p)
    print("SOURCE_FILE",rel(p),"LINES",len(lines))
    count=0
    for i,l in enumerate(lines):
        if re.search(r'(?i)<form\b|<input\b|<select\b|action\s*=|method\s*=|'
                     r'(INSERT|UPDATE|DELETE)\s+|function\s+|DB::table|->save\(|'
                     r'\$_POST|route\(|->name\(',l):
            if sensitive.search(l):continue
            # Only static source lines, no applicant/account record data.
            print(" SRC",i+1,safe(l))
            count+=1
            if count>=70:break
def eligible(p):
    return p.is_file() and p.stat().st_size<450_000 and p.suffix.lower() in (".php",".html",".htm",".js") and not re.search(r'(?i)(\.env|backup|\.bak|secret|credentials|config\.php|database\.php)',p.name)
def related(p):
    return bool(re.search(r'(?i)(configuration|settings|preferences|workshop.?config|system.?setup|shop.?setup)',p.name))
print("\nSECTION SIDEBAR_CONFIGURATION_LINKS")
navbases=[
    root/"resources/views/layouts",
    root/"resources/views/partials",
    root/"resources/views/admin",
]
seen=set()
for base in navbases:
    if not base.is_dir():continue
    for folder,dirs,files in os.walk(base):
        dirs[:]=[d for d in dirs if d not in ("vendor","node_modules","cache")]
        depth=len(Path(folder).relative_to(base).parts)
        if depth>2:
            dirs.clear();continue
        for name in files:
            p=Path(folder)/name
            if p in seen or not p.name.endswith(".blade.php"):continue
            seen.add(p)
            if p.stat().st_size>400_000:continue
            if any("configuration" in l.lower() for l in read(p)):
                scan_nav(p)
print("\nSECTION CONFIGURATION_ROUTE_SOURCE")
for p in (root/"routes/web.php",root/"routes/api.php"):
    if not p.is_file():continue
    lines=read(p)
    print("ROUTE_FILE",rel(p),"LINES",len(lines))
    for i,l in enumerate(lines):
        if hint.search(l):
            print(" ROUTE",i+1,safe(l))
print("\nSECTION RELATED_CONTROLLERS_AND_VIEWS")
for base in (root/"app/Http/Controllers",root/"app/Services",
             root/"resources/views"):
    if not base.is_dir():continue
    files=[]
    for folder,dirs,names in os.walk(base):
        dirs[:]=[d for d in dirs if d not in ("vendor","node_modules","cache")]
        for n in names:
            p=Path(folder)/n
            if eligible(p) and related(p):
                files.append(p)
    print("RELATED_SOURCE_DIR",rel(base),"FILES",len(files))
    for p in sorted(files)[:40]:
        scan_file(p)
print("\nSECTION LEGACY_AND_PUBLIC_CONFIGURATION_FILES")
for base in (
 root/"public/legacy/workshop",
 root/"public/legacy/admin",
 root/"public/legacy",
 domain/"public_html/workshop",
 domain/"public_html/admin",
 domain/"public_html"
):
    if not base.is_dir():continue
    found=[]
    for p in base.iterdir():
        if eligible(p) and related(p):found.append(p)
        if p.is_dir() and related(p):
            for child in list(p.iterdir())[:100]:
                if eligible(child):found.append(child)
    print("LEGACY_DIR",rel(base),"CONFIG_PAGE_CANDIDATES",len(found))
    for p in sorted(found)[:25]:
        scan_file(p)
print("\nSECTION RUNTIME_CONFIG_ROUTES")
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
 foreach($app->make('router')->getRoutes() as $r){
  $uri=(string)$r->uri();$name=(string)$r->getName();
  $action=(string)$r->getActionName();
  if(preg_match('/configur|settings|preferences|setup/i',$uri.' '.$name.' '.$action))
   echo 'ROUTE '.json_encode(['uri'=>$uri,'name'=>$name,'action'=>$action,
    'methods'=>implode(',',$r->methods()),'middleware'=>$r->gatherMiddleware()],
    JSON_UNESCAPED_SLASHES)."\n";
 }
}catch(Throwable $e){echo 'ROUTE_PROBE_FAILED '.get_class($e)."\n";}
"""
with tempfile.TemporaryDirectory(prefix="am182rt_") as d:
    p=Path(d)/"routes.php";p.write_text(php,encoding="utf8")
    result=subprocess.run(["php",str(p),str(root)],capture_output=True,
        text=True,timeout=30)
    print(result.stdout.strip()[:6500] if result.returncode==0 else "ROUTE_PROBE_NOT_AVAILABLE")
print("\nSECTION SETTINGS_TABLE_NAMES_ONLY")
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
 foreach(Illuminate\Support\Facades\DB::select('SHOW TABLES') as $row){
  $values=array_values((array)$row);$t=(string)($values[0]??'');
  if(preg_match('/config|setting|option|preference|rate_master|tax_master/i',$t)){
   $cols=Illuminate\Support\Facades\Schema::getColumnListing($t);
   echo 'TABLE '.$t.' COLUMNS '.implode(',',$cols)."\n";
  }
 }
}catch(Throwable $e){echo 'SCHEMA_PROBE_FAILED '.get_class($e)."\n";}
"""
with tempfile.TemporaryDirectory(prefix="am182db_") as d:
    p=Path(d)/"tables.php";p.write_text(php,encoding="utf8")
    result=subprocess.run(["php",str(p),str(root)],capture_output=True,
        text=True,timeout=30)
    print(result.stdout.strip()[:4500] if result.returncode==0 else "SCHEMA_PROBE_NOT_AVAILABLE")
print("AM182_AUDIT_COMPLETE_NO_WRITES")
