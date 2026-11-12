#!/usr/bin/env python3
"""AM251 guarded Admin Login standalone-centering update. No auth flow, route or DB modification."""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM251_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM251_NOT_STAGING')
rel='resources/views/auth/login.blade.php'
target=root/rel
pkg=Path(__file__).resolve().parent/'login.blade.php'
backup_base=root/'storage/app/am-backups'
pointer=backup_base/'AM251_LOGIN_CENTER_LAST_BACKUP.txt'
marker='AM251_LOGIN_STANDALONE_CENTER'

def sha(blob):return hashlib.sha256(blob).hexdigest()
def run(cmd,timeout=90):
    return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(p,blob):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am251_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(blob)
        os.chmod(tmp,(p.stat().st_mode & 0o777) if p.is_file() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint_blade(blob):
    with tempfile.TemporaryDirectory(prefix='am251blade_') as t:
        a=Path(t)/'src.blade.php';b=Path(t)/'compiled.php'
        a.write_bytes(blob)
        compile=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        c=run(['php','-r',compile,str(a),str(b)],45)
        if c.returncode or not b.is_file():raise SystemExit('STOP_AM251_BLADE_COMPILE '+(c.stderr+c.stdout)[-400:])
        l=run(['php','-l',str(b)],30)
        if l.returncode:raise SystemExit('STOP_AM251_COMPILED_BLADE_PHP '+(l.stdout+l.stderr)[-400:])
def route_check():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r=\Illuminate\Support\Facades\Route::getRoutes();
$a=$r->getByName('erp.login');
$b=$r->getByName('erp.login.submit');
if(!$a||!$b||!in_array('GET',$a->methods())||!in_array('POST',$b->methods()))exit(32);
if(!str_contains($b->getActionName(),'WebAuthController@login'))exit(33);
echo 'AM251_EXISTING_AUTH_POST_ROUTE_UNCHANGED';"""
    x=run(['php','-r',php],40)
    if x.returncode or 'AM251_EXISTING_AUTH_POST_ROUTE_UNCHANGED' not in x.stdout:
        raise SystemExit('STOP_AM251_LOGIN_ROUTE '+(x.stdout+x.stderr)[-400:])
    print('AM251_EXISTING_AUTH_POST_ROUTE_UNCHANGED')
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM251_ROLLBACK_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backup_base.resolve() not in backup.parents or not backup.name.startswith('AM251_ADMIN_CENTER_'):
        raise SystemExit('STOP_AM251_BACKUP_PATH')
    manifest=json.loads((backup/'manifest.json').read_text())
    if not target.is_file() or sha(target.read_bytes())!=manifest['installed_sha']:
        raise SystemExit('STOP_AM251_SOURCE_CHANGED_SINCE_INSTALL')
    atomic(target,(backup/'login.blade.php').read_bytes())
    clear=run(['php','artisan','view:clear'],60)
    if clear.returncode:raise SystemExit('AM251_ROLLBACK_VIEW_RESTORED_CACHE_CLEAR_FAILED')
    print('AM251_PREVIOUS_LOGIN_VIEW_RESTORED')
    sys.exit(0)
if not target.is_file() or not pkg.is_file():
    raise SystemExit('STOP_AM251_LOGIN_SOURCE_MISSING')
old=target.read_bytes()
new=pkg.read_bytes()
if marker in old.decode('utf8',errors='replace'):
    print('AM251_LOGIN_ALREADY_INSTALLED')
    sys.exit(0)
oldtxt=old.decode('utf8',errors='replace')
newtxt=new.decode('utf8')
required_old=["@extends('layouts.erp')", "route('erp.login.submit')", 'name="email"', 'name="password"', '@csrf']
required_new=['AM251_LOGIN_STANDALONE_CENTER', 'place-items: center', "route('erp.login.submit')", 'name="email"', 'name="password"', '@csrf', '$errors->any()']
if not all(item in oldtxt for item in required_old):
    raise SystemExit('STOP_AM251_UNEXPECTED_LOGIN_TEMPLATE')
if not all(item in newtxt for item in required_new):
    raise SystemExit('STOP_AM251_NEW_VIEW_CONTRACT')
if '@extends(' in newtxt or '@include(' in newtxt or '@stack(' in newtxt:
    raise SystemExit('STOP_AM251_STANDALONE_VIEW_HAS_PARENT_LAYOUT')
lint_blade(old)
lint_blade(new)
route_check()
print('AM251_BLADE_AND_AUTH_CONTRACT_PASS')
if mode=='check':
    print('AM251_READ_ONLY_LOGIN_PREFLIGHT_PASS')
    sys.exit(0)
backup_base.mkdir(parents=True,exist_ok=True)
backup=backup_base/('AM251_ADMIN_CENTER_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(mode=0o700,exist_ok=False)
(backup/'login.blade.php').write_bytes(old)
try:
    if target.read_bytes()!=old:raise RuntimeError('SOURCE_CHANGED_BEFORE_WRITE')
    atomic(target,new)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=run(cmd,100)
        if r.returncode:raise RuntimeError('VIEW_CACHE '+(r.stdout+r.stderr)[-400:])
    route_check()
except BaseException as ex:
    atomic(target,old)
    run(['php','artisan','view:clear'],70)
    raise SystemExit('STOP_AM251_RESTORED_OLD_LOGIN '+str(ex))
(backup/'manifest.json').write_text(json.dumps({'installed_sha':sha(new)},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM251_ADMIN_LOGIN_STANDALONE_CENTER_INSTALLED')
print('AM251_LOGIN_AUTHENTICATION_AND_STAFF_VIEW_UNCHANGED')
print('AM251_ROLLBACK_BACKUP='+str(backup))
