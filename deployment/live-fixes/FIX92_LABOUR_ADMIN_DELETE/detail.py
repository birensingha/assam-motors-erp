#!/usr/bin/env python3
"""Read-only ROT reversal & labour schema detail, no personal rows."""
from pathlib import Path
import re,sys,hashlib
root=Path(sys.argv[1])
v=root/'routes/web.php';lines=v.read_text().splitlines()
for i,line in enumerate(lines):
 if 'reversal/labour' in line:
  print('FIX92_REV_ROUTE',i+1,' '.join(lines[max(0,i-2):i+3])[:900])
for p in (root/'app/Http/Controllers/Web').glob('*Reversal*'):
 print('FIX92_REV_FILE',p.name)
 lines=p.read_text(errors='replace').splitlines()
 for i,s in enumerate(lines):
  if re.search(r'function .*labour|function .*Labour|rot_sessions|labour_line_id|billing_included|job_card_labour|audit|delete\(',s,re.I):
   for j in range(max(0,i-3),min(i+6,len(lines))):
    line=lines[j]
    print('FIX92_REV_CODE',p.name,j+1,line[:420].encode('ascii','backslashreplace').decode('ascii'))
for path in ['app/Models/RotSession.php','app/Models/JobCardLabour.php']:
 p=root/path
 if p.exists():
  print('FIX92_MODEL',path)
  for i,s in enumerate(p.read_text(errors='replace').splitlines()):
   if any(k in s for k in ('protected','public function','table','labour_line','job_card','start_time','end_time','total_seconds')):
    print('FIX92_MODEL_LINE',i+1,s[:350])
try:
 import subprocess
 php=r'''<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['job_card_labour','rot_sessions','rot_events','rot_pause_events','rot_mechanic_segments','job_card_change_audits','job_cards'] as $t){
 if(\Illuminate\Support\Facades\Schema::hasTable($t)){
  print 'FIX92_DB_COLS '.$t.' '.implode(',',\Illuminate\Support\Facades\Schema::getColumnListing($t))."\n";
 }
}
'''
 from tempfile import NamedTemporaryFile
 with NamedTemporaryFile(mode='w',dir=str(root),suffix='.php') as f:
  f.write(php);f.flush()
  r=subprocess.run(['php',f.name],cwd=root,timeout=20,capture_output=True,text=True)
  print(r.stdout[:10000],r.stderr[:600],r.returncode)
except Exception as e:print('FIX92_DB_COLS_UNAVAILABLE',type(e).__name__)
print('FIX92_DETAIL_COMPLETE')
