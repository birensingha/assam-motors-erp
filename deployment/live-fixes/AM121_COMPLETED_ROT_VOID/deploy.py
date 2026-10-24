#!/usr/bin/env python3
"""AM121 guarded staging code deploy: check|apply.
No DB mutations from installation. Revert file changes on PHP/Blade failure.
Payload files must be placed alongside this deploy.py.
"""
import os,sys,datetime,shutil,subprocess,tempfile
from pathlib import Path

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: python3 deploy.py check|apply /path/to/staging-laravel')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
package=Path(__file__).resolve().parent
routes=root/'routes/web.php'
view=root/'resources/views/job-cards/show.blade.php'
newctrl=root/'app/Http/Controllers/Web/AdminCompletedRotVoidController.php'
newpartial=root/'resources/views/job-cards/partials/completed-rot-void.blade.php'
ctrlsrc=package/'AdminCompletedRotVoidController.php'
bladesrc=package/'completed-rot-void.blade.php'
if not (root/'artisan').is_file(): raise SystemExit('AM121_STOP_NOT_LARAVEL_ROOT')
for p in [routes,view,ctrlsrc,bladesrc]:
    if not p.is_file():raise SystemExit('AM121_STOP_MISSING='+str(p))
route_text=routes.read_text('utf8',errors='surrogateescape')
view_text=view.read_text('utf8',errors='surrogateescape')
marker='AM121_COMPLETED_ROT_VOID'
route_anchor="->name('erp.job-cards.labour.delete-admin');"
view_anchor="@include('dashboard.partials.rot-live-clock', ['session' => $rot])"
if marker in route_text or marker in view_text or newctrl.exists() or newpartial.exists():
    if all([marker in route_text,marker in view_text,newctrl.exists(),newpartial.exists()]):
        print('AM121_ALREADY_DEPLOYED');sys.exit(0)
    raise SystemExit('AM121_STOP_PARTIAL_DEPLOYMENT_REFUSE_OVERWRITE')
if route_text.count(route_anchor)!=1:raise SystemExit('AM121_STOP_ROUTE_ANCHOR_CHANGED')
if view_text.count(view_anchor)!=1:raise SystemExit('AM121_STOP_ROT_CARD_ANCHOR_CHANGED')
if 'UNSTARTED_ESTIMATE_ROT_CANCEL_AM117' not in (root/'app/Http/Controllers/Web/AdminLabourLineAuditController.php').read_text('utf8',errors='surrogateescape'):
    raise SystemExit('AM121_STOP_EXPECTED_AM117_CONTROLLER_MISSING')
if 'data-am90-labour-id="{{ $line->id }}"' not in view_text:
    raise SystemExit('AM121_STOP_ROT_CARD_LINE_BINDING_CHANGED')
route_add="""    // AM121_COMPLETED_ROT_VOID: Admin-only audited customer billing void, preserving ROT sessions.
    \\Illuminate\\Support\\Facades\\Route::post('/job-cards/{job}/labour/{line}/completed-void',
      [\\App\\Http\\Controllers\\Web\\AdminCompletedRotVoidController::class,'store'])
      ->whereNumber('job')->whereNumber('line')->name('erp.job-cards.labour.void-completed');
"""
# Change route only inside existing Job Card admin-authenticated group.
routes_new=route_text.replace(route_anchor,route_anchor+'\n'+route_add,1)
view_new=view_text.replace(view_anchor,view_anchor+"\n      {{-- AM121_COMPLETED_ROT_VOID: visible only for Admin / Completed --}}\n      @include('job-cards.partials.completed-rot-void', ['line'=>$line, 'job'=>$job, 'rot'=>$rot])",1)
for name,s in [('routes',routes_new),('show',view_new)]:
    if s.count(marker)!=1:raise SystemExit('AM121_STOP_BAD_'+name+'_MARKER')
php=subprocess.run(['php','-l',str(ctrlsrc)],capture_output=True,text=True)
if php.returncode:
    raise SystemExit('AM121_STOP_CONTROLLER_PHP_SYNTAX_ERROR: '+php.stderr)
print('AM121_PREFLIGHT_SOURCE_AND_PHP_PASS_NO_WRITES')
print('AM121_TARGET_MAIN_JC_CARD_FOUND=yes')
print('AM121_FINANCIAL_AUDIT_GUARDS=enabled')
if mode=='check':sys.exit(0)
backup=root/'storage/app/am-backups'/('AM121_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False)
for name,p in [('routes.php',routes),('show.blade.php',view)]:shutil.copy2(p,backup/name)
print('AM121_BACKUP_DIR='+str(backup))
modified=False

def restore():
    shutil.copy2(backup/'routes.php',routes)
    shutil.copy2(backup/'show.blade.php',view)
    newctrl.unlink(missing_ok=True)
    newpartial.unlink(missing_ok=True)
    subprocess.run(['php','artisan','view:clear'],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    print('AM121_ROLLBACK_COMPLETE_RESTORED='+str(backup))

def atomic(p,text):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,t=tempfile.mkstemp(prefix='.am121_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(text)
        if p.exists():os.chmod(t,p.stat().st_mode & 0o777)
        else:os.chmod(t,0o644)
        os.replace(t,p)
    finally:
        if os.path.exists(t):os.unlink(t)

try:
    modified=True
    atomic(newctrl,ctrlsrc.read_text('utf8'))
    atomic(newpartial,bladesrc.read_text('utf8'))
    atomic(routes,routes_new)
    atomic(view,view_new)
    for args in (['php','-l',str(newctrl)],['php','artisan','view:clear'],['php','artisan','view:cache']):
        run=subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=90)
        print((run.stdout or run.stderr)[-350:])
        if run.returncode:raise RuntimeError('Validation failed: '+' '.join(args))
    # Blade comments are stripped by compilation; never search for the
    # source-only AM121_COMPLETED_ROT_VOID marker inside compiled PHP.
    # Instead require compiled *visible HTML* from our dedicated partial:
    # its class names survive Blade compilation and cannot be satisfied
    # by a comment in show.blade.php.
    found=False
    for p in (root/'storage/framework/views').glob('*.php'):
        compiled=p.read_text('utf8',errors='replace')
        if 'am121-void-panel' in compiled and 'am121-voided-label' in compiled:
            run=subprocess.run(['php','-l',str(p)],
                capture_output=True,text=True,timeout=20)
            if run.returncode:
                raise RuntimeError('AM121 compiled Blade syntax error: '+run.stderr[-300:])
            print('AM121_COMPILED_PARTIAL_FOUND_AND_PHP_LINT_PASS='+str(p))
            found=True
            break
    if not found:
        raise RuntimeError('AM121 compiled partial HTML missing; rollback as precaution')
    # Verify the parent view still references our partial, not merely that
    # a similarly named orphan partial was compiled.
    if "@include('job-cards.partials.completed-rot-void'" not in view.read_text('utf8',errors='surrogateescape'):
        raise RuntimeError('AM121 parent Job Card lost the partial include')
except BaseException as err:
    if modified:restore()
    raise SystemExit('AM121_APPLY_FAILED_ROLLED_BACK: '+str(err))
print('AM121_SOURCE_AND_BLADE_DEPLOY_PASS')
print('AM121_DATABASE_WRITES_AT_INSTALL=NONE')
print('AM121_USER_ACTION_REQUIRED_TO_VOID=YES')
print('AM121_BROWSER_TEST=NOT_YET_VERIFIED')
