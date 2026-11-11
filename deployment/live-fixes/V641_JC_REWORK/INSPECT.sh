#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo '=== ROUTES: JC, ROT, QC, Rework, Reversal ==='
php artisan route:list --json | python3 -c 'import json,sys; a=json.load(sys.stdin); [print(x.get("method"), x.get("uri"), x.get("action")) for x in a if any(q in x.get("uri","").lower() for q in ["job-card","job-cards","rot","rework","reverse","qc","reversal"]) and any(q in x.get("uri","").lower() for q in ["rework","reverse","reversal","wip","rot","billing","line","delete","qc","lifecycle","parts","undo","status"])]' | head -125
echo '=== REWORK + ROT FINANCE + REVERSAL METHODS ==='
python3 - <<'PY'
from pathlib import Path
import re
needles=['failed_rot_session_id','rework','reverse','reversal','ROT/Labour editing requires','ROT reopen is allowed only','ROT edit/reassignment requires','function updateJobLine','function delete','function reopen','function changeStatus','function transition','function storeQc','function qc','function workComplete']
roots=[Path('app/Http/Controllers'),Path('app/Services'),Path('resources/views')]
files=[]
for root in roots:
 for f in root.rglob('*'):
  if not f.is_file() or f.suffix not in ('.php',):continue
  try:s=f.read_text(errors='replace')
  except:continue
  if (f.name in ['RotWorkflowService.php','AdminWorkshopControlPageController.php','AdminRotBillingController.php','AdminJobCardPageController.php','OwnerWorkshopController.php','AdminReversalController.php','JobCardLifecycleService.php']) or 'failed_rot_session_id' in s or ('reverse' in f.name.lower()) or ('rework' in f.name.lower()):
   files.append((f,s))
for f,s in files:
 ls=s.splitlines()
 hits=[i for i,line in enumerate(ls) if any(k.lower() in line.lower() for k in needles)]
 if not hits:continue
 print('===FILE',f,'TOTAL',len(ls),'===')
 excerpts=set()
 for i in hits[:65]:
  width=13 if 'failed_rot_session_id' in ls[i] else 6
  excerpts.update(range(max(0,i-4),min(len(ls),i+width)))
 for i in sorted(excerpts)[:340]:
  line=ls[i]
  if re.search('password|api_secret|private_key|bearer',line,re.I):continue
  print(f'{i+1}: {line[:240]}')
print('=== UI FORMS / WEB METHODS ===')
for file in [Path('resources/views/job-cards/show.blade.php'),Path('resources/views/job-cards/rot-billing-control.blade.php'),Path('resources/views/dashboard/workshop.blade.php')]:
 if not file.exists(): continue
 ls=file.read_text(errors='replace').splitlines()
 hits=[i for i,t in enumerate(ls) if re.search('failed_rot_session_id|Rework|reversal|reverse|Labour / ROT|Modification Reason|Save Line Amount|discount|WIP',t,re.I)]
 print('===VIEW',file,'=== HITS',len(hits))
 for i in hits[:36]:
  for j in range(max(0,i-2),min(len(ls),i+5)):
   print(f'{j+1}: {ls[j][:230]}')
PY
echo '=== TABLE COLUMNS AND EXISTENCE ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['job_cards','rot_sessions','job_card_labour','job_card_labor','job_card_parts','job_card_qc','qc_reworks','job_card_reworks','invoices','invoice_items','parts_allocations','parts_issues','inventory_movements','audit_logs'] as $table) {
 if(!Schema::hasTable($table))continue;
 $cols=DB::select("SHOW COLUMNS FROM ".$table);
 echo 'TABLE='.$table.' COLUMNS='.implode(',',array_map(fn($x)=>$x->Field,$cols)).' COUNT='.DB::table($table)->count().PHP_EOL;
}
PHP
echo '=== FULL TARGETED METHODS: REVERSAL, FINANCE, LIFECYCLE ==='
python3 - <<'PY'
from pathlib import Path
import re
specs={
'app/Http/Controllers/Web/AdminJobCardUnwindController.php':['show','approvalDesk','removeLabour','cancel','requestReview','decideReversalReview'],
'app/Http/Controllers/Web/AdminRotVoidController.php':['voidCompleted'],
'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php':['markWip','markWorkComplete','qcResult','reopenClosed','assertNotLocked'],
'app/Http/Controllers/Web/AdminWorkshopControlPageController.php':['jobAmounts','updateJobLine'],
'app/Http/Controllers/Web/AdminJobCardPageController.php':['updatePart','deletePart','deleteLabour','addLabourDirect'],
'app/Http/Controllers/Web/AdminJobCardPartIssueController.php':['issue','fitted'],
}
for path,methods in specs.items():
 f=Path(path)
 if not f.is_file():continue
 lines=f.read_text(errors='replace').splitlines()
 print('===SRC',path,'LINES',len(lines),'===')
 for method in methods:
  hits=[i for i,x in enumerate(lines) if re.search(r'\bfunction\s+'+re.escape(method)+r'\s*\(',x)]
  if len(hits)!=1:continue
  i=hits[0]
  end=next((j for j in range(i+1,len(lines)) if re.search(r'\b(?:public|private|protected)\s+function\s+\w+\s*\(',lines[j])),len(lines))
  print('---METHOD',method,'RANGE',i+1,end,'---')
  for j in range(max(0,i-1),min(end,i+110)):
   x=lines[j]
   if re.search('password|secret|private.key|bearer',x,re.I):continue
   print(f'{j+1}: {x[:270]}')
for p,start,end in [
('resources/views/job-cards/lifecycle-panel.blade.php',1,250),
('resources/views/workshop-tools/job-amounts.blade.php',1,180),
('resources/views/job-cards/reversal.blade.php',1,155)]:
 f=Path(p)
 if f.exists():
  ls=f.read_text(errors='replace').splitlines()
  print('===VIEW',p,'===')
  for i in range(start-1,min(end,len(ls))):
   t=ls[i]
   if re.search('password|secret|private.key|bearer',t,re.I):continue
   print(f'{i+1}: {t[:235]}')
PY
echo '=== BILLING VIEW / REVERSAL VIEW REVIEW ==='
python3 - <<'PY'
from pathlib import Path
import re
files=[f for f in Path('resources/views').rglob('*.blade.php') if ('amount' in f.name.lower() or 'revers' in f.name.lower())]
for f in files[:15]:
 print('FILEPATH',str(f))
 if 'job-amount' not in f.name.lower() and 'reversal' not in f.name.lower():continue
 lines=f.read_text(errors='replace').splitlines()
 print('===VIEW',f,'TOTAL',len(lines),'===')
 for i,x in enumerate(lines[:320]):
  if re.search('password|bearer|api.secret',x,re.I):continue
  print(str(i+1)+': '+x[:260])
PY
echo '=== JOB CARD SHOW DIRECT LABOUR / HEADER ==='
python3 - <<'PY'
from pathlib import Path
f=Path('resources/views/job-cards/show.blade.php')
a=f.read_text(errors='replace').splitlines()
for s,e in [(380,419),(554,616)]:
 print('===VIEW SOURCE RANGE',s,e)
 for i in range(s-1,min(e,len(a))):print(str(i+1)+': '+a[i][:270])
PY
echo 'V641_READONLY_SOURCE_DIAGNOSIS=COMPLETE'
