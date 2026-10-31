#!/usr/bin/env python3
"""AM225 STAGING Staff Portal layout and alert navigation parity.
Only modifies Staff Portal Blade. DOES NOT update reminders, attendance,
notification permissions, ROT assignments, APIs or Android.
check|apply|rollback STAGING_ROOT (rollback only if installed view unchanged).
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM225_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM225_STOP_NOT_STAGING')
view=root/'resources/views/staff/portal.blade.php'
layout=pkg/'layout.blade.php'
group=pkg/'panel.blade.php'
backup=root/'storage/app/am-backups/AM225_STAFF_PORTAL_COMPACT_ALERTS'
original_file=backup/'staff.portal.blade.php.before_AM225'
manifest=backup/'installed-sha256.txt'
marker='AM225_STAFF_PORTAL_COMPACT_ALERTS'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am225_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if any(not p.is_file() for p in [view,layout,group]):
    raise SystemExit('AM225_STOP_VIEW_OR_PATCH_MISSING')
old=read(view)
if mode=='rollback':
    if not original_file.is_file() or not manifest.is_file():
        raise SystemExit('AM225_STOP_ROLLBACK_BACKUPS_MISSING')
    if read(manifest).strip()!=sha(old):
        raise SystemExit('AM225_STOP_ROLLBACK_VIEW_CHANGED')
    atomic(view,read(original_file))
    print('AM225_ROLLBACK_RESTORED_STAFF_PORTAL_BLADE')
    sys.exit(0)
if marker in old:
    if 'am225-alert-groups' in old and 'am225-rot-panel' in old:
        print('AM225_ALREADY_INSTALLED')
        sys.exit(0)
    raise SystemExit('AM225_STOP_PARTIAL_INSTALL')
required=[
    '<main class="staff-shell">',
    'class="staff-cards"',
    '<section class="staff-panel"><div class="panel-head"><h2>🕒 Attendance</h2>',
    '<section class="staff-panel"><div class="panel-head"><h2>🔔 Mandatory Notifications</h2>',
    '<section class="staff-panel"><div class="panel-head"><h2>🔧 My ROT Work</h2>',
    '<section class="staff-panel inspection-review-panel">',
    '<section class="staff-panel" style="border-left:5px solid #157c60">',
    'id="rot-{{ $r[\'id\'] }}"',
    'href="#rot-{{ $r[\'target_id\'] }}"',
    'id="webCheckIn"','id="webCheckOut"'
]
for token in required:
    if old.count(token)!=1:
        raise SystemExit('AM225_STOP_EXPECTED_SOURCE_ANCHOR_'+token[:55]+'_'+str(old.count(token)))
new=old
# The NOTIFICATIONS SECTION has no nested sections, and is replaced once.
pattern=re.compile(
    r'<section class="staff-panel"><div class="panel-head"><h2>🔔 Mandatory Notifications</h2>'
    r'[\s\S]*?</section>'
)
matches=list(pattern.finditer(new))
if len(matches)!=1:raise SystemExit('AM225_STOP_NOTIFICATIONS_SECTION_NOT_UNIQUE')
new=new[:matches[0].start()]+read(group)+new[matches[0].end():]
# Keep authenticated old work, attendance, QC and inspection POST routes unchanged.
new=new.replace(
    '<section class="staff-panel"><div class="panel-head"><h2>🕒 Attendance</h2>',
    '<section class="staff-panel am225-attendance" id="am225-attendance"><div class="panel-head"><h2>🕒 Attendance</h2>',1)
new=new.replace(
    '<section class="permission-lock">',
    '<section class="permission-lock" id="am225-permission">',1)
new=new.replace(
    '<section class="staff-panel" style="border-left:5px solid #157c60">',
    '<section class="staff-panel am225-inspections" id="am225-inspections" style="border-left:5px solid #157c60">',1)
new=new.replace(
    '<section class="staff-panel inspection-review-panel">',
    '<section class="staff-panel inspection-review-panel" id="am225-diagnosis">',1)
new=new.replace(
    '<section class="staff-panel"><div class="panel-head"><h2>🔧 My ROT Work</h2>',
    '<section class="staff-panel am225-rot-panel" id="am225-my-rot"><div class="panel-head"><h2>🔧 My ROT Work</h2>',1)
navigation=(
    '<nav class="am225-nav" aria-label="Staff Portal work navigation">\n'
    '<a href="#am225-attendance">🕒 Attendance</a>\n'
    '@unless(\\App\\Support\\HrAccess::permitted(auth()->user()))'
    '<a href="#am225-my-rot">🔧 My ROT</a>'
    '<a href="#am225-diagnosis">🔍 Diagnosis</a>@endunless\n'
    '@if(($assignedInspections??collect())->isNotEmpty())'
    '<a href="#am225-inspections">📋 Inspections</a>@endif\n'
    '<a href="#am225-notifications">🔔 Alerts</a>\n'
    '</nav>\n'
)
attendance='<section class="staff-panel am225-attendance" id="am225-attendance">'
if new.count(attendance)!=1:raise SystemExit('AM225_STOP_ATTENDANCE_NAV_LOCATION')
new=new.replace(attendance,navigation+attendance,1)
if new.count('</head>')!=1:raise SystemExit('AM225_STOP_DOCUMENT_HEAD')
new=new.replace('</head>',read(layout)+'\n</head>',1)
if new.count('id="am225-notifications"')!=1 or new.count('id="am225-my-rot"')!=1:
    raise SystemExit('AM225_STOP_LINK_TARGETS_INVALID')
if old.count('@csrf')!=new.count('@csrf'):
    raise SystemExit('AM225_STOP_CSRF_CHANGED')
for token in ("route('staff.attendance.check-in')","route('staff.attendance.check-out')",
              "route('staff.rot.start',$r['id'])","route('staff.rot.pause',$r['id'])",
              "route('staff.rot.complete',$r['id'])"):
    if old.count(token)!=new.count(token):
        raise SystemExit('AM225_STOP_WORK_ROUTE_CHANGED_'+token[:40])
# Laravel Blade compile with the real staging application and PHP lint before
# touching any live source file.
with tempfile.TemporaryDirectory(prefix='am225check_') as t:
    temp=Path(t)
    html=temp/'portal.blade.php';html.write_text(new,encoding='utf8',errors='surrogateescape')
    compiler=temp/'compile.php';php=temp/'compiled.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    r=subprocess.run(['php',str(compiler),str(root),str(html),str(php)],
                     cwd=root,capture_output=True,text=True,timeout=50)
    if r.returncode or not php.is_file():
        raise SystemExit('AM225_STOP_BLADE_COMPILE '+r.stderr[-350:])
    r=subprocess.run(['php','-l',str(php)],cwd=root,capture_output=True,text=True,timeout=30)
    if r.returncode:
        raise SystemExit('AM225_STOP_COMPILED_PHP_SYNTAX '+(r.stdout+r.stderr)[-350:])
print('AM225_STAFF_PORTAL_BLADE_PREFLIGHT_PASS')
print('AM225_MANDATORY_REMINDERS_PRESERVED_TYPE_GROUPS_NO_DELETE')
print('AM225_OPEN_WORK_ATTENDANCE_PERMISSION_ROT_LINKS_CORRECTED')
print('AM225_ATTENDANCE_AND_ROT_ROUTES_CSRF_UNCHANGED')
if mode=='check':
    print('AM225_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if original_file.exists() or manifest.exists():
    raise SystemExit('AM225_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True);os.chmod(backup,0o700)
shutil.copy2(view,original_file);os.chmod(original_file,0o600)
try:
    atomic(view,new)
    manifest.write_text(sha(new)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        done=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if done.returncode:raise RuntimeError('BLADE_CACHE_FAILED '+done.stderr[-420:])
except BaseException as e:
    atomic(view,old)
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=45)
    raise SystemExit('AM225_APPLY_FAILED_RESTORED '+str(e))
print('AM225_STAFF_PORTAL_COMPACT_ALERTS_INSTALLED')
print('AM225_BROWSER_CLICK_ALERT_LINKS_AND_WORK_CARDS_PENDING')
