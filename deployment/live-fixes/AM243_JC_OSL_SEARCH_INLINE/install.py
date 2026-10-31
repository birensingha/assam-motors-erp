#!/usr/bin/env python3
"""AM243 repair OSL Master search missing: inline same audited AM242 JS beside modal Blade.
check|apply|rollback STAGING_ROOT. View source only, no data or finance writes.
"""
from pathlib import Path
import sys,os,tempfile,hashlib,json,subprocess
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM243_USAGE')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM243_STOP_NOT_STAGING')
view=root/'resources/views/job-cards/show.blade.php'
asset=root/'public/js/am242-jc-osl-one-field.js'
backup=root/'storage/app/am-backups/AM243_JC_OSL_INLINE'
manifest=backup/'checksums.json'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 fd,t=tempfile.mkstemp(prefix='.am243_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(t,p.stat().st_mode&0o777)
  os.replace(t,p)
 finally:
  if os.path.exists(t):os.unlink(t)
if not view.is_file() or not asset.is_file() or view.is_symlink():raise SystemExit('AM243_STOP_MISSING_SOURCE')
old=read(view);js=read(asset)
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM243_STOP_NO_BACKUP')
 checks=json.loads(read(manifest))
 if sha(old)!=checks['after']:raise SystemExit('AM243_STOP_CONCURRENT_SOURCE_CHANGE')
 atomic(view,read(backup/'show.blade.before'))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 print('AM243_ROLLBACK_RESTORED_PREVIOUS_VIEW');sys.exit(0)
marker='AM243_JC_OSL_SEARCH_INLINE'
if marker in old:
 if old.count(marker)==1 and 'id="am243-jc-osl-one-field-inline"' in old:
  print('AM243_ALREADY_INSTALLED');sys.exit(0)
 raise SystemExit('AM243_STOP_PARTIAL')
if 'AM242_JC_OSL_ONE_FIELD' not in old or 'AM242_JC_ONE_OSL_FIELD' not in js:
 raise SystemExit('AM243_STOP_AM242_NOT_DEPLOYED')
source='<script src="{{ asset(\'js/am242-jc-osl-one-field.js\') }}?v=AM242-20261031" defer></script>'
if old.count(source)!=1:raise SystemExit('AM243_STOP_EXTERNAL_ASSET_ANCHOR_'+str(old.count(source)))
if '</script' in js.lower():raise SystemExit('AM243_STOP_ASSET_HAS_SCRIPT_TERMINATOR')
replacement="""{{-- AM243_JC_OSL_SEARCH_INLINE: inline the audited JS so /erp path routing cannot 404.
     The combobox is added on every modal open using the source file in public/js. --}}
<script id="am243-jc-osl-one-field-inline">{!! file_get_contents(public_path('js/am242-jc-osl-one-field.js')) !!}</script>
<style id="am243-ensure-osl-combo-visible">
dialog.am91-osl-dialog .am146-master-row > .am242-combo{
 display:block!important;visibility:visible!important;opacity:1!important;
 position:relative!important;flex:1 1 340px!important;min-width:180px!important;
 width:auto!important;height:auto!important;overflow:visible!important;
}
dialog.am91-osl-dialog .am242-combo .am242-label,
dialog.am91-osl-dialog .am242-combo .am242-search{
 display:block!important;visibility:visible!important;opacity:1!important;
}
dialog.am91-osl-dialog .am242-combo .am242-options{z-index:2147483600!important}
</style>"""
new=old.replace(source,replacement,1)
if new.count(marker)!=1 or new.count('am243-jc-osl-one-field-inline')!=1:
 raise SystemExit('AM243_STOP_RENDER_SOURCE_INVARIANT')
if source in new or 'am241-jc-osl-master-live' not in new:raise SystemExit('AM243_STOP_EXISTING_MASTER_LOST')
with tempfile.TemporaryDirectory(prefix='am243qa_') as tmp:
 d=Path(tmp);inp=d/'show.blade.php';out=d/'compiled.php';helper=d/'compile.php'
 inp.write_text(new,encoding='utf8',errors='surrogateescape')
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 proc=subprocess.run(['php',str(helper),str(root),str(inp),str(out)],cwd=root,capture_output=True,text=True,timeout=60)
 if proc.returncode:raise SystemExit('AM243_STOP_BLADE_COMPILE '+proc.stderr[-500:])
 proc=subprocess.run(['php','-l',str(out)],cwd=root,capture_output=True,text=True,timeout=35)
 if proc.returncode:raise SystemExit('AM243_STOP_COMPILED_PHP '+(proc.stderr+proc.stdout)[-500:])
 c=read(out)
 if 'am243-jc-osl-one-field-inline' not in c or "file_get_contents(public_path('js/am242-jc-osl-one-field.js'))" not in c:
  raise SystemExit('AM243_STOP_COMPILED_SOURCE_NOT_INLINE')
 if 'am243-ensure-osl-combo-visible' not in c:raise SystemExit('AM243_STOP_COMPILED_CSS_MISSING')
print('AM243_JOB_CARD_BLADE_COMPILED_PHP_VALIDATION_PASS')
if mode=='check':
 print('AM243_CHECK_ONLY_NO_SOURCE_OR_FINANCIAL_WRITES');sys.exit(0)
if backup.exists() or read(view)!=old:raise SystemExit('AM243_STOP_BACKUP_OR_SOURCE_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
b=backup/'show.blade.before';b.write_text(old,encoding='utf8',errors='surrogateescape');os.chmod(b,0o600)
try:
 atomic(view,new)
 manifest.write_text(json.dumps({'after':sha(new)}),encoding='ascii');os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  p=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
  if p.returncode:raise RuntimeError('AM243_STOP_VIEW_CACHE '+p.stderr[-450:])
except BaseException as e:
 atomic(view,old)
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 raise SystemExit('AM243_APPLY_ROLLED_BACK '+str(e))
print('AM243_INLINE_OSL_SEARCH_STAGING_DEPLOYED_NO_DB_WRITES')
print('AM243_AUTHENTICATED_MODAL_BROWSER_QA_PENDING')
