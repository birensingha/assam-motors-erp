#!/usr/bin/env python3
"""AM126 corrected elapsed TAT for COMPLETED ROT Live display only.
Do not change productive time, sessions, invoices or clock events.
Modes: check / apply. Staging-only, backup and auto rollback.
"""
import os,sys,datetime,shutil,tempfile,subprocess
from pathlib import Path

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):
    raise SystemExit("Use: python3 apply.py check|apply STAGING_LARAVEL_ROOT")
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
view=root/"resources/views/dashboard/partials/rot-live-clock.blade.php"
job=root/"resources/views/job-cards/show.blade.php"
if not (root/"artisan").is_file() or not view.is_file() or not job.is_file():
    raise SystemExit("AM126_SAFE_STOP_STAGING_LARAVEL_REQUIRED")
old=view.read_text("utf8",errors="surrogateescape")
jc=job.read_text("utf8",errors="surrogateescape")
marker="AM126_COMPLETED_TAT_FROM_CORRECTED_START_END"
if marker in old:
    print("AM126_ALREADY_APPLIED");sys.exit(0)
expected=[
  ("SERVER_CLOCK",'@php($rotClock = \\App\\Support\\RotLiveClock::snapshot($session))'),
  ("CLOCK_RUNNING","data-am-running=\"{{ $rotClock['running'] ? '1':'0' }}\""),
  ("CLOCK_BASE","data-am-elapsed=\"{{ $rotClock['elapsed_seconds'] }}\""),
  ("DISPLAY_HMS","gmdate('H:i:s',$rotClock['elapsed_seconds'])"),
  ("CLOCK_JS", "const elapsed=Math.max(0,base+(running?"),
  ("LARAVEL_JC_INCLUDES_CLOCK","@include('dashboard.partials.rot-live-clock', ['session' => $rot])"),
  ("JC_HAS_LINE_ROW","@forelse($am83RotLabour as $line)"),
]
for name,needle in expected:
    text=jc if name.startswith("JC_") or name=="LARAVEL_JC_INCLUDES_CLOCK" else old
    ok=(needle in text)
    print("AM126_PREFLIGHT_"+name+"="+("PASS" if ok else "FAIL"))
    if not ok:raise SystemExit("AM126_STOP_CHANGED_SOURCE_"+name)
if old.count(expected[0][1])!=1:raise SystemExit("AM126_DUPLICATE_SNAPSHOT_MARKER_STOP")

old_php=expected[0][1]
new_php=old_php+StringMarkerNotUsed if False else None
new_php=old_php+'''
{{-- AM126_COMPLETED_TAT_FROM_CORRECTED_START_END:
    For COMPLETED ROT display corrected wall-clock elapsed TAT (Start->End)
    as primary time/progress; keep productive work and raw work events intact.
    The source of truth is the audited Labour start/end, not live work seconds.
    Running/Paused ROTs keep the original V639 productive stopwatch unchanged. --}}
@php
    $am126CompletedTAT=false;
    $am126FinishedAt=null;
    $am126ProductiveSeconds=max(0,(int)($session->total_seconds??0));
    $am126ProductiveHms=sprintf('%02d:%02d:%02d',intdiv($am126ProductiveSeconds,3600),intdiv($am126ProductiveSeconds%3600,60),$am126ProductiveSeconds%60);
    if(isset($line) && $session
        && strtolower(trim((string)($session->status??'')))==='completed'
        && (string)($session->labour_line_id??'')===(string)($line->id??'')
        && !empty($line->rot_start) && !empty($line->rot_end)) {
        try {
            $am126Start=\\Carbon\\CarbonImmutable::parse((string)$line->rot_start,'Asia/Kolkata');
            $am126End=\\Carbon\\CarbonImmutable::parse((string)$line->rot_end,'Asia/Kolkata');
            $am126TAT=(int)($am126End->getTimestamp()-$am126Start->getTimestamp());
            $am126Standard=(int)round(max(0,(float)($line->standard_hours??0))*3600);
            if($am126TAT>=0 && $am126Standard>0) {
                $am126CompletedTAT=true;
                $am126FinishedAt=$am126End->format('d-m-Y h:i:s A');
                $rotClock['elapsed_seconds']=$am126TAT;
                $rotClock['standard_seconds']=$am126Standard;
                $rotClock['running']=false;
                $rotClock['remaining_seconds']=max(0,$am126Standard-$am126TAT);
                $rotClock['overtime_seconds']=max(0,$am126TAT-$am126Standard);
                $rotClock['progress_percent']=round($am126TAT/$am126Standard*100);
                $rotClock['started_ist']=$am126Start->format('d-m-Y h:i:s A');
                $rotClock['expected_end_ist']=$am126Start->addSeconds($am126Standard)->format('d-m-Y h:i:s A');
                $rotClock['time_warning']=false;
            }
        }catch(\\Throwable $e){
            // Display-only safe fallback to V639 snapshot. No data writes.
            $am126CompletedTAT=false;
        }
    }
    $am126FmtSeconds=static fn(int $seconds): string => sprintf(
        '%02d:%02d:%02d',intdiv(max(0,$seconds),3600),
        intdiv(max(0,$seconds)%3600,60),max(0,$seconds)%60
    );
@endphp'''
if old.count(old_php)!=1:raise SystemExit("AM126_SOURCE_ANCHOR_COUNT")
candidate=old.replace(old_php,new_php,1)
changes=[
 ("aria-label=\"Live ROT productive and remaining time\"",
  "aria-label=\"{{ $am126CompletedTAT ? 'Completed ROT corrected elapsed TAT and saved productive time' : 'Live ROT productive and remaining time' }}\""),
 ("<span>● {{ $rotClock['running'] ? 'LIVE ROT TIME' : 'ROT TIME' }}</span>",
  "<span>● {{ $am126CompletedTAT ? 'COMPLETED ELAPSED TAT' : ($rotClock['running'] ? 'LIVE ROT TIME' : 'ROT TIME') }}</span>"),
 ("{{ gmdate('H:i:s',$rotClock['elapsed_seconds']) }}",
  "{{ $am126FmtSeconds((int)$rotClock['elapsed_seconds']) }}"),
 ("{{ gmdate('H:i:s',$rotClock['overtime_seconds']) }}",
  "{{ $am126FmtSeconds((int)$rotClock['overtime_seconds']) }}"),
 ("{{ gmdate('H:i:s',$rotClock['remaining_seconds']) }}",
  "{{ $am126FmtSeconds((int)$rotClock['remaining_seconds']) }}"),
 ("    <span>Expected End (IST): <b>{{ $rotClock['expected_end_ist'] }}</b></span>",
  """    <span>Expected End (IST): <b>{{ $rotClock['expected_end_ist'] }}</b></span>
    @if($am126CompletedTAT)
      <span class="am126-completed-end">Completed (IST): <b>{{ $am126FinishedAt }}</b></span>
      <span class="am126-productive">Productive Work Saved: <b>{{ $am126ProductiveHms }}</b> (unchanged)</span>
    @endif"""),
]
for before,after in changes:
    if candidate.count(before)!=1:raise SystemExit("AM126_ANCHOR_CHANGED_"+str(before[:35]))
    candidate=candidate.replace(before,after,1)
if candidate.count(marker)!=1:raise SystemExit("AM126_INVALID_PATCH_MARKER")
if mode=="check":
    print("AM126_READONLY_PREFLIGHT_PASS");sys.exit(0)

backup=root/"storage/app/am-backups"/("AM126_rot-live-clock_"+datetime.datetime.now().strftime("%Y%m%d_%H%M%S")+"_"+str(os.getpid())+".blade.php")
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(view,backup)
print("AM126_BACKUP="+str(backup))
def rollback(reason):
    shutil.copy2(backup,view)
    subprocess.run(["php","artisan","view:clear"],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    raise SystemExit("AM126_ROLLBACK_COMPLETE_"+str(reason))
try:
    fd,tmp=tempfile.mkstemp(prefix=".am126_",dir=view.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as h:h.write(candidate)
        os.chmod(tmp,view.stat().st_mode&0o777)
        os.replace(tmp,view)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    for cmd in (["php","artisan","view:clear"],["php","artisan","view:cache"]):
        r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=100)
        print((r.stdout or r.stderr)[-200:])
        if r.returncode:raise RuntimeError("Laravel view cache error: "+" ".join(cmd))
    hits=[]
    for p in (root/"storage/framework/views").glob("*.php"):
        s=p.read_text("utf8",errors="replace")
        if "am126-productive" in s and "AM126_COMPLETED_TAT_FROM_CORRECTED_START_END" in s:
            r=subprocess.run(["php","-l",str(p)],cwd=root,capture_output=True,text=True,timeout=25)
            if r.returncode:raise RuntimeError("Compiled partial PHP syntax error: "+r.stderr[-200:])
            hits.append(str(p))
    if len(hits)!=1:raise RuntimeError("AM126 compiled view missing/duplicate: "+str(len(hits)))
    print("AM126_COMPILED_VIEW_PHP_PASS="+hits[0])
except BaseException as error:
    rollback(error)
print("AM126_STAGING_VIEW_DEPLOY_PASS")
print("AM126_DB_ROUTES_CONTROLLERS_UNCHANGED")
print("AM126_PRODUCTIVE_SECONDS_PRESERVED")
print("AM126_BROWSER_TEST_PENDING")
