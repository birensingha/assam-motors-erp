#!/usr/bin/env python3
"""AM191 READ-ONLY sidebar reset root-cause audit.

Runs in the live STAGING Laravel root, prints only DOM/CSS/JS source excerpts
relevant to sidebar scroll and rendered asset URL. Does not change files.
Does not print environment secrets, database data or session values.

Usage: python3 audit.py /path/to/assam-erp-staging
"""
from pathlib import Path
import sys,re,hashlib,subprocess,tempfile,os,json,urllib.request,urllib.parse
if len(sys.argv)!=2:raise SystemExit("Usage: python3 audit.py STAGING_LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
layout=root/"resources/views/layouts/erp.blade.php"
if not (root/"artisan").is_file() or not layout.is_file():
    raise SystemExit("AM191_STOP_NOT_STAGING_LARAVEL_LAYOUT")
print("AM191_SIDEBAR_ROOT_CAUSE_READ_ONLY")
def read(p):
    try:return p.read_text(encoding="utf8",errors="replace").splitlines()
    except OSError:return []
def relative(p):
    try:return str(p.relative_to(root))
    except ValueError:return p.name
# Prevent inadvertent printing of keys or personal data from arbitrary files.
block=re.compile(r"(?i)password|secret|token|authorization|session|private.?key|API_KEY|DB_HOST|DB_PASS|DB_USERNAME|APP_KEY|cookie|bearer|credentials")
def safe(s):
    if block.search(s):return "[OMITTED_SENSITIVE_LINE]"
    return s.strip()[:340]

print("\nSECTION LAYOUT_SCROLL_STRUCTURE")
layout_lines=read(layout)
patterns=[
    (re.compile(r"(?i)<(aside|nav|div|section|main)\b[^>]*(sidebar|side-nav|side_menu|side-menu|menu-scroll|nav-scroll|sidenav)[^>]*>"),"SIDEBAR_ELEMENT"),
    (re.compile(r"(?i)<(aside|nav)\b"),"NAV_STRUCTURE"),
    (re.compile(r"(?i)overflow-y\s*:|overflow\s*:|scrollbar|height:\s*(?:100vh|calc|100%)|max-height:"),"SCROLL_CSS"),
    (re.compile(r"(?i)scrollTop|scrollTo\(|scrollIntoView\(|focus\(|active.?menu|active.?nav"),"SCROLL_BEHAVIOUR"),
    (re.compile(r"(?i)(<script|@include|@vite|@push|@stack|@livewireScripts|</body)"),"SCRIPT_INCLUDE"),
]
hits=[]
for i,l in enumerate(layout_lines):
    for regex,label in patterns:
        if regex.search(l):
            hits.append((i,label))
            break
print("LAYOUT_LINES",len(layout_lines),"HITS",len(hits))
printed=set()
for i,label in hits[:95]:
    print("MATCH",label,"LINE",i+1)
    for j in range(max(0,i-1),min(len(layout_lines),i+2)):
        if j not in printed:
            print("LAYOUT",j+1,safe(layout_lines[j]))
            printed.add(j)

print("\nSECTION CURRENT_SIDEBAR_JS")
asset=root/"public/js/am189-sidebar-scroll.js"
if asset.exists():
    lines=read(asset)
    js="\n".join(lines)
    print("ASSET_EXISTS True")
    print("ASSET_SIZE",asset.stat().st_size)
    print("ASSET_SHA256_PREFIX",hashlib.sha256(asset.read_bytes()).hexdigest()[:16])
    print("ASSET_IS_AM189","__am189SidebarScrollLoaded" in js)
    print("ASSET_IS_AM190","__am190SidebarScrollLoaded" in js)
    for i,l in enumerate(lines):
        if re.search(r"scrollTop|sessionStorage|matchSidebar|best\(|restore\(|getBoundingClientRect|scrollHeight",l):
            print("JS",i+1,safe(l))
            if i>220:break
else:print("ASSET_EXISTS False")
for x in ("?v=1891","?v=1902","am189-sidebar-scroll.js","AM189_SIDEBAR_SCROLL_PERSIST"):
    print("LAYOUT_CONTAINS",x,any(x in y for y in layout_lines))

print("\nSECTION SIDEBAR_CSS_JS_PROJECT_REFERENCES")
# Scan only local sidebar/nav-related files for explicit programmatic reset
# or overflow declarations. Avoid dumping source from arbitrary modules.
candidates=[]
for folder in (root/"public/css",root/"public/js",root/"resources/css",root/"resources/js"):
    if not folder.exists():continue
    for p in folder.rglob("*"):
        if p.is_file() and p.suffix in (".js",".css") and (
            any(token in p.name.lower() for token in ("erp","admin","layout","sidebar","nav","menu"))
        ):
            if p.stat().st_size<500000:candidates.append(p)
for p in candidates[:25]:
    lines=read(p)
    found=[(i,l) for i,l in enumerate(lines) if re.search(
        r"(?i)scrollIntoView|scrollTop\s*=|\.scrollTo\(|overflow-y|scrollbar|scroll-behavior",l)]
    if found:
        print("SOURCE",relative(p),"HITS",len(found))
        for i,l in found[:15]:print("SOURCE_LINE",i+1,safe(l))

print("\nSECTION LARAVEL_PUBLIC_ASSET_URL")
# Bootstrap without accessing records; print ONLY the public URL to static JS.
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo asset('js/am189-sidebar-scroll.js');
"""
with tempfile.TemporaryDirectory(prefix="am191php_") as d:
    p=Path(d)/"asset.php";p.write_text(php,encoding="utf8")
    result=subprocess.run(["php",str(p),str(root)],capture_output=True,text=True,timeout=25)
    if result.returncode==0:
        url=result.stdout.strip()
        if url.startswith("http"):
            print("ASSET_URL",url[:200])
            host=urllib.parse.urlsplit(url)
            if host.scheme == "https" and host.hostname in (
                "assammotors.com","www.assammotors.com",
                "staging.assammotors.com"
            ):
                try:
                    request=urllib.request.Request(
                        url,headers={"User-Agent":"AM191SidebarAssetCheck/1"},
                        method="GET")
                    with urllib.request.urlopen(request,timeout=6) as response:
                        first=response.read(32768)
                        print("ASSET_HTTP_STATUS",response.status)
                        print("ASSET_CONTENT_TYPE",response.headers.get("Content-Type","")[:70])
                        print("ASSET_HTTP_HAS_AM190_MARKER",
                              b"__am190SidebarScrollLoaded" in first)
                except Exception as e:
                    print("ASSET_HTTP_CHECK_UNAVAILABLE",type(e).__name__)
        else:print("ASSET_URL_UNKNOWN")
    else:print("ASSET_URL_PROBE_FAILED")

print("\nAM191_READ_ONLY_COMPLETE_NO_WRITES")
