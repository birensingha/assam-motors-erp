#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo '=== JC DATETIME AND ROT CONSTRAINTS ==='
python3 - <<'PY'
from pathlib import Path
import re
files=[
 ('app/Http/Controllers/Web/AdminRotTimeEditController.php',1,250),
 ('resources/views/job-cards/rot-time-editor.blade.php',1,150),
 ('app/Services/RotWorkflowService.php',1,95),
 ('app/Services/RotWorkflowService.php',150,225),
 ('app/Services/RotWorkflowService.php',330,375),
 ('app/Models/JobCard.php',1,145),
 ('app/Models/RotSession.php',1,155),
 ('app/Models/RotAdminCorrection.php',1,90),
 ('app/Http/Controllers/Web/AdminJobCardPageController.php',15,130),
]
for p,a,b in files:
 f=Path(p)
 if not f.exists():continue
 lines=f.read_text(errors='replace').splitlines()
 print('CODE',p,'RANGE',a,b,'TOTAL',len(lines))
 for i in range(a-1,min(b,len(lines))):
  if re.search('password|bearer|api.secret|private.key',lines[i],re.I):continue
  print(f'{i+1}: {lines[i][:255]}')
PY
echo '=== DB SCHEMA + CURRENT JC/ROT dates (NO WRITES) ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['job_cards','rot_sessions','job_card_labour','rot_mechanic_segments','rot_session_events','rot_admin_corrections'] as $t){
 if(!Schema::hasTable($t))continue;
 $cols=DB::select('SHOW COLUMNS FROM '.$t);
 echo 'SCHEMA='.$t.' '.json_encode(array_map(fn($x)=>['f'=>$x->Field,'type'=>$x->Type],$cols)).PHP_EOL;
}
foreach(['JC-20261018-00037','JC-20261016-00036'] as $no){
 $c=DB::table('job_cards')->where('job_no',$no)->first();
 if(!$c)continue;
 $out=[];
 foreach(['id','job_no','status','invoice_id','created_at','job_date','job_card_date','vehicle_inward_at','inward_at','received_at','opened_at','work_started_at','updated_at'] as $f)
  if(property_exists($c,$f))$out[$f]=$c->{$f};
 echo 'JC_TIMELINE='.json_encode($out).PHP_EOL;
 foreach(DB::table('rot_sessions')->where('job_card_id',$c->id)->get() as $r){
  $item=[];
  foreach(['id','rot_code','status','first_start_at','start_time','end_time','completed_at','std_hours','total_seconds','labour_line_id','updated_at'] as $k)
   if(property_exists($r,$k))$item[$k]=$r->{$k};
  echo 'ROT_TIMELINE='.json_encode($item).PHP_EOL;
 }
}
PHP
echo 'V647_DATETIME_INSPECTION=COMPLETE'
