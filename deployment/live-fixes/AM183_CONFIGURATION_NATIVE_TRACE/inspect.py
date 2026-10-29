#!/usr/bin/env python3
"""AM183: READ-ONLY trace for existing STAGING /erp/configuration.

Reports whether native controller redirects to OLD ERP, exact sidebar link
destination, whether native Configuration Blade view exists, and legacy feature
names/save endpoints. No changes, database rows, .env, API secrets, passwords or
user data are printed.

Use: python3 inspect.py /path/to/assam-erp-staging
"""
from pathlib import Path
import os,re,sys,subprocess,tempfile

if len(sys.argv)!=2:raise SystemExit("Usage: inspect.py STAGING_LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("AM183_STOP_NOT_LARAVEL")
domain=root.parent
print("AM183_CONFIGURATION_NATIVE_TRACE_READ_ONLY")

private=re.compile(r"(?i)password|passwd|secret|token|api[_-]?key|bearer|auth[_-]?header|private_key|db[_-]?(pass|host|user|name)|dsn|database[_-]?url|\\.env")
def read(p):
    try:return p.read_text(encoding="utf8",errors="replace").splitlines()
    except OSError:return []
def clean(s):
    if private.search(s):return "[SENSITIVE_LINE_SUPPRESSED]"
    return s.strip()[:270]
def rel(p):
    try:return str(p.relative_to(root))
    except ValueError:
        try:return str(p.relative_to(domain))
        except ValueError:return p.name
def trace_method(p,name,length=60):
    lines=read(p)
    pattern=r"\bfunction\s+"+re.escape(name)+r"\s*\("
    matches=[i for i,l in enumerate(lines) if re.search(pattern,l)]
    print("CONTROLLER_FILE",rel(p),"METHOD",name,"FOUND",len(matches))
    for i in matches[:2]:
        print("METHOD_START_LINE",i+1)
        # Take method only; do not print arbitrary unrelated functions.
        started=False;depth=0
        for j in range(i,min(i+length,len(lines))):
            s=lines[j]
            if '{' in s:started=True
            if started:
                depth+=s.count("{")-s.count("}")
            print("METHOD",j+1,clean(s))
            if started and depth<=0:break

print("\nSECTION CONFIGURATION_CONTROLLER")
for p in [
    root/"app/Http/Controllers/Web/AdminDashboardPageController.php",
    root/"app/Http/Controllers/Web/AdminNativeModuleController.php",
]:
    if p.is_file():
        trace_method(p,"configuration",90)

print("\nSECTION STAGING_CONFIGURATION_NAV")
seen=set()
for base in (root/"resources/views/layouts",root/"resources/views/partials",
             root/"resources/views/admin",root/"resources/views/components"):
    if not base.is_dir():continue
    for folder,dirs,filenames in os.walk(base):
        dirs[:]=[x for x in dirs if not x.startswith(".") and x not in ("vendor","node_modules")]
        if len(Path(folder).relative_to(base).parts)>3:
            dirs.clear();continue
        for fname in filenames:
            if not fname.endswith(".blade.php"):continue
            p=Path(folder)/fname
            if p in seen or p.stat().st_size>600000:continue
            seen.add(p)
            lines=read(p)
            hits=[i for i,line in enumerate(lines) if re.search("configuration",line,re.I)]
            if not hits:continue
            print("NAV_FILE",rel(p),"HITS",len(hits))
            for i in hits[:12]:
                for j in range(max(0,i-2),min(len(lines),i+3)):
                    print("NAV",j+1,clean(lines[j]))

print("\nSECTION NATIVE_CONFIGURATION_VIEWS")
views=root/"resources/views"
native=[]
if views.is_dir():
    for p in views.rglob("*configuration*.blade.php"):
        if p.stat().st_size<=300000:native.append(p)
for p in sorted(native)[:25]:
    lines=read(p)
    print("VIEW",rel(p),"LINES",len(lines))
    for i,line in enumerate(lines):
        if re.search(r"(?i)<form\b|</form>|route\(|href=|action=|method=|"
                     r"<h[1-6]\b|<input|<select|settings|legacy|workshop/",line):
            print("VIEW_LINE",i+1,clean(line))
            if i>450:break
if not native:print("NATIVE_CONFIGURATION_VIEW_NOT_FOUND_BY_NAME")

print("\nSECTION LEGACY_CONFIGURATION_SOURCE_NAMES")
bases=[
  domain/"public_html/workshop",domain/"public_html",
  root/"public/legacy/workshop",root/"public/legacy",
]
for base in bases:
    if not base.is_dir():continue
    matches=[]
    for p in base.iterdir():
        if (p.is_file() and re.search(r"(?i)configur|setup|setting|preference",p.name)
            and not re.fullmatch(r"(?i)config(?:uration)?\.php",p.name)):
            matches.append(p)
        if p.is_dir() and re.search(r"(?i)configur|setup|setting|preference",p.name):
            matches.append(p)
    print("LEGACY_SCAN_FOLDER",rel(base),"POSSIBLE_FILES",len(matches))
    for p in sorted(matches)[:25]:
        print("LEGACY_CONFIG_PATH",rel(p),"DIRECTORY" if p.is_dir() else "FILE")
        if p.is_file() and p.suffix.lower()==".php" and p.stat().st_size<250000:
            lines=read(p)
            extracts=[(i+1,clean(line)) for i,line in enumerate(lines)
                      if re.search(r"(?i)<form\b|action=|method=|href=|"
                                   r"(INSERT|UPDATE|DELETE)\s+|function\s+",line)
                      and not private.search(line)]
            for i,line in extracts[:35]:print("LEGACY_FEATURE",i,line)

print("\nSECTION ROUTE_DECLARATIONS")
for p in (root/"routes/web.php",root/"routes/api.php"):
    if p.is_file():
        lines=read(p)
        for i,line in enumerate(lines):
            if re.search(r"(?i)\bconfiguration\b|erp\.configuration|"
                         r"AdminDashboardPageController",line):
                if re.search("configuration",line,re.I):
                    print("ROUTE_SOURCE",rel(p),i+1,clean(line))
print("AM183_TRACE_COMPLETE_NO_WRITES")
