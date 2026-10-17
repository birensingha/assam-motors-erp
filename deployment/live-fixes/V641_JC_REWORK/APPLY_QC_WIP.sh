#!/usr/bin/env bash
# V641: QC ROT varchar validation and audited stage return. Source-only deployment.
set -euo pipefail
ROOT="$1"
test -f "$ROOT/artisan" && test -f "$ROOT/vendor/autoload.php"
export V641_ROOT="$ROOT"
python3 - <<'PY'
import os,subprocess
from pathlib import Path
from datetime import datetime,timezone
root=Path(os.environ['V641_ROOT'])
targets={
 'ctrl':'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',
 'view':'resources/views/job-cards/lifecycle-panel.blade.php',
}
before={k:(root/p).read_bytes() for k,p in targets.items()}
def edit_one(source,needle,replacement,label):
 n=source.count(needle)
 if n==0 and replacement in source:return source
 if n!=1:raise RuntimeError('V641_PRECHECK_FAILED: '+label+' count='+str(n))
 return source.replace(needle,replacement,1)
code=before['ctrl'].decode('utf-8','surrogateescape')
# A session id is a varchar ROT+timestamp+random suffix, NOT an integer.
code=edit_one(code,
 "'failed_rot_session_id'=>['nullable','integer','min:1'], // FIX47_QC_FAILED_ROT_SESSION",
 "'failed_rot_session_id'=>['nullable','string','max:40'], // V641 ROT varchar ID, verified against same JC",
 'QC varchar validation')
code=edit_one(code,
 "->where('id',(int)$data['failed_rot_session_id'])",
 "->where('id',trim((string)$data['failed_rot_session_id']))",
 'QC typed varchar lookup')
# Require safe accounting lock even for completed cards whose invoice_id is not cached.
old="""            $this->assertNotLocked($card);
            $status=strtolower(trim((string)$card->status));
            if(!in_array($status,['open','completed'],true)){
                throw ValidationException::withMessages(['status'=>'Only OPEN / rework-ready Job Card can move to Work In Progress.']);
            }"""
new="""            $this->assertNotLocked($card);
            $status=strtolower(trim((string)$card->status));
            if(!in_array($status,['open','completed'],true)){
                throw ValidationException::withMessages(['status'=>'Only OPEN / Work Complete / QC / Ready Delivery can move to WIP. Closed requires audited Reopen.']);
            }
            if(Schema::hasTable('invoices') && DB::table('invoices')->where('job_card_id',$job)
                ->whereRaw("LOWER(COALESCE(status,'')) <> 'reversed'")->exists()){
                throw ValidationException::withMessages(['status'=>
                    'Invoice-linked Job Card locked: reverse and reconcile its financial transactions first. No WIP change saved.']);
            }
            if($status==='completed'){
                if(mb_strlen(trim((string)$data['reason']))<12){
                    throw ValidationException::withMessages(['reason'=>'Reopening after Work Complete / QC needs a minimum 12-character correction reason.']);
                }
                if(empty($data['acknowledge_qc_repeat'])){
                    throw ValidationException::withMessages(['acknowledge_qc_repeat'=>'Confirm that Work Complete and QC approval must be repeated after this correction.']);
                }
            }"""
code=edit_one(code,old,new,'WIP status and financial lock')
code=edit_one(code,
 "$data=$request->validate(['reason'=>['required','string','max:1000']]);",
 "$data=$request->validate(['reason'=>['required','string','max:1000'],'acknowledge_qc_repeat'=>['nullable','accepted']]);",
 'WIP acknowledgement validation')
code=edit_one(code,
 """            $card->customer_public_status='Repair In Progress';
            $card->save();

            $this->audit($job,'JOB_CARD',$job,'WIP_START',$before,$card->fresh()->toArray(),$data['reason'],$user);""",
 """            $card->customer_public_status=$status==='completed'
                ? 'Repair In Progress / Rework (QC repeat required)' : 'Repair In Progress';
            $card->save();

            $this->audit($job,'JOB_CARD',$job,
                $status==='completed'?'RETURN_TO_WIP_CORRECTION':'WIP_START',
                $before,$card->fresh()->toArray(),$data['reason'],$user);""",
 'WIP audit action')
code=edit_one(code,
 "return redirect()->route('erp.job-cards.show',$job)->with('success','Job Card moved to WORK IN PROGRESS.');",
 "return redirect()->route('erp.job-cards.show',$job)->with('success','Job Card moved to WIP. Recalculate authorised billing, complete remaining work and perform QC again before delivery.');",
 'WIP result message')
view=before['view'].decode('utf-8','surrogateescape')
anchor="""        {{-- FIX48_WORK_COMPLETE_GUARDS: don't offer premature Work Complete submit --}}"""
fragment="""        {{-- V641_QC_REWORK_RETURN: same authorized markWip route; no silent QC/invoice reversal. --}}
        @if(in_array($amLifeStage,['WORK_COMPLETE','QC_PENDING','QC_PASSED','READY_FOR_DELIVERY'],true) && !$amLifeInvoice && empty($job->invoice_id))
          <details class="am-life-correction">
            <summary class="btn" style="background:#fff2d6;color:#925908;border:1px solid #e4b364">↶ Return to WIP — Edit Parts / ROT / Discount</summary>
            <form method="post" action="{{ route('erp.job-cards.lifecycle.wip',$job->id) }}" class="am-life-form">
              @csrf
              <p>Authorized correction only. Previous QC history stays in the audit. Work Complete and a fresh QC PASS are required again. Any issued invoice must be reversed via Finance first.</p>
              <label>Why return to WIP? (minimum 12 characters)
                <textarea name="reason" rows="2" required minlength="12" maxlength="1000" placeholder="Describe missed item, wrong labour rate / discount, or correction needed"></textarea>
              </label>
              <label><input type="checkbox" name="acknowledge_qc_repeat" value="1" required style="width:auto"> I confirm Work Complete and QC must be repeated before delivery.</label>
              <button class="btn primary" type="submit">Confirm Return to WIP</button>
            </form>
          </details>
        @endif

"""
if fragment not in view:
 view=edit_one(view,anchor,fragment+anchor,'WIP correction button')
planned={'ctrl':code.encode('utf-8','surrogateescape'),'view':view.encode('utf-8','surrogateescape')}
for k,data in planned.items():
 if k=='ctrl':
  p=subprocess.run(['php','-l'],input=data,capture_output=True)
  if p.returncode:raise RuntimeError('V641 pre-deploy PHP lint failed: '+p.stderr.decode('utf-8','replace'))
backup=root.parent/('BACKUP_V641_QC_WIP_'+datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ'))
backup.mkdir(mode=0o700)
done=[]
try:
 for k,p in targets.items():
  path=root/p
  if before[k]==planned[k]:continue
  saved=backup/p;saved.parent.mkdir(parents=True,exist_ok=True);saved.write_bytes(before[k]);saved.chmod(0o600)
  tmp=path.with_name('.'+path.name+'.v641')
  tmp.write_bytes(planned[k]);tmp.chmod(path.stat().st_mode&0o777)
  os.replace(tmp,path);done.append(k)
 check=subprocess.run(['php','-l',str(root/targets['ctrl'])],capture_output=True,text=True)
 if check.returncode:raise RuntimeError('V641 PHP verification failed: '+check.stdout+check.stderr)
 viewcache=subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,text=True,timeout=90)
 if viewcache.returncode:raise RuntimeError('V641 Blade verification failed: '+viewcache.stdout+viewcache.stderr)
 print('V641_PHP_LINT=PASS')
 print('V641_BLADE_COMPILE=PASS')
except BaseException:
 for k in done:(root/targets[k]).write_bytes(before[k])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
 print('V641_QC_WIP_ROLLBACK=RESTORED')
 raise
print('V641_QC_WIP_BACKUP='+str(backup))
print('V641_QC_VARCHAR_ID_AND_RETURN_TO_WIP=DEPLOYED')
PY
