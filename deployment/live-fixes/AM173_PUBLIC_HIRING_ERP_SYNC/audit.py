#!/usr/bin/env python3
"""AM173: READ-ONLY public hiring-to-ERP job application data-flow audit.
No form submission, DB writes, applicant records, credentials or CV contents.
Run: python3 audit.py /path/to/assam-erp-staging
"""
from pathlib import Path
import hashlib,re,subprocess,sys,tempfile

if len(sys.argv)!=2:raise SystemExit("Usage: audit.py STAGING_LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():raise SystemExit("NOT_STAGING_LARAVEL_ROOT")
domain=root.parent
print("AM173_READ_ONLY_BEGIN")
def textfile(p):
 try:return p.read_text(encoding="utf8",errors="replace")
 except OSError:return ""
def filehash(p):return hashlib.sha256(p.read_bytes()).hexdigest()[:12]
def rel(p):
 try:return str(p.relative_to(domain))
 except ValueError:return p.name
def eligible(p):
 return (p.is_file() and p.suffix.lower() in (".php",".html",".htm",".js")
         and p.stat().st_size<350000
         and not re.search(r"config|secret|backup|\.env|\.bak",p.name,re.I))
print("\nPUBLIC_HIRING_FILES")
bases=[
 domain/"public_html/hiring",domain/"public_html/careers",
 domain/"public_html/jobs",domain/"hiring",
 root/"public/hiring",root/"public/legacy/hiring",
]
wp=domain/"public_html/wp-content/plugins"
if wp.is_dir():
 bases.extend(p for p in wp.iterdir() if p.is_dir() and
   re.search(r"hiring|job|career|recruit|applicant",p.name,re.I))
seen=set()
for base in bases:
 if not base.is_dir():continue
 print("PUBLIC_DIRECTORY",rel(base))
 count=0
 for p in base.rglob("*"):
  if count>=65:break
  if not eligible(p) or p in seen:continue
  seen.add(p);count+=1
  t=textfile(p)
  markers=[label for label,rx in (
   ("FORM",r"<form\b"),("POST",r"\$_POST|method\s*=\s*['\"]post"),
   ("INSERT",r"insert\s+into|->insert\(|->create\("),
   ("UPDATE",r"\bupdate\s+\w+|->update\("),
   ("UPLOAD",r"\$_FILES|move_uploaded_file|wp_handle_upload"),
   ("AJAX",r"fetch\(|XMLHttpRequest|axios\."),
   ("DATABASE",r"mysqli|PDO\(|wpdb|DB::table"),
   ("JOB_APPS",r"job_applications|hiring_applications|career_applications")
  ) if re.search(rx,t,re.I)]
  tables=re.findall(
    r"(?i)\b(?:insert\s+into|update|from|join)\s+['\"]?([a-z_][a-z0-9_]*)",
    t)
  print(" PUBLIC_FILE",rel(p),"SHA",filehash(p),"FLAGS",",".join(markers) or "-",
        "TABLE_HINTS",",".join(sorted(set(tables))[:12]) or "-")
  # Print no URLs or form values: actions can have embedded tokens.
 print("FILES_EXAMINED",count)
if not seen:print("PUBLIC_HIRING_DIRECTORY_NOT_FOUND_IN_EXPECTED_PATHS")
for p in [domain/"public_html/index.php",domain/"public_html/.htaccess",
          root/"public/index.php",root/"public/.htaccess"]:
 if p.is_file():
  print("PUBLIC_ENTRYPOINT",rel(p),"HIRING_WORD",bool(re.search(
      r"hiring|job.applications|career",textfile(p),re.I)))
print("\nERP_JOB_APPLICATION_ROUTES")
for p in [root/"routes/web.php",root/"routes/api.php"]:
 if not p.is_file():continue
 lines=textfile(p).splitlines()
 matches=[(i+1,x) for i,x in enumerate(lines) if re.search(
   r"hiring|career|recruit|job.?applications|applicant",x,re.I)]
 print("ROUTE_FILE",p.name,"MATCHES",len(matches))
 for n,x in matches[:24]:
  if not re.search(r"password|secret|token|api.key",x,re.I):
   print(" ROUTE",n,x.strip()[:235])
print("\nERP_JOB_APPLICATION_SOURCE")
for p in [
 root/"app/Http/Controllers/Web/AdminNativeModuleController.php",
 root/"resources/views/admin/native/job-applications.blade.php",
 root/"resources/views/admin/native/job_applications.blade.php",
 root/"app/Models/JobApplication.php",
]:
 if not p.is_file():
  print("SOURCE_MISSING",p.name);continue
 t=textfile(p)
 print("SOURCE",p.relative_to(root),"SHA",filehash(p),
       "LINES",len(t.splitlines()),
       "READS_JOB_APPLICATIONS",bool(re.search(
         r"DB::table\(['\"]job_applications|JobApplication::",t,re.I)),
       "PLACEHOLDER",bool(re.search(r"coming soon|placeholder",t,re.I)))
 for n,x in enumerate(t.splitlines(),1):
  if re.search(r"function\s+\w*(jobApplication|recruit|career)\w*\s*\(",x,re.I):
   print(" METHOD",n,x.strip()[:150])
print("\nERP_APPLICATION_TABLE_SCHEMA_COUNTS_ONLY")
with tempfile.TemporaryDirectory(prefix="am173_") as d:
 s=Path(d)/"schema.php"
 s.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
try{
 foreach(DB::select('SHOW TABLES') as $row){
  $x=array_values((array)$row);$t=(string)($x[0]??'');
  if(!preg_match('/hiring|career|applic|recruit|candidate|vacanc|resume/i',$t))continue;
  try{
   $cols=Schema::getColumnListing($t);
   $n=DB::table($t)->count();
   echo 'TABLE '.$t.' COUNT '.$n.' COLUMNS '.implode(',',$cols)."\n";
  }catch(Throwable $e){echo 'TABLE_ERROR '.$t.' '.get_class($e)."\n";}
 }
}catch(Throwable $e){echo 'SCHEMA_ERROR '.get_class($e)."\n";}
""",encoding="utf8")
 try:
  p=subprocess.run(["php",str(s),str(root)],text=True,
    capture_output=True,timeout=25)
  print((p.stdout.strip() or "NO_MATCHING_TABLES")[:7000] if p.returncode==0
        else "SCHEMA_CHECK_FAILED")
 except (OSError,subprocess.TimeoutExpired) as e:
  print("SCHEMA_CHECK_UNAVAILABLE",type(e).__name__)
print("AM173_AUDIT_COMPLETE_NO_WRITES")
