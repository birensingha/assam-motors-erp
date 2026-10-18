#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo '=== RELEVANT ROT ROUTES ==='
php artisan route:list --json | python3 -c 'import sys,json; a=json.load(sys.stdin); [print(x.get("method"),x.get("uri"),x.get("action")) for x in a if "rot" in x.get("uri","").lower() and any(k in x.get("uri","").lower() for k in ["start","resume","reopen","time","complete","pause"])]' | head -80
echo '=== ROT SERVICE CORE AND INVOICE TRANSITIONS ==='
python3 - <<'PY'
from pathlib import Path
import re
files={
'app/Services/RotWorkflowService.php':['start','resume','pause','reopenResetEndTime','complete','changeMechanic'],
'app/Http/Controllers/Web/AdminRotTimeEditController.php':['standard','actual','apply','admin'],
'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php':['markWorkComplete','markQc','markClosed','createInvoice'],
'app/Http/Controllers/Api/V1/OwnerWorkshopController.php':['startRot','resumeRot','pauseRot','completeRot'],
}
for p,methods in files.items():
 f=Path(p)
 if not f.exists():continue
 a=f.read_text(errors='replace').splitlines()
 print('FILE',p,'TOTAL',len(a))
 fn=[(i,m.group(1)) for i,x in enumerate(a) if (m:=re.search(r'\bfunction\s+(\w+)\s*\(',x))]
 for m in methods:
  for i,n in fn:
   if n.lower()!=m.lower():continue
   end=next((k for k,n in fn if k>i),len(a))
   print('METHOD',m,'LINES',i+1,end)
   for k in range(i,min(end,i+98)):
    if re.search('password|secret|private.key|bearer',a[k],re.I):continue
    print(f'{k+1}: {a[k][:260]}')
print('=== INVOICE PATHS / COMPLETE-GATE ===')
for f in [Path('app/Services/JobCardLifecycleService.php'),Path('app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php')]:
 if not f.exists():continue
 a=f.read_text(errors='replace').splitlines()
 for i,x in enumerate(a):
  if re.search(r'ROT.*running|Running.*ROT|invoice|function (markClosed|markReady|complete)',x,re.I):
   if any(y in x.lower() for y in ['running','rot','invoice','function']):
    print(str(f),i+1,x[:250])
PY
echo '=== SPECIFIC JC/ROT SERVER STATE (NO WRITES) ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['JC-20261018-00037','JC-20261016-00036'] as $number){
 $j=DB::table('job_cards')->where('job_no',$number)->first();
 if(!$j){echo "JC_MISSING=$number\n";continue;}
 $o=[];foreach(['id','job_no','status','invoice_id','updated_at','grand_total'] as $k)
   if(property_exists($j,$k))$o[$k]=$j->$k;
 echo 'JC_STATUS='.json_encode($o).PHP_EOL;
 $invoices=DB::table('invoices')->where('job_card_id',$j->id)->get();
 foreach($invoices as $invoice){
  $out=[];foreach(['id','invoice_no','status','total','grand_total','created_at'] as $k)
    if(property_exists($invoice,$k))$out[$k]=$invoice->$k;
  echo 'LINKED_INVOICE='.json_encode($out).PHP_EOL;
 }
 $rot=DB::table('rot_sessions')->where('job_card_id',$j->id)->get();
 foreach($rot as $r) {
  $x=[];foreach(['id','rot_code','job_card_id','status','std_hours','total_seconds','start_time','first_start_at','end_time','completed_at','labour_line_id','updated_at'] as $k)if(property_exists($r,$k))$x[$k]=$r->$k;
  echo 'ROT_STATE='.json_encode($x).PHP_EOL;
 }
}
foreach(['rot_admin_corrections','rot_session_events','rot_mechanic_segments','job_card_labour'] as $t){
 if(!Schema::hasTable($t))continue;
 echo 'TABLE='.$t.' COLUMNS='.json_encode(array_map(fn($x)=>['column'=>$x->Field,'type'=>$x->Type],DB::select('SHOW COLUMNS FROM '.$t))).PHP_EOL;
}
PHP
echo '=== START/RESUME transaction guard / model sync ==='
python3 - <<'PY'
from pathlib import Path
spec=[
 ('app/Services/RotWorkflowService.php',25,75),
 ('app/Services/RotWorkflowService.php',325,502),
 ('app/Http/Controllers/Web/AdminRotPageController.php',1,165),
 ('app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',255,345),
 ('resources/views/job-cards/show.blade.php',570,650),
 ('resources/views/job-cards/rot-time-editor.blade.php',1,160),
]
for name,start,end in spec:
 p=Path(name)
 if not p.exists():continue
 a=p.read_text(errors='replace').splitlines()
 print('==== SOURCE',name,start,end,'====')
 for i in range(start-1,min(end,len(a))):
  s=a[i]
  if any(w in s.lower() for w in ['password','api_key','bearer','private_key']):continue
  print(f'{i+1}: {s[:250]}')
PY
echo '=== READONLY DB RETRY ==='
for attempt in 1 2 3; do
  if php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['JC-20261018-00037','JC-20261016-00036'] as $no){
 $c=\Illuminate\Support\Facades\DB::table('job_cards')->where('job_no',$no)->first();
 if(!$c)continue;
 $inv=\Illuminate\Support\Facades\DB::table('invoices')->where('job_card_id',$c->id)->get(['id','status','invoice_no']);
 echo 'CURRENT_JC='.json_encode(['job_no'=>$c->job_no,'id'=>$c->id,'status'=>$c->status,'invoice_id'=>$c->invoice_id??null,'invoices'=>$inv]).PHP_EOL;
 foreach(\Illuminate\Support\Facades\DB::table('rot_sessions')->where('job_card_id',$c->id)->get() as $r)
   echo 'CURRENT_ROT='.json_encode(['id'=>$r->id,'rot_code'=>$r->rot_code,'status'=>$r->status,'std_hours'=>$r->std_hours,'total_seconds'=>$r->total_seconds,'start_time'=>$r->start_time,'first_start_at'=>$r->first_start_at,'end_time'=>$r->end_time]).PHP_EOL;
}
PHP
  then echo 'CURRENT_DB_READ=PASS';break;fi
  echo 'CURRENT_DB_READ=RETRY_'$attempt
  sleep 4
done
echo '=== JC36 ROT TIME HISTORY vs INVOICE DATE (read-only) ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$j=DB::table('job_cards')->where('id',36)->first();
$inv=DB::table('invoices')->where('job_card_id',36)->first();
if($j && $inv){
 $a=[];foreach(['invoice_no','invoice_date','created_at','updated_at','status'] as $k)if(property_exists($inv,$k))$a[$k]=$inv->$k;
 echo 'JC36_INVOICE_TIMELINE='.json_encode($a).PHP_EOL;
 foreach(DB::table('rot_sessions')->where('job_card_id',36)->get() as $r){
  if(Schema::hasTable('rot_session_events')){
   $ev=DB::table('rot_session_events')->where('session_id',$r->id)->orderBy('event_at')->get();
   foreach($ev as $e){$d=[];foreach(['action','event_at','staff_id','created_at'] as $k)if(property_exists($e,$k))$d[$k]=$e->$k;
    echo 'ROT_EVENT='.json_encode(['rot_id'=>$r->id,'event'=>$d]).PHP_EOL;}
  }
 }
}
PHP
echo 'V646_READONLY_ROT_INVOICE_AUDIT=COMPLETE'
