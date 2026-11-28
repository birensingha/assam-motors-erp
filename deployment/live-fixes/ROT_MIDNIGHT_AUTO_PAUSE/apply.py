#!/usr/bin/env python3
"""Install staging-only midnight pause using existing ROT transaction and history."""
import sys,os,tempfile,subprocess,shutil
from pathlib import Path
mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
if mode not in ('check','apply') or not root.name.endswith('staging') or not (root/'artisan').is_file():raise SystemExit('STOP staging only')
service=root/'app/Services/RotWorkflowService.php'
routes=root/'routes/console.php'
old={p:p.read_text() for p in (service,routes)}
marker='AM_ROT_MIDNIGHT_AUTO_PAUSE_V1'
if all(marker in s for s in old.values()):
 print('MIDNIGHT_ALREADY_INSTALLED');sys.exit(0)
assert not any(marker in s for s in old.values()),'Partial install'
s=old[service]
anchor='    public function complete(string $sessionId, string $actor'
assert s.count(anchor)==1
assert all(x in s for x in ['private function finalizeRunningSegmentSlice(', 'private function syncLabour(', 'private function event(', 'private function lockedRot(', "if (($pauseReason === 'AUTO_CHECK_OUT') !== $trustedAutoCheckout)"])
method=r"""    // AM_ROT_MIDNIGHT_AUTO_PAUSE_V1: scheduler-only entry, no attendance write.
    public function pauseOverdueAtMidnight(string $sessionId): bool
    {
        return DB::transaction(function () use ($sessionId) {
            $rot = RotSession::query()->whereKey($sessionId)->lockForUpdate()->first();
            if (!$rot || $rot->status !== 'Running' || !$rot->start_time) return false;
            $now = CarbonImmutable::now('Asia/Kolkata');
            // Use the same storage/time interpretation as existing ROT timing.
            $start = CarbonImmutable::parse($rot->start_time, 'Asia/Kolkata')->setTimezone('Asia/Kolkata');
            $cutoff = $start->startOfDay()->addDay();
            if ($now->lt($cutoff)) return false;
            $delta = max(0, (int)$start->diffInSeconds($cutoff, false));
            $pauses = is_array($rot->pauses) ? $rot->pauses : [];
            $pauses[] = [
                'paused_at' => $cutoff->toDateTimeString(),
                'resumed_at' => null,
                'reason' => 'AUTO_MIDNIGHT_PAUSE',
                'note' => 'Automatic midnight IST cutoff; attendance unchanged.',
            ];
            // Finalize while start_time and Running status are still available.
            $this->finalizeRunningSegmentSlice($rot, $cutoff, false);
            $rot->total_seconds = (int)$rot->total_seconds + $delta;
            $rot->status = 'Paused';
            $rot->start_time = null;
            $rot->end_time = null;
            $rot->pauses = $pauses;
            $rot->last_action_by = 'system';
            $rot->save();
            $this->event($rot, $rot->staff_id, 'pause', $cutoff);
            $this->syncLabour($rot, false);
            return true;
        }, 3);
    }

    public function pauseMidnightOverdueRot(): int
    {
        $count = 0;
        $failed = 0;
        // Scan all Running sessions, then recheck each under its own lock.
        // No date comparison in SQL: preserve application timestamp semantics.
        foreach (RotSession::query()->where('status','Running')->pluck('id') as $id) {
            try {
                if ($this->pauseOverdueAtMidnight((string)$id)) $count++;
            } catch (\Throwable $e) {
                report($e);
                $failed++;
            }
        }
        if ($failed) throw new \RuntimeException('Midnight ROT pause failed for '.$failed.' session(s); scheduler will retry.');
        return $count;
    }

"""
s=s.replace(anchor,method+anchor,1)
# Reserve the automatic reason so a manual user cannot forge it.
guard="        if (($pauseReason === 'AUTO_CHECK_OUT') !== $trustedAutoCheckout) {"
assert s.count(guard)==1
s=s.replace(guard,"        if (strtoupper($pauseReason) === 'AUTO_MIDNIGHT_PAUSE') {\n            throw ValidationException::withMessages(['pause_reason' => 'Midnight pause is reserved for the system scheduler.']);\n        }\n"+guard,1)
r=old[routes]
assert "use Illuminate\\Support\\Facades\\Schedule;" in r
r+=r"""
// AM_ROT_MIDNIGHT_AUTO_PAUSE_V1: every minute also catches delayed runs.
Schedule::call(function (): void {
    app(\App\Services\RotWorkflowService::class)->pauseMidnightOverdueRot();
})->name('assam-rot-midnight-auto-pause')->everyMinute()->timezone('Asia/Kolkata')->withoutOverlapping(5);
"""
candidate={service:s,routes:r}
for p,body in candidate.items():
 proc=subprocess.run(['php','-l'],input=body,text=True,capture_output=True,cwd=root)
 if proc.returncode:raise RuntimeError(proc.stdout+proc.stderr)
 print('PHP_LINT_PASS',p.name)
print('MIDNIGHT_PREFLIGHT_PASS')
if mode=='check':sys.exit(0)
backup=root/'storage/app/am-backups'/marker
assert not backup.exists()
backup.mkdir(parents=True)
for p in old:shutil.copy2(p,backup/p.name)
def write(p,data):
 fd,tmp=tempfile.mkstemp(dir=p.parent)
 try:
  with os.fdopen(fd,'w') as f:f.write(data)
  os.chmod(tmp,p.stat().st_mode&0o777);os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
try:
 for p,data in candidate.items():write(p,data)
 out=subprocess.run(['php','artisan','schedule:list'],cwd=root,check=True,capture_output=True,text=True,timeout=40)
 print(out.stdout)
 if 'assam-rot-midnight-auto-pause' not in out.stdout:raise RuntimeError('Scheduler task not registered')
except BaseException:
 for p,data in old.items():write(p,data)
 raise
print('MIDNIGHT_PATCH_INSTALLED_SCHEDULE_REGISTERED')
print('ATTENDANCE_UNCHANGED_NO_MANUAL_SCHEDULE_RUN')
