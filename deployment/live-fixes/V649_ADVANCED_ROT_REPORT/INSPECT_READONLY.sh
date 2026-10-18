#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo '=== FIELDS AND TIME EVENTS ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(DB::select('SHOW TABLES') as $v){
 $t=(string)array_values((array)$v)[0];
 if(preg_match('/delivery|vehicle.*out|movement|handover|gate.*pass|inspection|job_card|attendance|rot_session|rot_mechanic/i',$t)) {
  $cols=DB::select('SHOW COLUMNS FROM '.$t);
  if(in_array($t,['job_cards','rot_sessions','rot_session_events','rot_mechanic_segments','attendance','job_card_inspection','job_card_status_history','job_card_gate_passes','vehicle_movements','workshop_vehicle_inward'])||preg_match('/gate|delivery|movement|outward/i',$t))
   echo 'TABLE='.$t.' FIELDS='.json_encode(array_map(fn($x)=>['field'=>$x->Field,'type'=>$x->Type],$cols)).PHP_EOL;
 }
}
$sample=DB::table('rot_session_events')->selectRaw('LOWER(action) action,COUNT(*) c')->groupBy('action')->orderByDesc('c')->limit(25)->get();
echo 'EVENT_ACTION_COUNTS='.$sample->toJson().PHP_EOL;
$jc=DB::table('job_cards')->orderByDesc('id')->first();
if($jc){ $out=[];foreach(['id','job_no','created_at','vehicle_inward_at','closed_at','completed_at','vehicle_out_at','delivered_at','status','customer_public_status'] as $k)if(property_exists($jc,$k))$out[$k]=$jc->$k;
echo 'LAST_JC_META='.json_encode($out).PHP_EOL;}
$s=DB::table('rot_sessions')->whereRaw("pauses is not null and pauses <> '[]'")->orderByDesc('id')->first();
if($s)echo 'PAUSE_FORMAT='.json_encode(['rot_id'=>$s->id,'pauses'=>substr((string)$s->pauses,0,1350)]).PHP_EOL;
PHP
echo '=== JOB VEHICLE OUT FIELDS IN CODE ==='
grep -RniE 'vehicle_out_at|delivered_at|outward_at|gate.?out|delivery_at|vehicle_out|vehicle_outward|ready_for_delivery' app/Http/Controllers/Web app/Services resources/views/job-cards 2>/dev/null | head -55 || true
echo '=== RECENT STAGING PERFORMANCE SOURCE ==='
python3 - <<'PY'
from pathlib import Path
for path in ['app/Http/Controllers/Web/AdminDashboardPageController.php','resources/views/dashboard/rot-performance.blade.php','resources/views/dashboard/partials/staff-daily-report.blade.php','app/Services/StaffDailyRotPerformanceService.php','resources/views/dashboard/partials/pending-rot-panel.blade.php']:
 p=Path(path)
 print('SOURCE',path,'EXISTS',p.is_file())
 if not p.exists():continue
 a=p.read_text(errors='replace').splitlines()
 if path.endswith('AdminDashboardPageController.php'):
  print('\n'.join(f'{i+1}: {a[i][:200]}' for i in range(57,min(132,len(a)))))
 if path.endswith('rot-performance.blade.php'):
  print('\n'.join(f'{i+1}: {a[i][:250]}' for i in range(min(38,len(a)))))
PY
echo 'V649_READONLY_INSPECTION=COMPLETE'
