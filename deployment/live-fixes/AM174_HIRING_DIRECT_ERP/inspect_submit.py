#!/usr/bin/env python3
"""AM174 read-only public Hiring form submission trace.
No applicant records, credentials, database writes or form submissions.
Prints public HTML form declarations, JavaScript submit/fetch handler code,
existing application API file paths and ERP application methods.
Usage: python3 inspect_submit.py LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import hashlib,re,sys
if len(sys.argv)!=2:
    raise SystemExit("Usage: python3 inspect_submit.py LARAVEL_STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():
    raise SystemExit("AM174_STOP_INVALID_ROOT")
domain=root.parent
source=domain/"public_html/hiring"
paths=[
    source/"index.html",
    source/"hiring.js",
    root/"public/legacy/hiring/index.html",
    root/"public/legacy/hiring/hiring.js",
]
def read(path):
    return path.read_text(encoding="utf8",errors="replace").splitlines()
def redact(line):
    if re.search(r'(?i)(private.?key|api.?key|password|authorization:|bearer |access.?token|client_secret|\.env)',line):
        return "[suppressed credential-related source line]"
    # HTML/JS are static source files; never read or print actual applicant rows.
    return line.rstrip()[:360]
print("AM174_PUBLIC_SUBMISSION_TRACE_READ_ONLY")
for p in paths:
    print("\nSOURCE_FILE",p.relative_to(domain) if p.is_relative_to(domain) else p.name, "EXISTS",p.is_file())
    if not p.is_file():continue
    lines=read(p)
    print("SIZE_LINES",len(lines),"SHA256",hashlib.sha256(p.read_bytes()).hexdigest()[:16])
    if p.suffix==".js":
        rx=re.compile(r'fetch\s*\(|XMLHttpRequest|axios|submit|FormData|JSON\.stringify|endpoint|action|URL|method|response|preventDefault|const\s+form|addEventListener',re.I)
        indexes=[i for i,l in enumerate(lines) if rx.search(l)]
        windows=set()
        for i in indexes[:40]:
            for j in range(max(0,i-4),min(len(lines),i+9)):windows.add(j)
        # Cap output so no excessive code or confidential dependencies are exposed.
        for i in sorted(windows)[:260]:
            print("JS",i+1,redact(lines[i]))
        print("SUBMISSION_MARKERS",len(indexes))
    else:
        rx=re.compile(r'<form\b|</form>|name=|id=|type=|<input|<select|<textarea|<button|<script|action=|method=|enctype=',re.I)
        for i,l in enumerate(lines):
            if rx.search(l) and i<400:
                print("HTML",i+1,redact(l))
print("\nERP_JOB_APPLICATIONS_METHODS")
ctl=root/"app/Http/Controllers/Web/AdminNativeModuleController.php"
if ctl.is_file():
    lines=read(ctl)
    for a,b in [(203,246)]:
        for i in range(a-1,min(b,len(lines))):
            print("ERP",i+1,redact(lines[i]))
print("\nPOTENTIAL_PUBLIC_APPLICANT_HANDLERS")
scan=[
    domain/"public_html/hiring",
    domain/"public_html/api",
    domain/"public_html",
    root/"public/legacy/api",
    root/"public/api",
]
seen=set()
for folder in scan:
    if not folder.is_dir():continue
    for p in folder.iterdir():
        if (not p.is_file() or p in seen or p.stat().st_size>400000):
            continue
        seen.add(p)
        if re.search(r'(?i)hiring|application|apply|job|career|recruit',p.name):
            print("HANDLER_FILE",p.relative_to(domain) if p.is_relative_to(domain) else p.name)
print("\nERP_APPLICATION_TABLE_INFO")
print("Existing STAGING job_applications table confirmed by AM173; no rows retrieved.")
print("AM174_AUDIT_COMPLETE_NO_WRITES")
