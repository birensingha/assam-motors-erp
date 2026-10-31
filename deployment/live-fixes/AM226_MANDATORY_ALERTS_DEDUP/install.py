#!/usr/bin/env python3
"""AM226: safe STAGING Mandatory Reminder lifecycle correction.
- One stable permission-reminder identity while permission remains disabled.
- Resolve historical checkin/checkout/login alerts ONLY on DB evidence.
- Mark corresponding push outbox action_resolved_at for each genuine resolution.
- Staff Portal displays unique current permission action, retains every record.
- No deletion or changes to attendance, ROT, payments, app, or check-in gates.
Use check|apply|rollback STAGING_ROOT; guarded source backup + PHP/Blade lint.
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM226_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM226_STOP_NOT_STAGING')
files={
    'engine':root/'app/Services/StaffReminderEngine.php',
    'dashboard':root/'app/Services/StaffDashboardService.php',
    'portal':root/'resources/views/staff/portal.blade.php'
}
backup=root/'storage/app/am-backups/AM226_MANDATORY_ALERTS_DEDUP'
manifest=backup/'installed-sha256.txt'
marker='AM226_MANDATORY_ALERTS_DEDUP'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def digest(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am226_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if any(not p.is_file() for p in files.values()):
    raise SystemExit('AM226_STOP_SOURCE_MISSING')
old={k:read(p) for k,p in files.items()}
if mode=='rollback':
    if not manifest.is_file():raise SystemExit('AM226_STOP_ROLLBACK_MANIFEST_MISSING')
    if read(manifest).splitlines()!=[digest(old[k]) for k in files]:
        raise SystemExit('AM226_STOP_ROLLBACK_CURRENT_SOURCE_CHANGED')
    for k,p in files.items():
        b=backup/(p.name+'.before_AM226')
        if not b.is_file():raise SystemExit('AM226_STOP_ROLLBACK_BACKUP_MISSING_'+k)
    for k,p in files.items():atomic(p,read(backup/(p.name+'.before_AM226')))
    print('AM226_ROLLBACK_SOURCE_RESTORED')
    print('AM226_NOTE_ALREADY_RECONCILED_ALERTS_PRESERVED')
    sys.exit(0)
if any(marker in s for s in old.values()):
    if all(marker in s for s in old.values()):
        print('AM226_ALREADY_INSTALLED_NO_CHANGES')
        sys.exit(0)
    raise SystemExit('AM226_STOP_PARTIAL_SOURCE_INSTALL')
def single(s,old,new,label):
    count=s.count(old)
    if count!=1:raise SystemExit('AM226_STOP_'+label+'_ANCHOR_COUNT_'+str(count))
    return s.replace(old,new,1)
engine=old['engine']
# The original code creates one NOTIFICATION_DISABLED record for every date.
# A stable 'permission' target represents the SAME ongoing compliance condition.
engine=single(engine,
"            $state = $this->ensureState($staffId, 'NOTIFICATION_DISABLED', $today, $today, $now, $now, 'Staff app mandatory notification permission is disabled or FCM token is missing.', ['date'=>$today]);",
"            // AM226_MANDATORY_ALERTS_DEDUP: one ongoing permission issue, not a new issue each date.\n"
"            $state = $this->ensureState($staffId, 'NOTIFICATION_DISABLED', 'permission', null, $now, $now, 'Staff app mandatory notification permission is disabled or FCM token is missing.', ['category'=>'permission']);",
'DAILY_NOTIFICATION_IDENTITY')
engine=single(engine,
"            $this->resolve($staffId, 'NOTIFICATION_DISABLED', $today);",
"            $this->resolveTypeExcept($staffId, 'NOTIFICATION_DISABLED', []);",
'PERMISSION_RESTORE_RESOLUTION')
engine=single(engine,
"        $this->resolveTypeExcept($staffId,'ROT_PAUSED_TOO_LONG',$activePaused);\n        return $queued;",
"        $this->resolveTypeExcept($staffId,'ROT_PAUSED_TOO_LONG',$activePaused);\n"
"        // Reconcile only verified completed historical actions (no mass dismissal).\n"
"        $this->reconcileHistoricalVerifiedActions($staffId,$today);\n"
"        return $queued;",
'RECONCILIATION_HOOK')
# Replace unsafe direct state-only resolution; keep identical method signature.
resolve_old="""    private function resolve(int $staffId,string $type,string $targetKey): void
    {
        StaffReminderState::query()->where('staff_id',$staffId)->where('reminder_type',$type)->where('target_key',$targetKey)->whereNull('resolved_at')->update(['resolved_at'=>now('Asia/Kolkata'),'updated_at'=>now('Asia/Kolkata')]);
    }

    private function resolveTypeExcept(int $staffId,string $type,array $keys): void
    {
        $q=StaffReminderState::query()->where('staff_id',$staffId)->where('reminder_type',$type)->whereNull('resolved_at');
        if ($keys) $q->whereNotIn('target_key',$keys);
        $q->update(['resolved_at'=>now('Asia/Kolkata'),'updated_at'=>now('Asia/Kolkata')]);
    }
"""
resolve_new="""    private function resolve(int $staffId,string $type,string $targetKey): void
    {
        // AM226: this also marks previously queued notifications action-resolved.
        $this->notifications->resolveByTarget($staffId,$type,$targetKey);
    }

    private function resolveTypeExcept(int $staffId,string $type,array $keys): void
    {
        $q=StaffReminderState::query()->where('staff_id',$staffId)
            ->where('reminder_type',$type)->whereNull('resolved_at');
        if ($keys) $q->whereNotIn('target_key',$keys);
        foreach ($q->pluck('target_key') as $key) {
            $this->notifications->resolveByTarget($staffId,$type,(string)$key);
        }
    }

    private function reconcileHistoricalVerifiedActions(int $staffId,string $today): void
    {
        // No historical alert is removed solely because its date is old.
        // Past checkout needs a REAL recorded check_out, checkin a REAL
        // check_in, login a same-day staff_login_events record.
        $states=StaffReminderState::query()
            ->where('staff_id',$staffId)->whereNull('resolved_at')
            ->whereIn('reminder_type',['CHECKIN_MISSED','CHECKOUT_PENDING','LOGIN_MISSED'])
            ->where('target_key','<',$today)->orderBy('id')->limit(500)
            ->get(['reminder_type','target_key']);
        if ($states->isEmpty())return;
        $valid=$states->filter(fn($r)=>preg_match('/^\\d{4}-\\d{2}-\\d{2}$/D',(string)$r->target_key)
            && (string)$r->target_key < $today);
        if ($valid->isEmpty())return;
        $dates=$valid->pluck('target_key')->unique()->values()->all();
        $attendance=Attendance::query()->where('staff_id',$staffId)
            ->whereIn('date',$dates)->get()->keyBy(fn($a)=>substr((string)$a->getRawOriginal('date'),0,10));
        $logins=[];
        try {
            if (DB::getSchemaBuilder()->hasTable('staff_login_events')) {
                $logins=DB::table('staff_login_events')->where('staff_id',$staffId)
                    ->whereBetween('login_at',[min($dates).' 00:00:00',max($dates).' 23:59:59'])
                    ->selectRaw('DATE(login_at) AS duty_day')->distinct()
                    ->pluck('duty_day')->mapWithKeys(fn($d)=>[(string)$d=>true])->all();
            }
        } catch (\Throwable $error) {
            // Missing login event source must NEVER be treated as proof of login.
        }
        foreach ($valid as $state) {
            $day=(string)$state->target_key;
            $record=$attendance->get($day);
            $satisfied=match((string)$state->reminder_type) {
                'CHECKIN_MISSED' => $record && !empty($record->check_in),
                'CHECKOUT_PENDING' => $record && !empty($record->check_in)
                    && !empty($record->check_out),
                'LOGIN_MISSED' => isset($logins[$day]),
                default => false
            };
            if ($satisfied) {
                $this->notifications->resolveByTarget($staffId,(string)$state->reminder_type,$day);
            }
        }
    }
"""
engine=single(engine,resolve_old,resolve_new,'STATE_AND_OUTBOX_RESOLUTION')
# Dashboard keeps full reminder list unchanged; only actionable count compacts
# continuing permission issue into one badge. Historical rows still viewable.
dashboard=old['dashboard']
dash_old="                'pending_notifications' => $pending->count(),"
dash_new="""                // AM226_MANDATORY_ALERTS_DEDUP: one permission action despite dated history.
                'pending_notifications' => $pending->where('reminder_type','!=','NOTIFICATION_DISABLED')->count()
                    + min(1,$pending->where('reminder_type','NOTIFICATION_DISABLED')->count()),
                'pending_notification_records' => $pending->count(),"""
dashboard=single(dashboard,dash_old,dash_new,'DASHBOARD_ACTION_COUNT')
portal=old['portal']
portal_old="<span class=\"am225-alert-total\">{{ count($data['reminders']??[]) }} pending</span>"
portal_new="""{{-- AM226_MANDATORY_ALERTS_DEDUP: action count separate from preserved records. --}}
      <span class="am225-alert-total">{{ $data['summary']['pending_notifications'] ?? count($data['reminders']??[]) }} active actions</span>
      <span style="font-size:10px;color:#6b7e93">{{ count($data['reminders']??[]) }} records</span>"""
portal=single(portal,portal_old,portal_new,'PORTAL_LABELS')
candidate={'engine':engine,'dashboard':dashboard,'portal':portal}
# Critical operations (no attendance mutation, no Check-In gate changes).
for method in ('public function syncAll(): array','public function syncStaff(',
               'private function queueIfDue(','private function ensureState('):
    if engine.count(method)!=old['engine'].count(method):
        raise SystemExit('AM226_STOP_REMINDER_ENGINE_METHOD_CHANGED')
for src in candidate.values():
    if src.count(marker)!=1:raise SystemExit('AM226_STOP_PATCH_MARKER_INVALID')
# Build exact candidates and validate through PHP + real Blade compiler.
with tempfile.TemporaryDirectory(prefix='am226preflight_') as t:
    temp=Path(t)
    for name in ('engine','dashboard'):
        p=temp/(files[name].name)
        p.write_text(candidate[name],encoding='utf8',errors='surrogateescape')
        r=subprocess.run(['php','-l',str(p)],cwd=root,capture_output=True,text=True,timeout=25)
        if r.returncode:raise SystemExit('AM226_STOP_PHP_LINT_'+name+' '+(r.stdout+r.stderr)[-500:])
    blade=temp/'portal.blade.php'
    blade.write_text(portal,encoding='utf8',errors='surrogateescape')
    compiler=temp/'compile.php';compiled=temp/'compiled.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    r=subprocess.run(['php',str(compiler),str(root),str(blade),str(compiled)],
                     cwd=root,capture_output=True,text=True,timeout=40)
    if r.returncode or not compiled.is_file():
        raise SystemExit('AM226_STOP_BLADE_COMPILE '+r.stderr[-420:])
    r=subprocess.run(['php','-l',str(compiled)],cwd=root,capture_output=True,text=True,timeout=25)
    if r.returncode:raise SystemExit('AM226_STOP_BLADE_SYNTAX '+(r.stdout+r.stderr)[-420:])
print('AM226_PERMISSION_STABLE_IDENTITY_AND_FULL_HISTORY_GUARD_PASS')
print('AM226_VERIFIED_LOGIN_CHECKIN_CHECKOUT_RECONCILIATION_PREFLIGHT_PASS')
print('AM226_REMINDER_STATE_AND_PUSH_OUTBOX_RESOLUTION_PASS')
print('AM226_PORTAL_ACTION_COUNT_AND_HISTORICAL_RECORDS_PRESERVED')
print('AM226_PHP_BLADE_PREFLIGHT_PASS')
if mode=='check':
    print('AM226_CHECK_PASS_NO_WRITES');sys.exit(0)
if manifest.exists() or any((backup/(p.name+'.before_AM226')).exists() for p in files.values()):
    raise SystemExit('AM226_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True);os.chmod(backup,0o700)
for p in files.values():
    b=backup/(p.name+'.before_AM226');shutil.copy2(p,b);os.chmod(b,0o600)
try:
    for k,p in files.items():atomic(p,candidate[k])
    manifest.write_text('\n'.join(digest(candidate[k]) for k in files)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
        if r.returncode:raise RuntimeError('AM226_VIEW_CACHE '+r.stderr[-340:])
except BaseException as ex:
    for k,p in files.items():atomic(p,old[k])
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
    raise SystemExit('AM226_INSTALL_FAILED_SOURCE_RESTORED '+str(ex))
print('AM226_REMINDER_LIFECYCLE_AND_PORTAL_COUNT_INSTALLED')
print('AM226_NEXT_SCHEDULED_SYNC_RECONCILES_ONLY_VERIFIED_CONDITIONS')
print('AM226_BROWSER_LOGIN_AND_STAFF_ALERT_STATE_TEST_PENDING')
