#!/usr/bin/env bash
# V646: block invoice-linked ROT Start/Resume; allow audit-only TAT correction
# post-closure without altering invoice, rate, tax, discount or historical events.
set -euo pipefail
ROOT="$1"
test "$ROOT" = "/home/u956497103/domains/assammotors.com/assam-erp-staging"
test -f "$ROOT/artisan"
export V646_ROOT="$ROOT"
python3 - <<'PY'
import os, subprocess, re
from pathlib import Path
from datetime import datetime,timezone
root=Path(os.environ['V646_ROOT'])
files={
 'service':'app/Services/RotWorkflowService.php',
 'controller':'app/Http/Controllers/Web/AdminRotTimeEditController.php',
 'partial':'resources/views/job-cards/rot-time-editor.blade.php',
 'lifecycle':'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',
}
old={}
for key,name in files.items():
 path=root/name
 if not path.exists():raise RuntimeError('V646 missing required deployed V645 file: '+name)
 old[key]=path.read_bytes().decode('utf-8','surrogateescape')
def only(text,needle,repl,label):
 if needle not in text and repl in text:return text
 count=text.count(needle)
 if count!=1:raise RuntimeError('V646_ANCHOR '+label+' count='+str(count))
 return text.replace(needle,repl,1)
s=old['service']
mark="        if ($job->vehicle_inward_at && CarbonImmutable::now('Asia/Kolkata').lt("
# Raw PHP has ->lt not dot
mark="        if ($job->vehicle_inward_at && CarbonImmutable::now('Asia/Kolkata')->lt("
addition="""        // V646: WIP status alone does not authorize Start/Resume on an invoiced JC.
        // Called by both Staff and Admin API inside the same ROT transaction.
        if (!empty($job->invoice_id) || DB::table('invoices')
            ->where('job_card_id', $job->id)
            ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()) {
            throw ValidationException::withMessages(['job_card'=>
                'ROT Start/Resume blocked: Job Card has an active invoice. Only audited TAT correction is permitted; do not resume work.']);
        }
"""
if 'V646: WIP status alone' not in s:
 if s.count(mark)!=1:raise RuntimeError('Service WIP/invoice gate anchor missing')
 s=s.replace(mark,addition+mark,1)
new={'service':s}
s=old['controller']
start=s.index('private function apply(')
method=s[start:]
oldRule="""            $status=strtolower(trim((string)$card->status));
            if(!in_array($status,['wip','in-progress','in progress','work in progress'],true)){
                throw ValidationException::withMessages(['reason'=>
                    'ROT Time Correction is allowed only while Job Card is WIP. Use authorized lifecycle reopening first.']);
            }
            if(!empty($card->invoice_id) || DB::table('invoices')->where('job_card_id',$card->id)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()){
                throw ValidationException::withMessages(['reason'=>
                    'Invoice-linked Job Card is financially locked. Reverse and reconcile the invoice before returning to WIP.']);
            }"""
newRule="""            // V646: time correction and billing correction are different actions.
            // Standard Hours may influence billing; keep that WIP/non-invoiced only.
            // Productive TAT after completion is a factual Admin-only audit correction,
            // permitted on Work Complete, Closed, or Invoiced without invoice reversal.
            $status=strtolower(trim((string)$card->status));
            $wip=in_array($status,['wip','in-progress','in progress','work in progress'],true);
            $finalStage=in_array($status,['completed','closed','invoiced'],true);
            $hasInvoice=!empty($card->invoice_id) || DB::table('invoices')
                ->where('job_card_id',$card->id)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists();
            if($type==='standard' && (!$wip || $hasInvoice)){
                throw ValidationException::withMessages(['reason'=>
                    'Standard Hours editing requires an uninvoiced WIP Job Card. Issued invoice and billing quantity remain locked.']);
            }
            if($type==='actual' && !($wip || $finalStage)){
                throw ValidationException::withMessages(['reason'=>
                    'Actual TAT correction is allowed only in WIP, Work Complete, Closed, or Invoiced stage.']);
            }
            if($type==='actual' && ($finalStage || $hasInvoice) && $rot->status!=='Completed'){
                throw ValidationException::withMessages(['reason'=>
                    'After Work Complete/Closure/Invoice, only a Completed ROT can receive audited TAT correction. Start/Resume is prohibited.']);
            }"""
s=only(s,oldRule,newRule,'stage-specific invoice/TAT policy')
new['controller']=s
s=old['partial']
s=only(s,
"  $rotClockAllowed=$rotClockRole==='admin' && $rotClockWip && empty($job->invoice_id);",
"""  $rotClockFinalStage=in_array(strtolower(trim((string)$job->status)),['completed','closed','invoiced'],true);
  $rotClockStandardAllowed=$rotClockRole==='admin' && $rotClockWip && empty($job->invoice_id);
  $rotClockAllowed=$rotClockRole==='admin' && ($rotClockWip || $rotClockFinalStage);
""".rstrip(),'allow history correction on non-WIP card')
s=only(s,
'<details class="action-pop" data-v645-rot-time="{{ $rot->id }}">',
'<details class="am-v646-time-editor" data-v645-rot-time="{{ $rot->id }}" style="position:static!important; flex-basis:100%; width:100%; order:20; margin-top:8px; overflow:visible!important">',
'inline expandable edit controls')
s=only(s,
'<div class="pop-form" style="min-width:min(360px,85vw);max-width:460px">',
'<div class="am-v646-time-form" style="position:static!important; display:block!important; overflow:visible!important; height:auto!important; max-height:none!important; width:100%; max-width:660px; border:1px solid #bfd4ee; border-radius:9px; padding:12px; background:#f8fbff; margin-top:8px">',
'cut off popup layout')
s=only(s,
'Only WIP and no active invoice. Every edit needs a reason and is recorded in ROT History. Customer rate, discount and GST stay unchanged.',
'Standard Hours: only uninvoiced WIP. Actual/Productive TAT: Admin audit is allowed after Work Complete/Closure/Invoice for a Completed ROT. No customer invoice, rate, discount, or GST changes.',
'correct panel description')
s=only(s,
'    <form method="post" action="{{ route(\'erp.rot.time.standard\',$rot->id) }}" style="display:grid;gap:7px">',
'''    @if($rotClockStandardAllowed)
    <form method="post" action="{{ route('erp.rot.time.standard',$rot->id) }}" style="display:grid;gap:7px">''',
'standard editable only WIP')
s=only(s,
'''      <button class="btn small primary" type="submit" onclick="return confirm('Save audited Standard Hours correction? Labour billing totals will NOT be recalculated.')">Save Standard Hours</button>
    </form>''',
'''      <button class="btn small primary" type="submit" onclick="return confirm('Save audited Standard Hours correction? Labour billing totals will NOT be recalculated.')">Save Standard Hours</button>
    </form>
    @else
      <p style="padding:8px;background:#fff3e4;color:#87540e;border-radius:6px;font-size:11px">
        Standard Hours and billing are locked at this stage. Invoice correction must use Finance reversal; factual Actual TAT can be corrected below.
      </p>
    @endif''','standard closed form end')
s=only(s,
"    @if(in_array($rot->status,['Paused','Completed'],true) && $rot->first_start_at)",
"    @if(in_array($rot->status,['Paused','Completed'],true) && $rot->first_start_at && ($rotClockWip || $rot->status==='Completed'))",
'actual requires completed after closure')
s=only(s,
'''        <button class="btn small warning-btn" type="submit" onclick="return confirm('Correct saved productive seconds with a permanent Admin audit? Raw mechanic timing events remain unchanged.')">Save Actual Time Correction</button>''',
'''        <button class="btn small warning-btn" type="submit" onclick="return confirm('Save Admin-audited productive/TAT correction? Original events and any issued invoice will remain unchanged.')">Save Actual TAT Correction</button>''','TAT label')
new['partial']=s
s=old['lifecycle']
needle="""            if(empty($card->customer_id)){
                throw ValidationException::withMessages(['status'=>'Invoice se pehle Job Card ko Customer Master se link kijiye.']);
            }"""
add="""            // V646: defence in depth against invoices being issued while a ROT is live.
            if(DB::table('rot_sessions')->where('job_card_id',$job)
                ->whereIn('status',['Running','Paused'])->exists()){
                throw ValidationException::withMessages(['status'=>
                    'Invoice blocked: Running/Paused ROT must be correctly Completed first.']);
            }
"""
if 'V646: defence in depth' not in s:
 s=only(s,needle,needle+'\n'+add.rstrip(),'invoice live ROT guard')
new['lifecycle']=s

# Preflight must preserve all history tables and customer financial routines.
for key in ['service','controller','lifecycle']:
 p=subprocess.run(['php','-l'],input=new[key].encode('utf-8','surrogateescape'),capture_output=True)
 if p.returncode:raise RuntimeError('V646 invalid '+key+': '+(p.stdout+p.stderr).decode('utf-8','replace'))
if 'V646: WIP status alone' not in new['service'] or 'V646: time correction and billing correction' not in new['controller']:
 raise RuntimeError('Core no-invoice guards not present')
if 'Original events and any issued invoice will remain unchanged' not in new['partial']:
 raise RuntimeError('No clear TAT invoice disclosure')
backup=root.parent/('BACKUP_V646_INVOICED_ROT_TAT_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700)
done=[]
try:
 for key,path in files.items():
  if new[key]==old[key]:continue
  dest=root/path
  previous=backup/path;previous.parent.mkdir(parents=True,exist_ok=True)
  previous.write_bytes(old[key].encode('utf-8','surrogateescape'));previous.chmod(0o600)
  temp=dest.with_name('.'+dest.name+'.v646tmp')
  temp.write_bytes(new[key].encode('utf-8','surrogateescape'))
  temp.chmod(dest.stat().st_mode&0o777)
  os.replace(temp,dest);done.append(key)
 for key in ['service','controller','lifecycle']:
  p=subprocess.run(['php','-l',str(root/files[key])],capture_output=True,text=True)
  if p.returncode:raise RuntimeError('V646 server PHP lint '+key+': '+p.stdout+p.stderr)
 p=subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,text=True,timeout=90)
 if p.returncode:raise RuntimeError('V646 view compile failed '+p.stdout+p.stderr)
 print('V646_PHP_AND_BLADE=PASS')
except BaseException:
 for key in reversed(done):
  (root/files[key]).write_bytes(old[key].encode('utf-8','surrogateescape'))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
 print('V646_ROLLBACK=RESTORED')
 raise
print('V646_BACKUP='+str(backup))
print('V646_CHANGED='+','.join(done))
print('V646_INVOICE_RESUME_GUARD_AND_ADMIN_TAT=DEPLOYED')
PY
