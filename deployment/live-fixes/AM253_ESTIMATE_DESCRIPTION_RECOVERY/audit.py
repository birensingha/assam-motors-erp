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

print('AM253_TARGETED_DETAIL_START')
for rel,ranges in [
 ('resources/views/estimates/create.blade.php',[(295,389)]),
 ('app/Http/Controllers/Web/AdminEstimatePageController.php',[(25,79)]),
 ('app/Services/EstimateService.php',[(1,250)]),
 ('app/Models/JobEstimateItem.php',[(1,100)]),
 ('app/Models/JobEstimate.php',[(1,100)])]:
 p=root/rel
 if not p.is_file():print('AM253_DETAIL_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 for aa,bb in ranges:
  for i in range(aa-1,min(len(lines),bb)):
   line=lines[i].strip()
   if secrets.search(line):line='[REDACTED]'
   print('AM253_DETAIL',rel,i+1,line[:610])
phpmore=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
foreach(DB::select("SELECT TABLE_NAME n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE '%estimat%'") as $t){
 echo "AM253_EST_TABLE ".$t->n."\n";
 foreach(DB::select("SELECT COLUMN_NAME c,DATA_TYPE t,CHARACTER_MAXIMUM_LENGTH l FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND (COLUMN_NAME LIKE '%description%' OR COLUMN_NAME LIKE '%note%')",[$t->n]) as $c)echo "AM253_EST_COLUMN ".$t->n." ".$c->c." ".$c->t." LEN=".($c->l??'NULL')."\n";
}
"""
z=subprocess.run(['php','-r',phpmore],cwd=root,capture_output=True,text=True,timeout=35)
if z.returncode:raise SystemExit('STOP_AM253_DETAIL_SCHEMA '+z.stderr[-450:])
print(z.stdout[:5000])
print('AM253_TARGETED_DETAIL_READONLY_DONE')


print('AM253_DOWNSTREAM_SOURCE_START')
for rel in ('app/Services/EstimateService.php',):
 p=root/rel;ls=p.read_text('utf8','replace').splitlines()
 for i,v in enumerate(ls):
  if re.search(r'function calculate|description|job_card_labour|job_card_parts|line_note',v,re.I):
   print('AM253_SERVICE_SOURCE',i+1,' '.join(x.strip() for x in ls[max(0,i-1):min(len(ls),i+2)])[:470])
sql=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
foreach(['job_estimate_items','job_card_labour','job_card_parts'] as $t){
 foreach(DB::select('SELECT COLUMN_NAME c,DATA_TYPE d,CHARACTER_MAXIMUM_LENGTH len,IS_NULLABLE nullable,COLUMN_DEFAULT def FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$t,'description']) as $c){
 echo 'AM253_COLUMN_FULL '.$t.' '.$c->c.' '.$c->d.' LEN='.($c->len??'NULL').' NULL='.$c->nullable.' DEFAULT='.($c->def??'NULL')."\n";
 }
 foreach(DB::select('SHOW INDEX FROM '.$t) as $i){if($i->Column_name==='description')echo 'AM253_DESCRIPTION_INDEX '.$t.' '.$i->Key_name."\n";}
}
"""
z=subprocess.run(['php','-r',sql],cwd=root,capture_output=True,text=True,timeout=35)
if z.returncode:raise SystemExit('STOP_AM253_DOWNSTREAM_SCHEMA '+z.stderr[-420:])
print(z.stdout[:4500])
print('AM253_DOWNSTREAM_SOURCE_END')

print('AM253_AUDIT_READ_ONLY_NO_PRIVATE_SESSIONS_OR_DB_WRITES')
