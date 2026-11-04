#!/usr/bin/env python3
"""AM242 READ ONLY: Payment Voucher sources, Staff/Customer selection, posting destinations."""
from pathlib import Path
import re,sys,subprocess,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM242_INVALID_ROOT')
terms=re.compile(r'Universal Payment|Payment To|Payment Against|payment[_ -]voucher|staff[_ -]ledger|advance salary|local conveyance|tour expenses|ON ACCOUNT|on_account|payee_type|entity_type|against_reference|customer.*picker',re.I)
paths=[root/'routes/web.php']
for d in ['app/Http/Controllers/Web','app/Services','resources/views','public/js','app/Models']:
 for p in (root/d).rglob('*'):
  if not p.is_file() or p.suffix not in {'.php','.js'}:continue
  if p.stat().st_size>350000:continue
  if re.search(r'(backup|\.bak|\.old|\.tmp)',p.name,re.I):continue
  paths.append(p)
matches=[]
for p in paths:
 if not p.is_file():continue
 src=p.read_text('utf8','replace').splitlines()
 hit=[i for i,l in enumerate(src) if terms.search(l)]
 if not hit:continue
 rel=p.relative_to(root).as_posix()
 print('AM242_MATCH_FILE',rel,'LINES',len(src),'HITS',len(hit))
 print('AM242_HITS',rel,','.join(str(i+1) for i in hit[:45]))
 matches.append((rel,p,src,hit))
# Focused source dump: files with voucher in filename; and likely related models/controllers.
for rel,p,src,hits in matches:
 if not (re.search(r'payment|voucher|expense|staff|customer',rel,re.I) and (
       re.search(r'payment|voucher',rel,re.I) or ('routes' in rel))):continue
 if rel.endswith('.js'):
  window=[(max(0,i-5),min(len(src),i+12)) for i in hits[:22]]
 elif rel=='routes/web.php':
  window=[(max(0,i-3),min(len(src),i+4)) for i in hits[:50]]
 else:
  window=[(max(0,i-7),min(len(src),i+14)) for i in hits[:34]]
 seen=set()
 for a,b in window:
  for i in range(a,b):
   if i in seen:continue
   seen.add(i)
   line=src[i]
   if re.search(r'password|secret|private.key|api[_-]?key|env\(',line,re.I):line='[REDACTED]'
   print('AM242_SOURCE',rel,i+1,line[:440])
print('AM242_SOURCE_READONLY_DONE',flush=True)
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$tables=DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name');
foreach($tables as $row){
 $t=$row->t;
 if(!preg_match('/payment|voucher|staff|employee|customer|expense|ledger|account|advance/', $t))continue;
 try{$columns=DB::select('SHOW COLUMNS FROM `'.$t.'`');
 echo 'AM242_SCHEMA '.$t.' '.implode(',',array_map(fn($x)=>$x->Field,$columns))."\n";
 if(preg_match('/^staff$|^employees$|^customers$|^workshop_customers$|^staff_members$|^erp_users$/',$t)){
  echo 'AM242_COUNT '.$t.' '.DB::table($t)->count()."\n";
 }
 }catch(Throwable $e){echo 'AM242_TABLE_UNAVAILABLE '.$t."\n";}
}
echo 'AM242_SCHEMA_READONLY_DONE'."\n";
"""
with tempfile.TemporaryDirectory(prefix='am242audit_') as d:
 f=Path(d)/'audit.php';f.write_text(php)
 run=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=45)
 print(run.stdout[:24000],flush=True);print('AM242_SCHEMA_EXIT',run.returncode,run.stderr[-400:],flush=True)
print('AM242_AUDIT_FINISHED')
