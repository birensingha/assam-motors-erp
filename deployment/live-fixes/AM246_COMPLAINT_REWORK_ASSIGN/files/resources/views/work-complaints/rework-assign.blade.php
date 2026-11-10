<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Complaint #{{ $case->id }} · Rework Mechanic Assignment</title>
<style>
body{font:14px/1.5 Arial,sans-serif;background:#f3f6fa;color:#17314b;margin:0}
main{max-width:1010px;margin:25px auto;padding:0 15px}
h1{font-size:23px;margin:5px 0 7px}h2{font-size:17px;margin:0 0 11px}
a{color:#135a95;font-weight:700;text-decoration:none}
a:hover{text-decoration:underline}
nav{display:flex;gap:16px;flex-wrap:wrap;margin:0 0 16px}
.panel{background:white;border:1px solid #d5e1ed;border-radius:10px;padding:18px 20px;margin:12px 0}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.item{background:#f6f9fd;padding:12px;border-radius:8px}
small{font-size:11px;color:#647a8e;display:block}
strong{overflow-wrap:anywhere}
label{display:block;font-size:12px;font-weight:700;margin:13px 0 5px}
select,textarea,input{box-sizing:border-box;width:100%;padding:10px 11px;border:1px solid #b7cbdc;border-radius:7px;font:inherit}
button{padding:10px 16px;background:#1b609b;border:0;color:white;border-radius:7px;font-weight:700;cursor:pointer}
button:hover{background:#154d7f}
.alert{padding:12px 14px;background:#fff7e9;border:1px solid #e7cd92;border-radius:7px;margin:12px 0}
.success{padding:12px 14px;background:#eff8f2;border:1px solid #a7d4b6;border-radius:7px;margin:12px 0}
.badge{background:#eef3fa;border-radius:50px;padding:5px 11px;font-size:12px;font-weight:700;display:inline-block}
.muted{color:#657b90}.action{border-top:1px solid #e8eef5;padding:9px 0;font-size:12px}
@media(max-width:680px){.grid{grid-template-columns:1fr}.panel{padding:14px}h1{font-size:19px}}
</style></head><body><main>
<nav><a href="{{ route('erp.rot-work-complaints.show',$case->id) }}">← Complaint #{{ $case->id }} Report</a>
<a href="{{ route('erp.rot-work-complaints.register') }}">Work Complaint Register</a>
<a href="{{ route('erp.job-cards.show',$case->job_card_id) }}">Open Job Card</a></nav>
<h1>Assign Complaint Rework Mechanic</h1>
<p class="muted">Complaint #{{ $case->id }} · JC #{{ $case->job_card_id }} · Original Completed ROT {{ $case->rot_session_id }}</p>
@if(session('success'))<div class="success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert"><b>Action not saved:</b>
 @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
 </div>@endif
<div class="panel">
 <h2>Original Work — Preserved</h2>
 <div class="grid">
 <div class="item"><small>Original Mechanic Staff ID</small><strong>{{ $case->staff_id }}</strong></div>
 <div class="item"><small>Original ROT Session</small><strong>{{ $case->rot_session_id }} — Completed</strong></div>
 <div class="item"><small>Complaint Investigation</small><strong>{{ $case->outcome ?: $case->status }}</strong></div>
 <div class="item"><small>Job Card Status</small><strong>{{ $job->status ?? 'Unknown' }}</strong></div>
 </div>
 <p class="muted">New rework assignment will not reset original ROT time, change customer bill, adjust salary or declare a fault without investigation.</p>
</div>
@if(!$link)
<div class="panel">
 <h2>Step 1 — Assign Receiving Mechanic</h2>
 @if(!$canAssign)
  <div class="alert">This Job Card is closed/invoiced/cancelled. Financial reopening must be approved before new work can be assigned.</div>
 @else
 <form method="post" action="{{ route('erp.rot-work-complaints.rework.assign',$case->id) }}">
 @csrf
 <label for="receiver">New Mechanic *</label>
 <select name="assigned_staff_id" id="receiver" required>
  <option value="">Select another mechanic...</option>
  @foreach($staff as $s)
   @if((int)$s->id !== (int)$case->staff_id)
   <option value="{{ $s->id }}" @selected((string)old('assigned_staff_id')===(string)$s->id)>{{ $s->name }} — {{ $s->staff_code }} (ID {{ $s->id }})</option>
   @endif
  @endforeach
 </select>
 <label for="instructions">Complaint Rectification Instructions *</label>
 <textarea name="instructions" id="instructions" rows="4" minlength="12" maxlength="3000"
 required placeholder="Describe which bolts/parts to inspect, safety checks and rectification scope">{{ old('instructions',$case->description) }}</textarea>
 <div class="alert"><b>Important:</b> This first saves an auditable mechanic assignment and alerts both staff portals. It does not start a ROT timer or make a customer-billable line.</div>
 <button type="submit">Save Rework Mechanic Assignment</button>
 </form>
 @endif
</div>
@else
<div class="panel">
 <h2>Receiving Mechanic — Assigned</h2>
 <div class="grid">
 <div class="item"><small>Receiving Mechanic</small><strong>{{ $receiver->name ?? ('Staff #'.$link->assigned_staff_id) }} ({{ $receiver->staff_code ?? '—' }})</strong></div>
 <div class="item"><small>Assignment Status</small><strong>{{ $link->status }}</strong></div>
 <div class="item"><small>Assigned On</small><strong>{{ $link->assigned_at }}</strong></div>
 <div class="item"><small>New Rework ROT</small><strong>{{ $link->rework_rot_session_id ?: 'Pending creation / linking' }}</strong></div>
 </div>
 <label>Recorded Repair Instructions</label>
 <div class="item" style="white-space:pre-wrap">{{ $link->instructions }}</div>
</div>
@if(!$link->rework_rot_session_id)
<div class="panel">
 <h2>Step 2 — Link a Separate ROT for Work Timer & Productivity Credit</h2>
 <p>Open <a href="{{ route('erp.job-cards.show',$case->job_card_id) }}">JC #{{ $case->job_card_id }}</a> and add one NEW Labour/ROT line using existing ERP controls, assigned to the receiving mechanic above. Keep original Completed ROT untouched and confirm that no unintended customer charge is created.</p>
 <p>Then choose that new, not-yet-started ASSIGNED session below. Only this same Job Card + receiving mechanic combination will be accepted.</p>
 <form method="post" action="{{ route('erp.rot-work-complaints.rework.link',$case->id) }}">
 @csrf
 <label for="rot">Receiving Mechanic's New ASSIGNED ROT *</label>
 <select name="rework_rot_session_id" id="rot" required>
  <option value="">Select a separate new ROT...</option>
  @foreach($candidateRots as $rot)
   <option value="{{ $rot->id }}" @selected(old('rework_rot_session_id')===(string)$rot->id)>{{ $rot->id }} · {{ $rot->status }} · JC #{{ $rot->job_card_id }}</option>
  @endforeach
 </select>
 @if($candidateRots->isEmpty())<div class="alert">No eligible new ASSIGNED ROT found for this mechanic on this Job Card. Create it on the Job Card, return here and refresh.</div>@endif
 <p><button type="submit" @if($candidateRots->isEmpty()) disabled @endif>Link New ROT to Complaint</button></p>
 </form>
 <p class="muted">After linking, the receiving mechanic's existing native ROT Check-In / Start / Pause / Complete will determine actual hours and performance credit. This form does not create paid payroll incentives.</p>
</div>
@else
<div class="panel">
 <h2>Step 2 — New Rework ROT Linked</h2>
 <span class="badge">{{ $link->rework_rot_session_id }}</span>
 <p>The assigned mechanic can work with the existing native ROT controls. No original ROT reversal or duplication occurs.</p>
 <a href="{{ route('erp.job-cards.show',$case->job_card_id) }}">Open Job Card to follow Work & QC</a>
</div>
@endif
@endif
@if($actions->isNotEmpty())
<div class="panel"><h2>Rework Assignment Audit</h2>
 @foreach($actions as $a)
 <div class="action"><b>{{ str_replace('_',' ',$a->action) }}</b> · {{ $a->created_at }} · Admin #{{ $a->actor_admin_id }}</div>
 @endforeach
</div>
@endif
</main></body></html>
