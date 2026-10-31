#!/usr/bin/env python3
"""AM234 enhance Estimate OSL Master selection with click-menu and admin Quick Create.
STAGING only, check|apply|rollback STAGING_ROOT. No automatic financial mutations.
"""
from pathlib import Path
import sys,os,tempfile,hashlib,json,subprocess,shutil
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM234_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM234_STOP_NOT_STAGING')
files={'view':root/'resources/views/estimates/create.blade.php','js':root/'public/js/estimate-entry.js'}
backup=root/'storage/app/am-backups/AM234_ESTIMATE_OSL_QUICK_CREATE'
manifest=backup/'hashes.json';marker='AM234_ESTIMATE_OSL_QUICK_CREATE'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 fd,t=tempfile.mkstemp(dir=p.parent,prefix='.am234_')
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(t,p.stat().st_mode&0o777)
  os.replace(t,p)
 finally:
  if os.path.exists(t):os.unlink(t)
if any(not f.is_file() for f in files.values()):raise SystemExit('AM234_MISSING_SOURCE')
old={n:read(p) for n,p in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM234_STOP_NO_BACKUP')
 hashes=json.loads(read(manifest))
 if any(sha(old[n])!=hashes['after'][n] for n in files):raise SystemExit('AM234_STOP_SOURCE_CHANGED')
 for n,p in files.items():atomic(p,read(backup/(n+'.before')))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM234_ROLLBACK_PASS');sys.exit(0)
if all(marker in v for v in old.values()):
 print('AM234_ALREADY_INSTALLED');sys.exit(0)
if any(marker in v for v in old.values()):raise SystemExit('AM234_STOP_PARTIAL_INSTALL')
if not all('AM232_OSL_ESTIMATE_JC_LINK' in v for v in old.values()):
 raise SystemExit('AM234_STOP_AM232_NOT_INSTALLED')
new=dict(old)
def replace(n,a,b,label):
 if new[n].count(a)!=1:raise SystemExit('AM234_STOP_ANCHOR_'+label+'_'+str(new[n].count(a)))
 new[n]=new[n].replace(a,b,1)
replace('view',
 'target="_blank" rel="noopener" href="{{ route(\'erp.osl-masters.index\') }}">OSL Master Register</a>',
 'target="_blank" rel="noopener" href="{{ route(\'erp.osl-masters.index\') }}">OSL Master Register</a> <button type="button" class="btn small" id="am234-new-osl-master">+ New OSL Master</button>',
 'MASTER_BUTTON')
addon="""
{{-- AM234_ESTIMATE_OSL_QUICK_CREATE: OSL Master creation reuses existing admin endpoint. --}}
<script id="am234-new-osl-master">
(function(){
'use strict';
const btn=document.getElementById('am234-new-osl-master');
if(!btn)return;
const url=@json(route('erp.osl-masters.quick-create')),csrf=@json(csrf_token());
btn.addEventListener('click',async()=>{
 const name=(prompt('New OSL Master Service Name:')||'').trim();
 if(name.length<3)return;
 btn.disabled=true;
 try{
  const response=await fetch(url,{method:'POST',credentials:'same-origin',
    headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
    body:JSON.stringify({name})});
  const result=await response.json();
  if(!response.ok||!result.ok)throw Error(result.message||'Unable to save OSL Master');
  const catalog=window.AM_ESTIMATE_DATA.osl||[];
  if(!catalog.some(o=>String(o.id)===String(result.id))){
    catalog.push({id:result.id,code:result.code,description:result.name,unit:'JOB',qty:1,rate:0,tax:18});
  }
  window.AM_ESTIMATE_DATA.osl=catalog;
  alert('OSL Master ready: '+result.name+'. Select OSL type and choose this service.');
 }catch(err){alert('OSL Master not saved: '+String(err.message||err))}
 finally{btn.disabled=false}
});
})();
</script>
"""
if '@endsection' not in new['view']:raise SystemExit('AM234_VIEW_END_MISSING')
i=new['view'].rfind('@endsection')
new['view']=new['view'][:i]+addon+'\n'+new['view'][i:]
replace('js',"    refSearch.addEventListener('focus', () => closeMenu(tr));",
 """    /* AM234_ESTIMATE_OSL_QUICK_CREATE: click OSL input to show Master choices,
       but Tab/focus alone still does not open a suggestion. */
    refSearch.addEventListener('focus', () => closeMenu(tr));
    refSearch.addEventListener('click', () => {
      if(type.value==='OSL')renderMenu(tr,matches(type.value,refSearch.value));
    });""",
 'OSL_CLICK_MASTER_LIST')
for n in files:
 if marker not in new[n]:raise SystemExit('AM234_MARKER_MISSING_'+n)
with tempfile.TemporaryDirectory(prefix='am234_qa_') as t:
 d=Path(t)
 node=shutil.which('node')
 if node:
  f=d/'estimate-entry.js';f.write_text(new['js'],encoding='utf8',errors='surrogateescape')
  r=subprocess.run([node,'--check',str(f)],cwd=root,text=True,capture_output=True,timeout=35)
  if r.returncode:raise SystemExit('AM234_JS_LINT '+r.stderr[-350:])
 f=d/'v.blade.php';f.write_text(new['view'],encoding='utf8',errors='surrogateescape')
 o=d/'v.php';h=d/'compile.php'
 h.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 r=subprocess.run(['php',str(h),str(root),str(f),str(o)],cwd=root,text=True,capture_output=True,timeout=45)
 if r.returncode:raise SystemExit('AM234_BLADE_COMPILE '+r.stderr[-350:])
 r=subprocess.run(['php','-l',str(o)],cwd=root,text=True,capture_output=True,timeout=30)
 if r.returncode:raise SystemExit('AM234_BLADE_LINT '+(r.stdout+r.stderr)[-350:])
print('AM234_OSL_ESTIMATE_QUICK_CREATE_PREFLIGHT_PASS')
if mode=='check':
 print('AM234_CHECK_ONLY_NO_WRITES');sys.exit(0)
if backup.exists():raise SystemExit('AM234_STOP_BACKUP_COLLISION')
if any(read(files[n])!=old[n] for n in files):raise SystemExit('AM234_STOP_CONCURRENT_CHANGE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for n,p in files.items():
 f=backup/(n+'.before');f.write_text(old[n],encoding='utf8',errors='surrogateescape');os.chmod(f,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:sha(new[n]) for n in files}}),encoding='ascii');os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  r=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=80)
  if r.returncode:raise RuntimeError('AM234_VIEW_CACHE_FAILED '+r.stderr[-350:])
except BaseException as e:
 for n,p in files.items():atomic(p,old[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM234_APPLY_ROLLED_BACK '+str(e))
print('AM234_ESTIMATE_OSL_MASTER_CLICK_AND_CREATE_SOURCE_DEPLOYED')
