#!/usr/bin/env python3
"""AM227 move the ORIGINAL Amount Paid field immediately BELOW Select Vendor Name.
STAGING only; check|apply|rollback STAGING_ROOT; Blade view only, no DB writes.
"""
from pathlib import Path
import sys,os,hashlib,subprocess,tempfile,json
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('AM227_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM227_STOP_NOT_STAGING')
view=root/'resources/views/payments/create.blade.php'
backup=root/'storage/app/am-backups/AM227_AMOUNT_AFTER_VENDOR'
saved=backup/'payment.blade.before'; checksum=backup/'checksum.json'
def read(f):return f.read_text('utf8',errors='surrogateescape')
def digest(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def write(f,data):
 fd,temp=tempfile.mkstemp(prefix='.am227_',dir=f.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as out:out.write(data)
  os.chmod(temp,f.stat().st_mode & 0o777)
  os.replace(temp,f)
 finally:
  if os.path.exists(temp):os.unlink(temp)
if not view.is_file() or view.is_symlink():raise SystemExit('AM227_STOP_VIEW_MISSING')
old=read(view)
if mode=='rollback':
 if not saved.is_file() or not checksum.is_file():raise SystemExit('AM227_STOP_BACKUP_MISSING')
 if digest(old)!=json.loads(read(checksum))['after']:raise SystemExit('AM227_STOP_CHANGED_AFTER_INSTALL')
 write(view,read(saved))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM227_ROLLBACK_RESTORED_AMOUNT_POSITION');sys.exit(0)
marker='AM227_AMOUNT_AFTER_VENDOR'
if marker in old:
 if old.count(marker)==1: print('AM227_ALREADY_INSTALLED');sys.exit(0)
 raise SystemExit('AM227_STOP_PARTIAL')
anchor='</script>'
script="""<script id="am227-amount-after-vendor">
// AM227_AMOUNT_AFTER_VENDOR: reposition the existing amount input (no duplicate).
document.addEventListener('DOMContentLoaded', function(){
  var search = document.getElementById('am218-search-wrap');
  var slot = document.getElementById('am225-amount-first-slot');
  if (!search || !slot || !search.parentNode) return;
  search.insertAdjacentElement('afterend', slot);
});
</script>"""
head='<script id="am226-vendor-only-inline">'
if old.count(head)!=1:raise SystemExit('AM227_STOP_AM226_NOT_DEPLOYED')
p=old.find(head)
end=old.find('</script>',p)
if end<0:raise SystemExit('AM227_STOP_AM226_SCRIPT_UNCLOSED')
index=end+len(anchor)
new=old[:index]+'\n'+script+old[index:]
if new.count(marker)!=1 or new.count('id="payment-value"')!=old.count('id="payment-value"'):
 raise SystemExit('AM227_STOP_AMOUNT_DUPLICATION_INVARIANT')
with tempfile.TemporaryDirectory(prefix='am227qa_') as d:
 source=Path(d)/'v.blade.php'; output=Path(d)/'v.php'; comp=Path(d)/'comp.php'
 source.write_text(new,encoding='utf8',errors='surrogateescape')
 comp.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 r=subprocess.run(['php',str(comp),str(root),str(source),str(output)],cwd=root,capture_output=True,text=True,timeout=50)
 if r.returncode:raise SystemExit('AM227_BLADE_COMPILE_FAILED '+r.stderr[-300:])
 r=subprocess.run(['php','-l',str(output)],cwd=root,capture_output=True,text=True,timeout=30)
 if r.returncode:raise SystemExit('AM227_PHP_LINT_FAILED '+(r.stderr+r.stdout)[-300:])
 if marker not in read(output):raise SystemExit('AM227_STOP_COMPILED_SCRIPT_MISSING')
print('AM227_BLADE_PHP_PREFLIGHT_PASS')
if mode=='check':
 print('AM227_CHECK_ONLY_NO_MUTATIONS');sys.exit(0)
if backup.exists():raise SystemExit('AM227_STOP_BACKUP_COLLISION')
if read(view)!=old:raise SystemExit('AM227_STOP_CONCURRENT_CHANGE')
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
saved.write_text(old,encoding='utf8',errors='surrogateescape');os.chmod(saved,0o600)
try:
 write(view,new)
 checksum.write_text(json.dumps({'before':digest(old),'after':digest(new)}),encoding='ascii');os.chmod(checksum,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
  if r.returncode:raise RuntimeError('AM227_VIEW_CACHE_ERROR '+r.stderr[-250:])
except BaseException as e:
 write(view,old)
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 raise SystemExit('AM227_ROLLED_BACK_ON_FAILURE '+str(e))
print('AM227_AMOUNT_PAID_BELOW_VENDOR_DEPLOYED_NO_DB_WRITES')
