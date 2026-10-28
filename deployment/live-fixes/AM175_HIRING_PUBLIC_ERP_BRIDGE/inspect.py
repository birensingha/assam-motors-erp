#!/usr/bin/env python3
"""AM175 - read-only exact source audit of public job application save/list APIs.

No submits, data writes, applicant reads, resumes, environment contents,
authentication secrets or DB connection credentials printed.
Run: python3 inspect.py ~/domains/assammotors.com/assam-erp-staging
"""
from pathlib import Path
import hashlib, re, sys

if len(sys.argv)!=2:
    raise SystemExit("Usage: python3 inspect.py LARAVEL_STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():
    raise SystemExit("AM175_STOP_NOT_LARAVEL_STAGING")
domain=root.parent
locations={
    "PUBLIC_FORM_JS":domain/"public_html/hiring/hiring.js",
    "PUBLIC_APPLY_API":domain/"public_html/api/job-apply.php",
    "PUBLIC_APPLICATIONS_API":domain/"public_html/api/job-applications.php",
    "STAGING_FORM_JS":root/"public/legacy/hiring/hiring.js",
    "STAGING_APPLY_API":root/"public/legacy/api/job-apply.php",
    "STAGING_APPLICATIONS_API":root/"public/legacy/api/job-applications.php",
    "ERP_CONTROLLER":root/"app/Http/Controllers/Web/AdminNativeModuleController.php",
    "ERP_VIEW":root/"resources/views/admin/native/job-applications.blade.php",
}
# Static source inspection only. Redact even hardcoded secrets and auth code lines.
sensitive=re.compile(
    r"(?i)(password|passwd|secret|api.?key|bearer|authorization|"
    r"\.env|db_host|db_name|db_user|db_pass|database_url|dsn|"
    r"private.?key|access.?token|session.?token|credentials|mysqli_connect|new\s+PDO)"
)
js_rx=re.compile(
    r"fetch\s*\(|axios|XMLHttpRequest|FormData|submit|method|action|endpoint|"
    r"file|resume|url|json|preventDefault|status|response|redirect|name|email|phone",re.I)
php_rx=re.compile(
    r"job_applications|applicants|INSERT\s+INTO|UPDATE\s+\w+|SELECT\s+|"
    r"DB::table|prepare\s*\(|->execute|->query|->insert|->update|"
    r"GET|POST|REQUEST|FILES|php://input|\\baction\\b|"
    r"content.type|JSON|header\s*\(|status|file|resume|"
    r"connection|require|include|pdo|mysqli|bootstrap",re.I)
def safe_line(line):
    if sensitive.search(line):return "[CREDENTIAL_OR_AUTH_CONFIG_LINE_SUPPRESSED]"
    return line.strip()[:240]
def read(p):
    try:return p.read_text(encoding="utf8",errors="replace").splitlines()
    except OSError:return []
def digest(p):return hashlib.sha256(p.read_bytes()).hexdigest()[:20]
print("AM175_PUBLIC_TO_ERP_BRIDGE_READ_ONLY")
for k,p in locations.items():
    if not p.is_file():
        print("FILE_MISSING",k,p.name)
        continue
    lines=read(p)
    print("\nFILE",k,"LINES",len(lines),"SHA256_PREFIX",digest(p))
    if k.endswith("_JS"):
        selected=[i for i,line in enumerate(lines) if js_rx.search(line)]
        window=set()
        for i in selected[:48]:
            for j in range(max(0,i-2),min(len(lines),i+4)):window.add(j)
        for i in sorted(window)[:145]:
            print(" JS",i+1,safe_line(lines[i]))
        print(" JS_MATCH_LINES",len(selected))
    elif k=="ERP_CONTROLLER":
        for idx,line in enumerate(lines):
            if re.search(r"function\s+(?:jobApplications|updateJobApplication|deleteJobApplication)\s*\(",line):
                print(" METHOD_BEGIN",idx+1)
                for j in range(idx,min(idx+28,len(lines))):
                    print(" ERP",j+1,safe_line(lines[j]))
    elif k=="ERP_VIEW":
        for i,line in enumerate(lines[:78]):
            print(" VIEW",i+1,safe_line(line))
    else:
        selected=[i for i,line in enumerate(lines) if php_rx.search(line)]
        window=set()
        for i in selected[:60]:
            for j in range(max(0,i-1),min(len(lines),i+3)):window.add(j)
        for i in sorted(window)[:170]:
            print(" PHP",i+1,safe_line(lines[i]))
        print(" PHP_MATCH_LINES",len(selected))
print("\nPUBLIC_STAGING_COMPARISON")
for a,b in (
    ("PUBLIC_FORM_JS","STAGING_FORM_JS"),
    ("PUBLIC_APPLY_API","STAGING_APPLY_API"),
    ("PUBLIC_APPLICATIONS_API","STAGING_APPLICATIONS_API"),
):
    x,y=locations[a],locations[b]
    if x.is_file() and y.is_file():
        print("SAME_SOURCE",a,b,digest(x)==digest(y))
print("AM175_INSPECTION_COMPLETE_NO_WRITES")
