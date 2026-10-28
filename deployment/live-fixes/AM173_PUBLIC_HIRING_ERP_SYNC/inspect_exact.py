#!/usr/bin/env python3
"""AM173 phase 2: READ-ONLY exact Hiring form -> ERP mapping.
Prints only view/controller SOURCE, route declarations and public Hiring filenames/
table identifiers. Never prints applicants, submitted values, resumes or passwords.
No database modifications or form submissions.
Usage: python3 inspect_exact.py LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import re,sys

if len(sys.argv)!=2:raise SystemExit("Usage: inspect_exact.py LARAVEL_STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("NOT_LARAVEL_ROOT")
domain=root.parent
sensitive=re.compile(
    r'(?i)password|secret|api.?key|access.?token|bearer|private.?key|'
    r'db_password|database_password|\.env|csrf_token'
)
def read(p):
    try:return p.read_text(encoding="utf8",errors="replace").splitlines()
    except OSError:return []
def clean(line):
    return "[credential-related line suppressed]" if sensitive.search(line) else line.strip()[:230]
def section(name):
    print("\n###",name)

section("ERP_JOB_APPLICATION_VIEW")
view=root/"resources/views/admin/native/job-applications.blade.php"
if view.is_file():
    lines=read(view)
    print("VIEW_LINES",len(lines))
    for n,line in enumerate(lines[:100],1):print("VIEW",n,clean(line))
else:print("VIEW_MISSING")

section("ERP_JOB_APPLICATION_CONTROLLER")
ctl=root/"app/Http/Controllers/Web/AdminNativeModuleController.php"
if ctl.is_file():
    lines=read(ctl)
    rx=re.compile(r'function\s+(jobApplications|updateJobApplication|deleteJobApplication)\s*\(',re.I)
    for i,line in enumerate(lines):
        if rx.search(line):
            print("METHOD_FROM",i+1)
            for j in range(i,min(len(lines),i+43)):
                print("CTL",j+1,clean(lines[j]))
else:print("CONTROLLER_MISSING")

section("ERP_JOB_APPLICATION_ROUTES")
for path in [root/"routes/web.php",root/"routes/api.php"]:
    if not path.is_file():continue
    matches=[(i,s) for i,s in enumerate(read(path),1)
             if re.search(r'job.?applications|hiring|career|recruit',s,re.I)]
    print("ROUTE_FILE",path.name,"MATCHES",len(matches))
    for n,line in matches[:35]:print("ROUTE",n,clean(line))

section("PUBLIC_HIRING_FILES")
bases=[
 domain/"public_html/hiring",domain/"public_html/careers",
 domain/"public_html/jobs",domain/"public_html/application",
 domain/"hiring",root/"public/hiring",root/"public/legacy/hiring"
]
for path in [domain/"public_html/index.php",domain/"public_html/.htaccess",
             domain/"public_html/wp-config.php"]:
    print("PUBLIC_ENTRY",path.relative_to(domain),"EXISTS",path.is_file())
plugins=domain/"public_html/wp-content/plugins"
if plugins.is_dir():
    bases+=sorted(x for x in plugins.iterdir() if x.is_dir() and
        re.search(r'job|career|recruit|hiring|application|contact.form|wpforms',x.name,re.I))[:10]
# A /hiring/ URL can be implemented by a single PHP file or a CMS route;
# list only likely top-level public files without exposing their contents.
publicroot=domain/"public_html"
if publicroot.is_dir():
    print("PUBLIC_ROOT_FOUND",publicroot.relative_to(domain))
    for file in sorted(publicroot.iterdir()):
        if file.is_file() and re.search(r"hiring|career|recruit|apply|job",file.name,re.I):
            print("PUBLIC_TOPLEVEL_FILE",file.relative_to(domain))
    if (publicroot/"wp-content").is_dir():
        print("PUBLIC_CMS_WORDPRESS_DIRECTORY_FOUND")
found=0
for base in bases:
    if not base.is_dir():continue
    print("PUBLIC_DIRECTORY",base.relative_to(domain) if base.is_relative_to(domain) else base.name)
    checked=0
    for p in base.rglob("*"):
        if checked>=28:break
        if (not p.is_file() or p.suffix.lower() not in (".php",".html",".htm",".js")
            or p.stat().st_size>220000 or re.search(r'config|secret|\.bak|backup|\.env',p.name,re.I)):
            continue
        checked+=1
        found+=1
        t="\n".join(read(p))
        tables=sorted(set(re.findall(
            r'(?i)\b(?:insert\s+into|update|from|join)\s+[\x60"\x27]?([a-z_][a-z0-9_]*)',t)))
        actions=re.findall(
            r'(?i)<form[^>]{0,400}action\s*=\s*["\x27]([^"\x27]{0,150})',t)
        actions=[x.split("?")[0][:90] for x in actions if not re.search(r'[{}$=]',x)][:2]
        flags=[label for label,pattern in [
            ("FORM",r'<form\b'),("PHP_POST",r'\$_POST'),
            ("FILE_UPLOAD",r'\$_FILES|move_uploaded_file'),
            ("SQL_INSERT",r'INSERT\s+INTO'),("DB_CONNECTION",r'new\s+PDO|mysqli|wpdb'),
            ("LARAVEL",r'DB::table'),("AJAX",r'fetch\s*\(|XMLHttpRequest'),
            ("JOB_APPLICATIONS",r'job_applications|career_applications|hiring_applications')
        ] if re.search(pattern,t,re.I)]
        if flags:
            label=p.relative_to(domain) if p.is_relative_to(domain) else p.name
            print("PUBLIC_FILE",label,"FLAGS",",".join(flags),
                  "TABLE_HINTS",",".join(tables[:10]) or "-",
                  "FORM_ACTION",",".join(actions) or "-")
    print("FILES_CHECKED",checked)
if not found:
    print("PUBLIC_HIRING_FILES_NOT_FOUND_IN_EXPECTED_DIRECTORIES")
print("AM173_EXACT_INSPECTION_COMPLETE_NO_WRITES")
