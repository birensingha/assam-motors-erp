#!/usr/bin/env bash
# Read-only against DB: deploys ROT view-only timers and WIP edit permissions.
set -euo pipefail
APP="$1"
SRC="$(cd "$(dirname "$0")" && pwd)"
test -f "$APP/artisan"
php -l "$SRC/RotLiveClock.php"
export AM_V639_ROOT="$APP" AM_V639_SRC="$SRC"
python3 - <<'PY'
from pathlib import Path
from datetime import datetime,timezone
import os,subprocess
root=Path(os.environ["AM_V639_ROOT"])
src=Path(os.environ["AM_V639_SRC"])
names={
 "clock":"app/Support/RotLiveClock.php",
 "part":"resources/views/dashboard/partials/rot-live-clock.blade.php",
 "work":"resources/views/dashboard/workshop.blade.php",
 "perf":"resources/views/dashboard/rot-performance.blade.php",
 "jc":"resources/views/job-cards/show.blade.php",
 "native":"app/Services/StaffDashboardService.php",
 "bill":"app/Http/Controllers/Web/AdminWorkshopControlPageController.php",
 "rot":"app/Services/RotWorkflowService.php",
}
old={}
for key,name in names.items():
 f=root/name
 if not f.exists() and key not in ("clock","part"):raise RuntimeError("Missing "+name)
 old[key]=f.read_text() if f.exists() else None
def once(s,a,b,label):
 count=s.count(a)
 if count!=1:raise RuntimeError(label+" anchor count "+str(count))
 return s.replace(a,b,1)
new={}
new["clock"]=(src/"RotLiveClock.php").read_text()
new["part"]=(src/"rot-live-clock.blade.php").read_text()
for key in ("clock","part"):
 if old[key] is not None and old[key]!=new[key]:raise RuntimeError("Existing clock file differs: "+names[key])
partial="@include('dashboard.partials.rot-live-clock', ['session' => $r])"
s=old["work"]
new["work"]=s if partial in s else once(s,"   </article>","    "+partial+"\n   </article>","workshop")
s=old["perf"]
new["perf"]=s if partial in s else once(s,"</article>",partial+"</article>","performance")
jcpartial="@include('dashboard.partials.rot-live-clock', ['session' => $rot])"
s=old["jc"]
new["jc"]=s if jcpartial in s else once(s,"      @include('job-cards.rot-billing-control')",
 "      @if($rot)\n      "+jcpartial+"\n      @endif\n      @include('job-cards.rot-billing-control')","JC ROT")
s=old["native"]
needle="    private function rotPublic($r): array\n    {\n        return ["
replacement=r"""    private function rotPublic($r): array
    {
        $clock = \App\Support\RotLiveClock::snapshot($r);
        return [
            'live_seconds' => $clock['elapsed_seconds'],
            'standard_seconds' => $clock['standard_seconds'],
            'remaining_seconds' => $clock['remaining_seconds'],
            'overtime_seconds' => $clock['overtime_seconds'],
            'progress_percent' => $clock['progress_percent'],
            'server_epoch_ms' => $clock['server_epoch_ms'],
            'started_ist' => $clock['started_ist'],
            'expected_end_ist' => $clock['expected_end_ist'],
            'time_warning' => $clock['time_warning'],"""
new["native"]=s if "'time_warning' => $clock['time_warning']" in s else once(s,needle,replacement,"staff ROT API")
s=old["bill"]
i=s.index("public function updateJobLine(")
j=s.find("public function ",i+20)
if j<0:j=len(s)
part=s[i:j]
guard=r"""            if($kind==='labour' && !in_array(strtolower(trim((string)$card->status)),
                ['wip','in progress','in-progress','work in progress'],true)){
                throw ValidationException::withMessages(['reason'=>
                    'ROT/Labour editing requires WIP Job Card. Authorized reopening is required first.']);
            }
"""
if "ROT/Labour editing requires WIP Job Card" not in part:
 part=once(part,"            abort_unless($card,404);\n",
           "            abort_unless($card,404);\n"+guard,"billing WIP edit")
new["bill"]=s[:i]+part+s[j:]
s=old["rot"]
i=s.index("public function changeMechanic(")
j=s.find("public function ",i+20)
if j<0:j=len(s)
part=s[i:j]
guard=r"""            $editCard=DB::table('job_cards')->where('id',$rot->job_card_id)->lockForUpdate()->first();
            if(!$editCard || !in_array(strtolower(trim((string)$editCard->status)),
                ['wip','in progress','in-progress','work in progress'],true)){
                throw ValidationException::withMessages(['rot'=>
                    'ROT edit/reassignment requires Job Card WIP status. Reopen through authorized lifecycle first.']);
            }
            if(!empty($editCard->invoice_id) || DB::table('invoices')
                ->where('job_card_id',$rot->job_card_id)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()){
                throw ValidationException::withMessages(['rot'=>
                    'Invoiced Job Card is locked. Reverse invoice through authorized accounts, then reopen Job Card to WIP.']);
            }
"""
if "ROT edit/reassignment requires Job Card WIP status" not in part:
 part=once(part,"            $rot = RotSession::query()->lockForUpdate()->findOrFail($sessionId);\n",
           "            $rot = RotSession::query()->lockForUpdate()->findOrFail($sessionId);\n"+guard,"changeMechanic guard")
new["rot"]=s[:i]+part+s[j:]
for key,s in new.items():
 if names[key].endswith(".php"):
  result=subprocess.run(["php","-l"],input=s,text=True,capture_output=True)
  if result.returncode:raise RuntimeError("Syntax error in "+key+": "+result.stderr+result.stdout)
backup=root.parent/("BACKUP_V639_"+datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ"))
backup.mkdir(mode=0o700)
done=[]
try:
 for key,name in names.items():
  dest=root/name
  if old[key]==new[key]:continue
  if old[key] is not None:
   b=backup/name;b.parent.mkdir(parents=True,exist_ok=True);b.write_text(old[key]);b.chmod(0o600)
  dest.parent.mkdir(parents=True,exist_ok=True)
  tmp=dest.with_name("."+dest.name+".v639tmp")
  tmp.write_text(new[key])
  tmp.chmod(dest.stat().st_mode & 0o777 if dest.exists() else 0o644)
  os.replace(tmp,dest)
  done.append(key)
 for key in ("clock","native","bill","rot"):
  p=subprocess.run(["php","-l",str(root/names[key])],capture_output=True,text=True)
  if p.returncode:raise RuntimeError("PHP lint failed "+key+" "+p.stderr)
 p=subprocess.run(["php","artisan","view:cache"],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError("View compile failed: "+p.stdout+p.stderr)
except BaseException:
 for key in reversed(done):
  dest=root/names[key]
  if old[key] is None:dest.unlink(missing_ok=True)
  else:dest.write_text(old[key])
 subprocess.run(["php","artisan","view:clear"],cwd=root,capture_output=True)
 print("V639_ROLLBACK=COMPLETE")
 raise
print("V639_DESKTOP_BLADE=COMPILED")
print("V639_BACKUP="+str(backup))
print("V639_PATCHED="+",".join(done))
print("V639_ROT_CLOCK_AND_EDIT_GUARD=DEPLOYED")
PY
