#!/usr/bin/env python3
"""AM247: Change only public Admin/Staff login hrefs to verified STAGING ERP routes.
Usage: python3 site_links.py check|apply|rollback STAGING_ROOT PUBLIC_HTML_ROOT
"""
from pathlib import Path
from datetime import datetime
import hashlib,html,json,os,re,subprocess,sys,tempfile,urllib.parse

if len(sys.argv)!=4 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM247_USAGE check|apply|rollback STAGING_ROOT PUBLIC_HTML_ROOT')
mode=sys.argv[1]
stage=Path(sys.argv[2]).expanduser().resolve()
site=Path(sys.argv[3]).expanduser().resolve()
if stage.name!='assam-erp-staging' or not (stage/'artisan').is_file():
    raise SystemExit('STOP_AM247_BAD_STAGING_ROOT')
if site.name!='public_html' or not any((site/n).is_file() for n in ('index.php','index.html','index.htm')):
    raise SystemExit('STOP_AM247_BAD_PUBLIC_WEBSITE_ROOT')
if stage==site or stage in site.parents or site in stage.parents:
    raise SystemExit('STOP_AM247_STAGING_SITE_PATH_OVERLAP')
if (site/'wp-config.php').is_file() or (site/'wp-load.php').is_file():
    raise SystemExit('STOP_AM247_WORDPRESS_MENU_EDIT_REQUIRED')
backups=stage/'storage/app/am-backups'
pointer=backups/'AM247_PUBLIC_SITE_LAST_BACKUP.txt'
anchor_re=re.compile(r'<a\b(?P<attrs>[^>]{0,1500})>(?P<body>[\s\S]{0,500}?)</a\s*>',re.I)
href_re=re.compile(r'\bhref\s*=\s*(?P<q>["\'])(?P<url>[^"\']*)(?P=q)',re.I)
labels={'admin':{'admin login','login admin','admin portal','admin panel login'},
        'staff':{'staff login','login staff','staff portal','staff portal login','employee login'}}
skip={'.git','vendor','node_modules','storage','wp-admin','wp-content','wp-includes',
      'assets','css','js','images','img','fonts','uploads','backups','backup','.cache'}

def digest(data):return hashlib.sha256(data).hexdigest()
def shell(args,cwd,timeout=50):
    return subprocess.run(args,cwd=cwd,capture_output=True,text=True,timeout=timeout)
def atomic(path,data):
    fd,tmp=tempfile.mkstemp(prefix='.am247_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,path.stat().st_mode&0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(path,data):
    if path.suffix.lower()!='.php':return
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as f:
        f.write(data);test=Path(f.name)
    try:
        r=shell(['php','-l',str(test)],site,30)
        if r.returncode:raise SystemExit('STOP_AM247_PHP_SYNTAX_'+path.name+' '+(r.stdout+r.stderr)[-500:])
    finally:test.unlink(missing_ok=True)

def get_targets():
    probe=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$out=[];
foreach(\Illuminate\Support\Facades\Route::getRoutes() as $r){
 if(!in_array('GET',$r->methods(),true))continue;
 $name=(string)($r->getName()??'');$uri=(string)$r->uri();
 if(str_contains($uri,'{') || !preg_match('/login|staff[.\/-]?(portal|dashboard)/i',$name.' '.$uri))continue;
 try{$url=$name!==''?route($name,[],true):url($uri);}catch(\Throwable $e){continue;}
 $out[]=['name'=>$name,'uri'=>$uri,'url'=>$url];
}
echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);"""
    r=shell(['php','-r',probe],stage,60)
    if r.returncode:raise SystemExit('STOP_AM247_STAGING_ROUTE_LOOKUP_FAILED '+r.stderr[-450:])
    try:rows=json.loads(r.stdout.strip())
    except Exception:raise SystemExit('STOP_AM247_BAD_STAGING_ROUTE_JSON')
    allowed=[]
    for row in rows:
        u=urllib.parse.urlsplit(row['url'])
        if u.scheme!='https' or u.hostname!='staging.assammotors.com' or not u.path.startswith('/erp/'):continue
        if u.username or u.password or u.query or u.fragment:continue
        if any(x in u.path.lower() for x in ('logout','password','register','api/')):continue
        allowed.append(row)
    print('AM247_STAGING_ROUTE_CANDIDATES',json.dumps(allowed)[:2600])
    def score(row,role):
        name=row['name'].lower()
        path=urllib.parse.urlsplit(row['url']).path.lower().rstrip('/')
        if role=='admin':
            if path=='/erp/admin/login':return 110
            if path=='/erp/login':return 100
            if name in ('erp.login','erp.auth.login','erp.admin.login'):return 90
            return -1
        if path in ('/erp/staff/login','/erp/staff-portal/login'):return 110
        if 'staff' in name and 'login' in name:return 105
        if path in ('/erp/staff/portal','/erp/staff/dashboard'):return 95
        if name in ('staff.portal','erp.staff.portal','staff.dashboard'):return 90
        return -1
    result={}
    for role in ('admin','staff'):
        ranked=sorted(((score(x,role),x) for x in allowed),key=lambda t:-t[0])
        top=[v for n,v in ranked if n>0]
        if not top:raise SystemExit('STOP_AM247_'+role.upper()+'_STAGING_LOGIN_ROUTE_UNKNOWN')
        best=score(top[0],role)
        possibilities={x['url'] for x in top if score(x,role)==best}
        if len(possibilities)!=1:raise SystemExit('STOP_AM247_'+role.upper()+'_LOGIN_ROUTE_AMBIGUOUS')
        result[role]=next(iter(possibilities))
        print('AM247_'+role.upper()+'_STAGING_DESTINATION',result[role])
    return result

def find_links():
    found={'admin':[],'staff':[]}
    files=0
    for directory,dirs,names in os.walk(site,followlinks=False):
        folder=Path(directory);depth=len(folder.relative_to(site).parts)
        dirs[:]=[d for d in dirs if d not in skip and depth<4 and not (folder/d).is_symlink()]
        for name in names:
            p=folder/name
            if p.suffix.lower() not in ('.php','.html','.htm') or p.is_symlink():continue
            if p.stat().st_size>800000 or any(t in name.lower() for t in ('config.php','login.php','setup.php','install.php','wp-','test_')):continue
            files+=1
            if files>450:raise SystemExit('STOP_AM247_TOO_MANY_SITE_FILES')
            raw=p.read_bytes()
            if b'<a' not in raw.lower():continue
            src=raw.decode('utf8','surrogateescape')
            for m in anchor_re.finditer(src):
                href=href_re.search(m.group('attrs'))
                if not href:continue
                label=html.unescape(re.sub(r'<[^>]+>',' ',m.group('body')))
                label=re.sub(r'\s+',' ',label).lower().strip()
                aria=re.search(r'\baria-label\s*=\s*["\']([^"\']+)',m.group('attrs'),re.I)
                nameset={label}
                if aria:nameset.add(re.sub(r'\s+',' ',aria.group(1)).strip().lower())
                role=next((key for key in ('admin','staff') if nameset&labels[key]),None)
                if role is None:continue
                old=href.group('url').strip()
                if not old or len(old)>400 or any(x in old for x in ('<?','{{','\\')):
                    raise SystemExit('STOP_AM247_DYNAMIC_LOGIN_BUTTON_'+str(p.relative_to(site)))
                found[role].append((p,m.start(),m.end(),m.group(0),old,label))
    return found

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM247_NO_BACKUP')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM247_PUBLIC_LOGIN_'):
        raise SystemExit('STOP_AM247_INVALID_BACKUP_PATH')
    record=backup/'manifest.json'
    if not record.is_file():raise SystemExit('STOP_AM247_NO_MANIFEST')
    entries=json.loads(record.read_text())['files']
    for row in entries:
        target=(site/row['relative']).resolve()
        if site not in target.parents or digest(target.read_bytes())!=row['installed_sha']:
            raise SystemExit('STOP_AM247_SOURCE_CHANGED_REFUSE_ROLLBACK_'+row['relative'])
    for row in entries:atomic(site/row['relative'],(backup/row['relative']).read_bytes())
    print('AM247_PUBLIC_LOGIN_LINKS_ROLLED_BACK')
    raise SystemExit(0)

targets=get_targets()
links=find_links()
for role in ('admin','staff'):
    print('AM247_'+role.upper()+'_BUTTONS_FOUND',len(links[role]))
    for p,start,end,anchor,href,label in links[role][:8]:
        print('AM247_CHANGE',str(p.relative_to(site)),label,href,'=>',targets[role])
    if not links[role]:raise SystemExit('STOP_AM247_'+role.upper()+'_BUTTON_NOT_FOUND')
    if len(links[role])>8:raise SystemExit('STOP_AM247_TOO_MANY_'+role.upper()+'_BUTTONS')
source_files={row[0] for role in links for row in links[role]}
if len(source_files)>5:raise SystemExit('STOP_AM247_TOO_MANY_SITE_SOURCE_FILES')
old_files={};patched={}
for file in source_files:
    old=file.read_bytes()
    source=old.decode('utf8','surrogateescape')
    entries=sorted(((start,end,role,anchor) for role in links for p,start,end,anchor,href,label in links[role] if p==file),reverse=True)
    for start,end,role,anchor in entries:
        if source[start:end]!=anchor:raise SystemExit('STOP_AM247_SITE_SOURCE_CHANGED')
        new=href_re.sub(lambda m:m.group(0).replace(m.group('url'),targets[role],1),anchor,count=1)
        source=source[:start]+new+source[end:]
    new=source.encode('utf8','surrogateescape')
    if new==old:continue
    lint(file,new)
    old_files[file]=old;patched[file]=new
print('AM247_SITE_PHP_PREFLIGHT_PASS')
print('AM247_NO_DATABASE_OR_STAGING_FILE_MODIFICATIONS')
if not patched:
    print('AM247_BOTH_PUBLIC_LOGIN_LINKS_ALREADY_CORRECT')
    raise SystemExit(0)
print('AM247_PREFLIGHT_PASS')
if mode=='check':
    print('AM247_CHECK_READ_ONLY')
    raise SystemExit(0)

backup=backups/('AM247_PUBLIC_LOGIN_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False)
os.chmod(backup,0o700)
manifest=[];written=[]
for p in patched:
    rel=p.relative_to(site);snap=backup/rel;snap.parent.mkdir(parents=True,exist_ok=True)
    snap.write_bytes(old_files[p]);os.chmod(snap,0o600)
    manifest.append({'relative':str(rel),'installed_sha':digest(patched[p])})
try:
    for p,new in patched.items():
        if p.read_bytes()!=old_files[p]:raise RuntimeError('SITE_FILE_CHANGED_'+str(p.relative_to(site)))
        atomic(p,new);written.append(p)
except Exception as e:
    for p in written:atomic(p,old_files[p])
    raise SystemExit('STOP_AM247_APPLY_RESTORED_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'files':manifest},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM247_ADMIN_STAFF_PUBLIC_LINKS_POINT_TO_STAGING_INSTALLED')
print('BACKUP',backup)
print('AM247_BROWSER_BUTTON_TEST_REQUIRED')
