#!/usr/bin/env python3
"""AM205 read-only STAGING audit for optional inspection->estimate linkage.
Print only table names/column names/routes; NO row values, writes or migrations.
"""
import json,subprocess,sys
from pathlib import Path
if len(sys.argv)!=2:raise SystemExit("USAGE: python3 audit.py STAGING_LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM205_STOP_NOT_STAGING")
def run(cmd):
    p=subprocess.run(cmd,cwd=root,text=True,capture_output=True)
    if p.returncode:
        raise SystemExit("AM205_READ_ONLY_COMMAND_FAILED exit="+str(p.returncode))
    return p.stdout
routes=json.loads(run(["php","artisan","route:list","--json"]))
print("=== ESTIMATE / CUSTOMER APPROVAL ROUTES ===")
for row in routes:
    uri=str(row.get("uri",""));name=str(row.get("name",""));action=str(row.get("action",""))
    if any(t in (uri+" "+name+" "+action).lower() for t in ("estimate","approval","supplementary")):
        print("ROUTE",row.get("method"),uri,"NAME",name,"ACTION",action.rsplit("\\",1)[-1])
php=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$results=[];
$all=DB::select("SELECT TABLE_NAME AS table_name FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND (TABLE_NAME LIKE '%estimate%' OR TABLE_NAME LIKE '%approval%' OR TABLE_NAME LIKE '%inspection%' OR TABLE_NAME LIKE '%job_card%') ORDER BY TABLE_NAME");
foreach($all as $r){
  $name=(string)$r->table_name;
  $results[$name]=Schema::getColumnListing($name);
}
echo json_encode($results,JSON_THROW_ON_ERROR);
"""
result=json.loads(run(["php","-r",php]))
print("=== EXISTING RELATED DB TABLE SCHEMA (FIELDS ONLY) ===")
for name,columns in result.items():
    print("TABLE",name,"FIELDS",",".join(columns[:65]),"PLUS",max(0,len(columns)-65))
print("AM205_AUDIT_COMPLETE_NO_WRITES")
