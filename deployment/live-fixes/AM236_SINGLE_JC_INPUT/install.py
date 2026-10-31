#!/usr/bin/env python3
"""AM236: remove duplicate visible JC/Vehicle dropdown while retaining original backend ID.
check|apply|rollback STAGING_ROOT; view + JS only, no database mutations.
"""
from pathlib import Path
import sys,os,subprocess,tempfile,hashlib,json,shutil
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('AM236_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM236_STOP_NOT_STAGING')
files={'view':root/'resources/views/purchases/osl-create.blade.php',
       'js':root/'public/js/am232-osl-purchase.js'}
back=root/'storage/app/am-backups/AM236_ONE_JC_FIELD'
manifest=back/'checksums.json'
marker='AM236_SINGLE_JC_INPUT'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def digest(t):return hashlib.sha256(t.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,text):
 fd,temp=tempfile.mkstemp(prefix='.am236_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(text)
  os.chmod(temp,p.stat().st_mode&0o777)
  os.replace(temp,p)
 finally:
  if os.path.exists(temp):os.unlink(temp)
if any(not f.is_file() or f.is_symlink() for f in files.values()):
 raise SystemExit('AM236_STOP_SOURCE_NOT_FOUND')
old={n:read(p) for n,p in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM236_STOP_BACKUP_MISSING')
 checks=json.loads(read(manifest))
 if any(digest(old[n])!=checks['after'][n] for n in files):
  raise SystemExit('AM236_STOP_CONCURRENT_CHANGE')
 for n,p in files.items():atomic(p,read(back/(n+'.before')))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,timeout=90)
 print('AM236_ROLLBACK_RESTORED');sys.exit(0)
if all(marker in old[n] for n in files):
 print('AM236_ALREADY_INSTALLED');sys.exit(0)
if any(marker in old[n] for n in files):raise SystemExit('AM236_STOP_PARTIAL_INSTALL')
if 'am232-osl-purchase-inline' not in old['view'] or 'AM232_OSL_JOB_LINK_SELECTOR' not in old['js']:
 raise SystemExit('AM236_STOP_PREVIOUS_JC_SELECTOR_MISSING')
new=dict(old)
style="""
{{-- AM236_SINGLE_JC_INPUT: display ONE Job Card / Vehicle search input per OSL row;
     native select remains submitted to backend as job_card_id. --}}
<style id="am236-single-jc-input">
#osl-form .osl-line .job-filter-wrap > select.job-card,
#osl-form .osl-line .job-filter-wrap select.job-card,
#osl-lines .osl-line .job-filter-wrap > select.job-card,
.osl-form .osl-line .job-filter-wrap select.job-card {
  display:none!important;visibility:hidden!important;
  position:absolute!important;left:-99999px!important;
  width:0!important;height:0!important;min-width:0!important;
  min-height:0!important;padding:0!important;margin:0!important;
  border:0!important;pointer-events:none!important;
}
#osl-form .osl-line .job-filter-wrap > input.job-filter{
  display:block!important;width:100%!important;min-height:38px!important;
}
</style>
"""
anchor='{{-- AM232_OSL_ESTIMATE_JC_LINK: JC-specific estimate OSL items in one searchable picker. --}}'
if new['view'].count(anchor)!=1:raise SystemExit('AM236_STOP_VIEW_ANCHOR_MISMATCH')
new['view']=new['view'].replace(anchor,style+'\n'+anchor,1)
a=""" job.style.position='absolute';job.style.left='-10000px';job.style.width='1px';
 job.style.height='1px';job.style.opacity='0';job.style.pointerEvents='none';
 job.tabIndex=-1;job.required=false; // original form+server enforce job_card_id"""
b=""" // AM236_SINGLE_JC_INPUT: hidden native select is retained for Laravel POST + listeners.
 job.hidden=true;job.setAttribute('aria-hidden','true');
 job.style.setProperty('display','none','important');
 job.style.setProperty('visibility','hidden','important');
 job.style.setProperty('position','absolute','important');
 job.style.setProperty('left','-99999px','important');
 job.tabIndex=-1;job.required=false; // server and capture submit validate JC ID"""
if new['js'].count(a)!=1:raise SystemExit('AM236_STOP_JS_ANCHOR_MISMATCH')
new['js']=new['js'].replace(a,b,1)
assert new['view'].count(marker)==1 and new['js'].count(marker)==1
# Ensure original input's backend name and normal job select JS listeners remain.
for needle in ('name="lines[__INDEX__][job_card_id]"','class="job-card"','class="job-filter"','am232-osl-purchase-inline'):
 if needle not in new['view']:raise SystemExit('AM236_STOP_JC_BACKEND_INVARIANT '+needle)
for needle in ("job.value=o.value","job.dispatchEvent(new Event('change'","job.tabIndex=-1","job.required=false"):
 if needle not in new['js']:raise SystemExit('AM236_STOP_JOB_SELECTION_INVARIANT '+needle)
with tempfile.TemporaryDirectory(prefix='am236_qa_') as t:
 d=Path(t)
 js=d/'single-jc.js';js.write_text(new['js'],encoding='utf8',errors='surrogateescape')
 node=shutil.which('node')
 if node:
  r=subprocess.run([node,'--check',str(js)],cwd=root,capture_output=True,text=True,timeout=25)
  if r.returncode:raise SystemExit('AM236_STOP_JS_LINT '+r.stderr[-350:])
 view=d/'osl-create.blade.php';view.write_text(new['view'],encoding='utf8',errors='surrogateescape')
 comp=d/'compiled.php';helper=d/'compile.php'
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 r=subprocess.run(['php',str(helper),str(root),str(view),str(comp)],cwd=root,text=True,capture_output=True,timeout=55)
 if r.returncode:raise SystemExit('AM236_STOP_BLADE_COMPILE '+r.stderr[-450:])
 r=subprocess.run(['php','-l',str(comp)],cwd=root,text=True,capture_output=True,timeout=35)
 if r.returncode:raise SystemExit('AM236_STOP_COMPILED_PHP_LINT '+(r.stdout+r.stderr)[-450:])
print('AM236_BLADE_PHP_JS_GUARDS_PASS')
if mode=='check':
 print('AM236_CHECK_ONLY_NO_MUTATIONS');sys.exit(0)
if back.exists():raise SystemExit('AM236_STOP_BACKUP_COLLISION')
if any(read(files[n])!=old[n] for n in files):raise SystemExit('AM236_STOP_SOURCE_RACE')
back.mkdir(parents=True,mode=0o700,exist_ok=False)
for n in files:
 p=back/(n+'.before');p.write_text(old[n],encoding='utf8',errors='surrogateescape');os.chmod(p,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:digest(new[n]) for n in files}}),encoding='ascii')
 os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
  if r.returncode:raise RuntimeError('AM236_STOP_VIEW_CACHE '+r.stderr[-350:])
except BaseException as e:
 for n,p in files.items():atomic(p,old[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM236_APPLY_FAILED_ROLLED_BACK '+str(e))
print('AM236_SINGLE_JC_INPUT_STAGING_APPLIED_NO_FINANCIAL_WRITES')
