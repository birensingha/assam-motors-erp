#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo "=== RELEVANT LARAVEL ROUTES ==="
php artisan route:list --json | python3 -c 'import sys,json; a=json.load(sys.stdin); [print(x.get("method"),x.get("uri"),x.get("action"),x.get("middleware")) for x in a if "rot" in x.get("uri","").lower() or "job-cards" in x.get("uri","").lower() and any(k in x.get("uri","").lower() for k in ["wip","labour","lifecycle"])]' | head -105
echo "=== SOURCE METHODS ==="
python3 - <<'PY'
from pathlib import Path
import re
spec={
 'app/Services/RotWorkflowService.php':['start','pause','resume','complete','changeMechanic','reopenResetEndTime'],
 'app/Http/Controllers/Web/AdminJobCardPageController.php':['show','addLabourDirect','updateLabour','deleteLabour'],
 'app/Http/Controllers/Web/AdminRotBillingController.php':['changeBilling','update'],
 'app/Http/Controllers/Web/AdminWorkshopControlPageController.php':['updateJobLine','jobAmounts'],
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php':['markWip'],
}
for file,names in spec.items():
 f=Path(file)
 if not f.exists():continue
 lines=f.read_text(errors='replace').splitlines()
 print('===FILE',file,len(lines),'===')
 methods=[(i,re.search(r'\b(public|private|protected)\s+function\s+(\w+)\s*\(',line)) for i,line in enumerate(lines)]
 methods=[(i,m.group(2)) for i,m in methods if m]
 for name in names:
  locations=[i for i,m in methods if m.lower()==name.lower()]
  if not locations:continue
  i=locations[0]; nxt=next((j for j,m in methods if j>i),len(lines))
  print('METHOD',name,'RANGE',i+1,nxt)
  take=105 if name in ['start','pause','complete','changeMechanic','reopenResetEndTime','addLabourDirect'] else 75
  for k in range(i,min(nxt,i+take)):
   text=lines[k]
   if re.search(r'password|api_key|secret|private_key|bearer',text,re.I):continue
   print(f'{k+1}: {text[:240]}')
print('=== ROUTES SOURCE ===')
for file in ['routes/web.php','routes/api.php']:
 f=Path(file)
 if not f.exists():continue
 lines=f.read_text(errors='replace').splitlines()
 for i,line in enumerate(lines):
  if re.search(r'RotWorkflow|rot.session|rot-session|rot\\/|rot\.',line,re.I):
   print(f'{file}:{i+1}: {line[:260]}')
print('=== ROT UI TEMPLATE CLUES ===')
for file in ['resources/views/job-cards/show.blade.php','resources/views/job-cards/partials/rot-list.blade.php','resources/views/job-cards/rot-billing-control.blade.php']:
 f=Path(file)
 if not f.exists():continue
 lines=f.read_text(errors='replace').splitlines()
 matches=[i for i,line in enumerate(lines) if any(k in line for k in ['rot-live-clock','rot-session','Change Mechanic','Set No Charge','ROT Time','rot-card','ROT Session','Start ROT','rotList','rot-list','Change mechanic','rot-void'])]
 print('UI_FILE',file,'MATCHES',len(matches))
 selected=set()
 for i in matches[:20]:selected.update(range(max(0,i-5),min(len(lines),i+9)))
 for i in sorted(selected):print(f'{i+1}: {lines[i][:270]}')
PY
echo "=== ROT MODEL + AUDIT TABLE ==="
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['rot_sessions','job_card_labour','job_cards','job_card_change_audits','rot_time_corrections','invoices'] as $t){
 if(!Schema::hasTable($t))continue;
 $cols=DB::select('SHOW COLUMNS FROM '.$t);
 echo 'TABLE='.$t.' COLS='.json_encode(array_map(fn($x)=>['f'=>$x->Field,'type'=>$x->Type,'null'=>$x->Null,'default'=>$x->Default],$cols)).PHP_EOL;
}
foreach(['JC-20261018-00037'] as $jobNo) {
 $j=DB::table('job_cards')->where('job_no',$jobNo)->first();
 if(!$j)continue;
 echo 'EXAMPLE_JC='.json_encode(['id'=>$j->id,'no'=>$j->job_no,'status'=>$j->status,'invoice_id'=>$j->invoice_id??null]).PHP_EOL;
 $r=DB::table('rot_sessions')->where('job_card_id',$j->id)->limit(8)->get();
 foreach($r as $x){
  $out=[];foreach(['id','rot_code','job_card_id','staff_id','status','std_hours','total_seconds','first_start_at','start_time','end_time','updated_at'] as $k)if(property_exists($x,$k))$out[$k]=$x->$k;
  echo 'SAMPLE_ROT='.json_encode($out).PHP_EOL;
 }
}
PHP
echo "=== EXACT WEB ROT CONTROLLER + ROUTES + ROT SYNC ==="
python3 - <<'PY'
from pathlib import Path
import re
def show(p,start,end):
 f=Path(p)
 if not f.exists():return
 a=f.read_text(errors='replace').splitlines()
 print('EXACT',p,start,end,'TOTAL',len(a))
 for i in range(start-1,min(len(a),end)):
  t=a[i]
  if re.search(r'password|private.?key|api.?secret|bearer',t,re.I):continue
  print(f'{i+1}: {t[:300]}')
show('app/Http/Controllers/Web/AdminRotPageController.php',1,210)
show('app/Services/RotWorkflowService.php',325,502)
show('app/Models/RotSession.php',1,130)
show('resources/views/job-cards/show.blade.php',572,625)
show('resources/views/job-cards/rot-billing-control.blade.php',1,65)
for file in ['routes/web.php','routes/erp.php']:
 f=Path(file)
 if not f.exists():continue
 a=f.read_text(errors='replace').splitlines()
 for i,t in enumerate(a):
  if "AdminRotPageController" in t or "erp.rot" in t or "rot/{session}" in t or "RotAlteration" in t:
   for j in range(max(0,i-2),min(i+4,len(a))):print('ROUTE',file,j+1,a[j][:280])
PY
echo "=== ROT CORRECTION AUDIT + EXISTING ALTERATION ==="
python3 - <<'PY'
from pathlib import Path
files=[('app/Models/RotAdminCorrection.php',1,100),
       ('app/Models/ErpAuthUser.php',1,210),
       ('app/Http/Controllers/Web/JobCardRotAlterationController.php',1,170),
       ('resources/views/rot/history.blade.php',1,140)]
for path,start,end in files:
 f=Path(path)
 if not f.exists():continue
 a=f.read_text(errors='replace').splitlines()
 print("TARGET",path,len(a))
 for i in range(start-1,min(len(a),end)):
  line=a[i]
  if 'password' in line.lower() or 'secret' in line.lower():continue
  print(str(i+1)+': '+line[:245])
PY
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['rot_admin_corrections','rot_mechanic_segments','rot_session_events'] as $t){
 if(!\Illuminate\Support\Facades\Schema::hasTable($t))continue;
 echo 'AUDIT_TABLE='.$t.' COLUMNS='.json_encode(\Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM '.$t)).PHP_EOL;
}
PHP
echo 'V645_READONLY_SOURCE_AUDIT=COMPLETE'
