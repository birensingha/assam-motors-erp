#!/usr/bin/env python3
"""AM213 - Restore OLD ERP Parts/ROT category choices in native NEW ERP.
Read OLD source as reference, preserve native Create/Edit post/CSRF/backend.
Searchable HTML datalist allows selecting existing values or entering a new
category. Add existing database values for any formerly-used categories.

check: READ ONLY, lint/Blade compile candidate but no file/data writes.
apply: backs up exactly two STAGING native Blade views and refreshes cache.
rollback: restores backups only if deployed view digests are unchanged.
Never modifies OLD ERP, customer/jobcard/invoice/master data or Android.
"""
from pathlib import Path
from html.parser import HTMLParser
import hashlib,html,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM213_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM213_STOP_NOT_STAGING')
views={'parts':root/'resources/views/admin/native/parts.blade.php',
       'labour':root/'resources/views/admin/native/labour.blade.php'}
old_base=root.parent/'public_html/workshop'
if not old_base.is_dir():
    old_base=root/'public/legacy/workshop'
legacy={'parts':old_base/'parts-master.html',
        'labour':old_base/'labour-master.html'}
backup=root/'storage/app/am-backups/AM213_CONFIG_PART_ROT_DROPDOWNS'
manifest=backup/'installed-sha256.txt'
marker='AM213_CONFIG_PART_ROT_DROPDOWNS'

def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am213_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh:fh.write(s)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

class LegacyLists(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.select_id=None
        self.option_active=False
        self.option_text=[]
        self.option_value=''
        self.options={}
    def handle_starttag(self,tag,attrs):
        at=dict(attrs)
        if tag=='select':
            self.select_id=at.get('id') or at.get('name')
            if self.select_id:self.options.setdefault(self.select_id,[])
        elif tag=='option' and self.select_id:
            self.option_active=True
            self.option_text=[]
            self.option_value=at.get('value') or ''
    def handle_data(self,data):
        if self.option_active:self.option_text.append(data)
    def handle_endtag(self,tag):
        if tag=='option' and self.option_active:
            val=self.option_value.strip() or ' '.join(' '.join(self.option_text).split())
            if val and not re.match(r'^(-{1,2}\s*)?(select|choose|all|none)\b',val,re.I):
                opts=self.options.get(self.select_id)
                if opts is not None and val not in opts:opts.append(val[:100])
            self.option_active=False
        elif tag=='select':
            self.select_id=None
            self.option_active=False

def input_tags(source):
    """Find HTML input tags by quote-aware scanning; Blade $r->x uses '>' in attributes."""
    found=[]
    for m in re.finditer(r'<input\b',source,re.I):
        i=m.start();j=m.end();quote=None
        while j<len(source):
            ch=source[j]
            if quote:
                if ch==quote:quote=None
            elif ch in ('"',"'"):quote=ch
            elif ch=='>':
                found.append((i,j+1,source[i:j+1]))
                break
            j+=1
    return found

def php_quote(v):
    return "'"+v.replace('\\','\\\\').replace("'","\\'").replace('\r',' ').replace('\n',' ')+"'"

old={}
for name in views:
    if not views[name].is_file() or not legacy[name].is_file():
        raise SystemExit('AM213_STOP_VIEW_OR_OLD_REFERENCE_MISSING_'+name)
    old[name]=read(views[name])

if mode=='rollback':
    if not manifest.is_file():
        raise SystemExit('AM213_STOP_ROLLBACK_MANIFEST_MISSING')
    lines=read(manifest).splitlines()
    if len(lines)!=2 or lines!=[sha(old['parts']),sha(old['labour'])]:
        raise SystemExit('AM213_STOP_ROLLBACK_CURRENT_VIEWS_CHANGED')
    for name in views:
        b=backup/(name+'.blade.php.before_AM213')
        if not b.is_file():raise SystemExit('AM213_STOP_ROLLBACK_BACKUP_MISSING_'+name)
    for name in views:atomic(views[name],read(backup/(name+'.blade.php.before_AM213')))
    print('AM213_ROLLBACK_VIEWS_RESTORED_NO_DB_CHANGE')
    print('AM213_RUN_PHP_ARTISAN_VIEW_CLEAR')
    sys.exit(0)

for name in views:
    if marker in old[name]:
        raise SystemExit('AM213_ALREADY_INSTALLED_OR_PARTIAL_'+name)

# Select IDs are grounded in the live OLD ERP markup. Sub-category/unit/make
# options are imported only if present in the actual legacy SELECT fields.
mapping={
  'parts': {
    'category':('pfCategory','partCategory','category'),
    'sub_category':('pfSubCategory','partSubCategory','subCategory'),
    'unit':('pfUnit','partUnit','unit'),
    'vehicle_make':('pfVehicleMake','partVehicleMake','vehicleMake'),
    'vehicle_model':('pfVehicleModel','partVehicleModel','vehicleModel'),
  },
  'labour':{
    'category':('lfCategory','labourCategory','category')
  },
}
candidates={}
for name in views:
    parser=LegacyLists()
    parser.feed(read(legacy[name]))
    choices={}
    for column,ids in mapping[name].items():
        for field_id in ids:
            if parser.options.get(field_id):
                choices[column]=parser.options[field_id]
                break
    if 'category' not in choices or len(choices['category'])<3:
        raise SystemExit('AM213_STOP_OLD_'+name.upper()+'_CATEGORY_CHOICES_NOT_FOUND')
    print('AM213_OLD_'+name.upper()+'_CATEGORY_CHOICES',len(choices['category']))
    print('AM213_OLD_'+name.upper()+'_SELECT_IDS',','.join(sorted(parser.options.keys()))[:350])
    fields={}
    src=old[name]
    tags=input_tags(src)
    for field in mapping[name]:
        matching=[(a,b,tag) for a,b,tag in tags if re.search(
            r'\bname\s*=\s*(["\x27])'+re.escape(field)+r'\1',tag,re.I)]
        # Native form has one CREATE and one EDIT; if missing, leave it alone.
        if not matching:
            print('AM213_'+name.upper()+'_FIELD_UNAVAILABLE',field)
            continue
        if len(matching)!=2:
            raise SystemExit('AM213_STOP_'+name.upper()+'_FIELD_COUNT_'+field+'_'+str(len(matching)))
        if any(re.search(r'\blist\s*=',tag,re.I) for _,_,tag in matching):
            raise SystemExit('AM213_STOP_'+name.upper()+'_INPUT_ALREADY_HAS_LIST_'+field)
        fields[field]=matching
    if 'category' not in fields:
        raise SystemExit('AM213_STOP_'+name.upper()+'_CATEGORY_INPUT_NOT_FOUND_IN_CREATE_AND_EDIT')
    print('AM213_'+name.upper()+'_FIELDS',','.join(fields))
    # Do not guess categories; old HTML values + existing NEW DB values only.
    patches=[]
    for field,entries in fields.items():
        list_id='am213-'+name+'-'+field.replace('_','-')
        for a,b,tag in entries:
            pos=len(tag)-1
            if tag.endswith('/>'):pos-=1
            newer=tag[:pos]+' list="'+list_id+'" autocomplete="off"'+tag[pos:]
            patches.append((a,b,newer))
    for a,b,replacement in sorted(patches,key=lambda t:t[0],reverse=True):
        src=src[:a]+replacement+src[b:]
    # Make unique suggestions from the live OLD options and every existing
    # category value on STAGING (not just the current pagination page).
    items=[]
    columns=list(fields)
    quoted_columns=', '.join(php_quote(c) for c in columns)
    items.append('\n{{-- '+marker+' : OLD options + current NEW master DB distinct values --}}')
    items.append('@php')
    items.append('  $am213MasterTable='+php_quote('parts_master' if name=='parts' else 'labour_master')+';')
    items.append('  $am213MasterFields=['+quoted_columns+'];')
    items.append('  $am213Stored=[];')
    items.append('  foreach($am213MasterFields as $am213Field){')
    items.append('    $am213Stored[$am213Field]=\\Illuminate\\Support\\Facades\\DB::table($am213MasterTable)')
    items.append("      ->whereNotNull($am213Field)->where($am213Field,'<>','')")
    items.append('      ->distinct()->orderBy($am213Field)->limit(800)->pluck($am213Field);')
    items.append('  }')
    items.append('@endphp')
    for field in columns:
        old_choices=choices.get(field,[])
        li='am213-'+name+'-'+field.replace('_','-')
        items.append('<datalist id="'+li+'">')
        for choice in old_choices[:160]:
            items.append('  <option value="'+html.escape(choice,quote=True)+'"></option>')
        items.append('  @foreach($am213Stored['+php_quote(field)+'] as $am213Value)')
        items.append('    <option value="{{ $am213Value }}"></option>')
        items.append('  @endforeach')
        items.append('</datalist>')
    items.append('<p class="am213-master-hint" style="font-size:11px;color:#5b7084;margin:6px 0 12px">')
    items.append('  Category and other supported fields: type to search old/existing values, select a suggestion,')
    items.append('  or enter a new value. New Part/ROT uses the existing Admin Save and validation.')
    items.append('</p>')
    extra='\n'.join(items)+'\n'
    if src.count('@endsection')!=1:
        raise SystemExit('AM213_STOP_'+name.upper()+'_BLADE_SECTION_END_COUNT_'+str(src.count('@endsection')))
    src=src.replace('@endsection',extra+'@endsection',1)
    for field in fields:
        li='am213-'+name+'-'+field.replace('_','-')
        if src.count('list="'+li+'"')!=2 or src.count('id="'+li+'"')!=1:
            raise SystemExit('AM213_STOP_'+name.upper()+'_LIST_INTEGRITY_'+field)
    for token in ("route('erp.masters."+('parts' if name=='parts' else 'labour')+".create')","@csrf"):
        if token not in src:raise SystemExit('AM213_STOP_NATIVE_SAVE_FORM_MISSING_'+name)
    candidates[name]=src

# Compile modified Blade strings using Laravel's own compiler and PHP-lint
# the compiled output, before touching native views.
with tempfile.TemporaryDirectory(prefix='am213_check_') as work:
    directory=Path(work)
    for name in views:
        srcfile=directory/(name+'.blade.php')
        srcfile.write_text(candidates[name],encoding='utf8',errors='surrogateescape')
        compiler=directory/'compile.php'
        compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
        compiled=directory/(name+'.compiled.php')
        result=subprocess.run(['php',str(compiler),str(root),str(srcfile),str(compiled)],
                              cwd=root,capture_output=True,text=True,timeout=35)
        if result.returncode or not compiled.is_file():
            raise SystemExit('AM213_STOP_BLADE_COMPILE_'+name+' '+result.stderr[-450:])
        result=subprocess.run(['php','-l',str(compiled)],
                              cwd=root,capture_output=True,text=True,timeout=25)
        if result.returncode:
            raise SystemExit('AM213_STOP_COMPILED_BLADE_LINT_'+name+' '+(result.stdout+result.stderr)[-450:])
print('AM213_PART_ROT_OLD_DROPDOWN_PREFLIGHT_PASS')
print('AM213_CREATE_EDIT_POST_AND_CSRF_PRESERVED')
print('AM213_NO_DB_OR_LEGACY_ERP_WRITE')
if mode=='check':
    print('AM213_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if any((backup/(name+'.blade.php.before_AM213')).exists() for name in views) or manifest.exists():
    raise SystemExit('AM213_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True)
os.chmod(backup,0o700)
for name in views:
    target=backup/(name+'.blade.php.before_AM213')
    shutil.copy2(views[name],target)
    os.chmod(target,0o600)
try:
    for name in views:atomic(views[name],candidates[name])
    manifest.write_text(sha(candidates['parts'])+'\n'+sha(candidates['labour'])+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if result.returncode:raise RuntimeError('BLADE_CACHE '+result.stderr[-450:])
except BaseException as error:
    for name in views:atomic(views[name],old[name])
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=45)
    raise SystemExit('AM213_INSTALL_FAILURE_RESTORED '+str(error))
print('AM213_NATIVE_PART_ROT_DROPDOWNS_INSTALLED')
print('AM213_BROWSER_TEST_CREATE_EDIT_SELECT_NEW_CATEGORY_REQUIRED')
