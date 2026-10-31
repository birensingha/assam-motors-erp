#!/usr/bin/env python3
"""AM232 guarded OSL Master estimate selector and JC-only OSL Purchase selector.
check|apply|rollback STAGING_ROOT; no DB writes or purchase edits.
"""
from pathlib import Path
import sys,os,tempfile,json,hashlib,subprocess,shutil
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM232_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM232_STOP_NOT_STAGING')
base=Path(__file__).resolve().parent
names={'controller':'app/Http/Controllers/Web/AdminEstimatePageController.php',
 'estimate':'resources/views/estimates/create.blade.php',
 'entry':'public/js/estimate-entry.js',
 'service':'app/Services/EstimateService.php',
 'purchase':'resources/views/purchases/osl-create.blade.php',
 'purchasejs':'public/js/am232-osl-purchase.js'}
files={n:root/p for n,p in names.items()}
backup=root/'storage/app/am-backups/AM232_OSL_ESTIMATE_JC_LINK'
manifest=backup/'hashes.json'
mark='AM232_OSL_ESTIMATE_JC_LINK'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 p.parent.mkdir(parents=True,exist_ok=True)
 fd,t=tempfile.mkstemp(dir=p.parent,prefix='.am232_')
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(t,p.stat().st_mode&0o777 if p.exists() else 0o644)
  os.replace(t,p)
 finally:
  if os.path.exists(t):os.unlink(t)
if any(not p.is_file() for n,p in files.items() if n!='purchasejs') or not (base/'purchase-selector.js').is_file():
 raise SystemExit('AM232_STOP_MISSING_SOURCE')
original={n:(read(p) if p.exists() else '') for n,p in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM232_STOP_BACKUP_MISSING')
 hashes=json.loads(read(manifest))
 if any(sha(original[n])!=hashes['after'][n] for n in files):raise SystemExit('AM232_STOP_SOURCE_CHANGED')
 for n,p in files.items():
  if hashes['existed'][n]:atomic(p,read(backup/(n+'.before')))
  elif p.exists():p.unlink()
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM232_ROLLBACK_RESTORED_SOURCE');sys.exit(0)
if all(mark in original[n] for n in ('controller','estimate','entry','service','purchase')):
 print('AM232_ALREADY_INSTALLED');sys.exit(0)
if any(mark in original[n] for n in ('controller','estimate','entry','service','purchase')) or files['purchasejs'].exists():
 raise SystemExit('AM232_STOP_PARTIAL')
if 'AM233_OSL_MASTER_REGISTER' not in read(root/'routes/web.php'):
 raise SystemExit('AM232_STOP_REGISTER_ROUTE_MISSING')
new=dict(original)
def replace(n,a,b,label):
 count=new[n].count(a)
 if count!=1:raise SystemExit('AM232_STOP_ANCHOR_'+label+'_'+str(count))
 new[n]=new[n].replace(a,b,1)
replace('controller','use Illuminate\\Support\\Facades\\Validator;',
 'use Illuminate\\Support\\Facades\\Validator;\nuse Illuminate\\Support\\Facades\\DB;', 'DB_IMPORT')
replace('controller',
 "        return view('estimates.create',compact('jobCard','estimate','latest','items','parts','labour','defaultKind'));",
 """        // AM232_OSL_ESTIMATE_JC_LINK: one active OSL master for Estimate and Purchase.
        $oslMasters=DB::table('workshop_osl_master')->where('active',1)->orderBy('osl_name')
            ->limit(3000)->get(['id','osl_code','osl_name','default_cost','gst_percent']);
        return view('estimates.create',compact('jobCard','estimate','latest','items','parts','labour','oslMasters','defaultKind'));""",
 'CONTROLLER_MASTER')
replace('estimate',"    })->values();\n@endphp\n\n<script>\nwindow.AM_ESTIMATE_DATA",
 """    })->values();
    // AM232_OSL_ESTIMATE_JC_LINK: master cost must not become customer sale price.
    $estimateOslData=$oslMasters->map(function($o){
      return ['id'=>$o->id,'code'=>$o->osl_code,'description'=>$o->osl_name,
        'unit'=>'JOB','qty'=>1,'rate'=>0,'tax'=>18];
    })->values();
@endphp

<script>
window.AM_ESTIMATE_DATA""",'ESTIMATE_DATA_MAP')
replace('estimate','    labour: @json($estimateLabourData)',
 '    labour: @json($estimateLabourData),\n    osl: @json($estimateOslData)', 'ESTIMATE_DATA_OSL')
replace('estimate','id="add-estimate-line">+ Add Line</button>',
 'id="add-estimate-line">+ Add Line</button> <a class="btn small" target="_blank" rel="noopener" href="{{ route(\'erp.osl-masters.index\') }}">OSL Master Register</a>',
 'ESTIMATE_REGISTER_LINK')
replace('entry',
 "const catalog = type => type === 'PART' ? (data.parts || []) : (['ROT','LABOUR'].includes(type) ? (data.labour || []) : []);",
 "/* AM232_OSL_ESTIMATE_JC_LINK */\n  const catalog = type => type === 'PART' ? (data.parts || []) : (['ROT','LABOUR'].includes(type) ? (data.labour || []) : (type==='OSL' ? (data.osl || []) : []));",
 'ESTIMATE_JS_CATALOG')
replace('entry',"    return 'Manual OSL / outside service reference';",
 "    return 'Search OSL Master code / service';", 'OSL_PLACEHOLDER')
replace('entry',"tr.querySelector('.e-unit').value = item.unit || (type === 'PART' ? 'PCS' : 'HRS');",
 "tr.querySelector('.e-unit').value = item.unit || (type === 'PART' ? 'PCS' : type==='OSL' ? 'JOB' : 'HRS');", 'OSL_UNIT')
replace('entry',"""    if (type === 'OSL') {
      menu.hidden = true;
      menu.innerHTML = '';
      return;
    }
""",'','OSL_MENU_DISABLED')
replace('entry',"""    if (type !== 'OSL') {
      let item = selectedOptionItem(tr) || firstMatchForInput(tr);
      if (item) applyReference(tr, item);
    }""",
 """    let item = selectedOptionItem(tr) || firstMatchForInput(tr);
    if (item) applyReference(tr, item);""",
 'OSL_ENTER_REFERENCE')
replace('entry',"""      if (type.value === 'OSL') {
        if (e.key === 'Enter') {
          e.preventDefault();
          tr.querySelector('.e-desc').focus();
        }
        return;
      }
""",'','OSL_KEYDOWN_REF')
replace('service',"                $rotId=(int)($item->reference_id ?: 0);$rotCode=(string)($item->item_code ?: '');",
 """                // AM232_OSL_ESTIMATE_JC_LINK: OSL Master ID cannot be ROT Master ID.
                $rotId=$type==='OSL'?0:(int)($item->reference_id ?: 0);
                $rotCode=(string)($item->item_code ?: '');""",
 'TRANSFER_OSL_ROT_REF')
new['purchasejs']=read(base/'purchase-selector.js')
if 'AM232_OSL_JOB_LINK_SELECTOR' not in new['purchasejs'] or '</script' in new['purchasejs'].lower():
 raise SystemExit('AM232_STOP_BAD_PURCHASE_JS')
addon="""
{{-- AM232_OSL_ESTIMATE_JC_LINK: JC-specific estimate OSL items in one searchable picker. --}}
<style id="am232-osl-selector-styles">
#osl-form .job-filter-wrap{position:relative}
#osl-form .am232-job-menu[hidden]{display:none!important}
#osl-form .am232-job-menu{position:absolute;z-index:20000;top:100%;left:0;right:0;
 background:white;border:1px solid #9dbfd9;border-radius:8px;max-height:255px;overflow-y:auto;
 box-shadow:0 12px 24px #143b6330;padding:4px}
#osl-form .am232-job-choice{display:block;width:100%;background:white;border:0;
 border-bottom:1px solid #e8f1f8;text-align:left;padding:10px;font-size:11px;cursor:pointer}
#osl-form .am232-job-choice:hover,#osl-form .am232-job-choice:focus-visible{background:#eaf5ff}
#osl-form .am232-estimate-osl{width:100%;min-height:36px;border:1px solid #a9c7df;
 border-radius:6px;background:#eef8ff;font-size:11px}
#osl-form .am232-osl-note,#osl-form .am232-empty{font-size:10px;color:#4a6e8b;padding:4px}
</style>
<script>window.AM232_OSL_BILLING_LINES=@json($oslBillingOptions);</script>
<script id="am232-osl-purchase-inline">{!! file_get_contents(public_path('js/am232-osl-purchase.js')) !!}</script>
<script id="am232-osl-master-register-link">
(function(){
 const bar=document.querySelector('#osl-form .osl-toolbar');
 if(!bar)return;
 const a=document.createElement('a');a.className='btn small';
 a.href=@json(route('erp.osl-masters.index'));a.target='_blank';a.rel='noopener';
 a.textContent='OSL Master Register';bar.append(a);
})();
</script>
"""
if '@endsection' not in new['purchase']:raise SystemExit('AM232_STOP_OSL_VIEW_END')
idx=new['purchase'].rfind('@endsection')
new['purchase']=new['purchase'][:idx]+addon+'\n'+new['purchase'][idx:]
for n in ('controller','estimate','entry','service','purchase'):
 if mark not in new[n]:raise SystemExit('AM232_STOP_MARKER_'+n)
with tempfile.TemporaryDirectory(prefix='am232_qa_') as tmp:
 d=Path(tmp)
 for n in ('controller','service'):
  p=d/(n+'.php');p.write_text(new[n],encoding='utf8',errors='surrogateescape')
  r=subprocess.run(['php','-l',str(p)],cwd=root,capture_output=True,text=True,timeout=30)
  if r.returncode:raise SystemExit('AM232_PHP_LINT_'+n+' '+(r.stdout+r.stderr)[-350:])
 node=shutil.which('node')
 if node:
  for n in ('entry','purchasejs'):
   p=d/(n+'.js');p.write_text(new[n],encoding='utf8',errors='surrogateescape')
   r=subprocess.run([node,'--check',str(p)],cwd=root,capture_output=True,text=True,timeout=30)
   if r.returncode:raise SystemExit('AM232_JS_LINT_'+n+' '+r.stderr[-350:])
 helper=d/'compile.php'
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 for n in ('estimate','purchase'):
  p=d/(n+'.blade.php');out=d/(n+'.php')
  p.write_text(new[n],encoding='utf8',errors='surrogateescape')
  r=subprocess.run(['php',str(helper),str(root),str(p),str(out)],cwd=root,capture_output=True,text=True,timeout=45)
  if r.returncode:raise SystemExit('AM232_BLADE_COMPILE_'+n+' '+r.stderr[-500:])
  r=subprocess.run(['php','-l',str(out)],cwd=root,capture_output=True,text=True,timeout=30)
  if r.returncode:raise SystemExit('AM232_BLADE_LINT_'+n+' '+(r.stdout+r.stderr)[-350:])
print('AM232_ESTIMATE_OSL_PURCHASE_SOURCE_LINT_PASS')
if mode=='check':
 print('AM232_CHECK_ONLY_NO_DB_WRITES');sys.exit(0)
if backup.exists():raise SystemExit('AM232_STOP_BACKUP_COLLISION')
if any((read(files[n]) if files[n].exists() else '')!=original[n] for n in files):
 raise SystemExit('AM232_STOP_SOURCE_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for n,p in files.items():
 if p.exists():
  f=backup/(n+'.before');f.write_text(original[n],encoding='utf8',errors='surrogateescape');os.chmod(f,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:sha(new[n]) for n in files},
   'existed':{n:n!='purchasejs' for n in files}}),encoding='ascii')
 os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
  if r.returncode:raise RuntimeError('AM232_CACHE '+r.stderr[-350:])
except BaseException as e:
 for n,p in files.items():
  if n=='purchasejs':
   if p.exists():p.unlink()
  else:atomic(p,original[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 raise SystemExit('AM232_APPLY_ROLLED_BACK '+str(e))
print('AM232_STAGING_SOURCE_APPLIED_NO_DB_MUTATIONS')
