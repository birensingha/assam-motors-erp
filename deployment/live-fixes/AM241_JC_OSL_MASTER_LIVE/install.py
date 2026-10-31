#!/usr/bin/env python3
"""AM241: Job Card OSL Master dropdown live data source and status.
check|apply|rollback STAGING_ROOT; changes only source files, not records.
Uses same active workshop_osl_master table as Configuration + OSL Purchase.
"""
from pathlib import Path
import os,sys,subprocess,tempfile,hashlib,json,shutil,re
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('AM241_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM241_STOP_NOT_STAGING')
base=Path(__file__).resolve().parent
paths={'controller':'app/Http/Controllers/Web/AdminOslQuickMasterController.php',
       'routes':'routes/web.php',
       'view':'resources/views/job-cards/show.blade.php',
       'js':'public/js/am241-jc-osl-master.js'}
files={n:root/p for n,p in paths.items()}
backup=root/'storage/app/am-backups/AM241_JC_OSL_MASTER_LIVE'
manifest=backup/'checksum.json'
marker='AM241_JC_OSL_MASTER_LIVE'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(t):return hashlib.sha256(t.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 p.parent.mkdir(parents=True,exist_ok=True)
 fd,name=tempfile.mkstemp(prefix='.am241_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(name,p.stat().st_mode&0o777 if p.exists() else 0o644)
  os.replace(name,p)
 finally:
  if os.path.exists(name):os.unlink(name)
if any(not files[n].is_file() or files[n].is_symlink() for n in ('controller','routes','view')) or not (base/'live-picker.js').is_file():
 raise SystemExit('AM241_STOP_MISSING_SOURCE')
orig={n:(read(p) if p.exists() else '') for n,p in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM241_STOP_BACKUP_MISSING')
 m=json.loads(read(manifest))
 if any(sha(orig[n])!=m['after'][n] for n in files):
  raise SystemExit('AM241_STOP_SOURCE_CHANGED_AFTER_APPLY')
 for n,p in files.items():
  if m['existed'][n]:atomic(p,read(backup/(n+'.before')))
  elif p.exists():p.unlink()
 subprocess.run(['php','artisan','route:clear'],cwd=root,capture_output=True,timeout=35)
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM241_ROLLBACK_PASS');sys.exit(0)
if all(marker in orig[n] for n in files):
 print('AM241_ALREADY_INSTALLED');sys.exit(0)
if any(marker in orig[n] for n in ('controller','routes','view')) or files['js'].exists():
 raise SystemExit('AM241_STOP_PARTIAL_INSTALL')
if 'AM233_OSL_MASTER_REGISTER' not in orig['routes'] or 'AM146_OSL_MASTER' not in orig['view']:
 raise SystemExit('AM241_STOP_PREVIOUS_MASTER_WORKFLOW_MISSING')
new=dict(orig)
def replace(n,needle,replacement,name):
 count=new[n].count(needle)
 if count!=1:raise SystemExit('AM241_STOP_ANCHOR_'+name+'_'+str(count))
 new[n]=new[n].replace(needle,replacement,1)
controllerMethod="""    /** AM241_JC_OSL_MASTER_LIVE: same authoritative OSL Master as Configuration. */
    public function options(Request $request): JsonResponse
    {
        $this->authorizeMaster($request);
        $rows=DB::table('workshop_osl_master')
            ->where('active',1)->orderBy('osl_name')->limit(3000)
            ->get(['id','osl_code','osl_name','gst_percent']);
        $items=$rows->map(static fn($x)=>[
            'id'=>(int)$x->id,
            'code'=>(string)($x->osl_code??''),
            'name'=>(string)($x->osl_name??''),
            'gst_percent'=>(float)($x->gst_percent??0),
        ])->values();
        return response()->json(['ok'=>true,'items'=>$items,'count'=>$items->count()])
            ->header('Cache-Control','private, no-store, no-cache, must-revalidate')
            ->header('Pragma','no-cache');
    }

"""
replace('controller','    public function store(Request $request): JsonResponse',controllerMethod+'    public function store(Request $request): JsonResponse','OPTIONS_METHOD')
oldRoute="""    // AM233_OSL_MASTER_REGISTER: reusable OSL service register.
    \\Illuminate\\Support\\Facades\\Route::get('/osl-masters',"""
newRoute="""    // AM241_JC_OSL_MASTER_LIVE: same catalog, fresh authorized JSON for JC modal.
    \\Illuminate\\Support\\Facades\\Route::get('/osl-masters/options',
        [\\App\\Http\\Controllers\\Web\\AdminOslQuickMasterController::class,'options'])
        ->name('erp.osl-masters.options');

    // AM233_OSL_MASTER_REGISTER: reusable OSL service register.
    \\Illuminate\\Support\\Facades\\Route::get('/osl-masters',"""
replace('routes',oldRoute,newRoute,'OPTIONS_ROUTE')
viewAnchor="""</script>
<style id="am146-osl-styles">"""
addon="""</script>
{{-- AM241_JC_OSL_MASTER_LIVE: refresh master list each time the OSL billing dialog opens. --}}
<style id="am241-jc-osl-live-style">
.am91-osl-dialog .am241-live-status{flex:1 1 100%;font-size:11px;color:#335d7f;line-height:1.4}
.am91-osl-dialog .am241-refresh-master{background:#edf6fc;border:1px solid #9dbfda;border-radius:6px;
 padding:7px 10px;color:#174a70;font-weight:700;cursor:pointer}
.am91-osl-dialog .am241-refresh-master:disabled{opacity:.5;cursor:wait}
</style>
<script id="am241-jc-osl-master-live">
window.AM241_OSL_MASTER_OPTIONS_URL=@json(route('erp.osl-masters.options'));
{!! file_get_contents(public_path('js/am241-jc-osl-master.js')) !!}
</script>
<style id="am146-osl-styles">"""
replace('view',viewAnchor,addon,'INSERT_LIVE_PICKER')
new['js']=read(base/'live-picker.js')
if marker not in new['js']:
 raise SystemExit('AM241_STOP_JS_MARKER_MISSING')
for n in files:
 if marker not in new[n]:raise SystemExit('AM241_STOP_MARKER_'+n)
with tempfile.TemporaryDirectory(prefix='am241qa_') as td:
 d=Path(td)
 for n in ('controller','routes'):
  p=d/(n+'.php');p.write_text(new[n],encoding='utf8',errors='surrogateescape')
  result=subprocess.run(['php','-l',str(p)],cwd=root,capture_output=True,text=True,timeout=35)
  if result.returncode:raise SystemExit('AM241_STOP_PHP_LINT_'+n+' '+(result.stdout+result.stderr)[-550:])
 node=shutil.which('node')
 if node:
  p=d/'live-picker.js';p.write_text(new['js'],encoding='utf8',errors='surrogateescape')
  result=subprocess.run([node,'--check',str(p)],cwd=root,capture_output=True,text=True,timeout=35)
  if result.returncode:raise SystemExit('AM241_STOP_JS_LINT '+result.stderr[-550:])
 blade=d/'show.blade.php';blade.write_text(new['view'],encoding='utf8',errors='surrogateescape')
 compiled=d/'show.compiled.php';helper=d/'compile.php'
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 result=subprocess.run(['php',str(helper),str(root),str(blade),str(compiled)],
                       cwd=root,capture_output=True,text=True,timeout=60)
 if result.returncode:raise SystemExit('AM241_STOP_BLADE_COMPILE '+result.stderr[-550:])
 result=subprocess.run(['php','-l',str(compiled)],cwd=root,capture_output=True,text=True,timeout=35)
 if result.returncode:raise SystemExit('AM241_STOP_COMPILED_PHP_LINT '+(result.stdout+result.stderr)[-550:])
 if 'am241-jc-osl-master-live' not in read(compiled):raise SystemExit('AM241_STOP_COMPILED_LIVE_SCRIPT_MISSING')
print('AM241_PHP_BLADE_JS_PREFLIGHT_PASS')
if mode=='check':
 print('AM241_CHECK_ONLY_NO_SOURCE_OR_DB_WRITES');sys.exit(0)
if backup.exists():raise SystemExit('AM241_STOP_BACKUP_COLLISION')
if any((read(files[n]) if files[n].exists() else '')!=orig[n] for n in files):
 raise SystemExit('AM241_STOP_SOURCE_CHANGED')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for n,p in files.items():
 if p.exists():
  f=backup/(n+'.before');f.write_text(orig[n],encoding='utf8',errors='surrogateescape')
  os.chmod(f,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:sha(new[n]) for n in files},
      'existed':{n:n!='js' for n in files}}),encoding='ascii')
 os.chmod(manifest,0o600)
 for cmd in (['php','artisan','route:clear'],['php','artisan','view:clear'],['php','artisan','view:cache']):
  result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
  if result.returncode:raise RuntimeError('AM241_STOP_CACHE '+result.stderr[-550:])
except BaseException as e:
 for n,p in files.items():
  if n=='js':
   if p.exists():p.unlink()
  else:atomic(p,orig[n])
 subprocess.run(['php','artisan','route:clear'],cwd=root,capture_output=True,timeout=35)
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM241_APPLY_FAILED_ROLLED_BACK '+str(e))
print('AM241_LIVE_OSL_MASTER_JC_MODAL_SOURCE_DEPLOYED_NO_DB_WRITES')
print('AM241_AUTHENTICATED_OPTIONS_AND_SAVE_BROWSER_QA_PENDING')
