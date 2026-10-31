#!/usr/bin/env python3
"""AM233 guarded OSL Master Register source install. check|apply|rollback STAGING_ROOT."""
from pathlib import Path
import sys,os,subprocess,tempfile,hashlib,json
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM233_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM233_STOP_NOT_STAGING')
base=Path(__file__).resolve().parent
paths={'controller':'app/Http/Controllers/Web/AdminOslQuickMasterController.php','routes':'routes/web.php','view':'resources/views/osl-masters/index.blade.php'}
files={n:root/path for n,path in paths.items()}
backup=root/'storage/app/am-backups/AM233_OSL_MASTER_REGISTER'
manifest=backup/'hashes.json'
marker='AM233_OSL_MASTER_REGISTER'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 p.parent.mkdir(parents=True,exist_ok=True)
 fd,name=tempfile.mkstemp(prefix='.am233_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(name,p.stat().st_mode&0o777 if p.exists() else 0o644)
  os.replace(name,p)
 finally:
  if os.path.exists(name):os.unlink(name)
if any(not files[n].is_file() for n in ('controller','routes')) or not (base/'index.blade.php').is_file():
 raise SystemExit('AM233_STOP_SOURCE_MISSING')
original={n:(read(f) if f.exists() else '') for n,f in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM233_STOP_BACKUP_MISSING')
 hashes=json.loads(read(manifest))
 if any(sha(original[n])!=hashes['after'][n] for n in files):raise SystemExit('AM233_STOP_SOURCE_CHANGED')
 for n,p in files.items():
  if hashes['existed'][n]:atomic(p,read(backup/(n+'.before')))
  elif p.exists():p.unlink()
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM233_ROLLBACK_RESTORED_SOURCE');sys.exit(0)
if all(marker in original[n] for n in files):
 print('AM233_ALREADY_INSTALLED');sys.exit(0)
if files['view'].exists() or any(marker in original[n] for n in ('controller','routes')):
 raise SystemExit('AM233_STOP_EXISTING_OR_PARTIAL')
new=dict(original)
def replace(n,a,b,label):
 if new[n].count(a)!=1:raise SystemExit('AM233_STOP_ANCHOR_'+label+'_'+str(new[n].count(a)))
 new[n]=new[n].replace(a,b,1)
replace('controller','use Illuminate\Http\Request;',
 'use Illuminate\Http\Request;\nuse Illuminate\View\View;\nuse Illuminate\Http\RedirectResponse;\nuse Illuminate\Support\Facades\Log;',
 'IMPORTS')
methods="""    /** AM233_OSL_MASTER_REGISTER: existing workshop_osl_master catalog. */
    private function authorizeMaster(Request $request): ErpAuthUser
    {
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active
          && strtolower(trim((string)$user->role))==='admin',403,'Admin required for OSL Master.');
        return $user;
    }

    public function index(Request $request): View
    {
        $this->authorizeMaster($request);
        $q=trim(mb_substr((string)$request->query('q',''),0,120));
        $query=DB::table('workshop_osl_master');
        if($q!==''){
            $like='%'.str_replace(['%','_'],['\\\\%','\\\\_'],$q).'%';
            $query->where(function($x)use($like){
                $x->where('osl_name','like',$like)->orWhere('osl_code','like',$like);
            });
        }
        $rows=$query->orderBy('osl_name')->limit(1000)
            ->get(['id','osl_code','osl_name','category','default_cost','gst_percent','active']);
        return view('osl-masters.index',compact('rows','q'));
    }

    public function update(Request $request,int $id): RedirectResponse
    {
        $actor=$this->authorizeMaster($request);
        $data=$request->validate([
            'name'=>['required','string','min:3','max:180'],
            'category'=>['nullable','string','max:100'],
            'default_cost'=>['required','numeric','min:0','max:99999999.99'],
            'gst_percent'=>['required','numeric','between:0,100'],
            'active'=>['required','in:0,1'],
            'reason'=>['required','string','min:4','max:250'],
        ]);
        $name=mb_strtoupper(preg_replace('/\\s+/u',' ',trim((string)$data['name'])));
        DB::transaction(function()use($id,$data,$name,$actor){
            $row=DB::table('workshop_osl_master')->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404,'OSL Master not found.');
            $duplicate=DB::table('workshop_osl_master')
                ->where('id','<>',$id)
                ->whereRaw('LOWER(TRIM(osl_name))=?',[mb_strtolower($name)])
                ->exists();
            if($duplicate){
                throw \\Illuminate\\Validation\\ValidationException::withMessages([
                   'name'=>'Another OSL Master already has this name.']);
            }
            $new=[
                'osl_name'=>$name,'category'=>trim((string)($data['category']??'')) ?: 'OUTSIDE LABOUR',
                'default_cost'=>round((float)$data['default_cost'],2),
                'gst_percent'=>round((float)$data['gst_percent'],2),
                'active'=>(int)$data['active'],'updated_at'=>now(),
            ];
            DB::table('workshop_osl_master')->where('id',$id)->update($new);
            Log::info('AM233_OSL_MASTER_UPDATE',[
              'master_id'=>$id,'actor_id'=>(int)$actor->id,
              'old_name'=>$row->osl_name,'new_name'=>$new['osl_name'],
              'old_active'=>(int)$row->active,'new_active'=>$new['active'],
              'reason'=>trim((string)$data['reason']),
            ]);
        },3);
        return redirect()->route('erp.osl-masters.index')
            ->with('success','OSL Master saved with audit reason. Existing JC/invoice records unchanged.');
    }

"""
replace('controller','    public function store(Request $request): JsonResponse',methods+'    public function store(Request $request): JsonResponse','METHODS')
anchor="->name('erp.osl-masters.quick-create');"
routes="""
    // AM233_OSL_MASTER_REGISTER: reusable OSL service register.
    \\Illuminate\\Support\\Facades\\Route::get('/osl-masters',
        [\\App\\Http\\Controllers\\Web\\AdminOslQuickMasterController::class,'index'])
        ->name('erp.osl-masters.index');
    \\Illuminate\\Support\\Facades\\Route::post('/osl-masters/{id}',
        [\\App\\Http\\Controllers\\Web\\AdminOslQuickMasterController::class,'update'])
        ->whereNumber('id')->name('erp.osl-masters.update');
"""
replace('routes',anchor,anchor+'\n'+routes,'ROUTES')
new['view']=read(base/'index.blade.php')
for n in files:
 if marker not in new[n]:raise SystemExit('AM233_STOP_MISSING_MARKER_'+n)
with tempfile.TemporaryDirectory(prefix='am233_qa_') as directory:
 d=Path(directory)
 for n in ('controller','routes'):
  p=d/(n+'.php');p.write_text(new[n],encoding='utf8',errors='surrogateescape')
  v=subprocess.run(['php','-l',str(p)],cwd=root,capture_output=True,text=True,timeout=35)
  if v.returncode:raise SystemExit('AM233_PHP_LINT_'+n+' '+(v.stdout+v.stderr)[-450:])
 p=d/'view.blade.php';p.write_text(new['view'],encoding='utf8',errors='surrogateescape')
 out=d/'view.php';h=d/'compile.php'
 h.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 v=subprocess.run(['php',str(h),str(root),str(p),str(out)],cwd=root,capture_output=True,text=True,timeout=45)
 if v.returncode:raise SystemExit('AM233_BLADE_COMPILE '+v.stderr[-450:])
 v=subprocess.run(['php','-l',str(out)],cwd=root,capture_output=True,text=True,timeout=30)
 if v.returncode:raise SystemExit('AM233_BLADE_LINT '+(v.stdout+v.stderr)[-450:])
print('AM233_CONTROLLER_ROUTES_BLADE_LINT_PASS')
if mode=='check':
 print('AM233_CHECK_ONLY_NO_WRITES');sys.exit(0)
if backup.exists():raise SystemExit('AM233_STOP_BACKUP_EXISTS')
if any((read(files[n]) if files[n].exists() else '')!=original[n] for n in files):
 raise SystemExit('AM233_STOP_SOURCE_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for n in files:
 if files[n].exists():
  p=backup/(n+'.before');p.write_text(original[n],encoding='utf8',errors='surrogateescape');os.chmod(p,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:sha(new[n]) for n in files},
   'existed':{n:n!='view' for n in files}}),encoding='ascii')
 os.chmod(manifest,0o600)
 for cmd in (['php','artisan','route:clear'],['php','artisan','view:clear'],['php','artisan','view:cache']):
  v=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=85)
  if v.returncode:raise RuntimeError('AM233_CACHE_FAIL '+v.stderr[-450:])
except BaseException as e:
 for n,p in files.items():
  if n=='view':
   if p.exists():p.unlink()
  else:atomic(p,original[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM233_APPLY_ROLLED_BACK '+str(e))
print('AM233_OSL_MASTER_REGISTER_SOURCE_INSTALLED_NO_DB_WRITES')
