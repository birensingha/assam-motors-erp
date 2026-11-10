<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Complaint #{{ $case->id }} · Assam Motors</title>
<style>
:root{font-family:Arial,"Helvetica Neue",sans-serif;color:#1a2e42}
*{box-sizing:border-box}
body{background:#f3f6fa;margin:0;font-size:14px;line-height:1.5}
main{max-width:1060px;margin:26px auto;padding:0 14px}
header{display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:2px 0 16px}
header h1{font-size:24px;margin:2px 0}
.muted{color:#62768a}.eyebrow{letter-spacing:.07em;text-transform:uppercase;font-size:11px;font-weight:700;color:#50708a}
nav{display:flex;flex-wrap:wrap;gap:15px;align-items:center;margin-bottom:17px}a{color:#075ca0;font-weight:700;text-decoration:none}a:hover{text-decoration:underline}
.sheet{background:white;border:1px solid #d0dce7;border-radius:11px;box-shadow:0 3px 10px #1b46650b;padding:20px 22px;margin-bottom:14px}
h2{font-size:16px;color:#20425e;margin:0 0 12px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.field{background:#f7f9fc;border-radius:7px;padding:12px;overflow-wrap:anywhere}
.field small{display:block;text-transform:uppercase;color:#687f91;letter-spacing:.05em;font-size:10px;font-weight:700;margin-bottom:3px}
.field strong{display:block;font-size:14px}
.pill{display:inline-block;border-radius:25px;background:#eaf0f8;color:#2c5580;font-size:12px;font-weight:700;padding:7px 11px}
.pill.confirmed{background:#ffebe9;color:#a43c31}
.pill.pending{background:#fff3da;color:#86561c}
.prose{padding:13px 15px;border-left:3px solid #a9c1d9;background:#f6f9fd;border-radius:5px;white-space:pre-wrap;overflow-wrap:anywhere}
.notice{border:1px solid #eccb91;background:#fff8ed;border-radius:7px;padding:12px 14px;margin-top:12px}
.success{border:1px solid #9bd2b4;background:#effaf3;border-radius:7px;padding:12px 14px;margin-bottom:14px}
.timeline{border-left:2px solid #c9d8e6;padding-left:16px;margin-left:6px}
.event{margin:0 0 13px;position:relative;padding:10px 12px;border-radius:8px;background:#f8fafc;border:1px solid #e1e9ef}
.event:before{content:"";position:absolute;left:-23px;top:17px;width:10px;height:10px;border-radius:100%;background:#5b8fbb;border:2px solid white}
.event h3{font-size:13px;margin:0 0 5px;color:#214762}
.event p{margin:4px 0;font-size:12px}
.event .audit-note{white-space:pre-wrap;overflow-wrap:anywhere}
.event .subdued{color:#6d7d90}
details>summary{cursor:pointer;font-weight:700;color:#244d70}
details.correction{border:1px solid #dce5ee;border-radius:8px;padding:13px 15px}
label{display:block;font-size:12px;font-weight:700;margin:13px 0 4px}
textarea,select{width:100%;border:1px solid #b7c9d9;border-radius:7px;padding:9px 10px;font:inherit}
textarea{resize:vertical}
button{background:#175d94;color:white;padding:11px 17px;font-weight:700;border:0;border-radius:7px;cursor:pointer}
button:hover{background:#164e7b}
.checkbox{display:flex;gap:8px;align-items:flex-start;font-weight:400}
.checkbox input{margin-top:4px}
@media(max-width:650px){.grid{grid-template-columns:1fr}.sheet{padding:14px}header h1{font-size:20px}}
@media print{body{background:white}main{max-width:none}nav,.no-print,button{display:none!important}.sheet{break-inside:avoid;box-shadow:none;padding:12px}.event{break-inside:avoid}}
</style>
</head><body><main>
<nav class="no-print">
 <a href="{{ route('erp.rot-work-complaints.register') }}">Complaint Register</a>
 <a href="{{ route('erp.rot-work-complaints.monthly') }}">Monthly Staff Performance</a>
 <a href="{{ route('erp.job-cards.show',$case->job_card_id) }}">Back to Job Card</a>
</nav>
<header>
 <div><div class="eyebrow">Assam Motors · Work Complaint Investigation</div><h1>Complaint #{{ $case->id }} — Result & History</h1>
 <div class="muted">Recorded complaint against the original completed work; rework is a separate activity</div></div>
 <span class="pill {{ $case->outcome==='WORKMANSHIP_CONFIRMED'?'confirmed':(in_array($case->status,['REPORTED','UNDER_REVIEW'],true)?'pending':'') }}">
  Current: {{ str_replace('_',' ',$case->outcome ?: $case->status) }}
 </span>
</header>
@if(session('success'))<div class="success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice"><strong>Decision not saved.</strong>
 @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>@endif
<section class="sheet"><h2>Current Case Summary</h2>
 <div class="grid">
 <div class="field"><small>Job Card</small><strong>JC #{{ $case->job_card_id }}</strong></div>
 <div class="field"><small>Original Completed ROT / Session</small><strong>{{ $case->rot_session_id }}</strong></div>
 <div class="field"><small>Original Mechanic</small><strong>{{ $staff->name ?? ('Staff #'.$case->staff_id) }} ({{ $staff->staff_code ?? '—' }})</strong></div>
 <div class="field"><small>Reported at</small><strong>{{ $case->reported_at }}</strong></div>
 <div class="field"><small>Current Investigation Status</small><strong>{{ str_replace('_',' ',$case->status) }}</strong></div>
 <div class="field"><small>Latest Decision Time</small><strong>{{ $case->reviewed_at ?? 'Not yet reviewed' }}</strong></div>
 </div>
 @if(in_array($case->status,['REPORTED','UNDER_REVIEW'],true))
 <div class="notice"><strong>Current status is Under Review / Reported.</strong> This case is not currently counted as a confirmed workmanship fault in monthly performance. Earlier confirmations, if any, remain visible in the timeline below.</div>
 @endif
</section>
<section class="sheet">
 <h2>Reported Complaint</h2><div class="prose">{{ $case->description }}</div>
 <h2 style="margin-top:20px">Current Supervisor Findings</h2>
 <div class="prose">{{ $case->investigation_notes ?: 'Investigation findings have not yet been saved.' }}</div>
 <h2 style="margin-top:20px">Admin-Recorded Employee Response</h2>
 <div class="prose">{{ $case->employee_response ?: 'No response recorded.' }}</div>
 <p class="muted" style="font-size:11px;margin-bottom:0">This response is recorded by the Admin; the system has not independently verified a staff-submitted statement. No automatic salary deduction or penalty is applied.</p>
</section>
<section class="sheet">
 <h2>Investigation Decision Timeline</h2>
 <p class="muted" style="font-size:12px">Newest event first. Each event is preserved in the audit register; repeated older review saves are shown rather than hidden.</p>
 <div class="timeline">
 @forelse($audits as $event)
   @php
    $amOld=json_decode((string)($event->previous_json??''),true);
    $amNew=json_decode((string)($event->new_json??''),true);
    $amOld=is_array($amOld)?$amOld:[];
    $amNew=is_array($amNew)?$amNew:[];
    $amBefore=(string)($amOld['outcome']??$amOld['status']??'Not reviewed');
    $amAfter=(string)($amNew['outcome']??$amNew['status']??'Reported');
    $amReason=trim((string)($amNew['correction_reason']??''));
    $amChanged=($amBefore!==$amAfter);
   @endphp
   <article class="event">
     <h3>{{ str_replace('_',' ',$event->action) }} <span class="muted">· {{ $event->created_at }} · Admin #{{ $event->actor_admin_id }}</span></h3>
     <p><strong>Decision:</strong>
       @if($amChanged){{ str_replace('_',' ',$amBefore) }} → @endif
       <strong>{{ str_replace('_',' ',$amAfter) }}</strong>
     </p>
     @if($amReason!=='')
      <p class="audit-note"><strong>Recorded correction reason:</strong> {{ $amReason }}</p>
     @elseif($event->action==='INVESTIGATION_REVIEW' && $amChanged && count($amOld)>0)
      <p class="subdued">Earlier change: no separate correction reason was captured by the old system.</p>
     @endif
     @if(!empty($amNew['investigation_notes']) && (!isset($amOld['investigation_notes']) || $amNew['investigation_notes']!==$amOld['investigation_notes']))
      <details><summary>Supervisor note recorded</summary><p class="audit-note">{{ $amNew['investigation_notes'] }}</p></details>
     @endif
     @if(!empty($amNew['employee_response']) && (!isset($amOld['employee_response']) || $amNew['employee_response']!==$amOld['employee_response']))
      <details><summary>Admin-recorded employee response</summary><p class="audit-note">{{ $amNew['employee_response'] }}</p></details>
     @endif
     @if($event->action==='REPORTED')
      <p class="subdued">Original complaint recorded. Subsequent investigation events do not change the original ROT.</p>
     @endif
   </article>
 @empty <p>No audit history was found. Investigate complaint data integrity.</p>
 @endforelse
 </div>
</section>
<section class="sheet no-print">
 <details class="correction" @if($errors->any()) open @endif>
 <summary>Admin Only — Add / Correct Investigation Decision</summary>
 <p class="muted">Review the current finding above. Once reviewed, any change to decision, findings, or recorded response requires an explicit reason. Changing a final outcome also requires a checkbox.</p>
 <form method="post" action="{{ route('erp.rot-work-complaints.review',$case->id) }}">
 @csrf
 <input type="hidden" name="expected_audit_id" value="{{ $audits->first()->id ?? 0 }}">
 <label for="am244-outcome">Investigation Decision</label>
 <select name="outcome" id="am244-outcome" required>
 @foreach(['UNDER_REVIEW'=>'Under Review (not confirmed)','WORKMANSHIP_CONFIRMED'=>'Workmanship Confirmed','PART_FAILURE'=>'Part Failure','CUSTOMER_MISUSE'=>'Customer Misuse','NOT_WORKMANSHIP'=>'Not Workmanship','INCONCLUSIVE'=>'Inconclusive'] as $value=>$name)
  <option value="{{ $value }}" @selected(old('outcome',$case->outcome??'UNDER_REVIEW')===$value)>{{ $name }}</option>
 @endforeach
 </select>
 <label for="am244-note">Supervisor Investigation Findings</label>
 <textarea name="investigation_notes" id="am244-note" rows="4" minlength="12" maxlength="3000" required>{{ old('investigation_notes',$case->investigation_notes) }}</textarea>
 <label for="am244-response">Admin-Recorded Employee Response (optional)</label>
 <textarea name="employee_response" id="am244-response" rows="3" maxlength="3000">{{ old('employee_response',$case->employee_response) }}</textarea>
 <label for="am244-reason">Reason for Correcting a Reviewed Case (required when applicable)</label>
 <textarea name="correction_reason" id="am244-reason" rows="2" maxlength="1000" placeholder="Explain why this decision or recorded detail is being corrected">{{ old('correction_reason') }}</textarea>
 <label class="checkbox"><input type="checkbox" name="confirm_outcome_change" value="1" @checked(old('confirm_outcome_change')=='1')>
 I have reviewed the existing decision and explicitly authorise changing a previously saved final outcome.
 </label>
 <p><button type="submit">Save Decision with Audit</button></p>
 </form>
 </details>
</section>
</main></body></html>
