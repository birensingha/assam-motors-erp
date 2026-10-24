#!/usr/bin/env python3
"""AM128 - correct Main JC P55 completed mechanic binding without changing DB.
Source evidence: ROT-DNT-001 labour mechanic_id=3 and session staff_id=3, while
AM123 partial called P55 without p55Staff (thus select had only Not Assigned).
Modes: check|apply. STAGING ONLY, backup-first, guarded anchor and rollback.
"""
import datetime,os,shutil,subprocess,sys,tempfile
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: python3 apply.py check|apply /path/to/staging')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
target=root/'resources/views/job-cards/partials/am123-unified-correction.blade.php'
p55=root/'resources/views/job-cards/rot-alteration.blade.php'
if not(root/'artisan').is_file() or not target.is_file() or not p55.is_file():
    raise SystemExit('AM128_STOP_REQUIRED_STAGING_FILE_MISSING')
src=target.read_text('utf8',errors='surrogateescape')
p55source=p55.read_text('utf8',errors='surrogateescape')
marker='AM128_P55_MECHANIC_STAFF_BINDING'
if marker in src:
    print('AM128_ALREADY_APPLIED_NO_WRITES');sys.exit(0)
anchor="""      try {
          echo $__env->make('job-cards.rot-alteration', [
              'p55Rows'=>$am123Rows,
"""
if src.count(anchor)!=1:
    raise SystemExit('AM128_STOP_AM123_P55_INCLUDE_ANCHOR_CHANGED')
for name,ok in {
    'P55_STAFF_SOURCE':"($p55Staff??collect())" in p55source,
    'P55_STAFF_OPTION':'@selected((int)$line->mechanic_id===(int)$person->id)' in p55source,
    'P55_MECHANIC_POST':'name="mechanic_id"' in p55source,
    'P55_SESSION_SOURCE':'p55Sessions' in p55source,
    'MAIN_JC_AM123':'AM123_UNIFIED_ROT_WORKSPACE' in src,
    'MAIN_JC_ADMIN_GUARD':'$am123Admin && $am123Rows->isNotEmpty()' in src,
}.items():
    print('AM128_PREFLIGHT_'+name+'='+('PASS' if ok else 'FAIL'))
    if not ok:raise SystemExit('AM128_STOP_CHANGED_SOURCE_NO_WRITES')

replace="""      try {
          // AM128_P55_MECHANIC_STAFF_BINDING:
          // The Main JC AM123 include previously forgot p55Staff; that left
          // the P55 mechanic dropdown with ONLY "Not Assigned", even though
          // job_card_labour.mechanic_id and rot_sessions.staff_id were correct.
          // Populate legitimate ACTIVE staff, and always include the existing
          // assigned staff ID so a historical completion remains visible.
          // All queries below are SELECT only; neither record is rewritten.
          $am128ExistingIds = $am123Rows->pluck('mechanic_id')
              ->filter(fn($v)=>(int)$v>0)->map(fn($v)=>(int)$v)->unique()->values()->all();
          if (!\\Illuminate\\Support\\Facades\\Schema::hasTable('staff')
              || !\\Illuminate\\Support\\Facades\\Schema::hasTable('rot_sessions')
              || !\\Illuminate\\Support\\Facades\\Schema::hasColumn('staff','status')
              || !\\Illuminate\\Support\\Facades\\Schema::hasColumn('staff','staff_code')) {
              throw new \\RuntimeException('AM128 staff/ROT session schema mismatch');
          }
          $am128Staff = \\Illuminate\\Support\\Facades\\DB::table('staff')
              ->where(function($q) use ($am128ExistingIds) {
                  $q->whereRaw("LOWER(TRIM(COALESCE(status,'active')))='active'");
                  if (count($am128ExistingIds)) $q->orWhereIn('id',$am128ExistingIds);
              })->orderBy('name')->get(['id','name','staff_code']);
          $am128Sessions = \\Illuminate\\Support\\Facades\\DB::table('rot_sessions')
              ->where('job_card_id',$job->id)->get();
          echo $__env->make('job-cards.rot-alteration', [
              'p55Rows'=>$am123Rows,
              'p55Staff'=>$am128Staff,
              'p55Sessions'=>$am128Sessions,
"""
candidate=src.replace(anchor,replace,1)
if candidate.count(marker)!=1 or candidate.count("'p55Staff'=>$am128Staff")!=1:
    raise SystemExit('AM128_STOP_INVALID_CANDIDATE')
if mode=='check':
    print('AM128_SOURCE_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)

backup=root/'storage/app/am-backups'/('AM128_am123_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid())+'.blade.php')
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(target,backup)
print('AM128_BACKUP='+str(backup))
def rollback(reason):
    shutil.copy2(backup,target)
    subprocess.run(['php','artisan','view:clear'],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    raise SystemExit('AM128_ROLLBACK_DONE='+str(reason))
try:
    fd,tmp=tempfile.mkstemp(prefix='.am128_',dir=target.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(candidate)
        os.chmod(tmp,target.stat().st_mode&0o777)
        os.replace(tmp,target)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    for command in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        result=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=100)
        print((result.stdout or result.stderr)[-240:])
        if result.returncode:raise RuntimeError('Laravel Blade compile failed')
    compiled=[]
    for file in (root/'storage/framework/views').glob('*.php'):
        data=file.read_text('utf8',errors='replace')
        if "AM128_P55_MECHANIC_STAFF_BINDING" in data and "'p55Staff'=>$am128Staff" in data:
            result=subprocess.run(['php','-l',str(file)],cwd=root,capture_output=True,text=True,timeout=25)
            if result.returncode:raise RuntimeError('AM128 compiled PHP syntax error')
            compiled.append(str(file))
    if len(compiled)!=1:raise RuntimeError('AM128 expected exactly 1 compiled partial: '+str(len(compiled)))
    print('AM128_COMPILED_P55_STAFF_BINDING_PASS='+compiled[0])
except BaseException as ex:
    rollback(ex)

print('AM128_STAGING_VIEW_DEPLOY_PASS')
print('AM128_DB_WRITE_COUNT=0')
print('AM128_CONTROLLER_ROUTE_INVOICE_UNCHANGED')
print('AM128_BROWSER_MECHANIC_PRESELECT_NOT_YET_TESTED')
