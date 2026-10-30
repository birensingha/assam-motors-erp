#!/usr/bin/env python3
"""AM214 native Parts/ROT modal + working searchable dropdowns.
check/apply/rollback STAGING_ROOT. OLD ERP source read-only.
Existing Laravel routes, CSRF, fields, POST and database records untouched.
"""
from pathlib import Path
from html.parser import HTMLParser
import hashlib,json,os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM214_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM214_STOP_NOT_STAGING')
blade=pkg/'controls.blade.php'
viewpaths={
 'parts':root/'resources/views/admin/native/parts.blade.php',
 'labour':root/'resources/views/admin/native/labour.blade.php',
}
legacybase=root.parent/'public_html/workshop'
if not legacybase.is_dir():legacybase=root/'public/legacy/workshop'
oldpaths={
 'parts':legacybase/'parts-master.html',
 'labour':legacybase/'labour-master.html',
}
backup=root/'storage/app/am-backups/AM214_NATIVE_MASTER_MODAL_SELECT'
manifest=backup/'install-manifest.sha256'
marker='AM214_NATIVE_MASTER_MODAL_SELECT'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def digest(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am214_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
class SelectParser(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.field=None;self.in_option=False;self.option_value=None
        self.fragments=[];self.options={}
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='select':
            self.field=attrs.get('id') or attrs.get('name')
            if self.field:self.options.setdefault(self.field,[])
        if tag=='option' and self.field:
            self.in_option=True;self.fragments=[];self.option_value=attrs.get('value')
    def handle_data(self,data):
        if self.in_option:self.fragments.append(data)
    def handle_endtag(self,tag):
        if tag=='option' and self.in_option:
            display=' '.join(' '.join(self.fragments).split())
            val=(self.option_value or display).strip()
            if val and not re.match(r'^(--\s*)?(select|choose|all|none)\b',val,re.I):
                if val not in self.options[self.field]:self.options[self.field].append(val[:100])
            self.in_option=False
        if tag=='select':self.field=None;self.in_option=False
def category_data(name):
    parser=SelectParser()
    parser.feed(read(oldpaths[name]))
    fieldmap={
      'parts':{
        'category':['pfCategory','category'],
        'sub_category':['pfSubCategory','subCategory'],
        'unit':['pfUnit','unit'],
        'vehicle_make':['pfVehicleMake','vehicleMake'],
        'vehicle_model':['pfVehicleModel','vehicleModel'],
        'gst_percent':['pfGstPercent','pfGst','pfGstRate','gstPercent'],
      },
      'labour':{'category':['lfCategory','category']}
    }
    data={}
    for field,names in fieldmap[name].items():
        for nm in names:
            if parser.options.get(nm):
                data[field]=parser.options[nm]
                break
    if len(data.get('category',[]))<3:
        raise SystemExit('AM214_STOP_OLD_'+name.upper()+'_CATEGORY_LIST_MISSING')
    print('AM214_'+name.upper()+'_OLD_CATEGORY_OPTIONS',len(data['category']))
    return data
def safejson(obj):
    return json.dumps(obj,ensure_ascii=False,separators=(',',':')).replace('<','\\u003c').replace('>','\\u003e').replace('&','\\u0026')
for x in list(viewpaths.values())+list(oldpaths.values())+[blade]:
    if not x.is_file():raise SystemExit('AM214_STOP_REQUIRED_SOURCE_MISSING_'+x.name)
sources={name:read(p) for name,p in viewpaths.items()}
if mode=='rollback':
    if not manifest.is_file():raise SystemExit('AM214_ROLLBACK_MANIFEST_MISSING')
    expected=read(manifest).splitlines()
    if len(expected)!=2 or expected!=[digest(sources['parts']),digest(sources['labour'])]:
        raise SystemExit('AM214_STOP_ROLLBACK_VIEWS_CHANGED')
    for name in viewpaths:
        if not (backup/(name+'.blade.php.before_AM214')).is_file():
            raise SystemExit('AM214_STOP_ROLLBACK_BACKUP_MISSING')
    for name,p in viewpaths.items():
        atomic(p,read(backup/(name+'.blade.php.before_AM214')))
    print('AM214_ROLLBACK_RESTORED_VIEWS_NO_DB_WRITES')
    sys.exit(0)
if any(marker in s for s in sources.values()):
    if all(marker in s for s in sources.values()):
        print('AM214_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM214_STOP_PARTIAL_INSTALL')
for name,source in sources.items():
    actions={
      'parts':("route('erp.masters.parts.create')","route('erp.masters.parts.update',$r->id)","partCreatePanel"),
      'labour':("route('erp.masters.labour.create')","route('erp.masters.labour.update',$r->id)","newRotPanel")
    }[name]
    for tok in actions:
        if tok not in source:
            raise SystemExit('AM214_STOP_'+name.upper()+'_SOURCE_ANCHOR_MISSING_'+tok)
    if source.count('name="category"')!=2:
        raise SystemExit('AM214_STOP_'+name.upper()+'_CATEGORY_CREATE_EDIT_MISSING')
    if name=='parts':
        for tok in ('name="unit"','name="vehicle_make"','name="vehicle_model"','name="gst_percent"'):
            if tok not in source:raise SystemExit('AM214_STOP_PARTS_FIELD_MISSING_'+tok)
    if name=='labour' and ('name="work_procedure"' not in source
                           or 'Parts / Components to Remove &amp; Refit' not in source):
        raise SystemExit('AM214_STOP_AM212_APPROVED_ROT_UI_NOT_INSTALLED')
    if source.count('@endsection')!=1:
        raise SystemExit('AM214_STOP_'+name.upper()+'_BLADE_SECTION_ANCHOR')
payload=read(blade)
if payload.count(marker)!=1 or payload.count('__AM214_PAGE__')!=1 or payload.count('__AM214_LEGACY__')!=1 or payload.count('__AM214_MASTER_TABLE__')!=1:
    raise SystemExit('AM214_STOP_PAYLOAD_ANCHOR_INVALID')
result={}
for name,source in sources.items():
    ext=payload.replace('__AM214_PAGE__',safejson(name))
    ext=ext.replace('__AM214_LEGACY__',safejson(category_data(name)))
    ext=ext.replace('__AM214_MASTER_TABLE__','labour_master' if name=='labour' else 'parts_master')
    if any(x in ext for x in ('__AM214_PAGE__','__AM214_LEGACY__','__AM214_MASTER_TABLE__')):
        raise SystemExit('AM214_STOP_UNEXPANDED_PLACEHOLDER')
    result[name]=source.replace('@endsection',ext+'\n@endsection',1)
    if result[name].count(marker)!=1:
        raise SystemExit('AM214_STOP_DUPLICATE_MARKER')
    for tok in ("route('erp.masters."+('parts' if name=='parts' else 'labour')+".create')",
                "route('erp.masters."+('parts' if name=='parts' else 'labour')+".update',$r->id)",'@csrf'):
        if tok not in result[name]:raise SystemExit('AM214_STOP_FORM_CHANGED')
# Compile candidate Laravel Blade with app compiler before touching files.
with tempfile.TemporaryDirectory(prefix='am214qa_') as t:
    folder=Path(t)
    compiler=folder/'compile.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    for name,candidate in result.items():
        inputfile=folder/(name+'.blade.php')
        inputfile.write_text(candidate,encoding='utf8',errors='surrogateescape')
        output=folder/(name+'.compiled.php')
        run=subprocess.run(['php',str(compiler),str(root),str(inputfile),str(output)],
                           cwd=root,text=True,capture_output=True,timeout=45)
        if run.returncode or not output.exists():
            raise SystemExit('AM214_STOP_BLADE_COMPILE_'+name+' '+run.stderr[-350:])
        lint=subprocess.run(['php','-l',str(output)],cwd=root,text=True,
                            capture_output=True,timeout=35)
        if lint.returncode:
            raise SystemExit('AM214_STOP_COMPILED_BLADE_SYNTAX_'+name+' '+lint.stderr[-350:])
print('AM214_TWO_MASTER_FORMS_BLADE_PHP_PREFLIGHT_PASS')
print('AM214_NATIVE_CREATE_EDIT_ACTIONS_PRESERVED')
print('AM214_CLICK_TYPE_SELECT_MAKE_MODEL_GST_MODAL_PREPARED')
if mode=='check':
    print('AM214_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if manifest.exists() or any((backup/(name+'.blade.php.before_AM214')).exists() for name in viewpaths):
    raise SystemExit('AM214_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True)
os.chmod(backup,0o700)
for name,p in viewpaths.items():
    b=backup/(name+'.blade.php.before_AM214')
    shutil.copy2(p,b);os.chmod(b,0o600)
try:
    for name,p in viewpaths.items():atomic(p,result[name])
    manifest.write_text(digest(result['parts'])+'\n'+digest(result['labour'])+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        done=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
        if done.returncode:raise RuntimeError('VIEW_CACHE_FAILED_'+done.stderr[-250:])
except BaseException as e:
    for name,p in viewpaths.items():atomic(p,sources[name])
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    raise SystemExit('AM214_INSTALL_FAILED_SOURCE_ROLLED_BACK '+str(e))
print('AM214_NATIVE_PART_ROT_OLD_STYLE_MODAL_DROPDOWNS_INSTALLED')
print('AM214_BROWSER_VISUAL_AND_FORM_POST_TEST_REQUIRED')
