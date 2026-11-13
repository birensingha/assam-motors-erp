#!/usr/bin/env python3
"""AM253: read-only ESTIMATE #JC-20261113-00054 description/validation and old-input audit."""
from pathlib import Path
import sys,re,subprocess
if len(sys.argv)!=2:raise SystemExit("AM253_USAGE audit.py STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():raise SystemExit("STOP_AM253_NOT_STAGING")
cases=[
('resources/views/estimates/create.blade.php',r'old\(|initial|@json|json_encode|estimate-entry|estimate-form|description|items|note|script',280,2,4),
('public/js/estimate-entry.js',r'initial|description|rowHtml|function add|JSON|sessionStorage|localStorage|submit|name=',290,2,5),
('app/Http/Controllers/Web/AdminEstimatePageController.php',r'function |description|items\.\*|items\.\d+|max:255|store|validate|create|update',350,3,9),
('app/Http/Controllers/Web/EstimateController.php',r'function |description|items\.\*|max:255|store|validate|create|update',260,3,8),
('app/Http/Requests/StoreEstimateRequest.php',r'description|max:255|rules|items',80,2,4),
]
secrets=re.compile(r'(?i)password|secret|api.?token|bearer|authorization|APP_KEY|DB_PASSWORD|cookie|private_key')
for rel,pattern,cap,before,after in cases:
 p=root/rel
 if not p.is_file():
  print('AM253_SOURCE_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 mat=[i for i,v in enumerate(lines) if re.search(pattern,v,re.I)]
 selected=set()
 for i in mat[:cap//4]:selected.update(range(max(0,i-before),min(len(lines),i+after+1)))
 if rel.endswith('create.blade.php'):selected.update(range(185,min(len(lines),389)))
 if rel.endswith('estimate-entry.js'):selected.update(range(140,min(len(lines),278)))
 print('AM253_SOURCE',rel,'LINES',len(lines),'MATCHES',len(mat))
 for i in sorted(selected)[:cap]:
  v=lines[i].strip()
  if secrets.search(v):v='[REDACTED_SOURCE]'
  print('AM253_CODE',rel,i+1,v[:550])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
foreach(['estimates','estimate_items','estimate_lines','job_cards'] as $table){
 if(!Schema::hasTable($table)){echo "AM253_TABLE_MISSING ".$table."\n";continue;}
 $c=DB::select('SELECT COLUMN_NAME AS n, DATA_TYPE AS d, CHARACTER_MAXIMUM_LENGTH AS maxlen, IS_NULLABLE AS nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$table]);
 foreach($c as $row)if(str_contains(strtolower($row->n),'description') || $table==='estimate_items' ||
    str_contains(strtolower($row->n),'note'))
  echo 'AM253_COLUMN '.$table.' '.$row->n.' '.$row->d.' LEN='.($row->maxlen??'NULL').' NULL='.$row->nullable."\n";
}
foreach(Route::getRoutes() as $r){
 $n=(string)($r->getName()??'');$u=(string)$r->uri();$a=(string)$r->getActionName();
 if(str_contains($u,'estimate')&&in_array('POST',$r->methods(),true))
  echo 'AM253_ROUTE '.json_encode(['method'=>'POST','uri'=>$u,'name'=>$n,'action'=>$a])."\n";
}
echo "AM253_SCHEMA_AND_ROUTES_READONLY_DONE\n";"""
p=subprocess.run(['php','-r',php],cwd=root,capture_output=True,text=True,timeout=50)
if p.returncode:raise SystemExit('STOP_AM253_PHP_AUDIT_FAILED '+(p.stdout+p.stderr)[-750:])
print(p.stdout[:11000])
print('AM253_AUDIT_READ_ONLY_NO_PRIVATE_SESSIONS_OR_DB_WRITES')
