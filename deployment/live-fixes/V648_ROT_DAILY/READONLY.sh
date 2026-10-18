#!/usr/bin/env bash
set -euo pipefail
cd "$HOME/domains/assammotors.com/assam-erp-staging"
echo '=== LIVE ROUTES MATCHING ATTENDANCE / ROT REPORT / DASHBOARD ==='
php artisan route:list --json | python3 -c 'import sys,json; a=json.load(sys.stdin); [print(x.get("method"),x.get("uri"),x.get("action"),x.get("name","")) for x in a if any(k in x.get("uri","").lower() for k in ["attendance","checkout","check-out","rot-performance","productivity","rot/performance","dashboard","rot/session","rot/"])][:200]' | head -190
echo '=== TARGETED CODE CLASS FINDER ==='
python3 - <<'PY'
from pathlib import Path
import re
targets=[
 'class RotWorkflowService','function checkOut','function checkout','function checkIn','function checkin',
 'ROT Performance','Rot Performance','rot-performance','Staff Productivity','staff-performance',
 'function dashboard','function attendance','pending_rot','pending ROT','function pause','rot_session_events'
]
roots=[Path('app/Http/Controllers'),Path('app/Services'),Path('resources/views'),Path('routes')]
for root in roots:
 for p in root.rglob('*.php'):
  try: s=p.read_text(errors='replace')
  except: continue
  hits=[(i+1,x.strip()[:210]) for i,x in enumerate(s.splitlines()) if any(t.lower() in x.lower() for t in targets)]
  if hits:
   print('FILE',p,'LINES',len(s.splitlines()),'HITS',len(hits))
   for line,t in hits[:30]:print('  ',line,t)
print('=== MODELS ===')
for p in Path('app/Models').glob('*Attendance*'): print(p)
PY
echo '=== DB TABLES & FIELDS ==='
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['staff_attendance','staff_attendances','attendances','attendance_logs','staff','staff_members','rot_sessions','rot_session_events','rot_mechanic_segments','job_cards','job_card_labour'] as $table){
 if(!Schema::hasTable($table))continue;
 echo 'TABLE='.$table.'='.json_encode(array_map(fn($x)=>['f'=>$x->Field,'type'=>$x->Type],DB::select('SHOW COLUMNS FROM '.$table))).PHP_EOL;
}
PHP
echo 'V648_READONLY=COMPLETE'
