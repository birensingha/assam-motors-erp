#!/usr/bin/env python3
"""AM229 source + schema audit for Estimate -> JC OSL -> Purchase -> Master.
Read only. Prints source snippets and schema, not finance rows or credentials.
"""
from pathlib import Path
import sys,re,subprocess,tempfile
if len(sys.argv)!=2:raise SystemExit('AM229_USAGE STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM229_STOP_NOT_STAGING')
scan=[
 'resources/views/estimates',
 'resources/views/purchases',
 'resources/views/job-cards',
 'resources/views/configuration',
 'app/Http/Controllers/Web',
 'app/Services',
 'routes/web.php',
 'public/js'
]
keywords=re.compile(r'osl.master|oslMaster|osl_master|osl[_-]service|osl[_-]item|osl[_-]work|osl[_-]billing|osl-select|osl.*options|job-filter|job-select|job.card.id|osl.purchase|oslPurchase|supplier_invoice',re.I)
names=re.compile(r'(estimate|purchase|osl|config|job.card|master)',re.I)
print('AM229_SOURCE_SNIPPETS_BEGIN')
for p in scan:
 path=root/p
 if path.is_file():items=[path]
 elif path.is_dir():
  items=[x for x in path.rglob('*') if x.is_file() and x.suffix in ['.php','.js'] and names.search(str(x.relative_to(root)))]
 else:continue
 for f in items:
  if f.stat().st_size>700000:continue
  lines=f.read_text('utf8','replace').splitlines()
  hits=[i for i,l in enumerate(lines) if keywords.search(l) and not re.search(r'(?i)pass(word)?|token|api[_-]?key|secret|auth',l)]
  if not hits:continue
  print('AM229_FILE',str(f.relative_to(root)),'LINES',len(lines),'HITS',len(hits))
  seen=set()
  for i in hits[:26]:
   for j in range(max(0,i-2),min(len(lines),i+3)):
    if j in seen:continue
    seen.add(j)
    line=lines[j].strip()
    if re.search(r'(?i)pass(word)?|token|secret|bearer|authorization',line):continue
    print('AM229_LINE',j+1,line[:500])
print('AM229_SOURCE_SNIPPETS_END')
php=r"""<?php
$root=$argv[1];
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
echo "AM229_SCHEMA_BEGIN\n";
$tables=DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND
 (TABLE_NAME LIKE '%osl%' OR TABLE_NAME LIKE '%rot%' OR TABLE_NAME LIKE '%estimate%' OR TABLE_NAME LIKE '%purchase%' OR TABLE_NAME LIKE '%labour%')
 ORDER BY TABLE_NAME");
foreach($tables as $row){
 $name=$row->TABLE_NAME;
 if(strlen($name)>100)continue;
 echo $name.':'.implode(',',Schema::getColumnListing($name))."\n";
}
echo "AM229_SCHEMA_END\nAM229_ROUTE_BEGIN\n";
foreach(app('router')->getRoutes() as $r){
 $uri=$r->uri();$name=(string)$r->getName();
 if(!preg_match('/(osl|estimate|purchase|rot.master|config)/i',$name.' '.$uri))continue;
 if(!str_starts_with($uri,'erp/'))continue;
 echo implode(',',array_diff($r->methods(),['HEAD'])).' '.$uri.' '.$name.' '.$r->getActionName()."\n";
}
echo "AM229_ROUTE_END\n";
"""
with tempfile.TemporaryDirectory(prefix='am229_') as d:
 f=Path(d)/'audit.php';f.write_text(php,encoding='utf8')
 r=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=65)
 print(r.stdout[:24000])
 if r.returncode:raise SystemExit('AM229_PHP_AUDIT_FAILED '+r.stderr[-500:])
print('AM229_AUDIT_PASS_NO_DB_WRITES')
