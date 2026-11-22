#!/usr/bin/env python3
"""AM261: HSN on Parts, SAC on Labour/ROT and OSL Master create/edit.
Usage: install.py check|prepare|apply|verify|rollback STAGING_ROOT
Migration adds ONLY a nullable OSL Master sac_code column. No business posts.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','prepare','apply','verify','rollback'):
    raise SystemExit('AM261_USAGE check|prepare|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM261_NOT_STAGING')
native='app/Http/Controllers/Web/AdminNativeModuleController.php'
parts='resources/views/admin/native/parts.blade.php'
labour='resources/views/admin/native/labour.blade.php'
oslController='app/Http/Controllers/Web/AdminOslQuickMasterController.php'
oslView='resources/views/osl-masters/index.blade.php'
jcView='resources/views/job-cards/show.blade.php'
liveJs='public/js/am241-jc-osl-master.js'
estimateController='app/Http/Controllers/Web/AdminEstimatePageController.php'
estimateView='resources/views/estimates/create.blade.php'
migration='database/migrations/2026_11_22_133500_am261_osl_master_sac.php'
sources=[native,parts,labour,oslController,oslView,jcView,liveJs,estimateController,estimateView]
marker='AM261_HSN_SAC_MASTER'
backups=root/'storage/app/am-backups'
pointer=backups/'AM261_LAST_SOURCE_BACKUP.txt'

def sha(v):return hashlib.sha256(v).hexdigest()
def command(args,timeout=95):
    return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=timeout)
def atomic(p,b):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am261_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(tmp,(p.stat().st_mode&0o777) if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def exact(s,old,new,key):
    count=s.count(old)
    if count!=1:raise SystemExit('STOP_AM261_ANCHOR_'+key+'_COUNT_'+str(count))
    return s.replace(old,new,1)
def scoped(s,start,end,fn,key):
    a=s.find(start)
    if a<0 or s.count(start)!=1:raise SystemExit('STOP_AM261_SCOPED_START_'+key)
    b=s.find(end,a+len(start))
    if b<0:raise SystemExit('STOP_AM261_SCOPED_END_'+key)
    body=s[a:b]
    changed=fn(body)
    return s[:a]+changed+s[b:]
def replace_scoped(s,start,end,old,new,key):
    return scoped(s,start,end,lambda body:exact(body,old,new,key),key)
def phplint(raw,label):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as f:
        f.write(raw);p=Path(f.name)
    try:
        r=command(['php','-l',str(p)],40)
        if r.returncode:raise SystemExit('STOP_AM261_PHP_LINT_'+label+' '+(r.stdout+r.stderr)[-850:])
    finally:p.unlink(missing_ok=True)
def blade_lint(raw,label):
    with tempfile.TemporaryDirectory(prefix='am261_blade_') as td:
        src=Path(td)/'view.blade.php';out=Path(td)/'compiled.php'
        src.write_bytes(raw)
        code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        r=command(['php','-r',code,str(src),str(out)],65)
        if r.returncode or not out.exists():
            raise SystemExit('STOP_AM261_BLADE_COMPILE_'+label+' '+(r.stdout+r.stderr)[-800:])
        phplint(out.read_bytes(),'COMPILED_'+label)
def verify_schema(required):
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
foreach(['parts_master'=>'hsn_code','labour_master'=>'sac_code'] as $t=>$c){
 if(!Schema::hasTable($t)||!Schema::hasColumn($t,$c))exit(20);
}
if($argv[1]==='new' && !Schema::hasColumn('workshop_osl_master','sac_code'))exit(21);
if(!Schema::hasTable('workshop_osl_master'))exit(22);
echo 'AM261_HSN_PARTS_SAC_ROT_AND_OSL_SCHEMA_'.$argv[1].'_PASS'."\n";"""
    r=command(['php','-r',php,required],40)
    if r.returncode:raise SystemExit('STOP_AM261_MASTER_SCHEMA '+(r.stdout+r.stderr)[-450:])
    print(r.stdout.strip())

def patch():
    for name in sources:
        if not (root/name).is_file():raise SystemExit('STOP_AM261_MISSING_SOURCE_'+name)
    before={name:(root/name).read_bytes() for name in sources}
    src={name:b.decode('utf8','surrogateescape') for name,b in before.items()}
    if any(marker in txt for txt in src.values()):
        raise SystemExit('STOP_AM261_ALREADY_PATCHED_USE_VERIFY')

    # PARTS: already has parts_master.hsn_code, currently ignored by create/edit.
    x=native
    src[x]=replace_scoped(src[x],'public function createPart(', 'public function updatePart(',
        "'stock_qty'=>['nullable','numeric','min:0']]);",
        "'stock_qty'=>['nullable','numeric','min:0'],\n"
        "            // AM261_HSN_SAC_MASTER: goods HSN, keep 4/6/8 digits including leading zeros.\n"
        "            'hsn_code'=>['nullable','regex:/^(?:[0-9]{4}|[0-9]{6}|[0-9]{8})$/']]);",
        'PART_CREATE_VALIDATION')
    src[x]=replace_scoped(src[x],'public function createPart(', 'public function updatePart(',
        "'gst_percent'=>$data['gst_percent']??18",
        "'hsn_code'=>($data['hsn_code']??null),'gst_percent'=>$data['gst_percent']??18",
        'PART_CREATE_PERSIST')
    src[x]=replace_scoped(src[x],'public function updatePart(', 'public function deletePart(',
        "'active'=>['required','in:Yes,No']",
        "'hsn_code'=>['nullable','regex:/^(?:[0-9]{4}|[0-9]{6}|[0-9]{8})$/'],'active'=>['required','in:Yes,No']",
        'PART_EDIT_VALIDATION')
    src[x]=replace_scoped(src[x],'public function parts(Request', 'public function createPart(',
        "'vehicle_make','vehicle_model'",
        "'vehicle_make','vehicle_model','hsn_code'",
        'PART_SEARCH_BY_HSN')
    # Labour/ROT original SAC storage already exists and its POST validates six digits.
    # No change to business save code required.

    x=parts
    src[x]=exact(src[x],
        '<label>Purchase Price<input type="number"',
        '<label>HSN Code <small>(goods · 4/6/8 digits)</small><input name="hsn_code" type="text" '
        'inputmode="numeric" pattern="([0-9]{4}|[0-9]{6}|[0-9]{8})" maxlength="8" '
        'autocomplete="off" placeholder="Enter correct HSN" value="{{ old(\'hsn_code\',\'\') }}"></label>'
        '<label>Purchase Price<input type="number"',
        'PART_CREATE_HSN_FIELD')
    src[x]=exact(src[x],
        '<label>Purchase<input type="number"',
        '<label>HSN Code <small>(goods · 4/6/8 digits)</small><input name="hsn_code" type="text" '
        'inputmode="numeric" pattern="([0-9]{4}|[0-9]{6}|[0-9]{8})" maxlength="8" '
        'autocomplete="off" placeholder="Enter correct HSN" value="{{ $r->hsn_code ?? \'\' }}"></label>'
        '<label>Purchase<input type="number"',
        'PART_EDIT_HSN_FIELD')
    src[x]=exact(src[x],
        '<div>{{ $r->part_name }}</div>',
        '<div>{{ $r->part_name }}</div><div class="muted" style="font-size:10px">'
        'HSN: {{ $r->hsn_code ?: \'Not set\' }}</div>',
        'PART_LIST_HSN_VISIBLE')

    x=labour
    src[x]=exact(src[x], 'SAC / HSN *<input name="sac_code"', 'SAC Code *<input name="sac_code"','LABOUR_CREATE_SAC_LABEL')
    src[x]=exact(src[x], "old('sac_code','998714')", "old('sac_code','')",'LABOUR_CREATE_NO_ASSUMED_SAC')
    src[x]=exact(src[x], 'SAC / HSN *<input name="sac_code"', 'SAC Code *<input name="sac_code"','LABOUR_EDIT_SAC_LABEL')
    src[x]=exact(src[x], "old('sac_code',$r->sac_code??'998714')", "($r->sac_code??'')",'LABOUR_EDIT_EXACT_SAVED_SAC')
    src[x]=exact(src[x], '<th>SAC / HSN</th>', '<th>SAC Code</th>','LABOUR_TABLE_SAC_LABEL')
    src[x]=exact(src[x],
        '{{-- FIX76_ROT_SAC_EDITABLE_MASTER --}}',
        '{{-- FIX76_ROT_SAC_EDITABLE_MASTER · AM261_HSN_SAC_MASTER: explicit SAC required; no fabricated default. --}}',
        'LABOUR_MARKER')

    # OSL: one new nullable DB column, existing quick-create accepted for
    # legacy client compatibility; both user-facing creation forms request SAC.
    x=oslController
    src[x]=replace_scoped(src[x],'public function index(', 'public function update(',
        "->orWhere('osl_code','like',$like);",
        "->orWhere('osl_code','like',$like)->orWhere('sac_code','like',$like);",
        'OSL_SEARCH_BY_SAC')
    src[x]=replace_scoped(src[x],'public function index(', 'public function update(',
        "'id','osl_code','osl_name','category','default_cost','gst_percent','active'",
        "'id','osl_code','osl_name','sac_code','category','default_cost','gst_percent','active'",
        'OSL_LIST_SAC_COLUMN')
    src[x]=replace_scoped(src[x],'public function update(', 'public function options(',
        "'gst_percent'=>['required','numeric','between:0,100'],",
        "'gst_percent'=>['required','numeric','between:0,100'],\n"
        "            // AM261_HSN_SAC_MASTER: service SAC is six digits; old null codes remain editable.\n"
        "            'sac_code'=>['nullable','regex:/^[0-9]{6}$/'],",
        'OSL_UPDATE_SAC_VALIDATION')
    src[x]=replace_scoped(src[x],'public function update(', 'public function options(',
        "'default_cost'=>round((float)$data['default_cost'],2),",
        "'sac_code'=>trim((string)($data['sac_code']??''))?:null,\n"
        "                'default_cost'=>round((float)$data['default_cost'],2),",
        'OSL_UPDATE_SAC_PERSIST')
    src[x]=replace_scoped(src[x],'public function update(', 'public function options(',
        "'old_active'=>(int)$row->active,'new_active'=>$new['active'],",
        "'old_active'=>(int)$row->active,'new_active'=>$new['active'],\n"
        "              'old_sac'=>(string)($row->sac_code??''),'new_sac'=>(string)($new['sac_code']??''),",
        'OSL_UPDATE_AUDIT')
    src[x]=replace_scoped(src[x],'public function options(', 'public function store(',
        "->get(['id','osl_code','osl_name','gst_percent']);",
        "->get(['id','osl_code','osl_name','sac_code','gst_percent']);",
        'OSL_OPTIONS_SAC_COLUMN')
    src[x]=replace_scoped(src[x],'public function options(', 'public function store(',
        "'gst_percent'=>(float)($x->gst_percent??0),",
        "'gst_percent'=>(float)($x->gst_percent??0),\n"
        "            'sac_code'=>(string)($x->sac_code??''),",
        'OSL_OPTIONS_SAC_RESPONSE')
    src[x]=replace_scoped(src[x],'public function store(', '\n    }\n}', 
        "'name'=>['required','string','min:3','max:180'],",
        "'name'=>['required','string','min:3','max:180'],\n"
        "           // Quick-create compatible; Configuration/JC forms enforce this visually.\n"
        "           'sac_code'=>['nullable','regex:/^[0-9]{6}$/'],",
        'OSL_CREATE_SAC_VALIDATION')
    src[x]=replace_scoped(src[x],'public function store(', '\n    }\n}',
        '$row=DB::transaction(function()use($name){',
        '$row=DB::transaction(function()use($name,$data){',
        'OSL_CREATE_CLOSURE_INPUT')
    src[x]=replace_scoped(src[x],'public function store(', '\n    }\n}',
        "'category'=>'OUTSIDE LABOUR',",
        "'category'=>'OUTSIDE LABOUR',\n"
        "               'sac_code'=>trim((string)($data['sac_code']??''))?:null,",
        'OSL_CREATE_SAC_PERSIST')
    src[x]=replace_scoped(src[x],'public function store(', '\n    }\n}',
        "'code'=>(string)$row->osl_code,'name'=>(string)$row->osl_name]);",
        "'code'=>(string)$row->osl_code,'name'=>(string)$row->osl_name,\n"
        "             'sac_code'=>(string)($row->sac_code??'')]);",
        'OSL_CREATE_SAC_RESPONSE')

    x=oslView
    src[x]=exact(src[x],
        '<button type="submit" class="btn primary">Create OSL Master</button>',
        '<label for="am233-create-sac" style="display:block"><strong>SAC Code * (6 digits)</strong></label>'
        '<input id="am233-create-sac" name="sac_code" inputmode="numeric" '
        'pattern="[0-9]{6}" minlength="6" maxlength="6" required '
        'autocomplete="off" placeholder="Enter service SAC">'
        '<button type="submit" class="btn primary">Create OSL Master</button>',
        'OSL_REGISTER_CREATE_INPUT')
    src[x]=exact(src[x],
        '<th>OSL Code</th><th>Service Name</th><th>Category</th>',
        '<th>OSL Code</th><th>Service Name</th><th>SAC Code</th><th>Category</th>',
        'OSL_LIST_SAC_HEADER')
    src[x]=exact(src[x],
        '<td><input form="am233-row-{{ $row->id }}" name="category" maxlength="100" value="{{ $row->category }}"></td>',
        '<td><input form="am233-row-{{ $row->id }}" name="sac_code" aria-label="SAC Code for {{ $row->osl_code }}" '
        'type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" '
        'autocomplete="off" placeholder="Six-digit SAC" value="{{ $row->sac_code??\'\' }}"></td>'
        '<td><input form="am233-row-{{ $row->id }}" name="category" maxlength="100" value="{{ $row->category }}"></td>',
        'OSL_LIST_EDIT_SAC_FIELD')
    src[x]=exact(src[x],
        '<td colspan="7">No matching OSL Master entries.',
        '<td colspan="8">No matching OSL Master entries.',
        'OSL_EMPTY_COLSPAN')
    src[x]=exact(src[x],
        "const name=String(form.querySelector('[name=name]').value||'').trim();",
        "const name=String(form.querySelector('[name=name]').value||'').trim();\n"
        "  // AM261_HSN_SAC_MASTER: keep SAC as a digit string including leading zeros.\n"
        "  const sac_code=String(form.querySelector('[name=sac_code]').value||'').trim();\n"
        "  if(!/^[0-9]{6}$/.test(sac_code)){feedback.textContent='Enter valid six-digit SAC';return;}",
        'OSL_REGISTER_JS_SAC')
    src[x]=exact(src[x],
        'body:JSON.stringify({name})',
        'body:JSON.stringify({name,sac_code})',
        'OSL_REGISTER_JSON_SAC')
    src[x]=exact(src[x],
        "value=\"{{ $q }}\" placeholder=\"Search OSL code / name\"",
        "value=\"{{ $q }}\" placeholder=\"Search OSL code / name / SAC\"",
        'OSL_SEARCH_HINT')

    # Inline Job Card quick OSL Master creation (new master only; no effect on
    # existing OSL billing/JCs). Add visible inline SAC next to Create button.
    x=jcView
    src[x]=exact(src[x],
        "add.textContent='+ Create OSL Master';add.className='am146-new-master';",
        "add.textContent='+ Create OSL Master';add.className='am146-new-master';\n"
        "const sacLabel=document.createElement('label');sacLabel.className='am261-osl-sac';\n"
        "sacLabel.textContent='New OSL SAC Code (6 digits)';\n"
        "const sacInput=document.createElement('input');sacInput.type='text';sacInput.inputMode='numeric';\n"
        "sacInput.maxLength=6;sacInput.pattern='[0-9]{6}';sacInput.autocomplete='off';\n"
        "sacInput.placeholder='SAC for new service';sacInput.setAttribute('aria-label','New OSL Master SAC Code');\n"
        "sacLabel.appendChild(sacInput); // AM261_HSN_SAC_MASTER: Create Master only, not customer billing.",
        'JC_QUICK_CREATE_SAC_INPUT')
    src[x]=exact(src[x],
        'masterRow.append(combo,oldLabel,add,feedback);',
        'masterRow.append(combo,oldLabel,sacLabel,add,feedback);',
        'JC_QUICK_CREATE_SAC_SHOW')
    src[x]=exact(src[x],
        "if(name.length<3){feedback.textContent='Enter OSL Service name first';description.focus();return;}",
        "if(name.length<3){feedback.textContent='Enter OSL Service name first';description.focus();return;}\n"
        "const sacCode=sacInput.value.trim();\n"
        "if(!/^[0-9]{6}$/.test(sacCode)){feedback.textContent='Enter the six-digit SAC for this new OSL Master';sacInput.focus();return;}",
        'JC_QUICK_CREATE_VALIDATE_SAC')
    src[x]=exact(src[x],
        'body:JSON.stringify({name})',
        'body:JSON.stringify({name,sac_code:sacCode})',
        'JC_QUICK_CREATE_POST_SAC')
    src[x]=exact(src[x],
        "masterItems.push({id:body.id,osl_code:body.code,osl_name:body.name,gst_percent:0});",
        "masterItems.push({id:body.id,osl_code:body.code,osl_name:body.name,sac_code:body.sac_code,gst_percent:0});",
        'JC_QUICK_CREATE_LIST_SAC')
    src[x]=exact(src[x],
        'opt.value=x.id;opt.dataset.name=x.osl_name||x.name;opt.dataset.gst=x.gst_percent||0;',
        'opt.value=x.id;opt.dataset.name=x.osl_name||x.name;opt.dataset.gst=x.gst_percent||0;\n'
        'opt.dataset.sac=x.sac_code||\'\';',
        'JC_EXISTING_MASTER_SAC_DATA')

    # Reloaded Job Card OSL select receives service SAC as a data attribute.
    x=liveJs
    src[x]=exact(src[x],
        "option.dataset.gst=String(item.gst_percent??0);",
        "option.dataset.gst=String(item.gst_percent??0);\n"
        "        // AM261_HSN_SAC_MASTER: classification retained in live master options.\n"
        "        option.dataset.sac=String(item.sac_code??'');",
        'JC_OSL_OPTIONS_CLASSIFICATION')

    # HSN/SAC must flow to NEW Estimate lines from their master picker,
    # not be invented based on the selected GST percentage.
    x=estimateController
    src[x]=exact(src[x],
        "'standard_hours','labour_rate','gst_percent']);",
        "'standard_hours','labour_rate','gst_percent','sac_code']); // AM261_HSN_SAC_MASTER",
        'ESTIMATE_LABOUR_READ_SAC')
    src[x]=exact(src[x],
        "'default_cost','gst_percent']);",
        "'default_cost','gst_percent','sac_code']);",
        'ESTIMATE_OSL_READ_SAC')
    x=estimateView
    src[x]=exact(src[x],
        "'description' => $l->rot_description,\n            'unit' => 'HRS',",
        "'description' => $l->rot_description,\n            'hsn' => $l->sac_code,\n            'unit' => 'HRS',",
        'ESTIMATE_LABOUR_MAP_SAC')
    src[x]=exact(src[x],
        "'unit'=>'JOB','qty'=>1,'rate'=>0,'tax'=>18];",
        "'unit'=>'JOB','qty'=>1,'rate'=>0,'hsn'=>$o->sac_code,'tax'=>18];",
        'ESTIMATE_OSL_MAP_SAC')

    modified={name:s.encode('utf8','surrogateescape') for name,s in src.items()}
    print('AM261_PATCH_SOURCE_ANCHORS_PASS',len(modified))
    for name in [native,oslController,estimateController]:
        phplint(modified[name],name.replace('/','_'))
    print('AM261_CONTROLLER_PHP_LINT_PASS')
    for name in [parts,labour,oslView,jcView,estimateView]:
        blade_lint(modified[name],name.replace('/','_'))
    print('AM261_FIVE_BLADE_VIEWS_COMPILE_LINT_PASS')
    phplint((pkg/'files'/migration).read_bytes(),'MIGRATION')
    return before,modified

def verify():
    verify_schema('new')
    for name in sources:
        p=root/name
        if not p.is_file():raise SystemExit('STOP_AM261_VERIFY_SOURCE_MISSING_'+name)
    for name in [native,oslController,oslView,jcView,liveJs,estimateController,labour]:
        if marker not in (root/name).read_text('utf8','replace'):
            raise SystemExit('STOP_AM261_VERIFY_MARKER_MISSING_'+name)
    nativeText=(root/native).read_text('utf8','replace')
    oslText=(root/oslController).read_text('utf8','replace')
    for fragment in ("'hsn_code'=>($data['hsn_code']??null)","'sac_code'=>trim((string)($data['sac_code']??''))?:null"):
        if fragment not in nativeText+oslText:
            raise SystemExit('STOP_AM261_PERSIST_GUARD_MISSING')
    check=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Validator;
$v=Validator::make(['hsn_code'=>'8708','sac_code'=>'998714'],[
 'hsn_code'=>['nullable','regex:/^(?:[0-9]{4}|[0-9]{6}|[0-9]{8})$/'],
 'sac_code'=>['nullable','regex:/^[0-9]{6}$/']]);
if($v->fails())exit(42);
$bad=Validator::make(['hsn_code'=>'87080X','sac_code'=>'99871X'],[
 'hsn_code'=>['nullable','regex:/^(?:[0-9]{4}|[0-9]{6}|[0-9]{8})$/'],
 'sac_code'=>['nullable','regex:/^[0-9]{6}$/']]);
if(!$bad->fails())exit(43);
$count=DB::table('workshop_osl_master')->count();
echo 'AM261_CLASSIFICATION_VALIDATOR_SAMPLE_PASS AND_OSL_MASTER_ROWS='.$count."\n";"""
    r=command(['php','-r',check],45)
    if r.returncode:raise SystemExit('STOP_AM261_CLASSIFICATION_SMOKE '+(r.stdout+r.stderr)[-400:])
    print(r.stdout.strip())
    print('AM261_HSN_SAC_MASTER_FORMS_SERVER_AND_ESTIMATE_MAPPING_VERIFIED')
    print('AM261_NO_HISTORICAL_JOB_CARD_INVOICE_PURCHASE_OR_PAYMENT_WRITES')

if mode=='verify':
    verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM261_NO_BACKUP_POINTER')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM261_HSN_SAC_'):
        raise SystemExit('STOP_AM261_UNSAFE_BACKUP_PATH')
    manifest=json.loads((backup/'manifest.json').read_text())
    for name,checksum in manifest['installed'].items():
        if not (root/name).is_file() or sha((root/name).read_bytes())!=checksum:
            raise SystemExit('STOP_AM261_SOURCE_CHANGED_NO_ROLLBACK_'+name)
    for name in sources:atomic(root/name,(backup/name).read_bytes())
    command(['php','artisan','view:clear'])
    print('AM261_SOURCE_ROLLED_BACK_OSL_SAC_DB_COLUMN_INTENTIONALLY_PRESERVED')
    sys.exit(0)
if mode=='prepare':
    data=(pkg/'files'/migration).read_bytes()
    phplint(data,'MIGRATION_PREPARE')
    target=root/migration
    if target.is_file():
        if sha(target.read_bytes())!=sha(data):
            raise SystemExit('STOP_AM261_MIGRATION_PATH_CONFLICT')
        print('AM261_MIGRATION_ALREADY_PREPARED')
    else:
        atomic(target,data)
        print('AM261_OSL_SAC_MIGRATION_FILE_PREPARED_ONLY')
    print('AM261_FULL_STAGING_DB_BACKUP_REQUIRED_BEFORE_MIGRATE')
    sys.exit(0)

original,patched=patch()
if mode=='check':
    verify_schema('base')
    print('AM261_READ_ONLY_SOURCE_PREFLIGHT_PASS')
    sys.exit(0)
verify_schema('new')
backup=backups/('AM261_HSN_SAC_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for name,data in original.items():
    p=backup/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data);os.chmod(p,0o600)
written=[]
try:
    for name,data in patched.items():
        if (root/name).read_bytes()!=original[name]:
            raise RuntimeError('SOURCE_CHANGED_AFTER_PREFLIGHT_'+name)
        atomic(root/name,data);written.append(name)
    r=command(['php','artisan','view:clear'],100)
    if r.returncode:raise RuntimeError('VIEW_CLEAR_FAIL '+(r.stdout+r.stderr)[-500:])
    verify()
except Exception as err:
    for name in written:atomic(root/name,original[name])
    command(['php','artisan','view:clear'],100)
    raise SystemExit('STOP_AM261_APPLY_RESTORED_SOURCE_'+str(err))
(backup/'manifest.json').write_text(json.dumps({'installed':{name:sha(data) for name,data in patched.items()}},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM261_PARTS_LABOUR_OSL_ROT_HSN_SAC_MASTER_CREATE_EDIT_INSTALLED')
print('AM261_PRIVATE_SOURCE_BACKUP',backup)
print('AM261_AUTHENTICATED_BROWSER_CREATE_EDIT_TEST_PENDING')
