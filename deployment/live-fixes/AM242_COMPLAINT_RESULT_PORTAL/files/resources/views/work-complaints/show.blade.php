<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Work Complaint #{{ $case->id }} | Assam Motors</title>
<style>
body{background:#f3f6fb;color:#182b40;font:14px/1.5 Arial,sans-serif;margin:0}main{max-width:1040px;margin:28px auto;padding:0 15px}.sheet{background:white;border:1px solid #cbd7e4;border-radius:12px;padding:23px;margin:0 0 15px;box-shadow:0 2px 12px #1b41620d}.top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}h1{font-size:22px;margin:2px 0}h2{font-size:16px;color:#183c62;margin:0 0 12px}.muted{color:#526a80}.pill{border-radius:18px;padding:5px 10px;background:#e8f0fb;color:#235589;font-size:12px;font-weight:bold}.confirmed{background:#fdebe9;color:#9d3024}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.field{background:#f6f9fc;border-radius:8px;padding:11px}.field label{display:block;text-transform:uppercase;font-size:10px;letter-spacing:.06em;color:#687f96;font-weight:700}.field div{font-weight:700;overflow-wrap:anywhere}.textblock{white-space:pre-wrap;overflow-wrap:anywhere;border-left:3px solid #7da8cd;background:#f7fafe;padding:13px;border-radius:4px}nav{display:flex;gap:12px;flex-wrap:wrap}a{color:#125a98;font-weight:700;text-decoration:none}a:hover{text-decoration:underline}button{background:#155a96;color:white;border:0;border-radius:6px;padding:10px 14px;font-weight:bold;cursor:pointer}textarea,select{box-sizing:border-box;width:100%;padding:9px;margin:5px 0 13px;border:1px solid #a9bdd0;border-radius:6px}details>summary{font-weight:700;cursor:pointer;padding:7px 0}.audit{border-top:1px solid #e2eaf2;padding:11px 0}.audit pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#f3f7fb;padding:10px;font-size:11px}.warning{background:#fff7e8;border:1px solid #f1cb8d;padding:12px;border-radius:8px}small{font-size:11px}@media(max-width:640px){.grid{grid-template-columns:1fr}.sheet{padding:16px}h1{font-size:19px}}@media print{body{background:#fff}main{max-width:none}.sheet{box-shadow:none;break-inside:avoid}nav,button,.editpanel{display:none!important}}
</style></head><body><main>
<nav><a href="{{ route('erp.rot-work-complaints.register') }}">All Complaints</a><a href="{{ route('erp.rot-work-complaints.monthly') }}">Monthly Staff Performance</a><a href="{{ route('erp.job-cards.show',$case->job_card_id) }}">Job Card</a></nav>
<section class="sheet">
 <div class="top"><div><small class="muted">ASSAM MOTORS · WORK COMPLAINT INVESTIGATION RESULT</small><h1>Complaint #{{ $case->id }}</h1></div>
 <span class="pill {{ $case->outcome==='WORKMANSHIP_CONFIRMED'?'confirmed':'' }}">{{ str_replace('_',' ',$case->outcome ?: $case->status) }}</span></div>
 <div class="grid" style="margin-top:16px">
  <div class="field"><label>Job Card</label><div>JC #{{ $case->job_card_id }}</div></div>
  <div class="field"><label>Original ROT / Session</label><div>{{ $case->rot_session_id }}</div></div>
  <div class="field"><label>Original Mechanic</label><div>{{ $staff->name ?? ('Staff #'.$case->staff_id) }} ({{ $staff->staff_code ?? '—' }})</div></div>
  <div class="field"><label>Reported On</label><div>{{ $case->reported_at }}</div></div>
  <div class="field"><label>Investigation Status</label><div>{{ $case->status }}</div></div>
  <div class="field"><label>Decision Recorded</label><div>{{ $case->reviewed_at ?? 'Pending decision' }}</div></div>
 </div>
</section>
<section class="sheet"><h2>Customer / Workshop Complaint</h2><div class="textblock">{{ $case->description }}</div></section>
<section class="sheet"><h2>Supervisor Findings & Outcome</h2>
 @if($case->investigation_notes)
 <div class="textblock">{{ $case->investigation_notes }}</div>
 @else <div class="warning">Investigation findings have not yet been recorded.</div>@endif
 <h2 style="margin-top:18px">Employee Response</h2>
 <div class="textblock">{{ $case->employee_response ?: 'No employee response recorded.' }}</div>
 @if($case->outcome==='WORKMANSHIP_CONFIRMED')
 <p class="muted"><strong>Performance treatment:</strong> 1 reported and 1 confirmed workmanship complaint for the report month. No automatic payroll deduction.</p>
 @elseif(in_array($case->status,['REPORTED','UNDER_REVIEW'],true))
 <p class="muted">Reported only. Not counted as a confirmed workmanship fault unless a supervisor confirms it.</p>
 @endif
</section>
<section class="sheet">
 <h2>Audited Complaint History</h2>
 @forelse($audits as $event)
  <div class="audit"><strong>{{ str_replace('_',' ',$event->action) }}</strong> · {{ $event->created_at }} · Admin ID {{ $event->actor_admin_id }}
    <details><summary>View recorded change</summary><pre>{{ $event->new_json }}</pre></details>
  </div>
 @empty <p>No audit records found. Check complaint data integrity.</p>@endforelse
</section>
<section class="sheet editpanel">
 <details><summary>Admin: Correct Investigation Decision (audited)</summary>
 <p class="muted">Any correction creates a new audit event; the original reported complaint is retained.</p>
 @if($errors->any())<p class="warning">{{ implode(' | ',$errors->all()) }}</p>@endif
 <form method="post" action="{{ route('erp.rot-work-complaints.review',$case->id) }}">@csrf
 <label for="am242-outcome">Investigation outcome</label>
 <select id="am242-outcome" name="outcome" required>
 @foreach(['UNDER_REVIEW'=>'Under Review','WORKMANSHIP_CONFIRMED'=>'Confirmed Workmanship Issue','PART_FAILURE'=>'Part Failure','CUSTOMER_MISUSE'=>'Customer Misuse','NOT_WORKMANSHIP'=>'Not Workmanship','INCONCLUSIVE'=>'Inconclusive'] as $v=>$label)
 <option value="{{ $v }}" @selected(old('outcome',$case->outcome??'UNDER_REVIEW')===$v)>{{ $label }}</option>
 @endforeach
 </select>
 <label for="am242-finding">Supervisor investigation findings</label>
 <textarea id="am242-finding" name="investigation_notes" rows="4" minlength="12" maxlength="3000" required>{{ old('investigation_notes',$case->investigation_notes) }}</textarea>
 <label for="am242-response">Employee response (only actual response)</label>
 <textarea id="am242-response" name="employee_response" rows="3" maxlength="3000">{{ old('employee_response',$case->employee_response) }}</textarea>
 <button type="submit">Save Audited Decision</button>
 </form></details>
</section>
</main></body></html>
