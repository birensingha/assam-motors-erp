#!/usr/bin/env python3
"""AM254 sitewide safe browser-local entry draft backup for all eligible forms.
check|apply|verify|rollback STAGING_ROOT.
Never modifies business database or auto-submits forms.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,re,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
    raise SystemExit('AM254_USAGE check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM254_TARGET_NOT_STAGING')
asset='public/js/am254-global-form-autosave.js'
source=pkg/'files'/asset
if not source.is_file():raise SystemExit('STOP_AM254_SCRIPT_PAYLOAD_MISSING')
backups=root/'storage/app/am-backups'
pointer=backups/'AM254_LAST_BACKUP.txt'
mark='AM254_GLOBAL_FORM_AUTOSAVE'
script='''{{-- AM254_GLOBAL_FORM_AUTOSAVE: browser-only drafts, never business transaction submit. --}}
<script id="am254-global-draft-script"
 data-user="{{ auth()->id() ?: auth('staff_web')->id() }}"
 data-guard="{{ auth()->check() ? 'erp' : 'staff' }}"
 data-success="{{ session()->has('success') ? '1' : '0' }}">{!! file_get_contents(public_path('js/am254-global-form-autosave.js')) !!}</script>
'''
layout_names=['resources/views/layouts/erp.blade.php','resources/views/layouts/new-admin.blade.php']
excluded=re.compile(r'(?:login|sign-in|password|reset|otp|two-factor)',re.I)
standalone_dirs=['staff','hr','work-complaints','inspection-defects','job-cards','admin','purchases','payments','money-receipts','vendors','expenses','dashboard','workshop-controls']
def get_files():
    result=list(layout_names)
    views=root/'resources/views'
    for folder in standalone_dirs:
        base=views/folder
        if not base.exists():continue
        for p in sorted(base.rglob('*.blade.php')):
            rel=p.relative_to(root).as_posix()
            if excluded.search(p.name):continue
            s=p.read_text('utf8','replace')
            if len(s)>750000:continue
            if '@extends(' in s or '@extends (' in s:continue
            if not re.search(r'<(?:!doctype\s+html|html\b)',s[:280],re.I):continue
            if not re.search(r'<form\b',s,re.I):continue
            if not re.search(r'</body\s*>',s,re.I):continue
            result.append(rel)
    return result
def sha(b):return hashlib.sha256(b).hexdigest()
def cmd(args,timeout=100):
    return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(dest,b):
    dest.parent.mkdir(parents=True,exist_ok=True)
    fd,name=tempfile.mkstemp(prefix='.am254_',dir=dest.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(name,(dest.stat().st_mode&0o777) if dest.exists() else 0o644)
        os.replace(name,dest)
    finally:
        if os.path.exists(name):os.unlink(name)
def lint_php(b,label):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as f:
        f.write(b);p=Path(f.name)
    try:
        o=cmd(['php','-l',str(p)],30)
        if o.returncode:raise SystemExit('STOP_AM254_PHP_LINT_'+label+' '+(o.stdout+o.stderr)[-650:])
    finally:p.unlink(missing_ok=True)
def blade_lint(b,label):
    with tempfile.TemporaryDirectory(prefix='am254_blade_') as d:
        path=Path(d)/'file.blade.php';dest=Path(d)/'compiled.php'
        path.write_bytes(b)
        code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        o=cmd(['php','-r',code,str(path),str(dest)],45)
        if o.returncode or not dest.exists():
            raise SystemExit('STOP_AM254_BLADE_COMPILE_'+label+' '+(o.stdout+o.stderr)[-750:])
        lint_php(dest.read_bytes(),label+'_COMPILED')
def verify():
    files=get_files()
    for rel in layout_names:
        if (root/rel).read_text('utf8','replace').count(mark)!=1:
            raise SystemExit('STOP_AM254_LAYOUT_SCRIPT_MISSING_'+rel)
    if not (root/asset).is_file() or mark not in (root/asset).read_text('utf8','replace'):
        raise SystemExit('STOP_AM254_ASSET_MISSING')
    for rel in files:
        if (root/rel).read_text('utf8','replace').count(mark)!=1:
            raise SystemExit('STOP_AM254_SCRIPT_MISSING_'+rel)
    # Important: all injected script tags must use the same private authenticated
    # guard; login pages and credential input forms have JS eligibility exclusions.
    assert "auth('staff_web')->id()" in script and "auth()->id()" in script
    print('AM254_ERP_NEW_ADMIN_AND_STANDALONE_FORMS_SCRIPT_VERIFIED',len(files))
    print('AM254_NO_DATABASE_OR_FINANCIAL_RECORD_CHANGE')
def patch():
    files=get_files()
    before={}
    after={}
    for rel in files:
        p=root/rel
        if not p.is_file():raise SystemExit('STOP_AM254_MISSING_VIEW_'+rel)
        b=p.read_bytes();s=b.decode('utf8','surrogateescape')
        if mark in s:raise SystemExit('STOP_AM254_ALREADY_PATCHED_'+rel)
        matches=list(re.finditer(r'</body\s*>',s,re.I))
        if len(matches)!=1:raise SystemExit('STOP_AM254_BODY_END_COUNT_'+rel+'_'+str(len(matches)))
        i=matches[0].start()
        updated=(s[:i]+script+s[i:]).encode('utf8','surrogateescape')
        before[rel]=b;after[rel]=updated
        blade_lint(updated,rel.replace('/','_'))
    js=source.read_bytes()
    if b'AM254_GLOBAL_FORM_AUTOSAVE' not in js:
        raise SystemExit('STOP_AM254_SOURCE_MARKER_MISSING')
    if b'</script>' in js.lower():
        raise SystemExit('STOP_AM254_INLINE_SCRIPT_CLOSE_TAG_UNSAFE')
    print('AM254_BLADE_ALL_TARGETS_PHP_LINT_PASS')
    print('AM254_ELIGIBLE_FORM_VIEW_COUNT',len(files))
    for rel in files:print('AM254_TARGET_VIEW',rel)
    print('AM254_PRECHECK_SOURCE_ONLY_NO_DB_WRITES')
    return before,after

if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM254_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM254_GLOBAL_DRAFT_'):
        raise SystemExit('STOP_AM254_BACKUP_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel,expected in manifest['installed'].items():
        p=root/rel
        if not p.is_file() or sha(p.read_bytes())!=expected:
            raise SystemExit('STOP_AM254_FILES_CHANGED_REFUSE_ROLLBACK_'+rel)
    for rel in manifest['views']:
        atomic(root/rel,(backup/rel).read_bytes())
    (root/asset).unlink(missing_ok=True)
    cmd(['php','artisan','view:clear'],80)
    print('AM254_ROLLBACK_SOURCE_ONLY_USER_DATA_UNCHANGED')
    sys.exit(0)

before,after=patch()
if mode=='check':
    print('AM254_CHECK_READ_ONLY_PASS')
    sys.exit(0)
if (root/asset).exists():raise SystemExit('STOP_AM254_ASSET_ALREADY_PRESENT')
backup=backups/('AM254_GLOBAL_DRAFT_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for rel,original in before.items():
    p=backup/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(original);os.chmod(p,0o600)
written=[]
after[asset]=source.read_bytes()
try:
    for rel,blob in after.items():
        if rel in before and (root/rel).read_bytes()!=before[rel]:
            raise RuntimeError('AM254_VIEW_CHANGED_'+rel)
        atomic(root/rel,blob);written.append(rel)
    for args in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        o=cmd(args,140)
        if o.returncode:raise RuntimeError('AM254_LARAVEL_VIEW_CACHE '+(o.stdout+o.stderr)[-1000:])
    verify()
except Exception as exc:
    for rel in written:
        if rel in before:atomic(root/rel,before[rel])
        else:(root/rel).unlink(missing_ok=True)
    cmd(['php','artisan','view:clear'],100)
    raise SystemExit('STOP_AM254_APPLY_RESTORED_ALL_VIEWS_'+str(exc))
(backup/'manifest.json').write_text(json.dumps({
    'views':list(before),
    'installed':{rel:sha(b) for rel,b in after.items()},
},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM254_GLOBAL_BROWSER_DRAFT_AUTOSAVE_INSTALLED')
print('AM254_PRIVATE_LAYOUT_BACKUP',backup)
print('AM254_CLIENT_BROWSER_TEST_PENDING')
