#!/usr/bin/env python3
"""AM206 read-only schema/route audit for item-wise Parts, ROT and OSL history.

Uses the actual STAGING Laravel DB connection to discover which source tables,
columns, foreign keys, and registered routes exist. Prints NO customer/vendor
records or financial values. Does not create DB tables, mutate any records,
clear caches or publish any code. Designed before implementing read-only
Parts / ROT / OSL reconciliation, to avoid invented invoice/vendor joins.

Usage: python3 audit.py /home/.../assam-erp-staging
"""
from pathlib import Path
import sys,subprocess,tempfile,json
if len(sys.argv)!=2:
    raise SystemExit("Usage: python3 audit.py LARAVEL_STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/'artisan').is_file():
    raise SystemExit("AM206_STOP_INVALID_LARAVEL_ROOT")
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
$catalog=[];
try {
 $names=DB::select('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name');
} catch (\Throwable $e) {
 echo 'AM206_DB_SCHEMA_ERROR '.get_class($e)."\n";exit(2);
}
$keywords=['parts','part_','inventory','stock','purchase','vendor','supplier',
 'job_card','invoice','rot_','labour','labor','osl_','service_master',
 'customer_payment','credit_note','estimate'];
foreach($names as $r){
 $name=(string)$r->name;
 $hit=false;
 foreach($keywords as $keyword)if(stripos($name,$keyword)!==false){$hit=true;break;}
 if(!$hit || count($catalog)>=85)continue;
 try {
  $cols=Schema::getColumnListing($name);
  $catalog[$name]=$cols;
 } catch (\Throwable $e) {$catalog[$name]=['SCHEMA_ERROR:'.get_class($e)];}
}
echo "AM206_TABLES_BEGIN\n";
foreach($catalog as $name=>$columns){
 echo 'TABLE '.$name.' COLUMNS '.implode(',',array_map(
  static fn($v)=>preg_replace('/[^a-zA-Z0-9_]/','',strval($v)),$columns))."\n";
}
echo "AM206_TABLES_END\n";
try {
 $rels=DB::select("SELECT table_name, column_name, referenced_table_name, referenced_column_name
 FROM information_schema.key_column_usage
 WHERE table_schema=DATABASE() AND referenced_table_name IS NOT NULL
 ORDER BY table_name, column_name");
 echo "AM206_FOREIGN_KEYS_BEGIN\n";
 foreach($rels as $rel){
  if(!array_key_exists($rel->table_name,$catalog)
    && !array_key_exists($rel->referenced_table_name,$catalog))continue;
  echo "FK ".$rel->table_name.'.'.$rel->column_name.' -> '
    .$rel->referenced_table_name.'.'.$rel->referenced_column_name."\n";
 }
 echo "AM206_FOREIGN_KEYS_END\n";
} catch (\Throwable $e){
 echo "AM206_FK_AUDIT_ERROR ".get_class($e)."\n";
}
echo "AM206_ROUTES_BEGIN\n";
$shown=0;
foreach(app('router')->getRoutes() as $r){
 $path=$r->uri();$action=$r->getActionName();
 if(!preg_match('/^erp\/(purchase|parts|inventory|stock|rot|labour|osl|vendor|invoice|job-cards)/i',$path))continue;
 if($shown++>=85)break;
 echo 'ROUTE '.implode(',',array_values(array_diff($r->methods(),['HEAD'])))
  .' '.$path.' NAME '.($r->getName()??'-')
  .' ACTION '.$action."\n";
}
echo "AM206_ROUTES_END\n";
echo "AM206_READ_ONLY_NO_CUSTOMER_ROWS_NO_WRITES\n";
"""
with tempfile.TemporaryDirectory(prefix='am206_audit_') as td:
    filename=Path(td)/'audit.php'
    filename.write_text(php,encoding='utf8')
    p=subprocess.run(['php',str(filename),str(root)],cwd=root,
                     capture_output=True,text=True,timeout=45)
    print('AM206_ITEM_TRACE_SCHEMA_AUDIT')
    if p.stdout:print(p.stdout.rstrip())
    if p.returncode:
        print('AM206_STOP_PHP_EXIT',p.returncode)
        print('AM206_PHP_ERROR_TYPE_OR_BRIEF',p.stderr[-350:].replace('\n',' '))
        sys.exit(p.returncode)
    if 'AM206_READ_ONLY_NO_CUSTOMER_ROWS_NO_WRITES' not in p.stdout:
        raise SystemExit('AM206_STOP_INCOMPLETE_AUDIT')
print('AM206_AUDIT_COMPLETE')
