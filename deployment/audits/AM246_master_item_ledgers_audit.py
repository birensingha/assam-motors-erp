#!/usr/bin/env python3
"""AM246 read-only schema/routing/master page audit. No state changes."""
from pathlib import Path
import subprocess,sys,re,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('STOP_WRONG_ROOT')
paths=['routes/web.php']
for folder in ['resources/views','app/Http/Controllers/Web','app/Services']:
 for p in (root/folder).rglob('*.php'):
  if p.stat().st_size>300000:continue
  name=p.name.lower()
  if any(x in name for x in ['part','rot','labour','labor','osl','inventory','stock','purchase','invoice']):
   paths.append(str(p.relative_to(root)))
for path in paths:
 p=root/path
 if not p.is_file():continue
 src=p.read_text('utf8','replace').splitlines()
 hits=[(i,s) for i,s in enumerate(src,1) if re.search(r'Route::|function (index|show|edit|create|store|master|register)|parts_master|rot_master|osl_master|stock_ledger|stock_movement|part_id|labour_id|rot_id|osl_id|transaction|inventory',s,re.I)]
 if not hits:continue
 print('AM246_FILE',path,'LINES',len(src),'MATCH',len(hits))
 for i,s in hits[:85]:
  if re.search(r'secret|password|api[_-]?key|env\(',s,re.I):s='[REDACTED]'
  if path=='routes/web.php' and not re.search(r'part|rot|labour|osl|inventory|stock|purchase|invoice',s,re.I):continue
  print('AM246_HIT',path,i,s[:450])
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$names=DB::select('SHOW TABLES');
foreach($names as $r){
 $t=(string)(array_values((array)$r)[0]??'');
 if(!preg_match('/part|rot|labour|labor|osl|stock|inventory|purchase|invoice|job_card|estimate|service|issue|fitting|bill|customer|vendor/i',$t))continue;
 $cols=DB::select('SHOW COLUMNS FROM '.$t);
 echo 'AM246_TABLE '.$t.' :: '.implode(',',array_map(fn($c)=>$c->Field,$cols))."\n";
}
"""
with tempfile.TemporaryDirectory(prefix='am246schema_') as folder:
 p=Path(folder)/'schema.php';p.write_text(php)
 run=subprocess.run(['php',str(p),str(root)],cwd=root,capture_output=True,text=True,timeout=40)
 print(run.stdout)
 print('AM246_SCHEMA_EXIT',run.returncode,run.stderr[-500:])
print('AM246_AUDIT_COMPLETE_READONLY')
