#!/usr/bin/env python3
"""AM198 — READ ONLY: Why AM197 installed but Job Card Register looks unchanged?

Tests actual Laravel route->controller->view->compiled view chain, detects
alternate template/layout and verifies live AM197 HTML marker. No DB writes,
no route changes, no caches cleared, no authentication bypass, no data access.
Runs entirely in STAGING root; prints sanitized source signatures only.

python3 audit.py /home/.../assam-erp-staging
"""
from pathlib import Path
import os,re,subprocess,sys,tempfile,json,hashlib

if len(sys.argv)!=2: raise SystemExit("Usage: python3 audit.py STAGING_LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("AM198_STOP_LARAVEL_ROOT_INVALID")
view=root/"resources/views/job-cards/index.blade.php"
controller=root/"app/Http/Controllers/Web/AdminJobCardPageController.php"
layout=root/"resources/views/layouts/erp.blade.php"
expected_names=["AM62_PROTOTYPE_MATCH","AM196_COMPACT_JOB_CARD_REGISTER",
                "AM197_REGISTER_REAL_LAYOUT","am197-filter-card",
                "Filter Job Cards — Status / Service / Inspection"]

def read(p):
    return p.read_text(encoding="utf8",errors="replace") if p.is_file() else ""
def rel(p):
    try:return str(p.relative_to(root))
    except ValueError:return str(p)
def yes(v):return "YES" if v else "NO"

print("AM198_READ_ONLY_REGISTER_RENDER_AUDIT")
print("ROOT_EXISTS",root.is_dir())
print("ROOT_ARTISAN",yes((root/"artisan").is_file()))
print("VIEW_PATH",rel(view))
print("VIEW_EXISTS",yes(view.is_file()))
if view.is_file():
    s=read(view)
    print("VIEW_SHA256_PREFIX",hashlib.sha256(view.read_bytes()).hexdigest()[:16])
    for name in expected_names:
        print("VIEW_MARKER",name,"FOUND" if name in s else "MISSING")
    print("VIEW_TOTAL_LINES",s.count("\n")+1)
    print("OLD_SECTION_OPENERS",s.count('<section class="am62-card am62-preview"'))
    print("NEW_SECTION_OPENERS",s.count('<section class="am62-card am197-preview"'))
    print("OLD_FILTER_OPENERS",s.count('<details class="am62-card am62-more"'))
    print("NEW_FILTER_OPENERS",s.count('id="am197-filter-card"'))
    print("OLD_UI_TEXT_PRESENT",yes("Register totals & advanced filters" in s))
    print("NEW_UI_TEXT_PRESENT",yes("Filter Job Cards — Status / Service / Inspection" in s))
    print("VIEW_CONTAINS_FINANCE_GUARDS",yes(all(x in s for x in (
        'id="am62-return-form"', 'id="am62-return-blocked"', "const eligible=!!a.post",
        "route('erp.job-cards.return-to-jc.unpaid'"))))
print("CONTROLLER_EXISTS",yes(controller.is_file()))
if controller.is_file():
    c=read(controller)
    for i,line in enumerate(c.splitlines(),1):
        if re.search(r"function\s+index\s*\(|return\s+view\s*\(|return\s+View::make\s*\(|return\s+response\(\)->view\s*\(",line):
            if re.search(r"(password|secret|token|key|credential|auth)",line,re.I):continue
            print("CONTROLLER_VIEW_OR_INDEX_LINE",i,line.strip()[:180])
print("LAYOUT_EXISTS",yes(layout.is_file()))
if layout.is_file():
    l=read(layout)
    print("LAYOUT_HAS_CONTENT_YIELD",yes("@yield('content')" in l or '@yield("content")' in l))
print("\nAM198_LARAVEL_BOOTSTRAP_ROUTE_AND_VIEW")
probe=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Blade;
$routes=[];
$router=$app->make('router');
foreach($router->getRoutes() as $r){
 if($r->getName()==='erp.job-cards.index' ||
    (in_array('GET',$r->methods(),true) && $r->uri()==='erp/job-cards')) {
  $routes[]=['methods'=>$r->methods(),'name'=>$r->getName(),
   'uri'=>$r->uri(),'action'=>$r->getActionName(),
   'domain'=>$r->getDomain(),'middleware'=>$r->gatherMiddleware()];
 }
}
try{
 $match=$router->getRoutes()->match(
  Illuminate\Http\Request::create('https://staging.assammotors.com/erp/job-cards','GET')
 );
 $actual=['uri'=>$match->uri(),'action'=>$match->getActionName(),
          'name'=>$match->getName()];
}catch(\Throwable $e){$actual=['error'=>get_class($e)];}
$finder=View::getFinder();
try{$resolved=$finder->find('job-cards.index');}
catch(\Throwable $e){$resolved='VIEW_NOT_RESOLVED: '.get_class($e);}
$compiled=null;
if(is_string($resolved) && is_file($resolved)){
 $compiled=$app->make('blade.compiler')->getCompiledPath($resolved);
}
$paths=$finder->getPaths();
echo "AM198_RUNTIME_JSON=".json_encode([
  'routes'=>$routes,'matched'=>$actual,'view'=>$resolved,
  'compiled_file'=>$compiled,'compiled_exists'=>$compiled && is_file($compiled),
  'compiled_mtime'=>$compiled && is_file($compiled)?date('c',filemtime($compiled)):null,
  'route_cached'=>$app->routesAreCached(),
  'view_paths'=>$paths,
  'public_path'=>public_path(),
],JSON_INVALID_UTF8_SUBSTITUTE)."\n";
"""
with tempfile.TemporaryDirectory(prefix="am198php_") as d:
    p=Path(d)/"audit.php";p.write_text(probe,encoding="utf8")
    run=subprocess.run(["php",str(p),str(root)],cwd=root,
                       capture_output=True,text=True,timeout=45)
    if run.returncode:
        print("AM198_RUNTIME_PROBE_ERROR",run.stderr[-350:].replace("\n"," "))
    else:
        line=next((x for x in run.stdout.splitlines()
                   if x.startswith("AM198_RUNTIME_JSON=")),None)
        if not line:
            print("AM198_RUNTIME_PROBE_NO_JSON")
        else:
            try:
                j=json.loads(line.split("=",1)[1])
                print("RUNTIME_ROUTE_CACHED",yes(j.get("route_cached")))
                print("RUNTIME_MATCHED_ROUTE",j.get("matched"))
                print("RUNTIME_NAMED_GET_ROUTE_COUNT",len(j.get("routes",[])))
                for r in j.get("routes",[])[:12]:
                    print("RUNTIME_ROUTE",r.get("methods"),r.get("name"),
                          r.get("uri"),r.get("action"),
                          "DOMAIN",r.get("domain"))
                resolved=Path(j.get("view") or "/")
                print("RESOLVED_VIEW",j.get("view"))
                print("RESOLVED_VIEW_IS_TARGET",yes(resolved.resolve()==view.resolve()))
                print("COMPILED_VIEW_EXISTS",yes(j.get("compiled_exists")))
                print("COMPILED_VIEW_MTIME",j.get("compiled_mtime"))
                if j.get("compiled_exists") and j.get("compiled_file"):
                    cp=Path(j["compiled_file"])
                    cp_content=read(cp)
                    for mark in ("AM197_REGISTER_REAL_LAYOUT","am197-filter-card",
                                 "Filter Job Cards — Status / Service / Inspection"):
                        print("COMPILED_MARKER",mark,
                              "FOUND" if mark in cp_content else "MISSING")
                print("PUBLIC_PATH",j.get("public_path"))
                print("VIEW_PATHS",j.get("view_paths"))
            except (ValueError,TypeError) as e:
                print("AM198_RUNTIME_PROBE_PARSE_ERROR",type(e).__name__)

print("\nAM198_KNOWN_ALT_REGISTER_COPIES")
for folder in (root/"resources/views",):
    if not folder.is_dir():continue
    found=0
    for p in folder.rglob("*.blade.php"):
        if p==view or not p.is_file() or p.stat().st_size>400000:continue
        raw=read(p)
        if ("Register totals & advanced filters" in raw or
            'id="am62-selected"' in raw or 'id="am197-filter-card"' in raw):
            print("ALTERNATIVE_REGISTER_TEMPLATE",rel(p),
                  "AM197",yes("AM197_REGISTER_REAL_LAYOUT" in raw))
            found+=1
            if found>=15:break
    if found==0:print("NO_ALTERNATIVE_REGISTER_TEMPLATES_FOUND")

print("\nAM198_NO_DATABASE_WRITES_NO_FILES_CHANGED")
print("AM198_AUDIT_COMPLETE")
