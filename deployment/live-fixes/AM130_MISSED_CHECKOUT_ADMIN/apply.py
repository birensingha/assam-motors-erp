#!/usr/bin/env python3
"""AM130 guarded STAGING Admin Missed Checkout Register + audited correction.
No attendance data is changed during deployment; actual corrections are
only triggered later by a confirmed Admin form submission with a reason.
"""
from pathlib import Path
import datetime,os,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: python3 apply.py check|apply STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
controller=root/'app/Http/Controllers/Web/AdminNativeModuleController.php'
view=root/'resources/views/admin/native/attendance.blade.php'
partial=root/'resources/views/admin/native/partials/am130-missed-checkout.blade.php'
payload=Path(__file__).with_name('register.blade.php')
if not(root/'artisan').is_file() or not all(p.is_file() for p in [controller,view,payload]):
    raise SystemExit('AM130_STOP_STAGING_SOURCE_MISSING')
a=controller.read_text('utf8',errors='surrogateescape')
b=view.read_text('utf8',errors='surrogateescape')
register=payload.read_text('utf8',errors='surrogateescape')
marker='AM130_MISSED_CHECKOUT_ADMIN_REGISTER'
if 'AM130_MISSED_CHECKOUT_ADMIN_AUDITED_CORRECTION' in a and "@include('admin.native.partials.am130-missed-checkout')" in b and partial.is_file():
    print('AM130_ALREADY_DEPLOYED_NO_WRITES');sys.exit(0)
if ('AM130_MISSED_CHECKOUT_ADMIN_AUDITED_CORRECTION' in a or "@include('admin.native.partials.am130-missed-checkout')" in b or partial.is_file()):
    raise SystemExit('AM130_STOP_PARTIAL_INSTALL_MUST_REVIEW_BEFORE_RETRY')
anchors={
 'VIEW_RETURN':"return view('admin.native.attendance',compact(",
 'VIEW_COMPACT':"'myAttendance','myAttendanceCount','myStaffRecord'",
 'UPDATE_METHOD':"    public function updateAttendance(Request $request,int $attendance): RedirectResponse",
 'UPDATE_RECORD':"        $existing=DB::table('attendance')->where('id',$attendance)->first(); abort_unless($existing,404);",
 'CREATE_STAFF':"        $staff=DB::table('staff')->where('id',(int)$data['staff_id'])->first(); abort_unless($staff,422,'Staff not found.');",
 'VIEW_INSERT':"{{-- P20_ADMIN_SELF_ATTENDANCE: dedicated Owner/Admin self-attendance panel (no GPS fabrication) --}}",
}
for name,anchor in anchors.items():
    src=b if name=='VIEW_INSERT' else a
    count=src.count(anchor)
    print('AM130_PREFLIGHT_'+name+'='+('PASS' if count==1 else 'FAIL_'+str(count)))
    if count!=1:raise SystemExit('AM130_STOP_CHANGED_SOURCE_'+name)
if register.count('AM130_MISSED_CHECKOUT_ADMIN_REGISTER')!=1:raise SystemExit('AM130_STOP_VIEW_PAYLOAD')

# Query independent of selected month and staff filter to show ALL previously
# unresolved dates, including historical rows for inactive staff.
controller_insert='''
        // AM130_MISSED_CHECKOUT_ADMIN_REGISTER: global unresolved register;
        // keep monthly attendance matrix and its filters unchanged.
        $am130Base=DB::table('attendance as a')
            ->where('a.date','<',now('Asia/Kolkata')->toDateString())
            ->whereNotNull('a.check_in')->whereNull('a.check_out');
        $am130PendingCount=(clone $am130Base)->count();
        $am130PendingRows=(clone $am130Base)
            ->leftJoin('staff as s','s.id','=','a.staff_id')
            ->select('a.id','a.staff_id','a.date','a.check_in',
                's.name as employee_name','s.staff_code','s.department')
            ->orderBy('a.date')->orderBy('a.id')
            ->limit(100)->get();
'''
a=a.replace(anchors['VIEW_RETURN'],controller_insert+'\n        '+anchors['VIEW_RETURN'],1)
a=a.replace(anchors['VIEW_COMPACT'],anchors['VIEW_COMPACT']+",'am130PendingRows','am130PendingCount'",1)

# Prevent silently bypassing audit via Admin "mark attendance" upsert.
create_guard='''
        // AM130: historical missing Checkout must only be resolved by
        // mandatory-reason Admin register or existing audited HR correction.
        if ((string)$data['date']<now('Asia/Kolkata')->toDateString() &&
            DB::table('attendance')->where('staff_id',(int)$staff->id)
                ->whereDate('date',(string)$data['date'])
                ->whereNotNull('check_in')->whereNull('check_out')->exists()) {
            return back()->withErrors(['attendance'=>
                'Missed Check-Out exists for this date. Use the Missed Check-Out Register with verified time and mandatory reason.']);
        }
'''
a=a.replace(anchors['CREATE_STAFF'],anchors['CREATE_STAFF']+'\n'+create_guard,1)

# Special audited correction branch inside the EXISTING admin-only update route.
# All other editing behavior remains as it was.
audit_php=r'''
        // AM130_MISSED_CHECKOUT_ADMIN_AUDITED_CORRECTION:
        // Mandatory reason and exact actual departure time; never invent time.
        $am130Day=substr((string)$existing->date,0,10);
        if($am130Day<now('Asia/Kolkata')->toDateString() &&
           !empty($existing->check_in) && empty($existing->check_out)) {
            if(!$request->boolean('am130_resolution')) {
                return back()->withErrors(['attendance'=>
                    'This is an unresolved previous-day Check-Out. Use the Missed Check-Out Register; Admin must enter actual departure time and reason.']);
            }
            $am130Data=$request->validate([
                'am130_checkout_time'=>['required','date_format:H:i'],
                'am130_reason'=>['required','string','min:12','max:1000'],
                'am130_resolution'=>['required','in:1'],
            ]);
            if(!Schema::hasTable('hr_audit_logs')) {
                return back()->withErrors(['attendance'=>'HR audit log is unavailable. Correction blocked safely.']);
            }
            $am130Actor=(int)($request->user()?->staff_id??0);
            if($am130Actor<1) {
                return back()->withErrors(['attendance'=>'Admin user must be linked to Staff Master for an audited correction.']);
            }
            DB::transaction(function()use($attendance,$am130Data,$am130Actor){
                $before=DB::table('attendance')->where('id',$attendance)
                    ->lockForUpdate()->first();
                if(!$before || !$before->check_in || $before->check_out ||
                   substr((string)$before->date,0,10)>=now('Asia/Kolkata')->toDateString()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'attendance'=>'Attendance was already corrected or is not a previous-day missing Check-Out. Refresh.',
                    ]);
                }
                $date=substr((string)$before->date,0,10);
                $in=\Carbon\CarbonImmutable::parse((string)$before->check_in,'Asia/Kolkata');
                $out=\Carbon\CarbonImmutable::createFromFormat(
                    '!Y-m-d H:i',$date.' '.$am130Data['am130_checkout_time'],'Asia/Kolkata'
                );
                $elapsed=$out ? $out->getTimestamp()-$in->getTimestamp() : 0;
                if($elapsed<=0 || $elapsed>18*3600) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'am130_checkout_time'=>'Actual Check-Out must be after Check-In and within 18 hours on the recorded date. Contact HR for overnight duty.',
                    ]);
                }
                $after=['check_out'=>$out->format('Y-m-d H:i:s'),
                    'worked_hours'=>round($elapsed/3600,2)];
                DB::table('attendance')->where('id',$attendance)->update($after);
                // Transaction guarantee: if audit cannot be stored, attendance
                // time UPDATE is rolled back as well.
                DB::table('hr_audit_logs')->insert([
                    'staff_id'=>$am130Actor,
                    'action'=>'ATTENDANCE_MISSED_CHECKOUT_RESOLVED',
                    'subject_type'=>'attendance','subject_id'=>$attendance,
                    'details'=>json_encode([
                        'source'=>'AM130_ADMIN_REGISTER',
                        'reason'=>trim((string)$am130Data['am130_reason']),
                        'attendance_date'=>$date,'employee_staff_id'=>(int)$before->staff_id,
                        'actor_user_id'=>(int)($request->user()?->id??0),
                        'actor_staff_id'=>$am130Actor,
                        'before'=>['check_in'=>$before->check_in,
                            'check_out'=>$before->check_out,
                            'worked_hours'=>$before->worked_hours,
                            'status'=>$before->status],
                        'after'=>['check_out'=>$after['check_out'],
                            'worked_hours'=>$after['worked_hours'],
                            'status'=>$before->status],
                    ],JSON_THROW_ON_ERROR),
                    'created_at'=>now('Asia/Kolkata'),
                ]);
            },3);
            return redirect()->route('erp.attendance')
                ->with('success','Previous Check-Out corrected and audited. Staff can Check-In once all older missing dates are resolved.');
        }
'''
# $request not captured by closure above; capture actor id prior to entering closure
audit_php=audit_php.replace(
"DB::transaction(function()use($attendance,$am130Data,$am130Actor){",
"$am130UserId=(int)($request->user()?->id??0);\n            DB::transaction(function()use($attendance,$am130Data,$am130Actor,$am130UserId){")
audit_php=audit_php.replace("'actor_user_id'=>(int)($request->user()?->id??0),","'actor_user_id'=>$am130UserId,")
a=a.replace(anchors['UPDATE_RECORD'],anchors['UPDATE_RECORD']+'\n'+audit_php,1)
b=b.replace(anchors['VIEW_INSERT'],"@include('admin.native.partials.am130-missed-checkout')\n\n"+anchors['VIEW_INSERT'],1)
if a.count('AM130_MISSED_CHECKOUT_ADMIN_AUDITED_CORRECTION')!=1 or b.count("am130-missed-checkout')")!=1:
    raise SystemExit('AM130_STOP_OUTPUT_VALIDATION')
with tempfile.TemporaryDirectory(prefix='am130_php_preflight_') as workdir:
    p=Path(workdir)/'AdminNativeModuleController.php'
    p.write_text(a,'utf8',errors='surrogateescape')
    c=subprocess.run(['php','-l',str(p)],cwd=root,capture_output=True,text=True,timeout=25)
    if c.returncode:raise SystemExit('AM130_STOP_PHP_LINT '+c.stderr[-300:])
print('AM130_CONTROLLER_PHP_PREFLIGHT_PASS')
if mode=='check':
    print('AM130_STAGING_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)

backup_dir=root/'storage/app/am-backups'
backup_dir.mkdir(parents=True,exist_ok=True)
stamp=datetime.datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid())
files=[controller,view]
backups=[]
for f in files:
    dest=backup_dir/('AM130_'+f.name+'_'+stamp+'.bak')
    shutil.copy2(f,dest);backups.append(dest)
print('AM130_CONTROLLER_BACKUP='+str(backups[0]))
print('AM130_VIEW_BACKUP='+str(backups[1]))
try:
    partial.parent.mkdir(parents=True,exist_ok=True)
    for dest,source in [(controller,a),(view,b),(partial,register)]:
        fd,tmp=tempfile.mkstemp(prefix='.am130_',dir=dest.parent)
        try:
            with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh:fh.write(source)
            os.chmod(tmp,(dest.stat().st_mode&0o777) if dest.is_file() else 0o644)
            os.replace(tmp,dest)
        finally:
            if os.path.exists(tmp):os.unlink(tmp)
    r=subprocess.run(['php','-l',str(controller)],cwd=root,capture_output=True,text=True,timeout=25)
    if r.returncode:raise RuntimeError('controller PHP syntax '+r.stderr[-200:])
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=100)
        if r.returncode:raise RuntimeError('Blade cache failure '+r.stderr[-300:])
except BaseException as exc:
    for f,backup in zip(files,backups):shutil.copy2(backup,f)
    if partial.exists():partial.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    raise SystemExit('AM130_SAFE_ROLLBACK_COMPLETE '+str(exc))
print('AM130_STAGING_SOURCE_VIEW_DEPLOY_PASS')
print('AM130_NO_ATTENDANCE_ROWS_MUTATED')
