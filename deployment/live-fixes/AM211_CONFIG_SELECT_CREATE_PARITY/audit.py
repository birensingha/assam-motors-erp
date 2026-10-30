#!/usr/bin/env python3
"""AM211 - OLD vs NEW ERP Configuration Parts / ROT-Labour / Vehicle master
select/create workflow parity, read-only. Source and schema *structure* only.
DOES NOT modify any file, table, session, or settings; DOES NOT output actual
customers/staff/vehicle records, credentials, token values, or DB data rows.

Run: python3 audit.py /home/.../assam-erp-staging
"""
from pathlib import Path
import os,re,sys,subprocess,tempfile,hashlib
if len(sys.argv)!=2:
    raise SystemExit("AM211_USAGE python3 audit.py STAGING_LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM211_STOP_INVALID_STAGING_LARAVEL_ROOT")
domain=root.parent
print("=== AM211 CONFIGURATION OLD/NEW SELECT + CREATE WORKFLOW ===")
print("STAGING",root.name,"READ_ONLY YES")
sensitive=re.compile(r"(?i)(password|passwd|secret|private[_-]?key|access[_-]?token|auth[_-]?token|bearer|api[_-]?key|DB_PASSWORD|DB_USERNAME|DB_HOST|mysqli_connect|new\s+PDO|->password|\.env|BEGIN\s+(RSA|OPENSSH)\s+PRIVATE|Authorization\s*:|cookie\s*=)")
code_rx=re.compile(r"(?i)(<form\b|<select\b|<option\b|<datalist\b|<input\b|<button\b|<label\b|</select>|<optgroup|"
 r"select2|tom-select|selectize|choices\.|dropdown|autocomplete|suggestion|typeahead|"
 r"change\b|fetch\(|XMLHttpRequest|ajax\b|\.on\(|addEventListener|"
 r"create|add\s*new|new\s*part|new\s*rot|new\s*vehicle|brand|model|variant|make|"
 r"part.?code|rot.?code|labou?r|vehicle|store|route\(|->name\(|action=|data-source|master)")
output_rx=re.compile(r"(?i)(<select\b|<option\b|<form\b|<input\b|<button\b|<label\b|<datalist\b|<optgroup\b|"
 r"create|add.?new|new.?entry|search|dropdown|select2|tom-select|selectize|choices|autocomplete|"
 r"fetch\(|ajax|addEventListener|function\b|route\(|->name\(|href=|action=)")
file_rx=re.compile(r"(?i)(part|labou?r|rot|vehicle|brand|model|variant|configuration|master|mapping)")
exclusions={".git","vendor","node_modules","storage","cache","build","backup",
 "backups","logs",".gradle","uploads","images","assets_images"}
extensions={".php",".html",".htm",".js",".blade.php"}
def is_source(p):
 return (p.name.endswith(".blade.php") or p.suffix.lower() in extensions) and p.is_file() and p.stat().st_size<450000
def read(p):
 try:return p.read_text("utf8","replace").splitlines()
 except (OSError,UnicodeError):return []
def relative(p):
 try:return str(p.relative_to(domain))
 except ValueError:return p.name
def cleanse(line):
 if sensitive.search(line):return "[REDACTED_AUTH_OR_DB_CONFIG]"
 cleaned=line.strip().replace("\x00","")
 # Prevent accidental dumping values embedded in inline input markup.
 cleaned=re.sub(r'(\bvalue\s*=\s*["\x27])([^"\x27]{20,})(["\x27])',r'\1[omitted]\3',cleaned,flags=re.I)
 return cleaned[:270]
def excerpts(p,section,maxlines=44):
 lines=read(p)
 print("FILE",section,relative(p),"LINES",len(lines),
       "SHA",hashlib.sha256(p.read_bytes()).hexdigest()[:12])
 found=[i for i,l in enumerate(lines) if output_rx.search(l)]
 print("SOURCE_MATCHES",len(found))
 for i in found[:maxlines]:
  print(" L"+str(i+1),cleanse(lines[i]))
 if len(found)>maxlines:print(" ADDITIONAL_MATCHING_LINES",len(found)-maxlines)
 print("END_FILE")
def method_block(p,names,max_lines=85):
 lines=read(p)
 print("CONTROLLER",relative(p),"LINES",len(lines))
 for method in names:
  matches=[i for i,l in enumerate(lines) if re.search(r"\bfunction\s+"+re.escape(method)+r"\s*\(",l)]
  print("METHOD",method,"COUNT",len(matches))
  if len(matches)!=1:continue
  start=matches[0];begun=False;brace=0
  for i in range(start,min(len(lines),start+max_lines)):
   l=lines[i];brace+=l.count("{")-l.count("}")
   if "{" in l:begun=True
   if code_rx.search(l) or i<start+12:
    print(" M"+str(i+1),cleanse(l))
   if begun and brace<=0:break
# NEW ERP views, their form controls and any embedded JS.
print("\n=== NEW ERP NATIVE CONFIGURATION VIEWS ===")
native=root/"resources/views/admin/native"
for name in ("parts","labour","vehicles","mappings"):
 p=native/(name+".blade.php")
 if p.is_file():excerpts(p,"NEW_"+name.upper(),maxlines=95)
 else:print("NEW_VIEW_MISSING",str(p.relative_to(root)))
print("\n=== NEW ERP CONTROLLER METHODS / SAVE ENTRY POINTS ===")
ctl=root/"app/Http/Controllers/Web/AdminNativeModuleController.php"
if ctl.is_file():
 lines=read(ctl)
 for i,l in enumerate(lines):
  m=re.search(r"public\s+function\s+(\w+)\s*\(",l)
  if m and re.search(r"part|labour|rot|vehicle|mapping",m.group(1),re.I):
   print("NATIVE_METHOD",m.group(1),"AT",i+1)
 # Relevant method windows only, no full source dump.
 for name in ("parts","partMaster","partsMaster","labour","labourMaster",
              "labourRotMaster","vehicles","vehicleMaster","vehiclesMaster","mappings"):
  matches=[i for i,l in enumerate(lines) if re.search(r"function\s+"+name+r"\s*\(",l)]
  for at in matches[:1]:
   print("NATIVE_METHOD_WINDOW",name,"LINE",at+1)
   for i in range(at,min(at+42,len(lines))):
    if i<at+7 or re.search(r"view\(|DB::table|Schema::|return|route\(|request|master",lines[i],re.I):
     print(" CODE",i+1,cleanse(lines[i]))
# Explicitly inspect the real form POST handlers; names / routes only.
web=root/"routes/web.php"
if web.is_file():
 print("\n=== STAGING ROUTES SOURCE ===")
 for i,l in enumerate(read(web)):
  if ("Route::" in l or "->name(" in l) and re.search(
    r"(?i)(erp\.(parts|labour|vehicles|mappings)|/erp/(parts|labour|vehicles|mappings)|master|part.*store|labour.*store|vehicle.*store)",l):
   print("ROUTE_SOURCE",i+1,cleanse(l))
# Legacy source candidates: search only deployment's original website tree.
# No complete file dumps, no data values and no secrets.
print("\n=== OLD ERP CONFIG MASTER SOURCE DISCOVERY ===")
bases=[
 domain/"public_html/workshop",
 domain/"public_html",
 root/"public/legacy/workshop",
 root/"public/legacy",
]
seen=set();candidate=[]
for base in bases:
 if not base.is_dir():continue
 print("LEGACY_PATH",relative(base),"EXISTS")
 for folder,dirs,files in os.walk(base):
  depth=len(Path(folder).relative_to(base).parts)
  dirs[:]=[d for d in dirs if d not in exclusions and not d.startswith(".")]
  if depth>=4:dirs[:]=[]
  for filename in files:
   p=Path(folder)/filename
   if p in seen or not file_rx.search(p.stem) or not is_source(p):continue
   seen.add(p)
   candidate.append(p)
print("OLD_ERP_CANDIDATE_FILES",len(candidate))
scored=[]
for p in candidate[:1100]:
 lines=read(p)
 score=sum(bool(re.search(r"(?i)(<select|<datalist|select2|add.?new|save|insert|<form)",l)) for l in lines[:1200])
 if score>0:scored.append((score,p))
for score,p in sorted(scored,key=lambda x:(-x[0],str(x[1])))[:19]:
 print("LEGACY_MATCH_SCORE",score)
 excerpts(p,"OLD",maxlines=45)
print("\n=== MASTER TABLE / COLUMN STRUCTURE (NO DATA RECORDS) ===")
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
try{
 $tables=DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name');
 foreach($tables as $row){
  $table=(string)$row->t;
  if(!preg_match('/(part|labou?r|rot|vehicle|brand|model|variant|master|mapping)/i',$table))continue;
  try{$cols=Schema::getColumnListing($table);}
  catch(Throwable $e){$cols=['COLUMN_SCAN_ERROR'];}
  echo "TABLE ".$table." COLUMNS ".implode(",",$cols)."\n";
 }
 echo "AM211_DB_COLUMNS_READ_ONLY\n";
}catch(Throwable $e){echo "AM211_DB_STRUCTURE_UNAVAILABLE ".get_class($e)."\n";}
echo "=== NEW ERP REGISTERED ROUTES ===\n";
foreach(app('router')->getRoutes() as $r){
 $uri=(string)$r->uri();$name=(string)$r->getName();
 if(preg_match('~^erp/(parts|labour|vehicles|mappings)(/|$)~i',$uri)
  || preg_match('/^erp\.(parts|labour|vehicles|mappings)/',$name)){
  echo implode(",",$r->methods())." ".$uri." NAME ".$name." ACTION ".$r->getActionName()."\n";
 }
}
"""
with tempfile.TemporaryDirectory(prefix="am211audit_") as td:
 f=Path(td)/"metadata.php";f.write_text(php,encoding="utf8")
 p=subprocess.run(["php",str(f),str(root)],cwd=root,text=True,
                  capture_output=True,timeout=40)
 if p.stdout:print(p.stdout[:18000])
 if p.returncode:
  print("AM211_METADATA_UNAVAILABLE_PHP_EXIT",p.returncode)
  print("AM211_METADATA_ERROR_CLASS_ONLY","PHP_PROCESS_ERROR")
print("AM211_COMPLETE_READ_ONLY_NO_FILES_OR_DB_DATA_MODIFIED")
