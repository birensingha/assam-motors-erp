#!/usr/bin/env python3
"""AM239 lifetime one-purchase-per-OSL-JC-line guard.
Strict history semantics: even reversed/deleted purchase reserves source line.
check|apply|rollback STAGING_ROOT; no database writes.
"""
from pathlib import Path
import sys,os,hashlib,json,tempfile,subprocess
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM239_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM239_STOP_NOT_STAGING')
files={'controller':root/'app/Http/Controllers/Web/AdminPurchasePageController.php',
       'service':root/'app/Services/PurchaseService.php'}
backup=root/'storage/app/am-backups/AM239_OSL_ONCE_EVER'
manifest=backup/'hashes.json'
marker='AM239_OSL_ONCE_EVER'
def read(f):return f.read_text('utf8','surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(f,s):
 fd,t=tempfile.mkstemp(prefix='.am239_',dir=f.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as w:w.write(s)
  os.chmod(t,f.stat().st_mode&0o777);os.replace(t,f)
 finally:
  if os.path.exists(t):os.unlink(t)
if any(not f.is_file() for f in files.values()):raise SystemExit('AM239_STOP_SOURCE_MISSING')
old={n:read(p) for n,p in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM239_STOP_NO_BACKUP')
 hashes=json.loads(read(manifest))
 if any(sha(old[n])!=hashes['after'][n] for n in files):raise SystemExit('AM239_STOP_CONCURRENT_CHANGE')
 for n,p in files.items():atomic(p,read(backup/(n+'.before')))
 print('AM239_ROLLBACK_COMPLETE');sys.exit(0)
if all(marker in old[n] for n in files):print('AM239_ALREADY_INSTALLED');sys.exit(0)
if any(marker in old[n] for n in files):raise SystemExit('AM239_STOP_PARTIAL')
if any('AM238_OSL_CONFIG_ONE_PURCHASE' not in s for s in old.values()):raise SystemExit('AM239_STOP_AM238_MISSING')
new=dict(old)
def replace(n,a,b,label):
 if new[n].count(a)!=1:raise SystemExit('AM239_STOP_ANCHOR_'+label+'_'+str(new[n].count(a)))
 new[n]=new[n].replace(a,b,1)
replace('controller',
"""                  ->where('purchased.purchase_type','OSL')
                  ->whereRaw("COALESCE(purchased.record_status,'ACTIVE') <> 'DELETED'")
                  ->whereNull('purchased.deleted_at');""",
"""                  ->where('purchased.purchase_type','OSL');
                  // AM239_OSL_ONCE_EVER: prior purchase reserves this JC OSL line,
                  // including deleted/reversed historical purchases.""",
'UNPURCHASED_DROPDOWN')
replace('service',
"""                ->where('purchase_type','OSL')
                ->whereRaw("COALESCE(record_status,'ACTIVE') <> 'DELETED'")
                ->whereNull('deleted_at')->lockForUpdate()->exists()){""",
"""                ->where('purchase_type','OSL')
                // AM239_OSL_ONCE_EVER: do not re-purchase this JC OSL line even if
                // a previous purchase was audited/deleted/reversed.
                ->lockForUpdate()->exists()){""",
'SERVER_LIFETIME_DUPLICATE')
replace('service',
"This JC OSL billing line already has an active supplier purchase. Open existing purchase to edit; do not create duplicate.",
"This JC OSL work has already been purchased once. Open that purchase for correction; to purchase again add a NEW OSL line to the Job Card.",
'DUPLICATE_ERROR')
with tempfile.TemporaryDirectory(prefix='am239_qa_') as d:
 for n in files:
  f=Path(d)/(n+'.php');f.write_text(new[n],encoding='utf8',errors='surrogateescape')
  p=subprocess.run(['php','-l',str(f)],cwd=root,capture_output=True,text=True,timeout=35)
  if p.returncode:raise SystemExit('AM239_PHP_LINT_'+n+' '+(p.stdout+p.stderr)[-400:])
print('AM239_ONE_PURCHASE_EVER_SERVER_AND_SELECTOR_LINT_PASS')
if mode=='check':print('AM239_CHECK_ONLY_NO_WRITES');sys.exit(0)
if backup.exists() or any(read(files[n])!=old[n] for n in files):raise SystemExit('AM239_STOP_BACKUP_OR_RACE')
backup.mkdir(mode=0o700,parents=True,exist_ok=False)
for n in files:
 p=backup/(n+'.before');p.write_text(old[n],encoding='utf8',errors='surrogateescape');os.chmod(p,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:sha(new[n]) for n in files}}),encoding='ascii');os.chmod(manifest,0o600)
except BaseException as e:
 for n,p in files.items():atomic(p,old[n])
 raise SystemExit('AM239_ROLLED_BACK '+str(e))
print('AM239_OSL_LIFETIME_DUPLICATE_BLOCKER_DEPLOYED_NO_DB_WRITES')
