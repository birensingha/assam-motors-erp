<!doctype html><html><head><meta charset="utf-8"><title>Work Complaint</title></head><body style="font-family:Arial;max-width:820px;margin:32px auto;padding:12px">
<h2>ASSAM MOTORS — Work Complaint #{{ $case->id }}</h2>
<p>JC #{{ $case->job_card_id }} · Completed ROT #{{ $case->rot_session_id }} · {{ $staff->name ?? 'Staff ID '.$case->staff_id }} · Reported: {{ $case->reported_at }}</p>
<p><strong>Status:</strong> {{ $case->status }} · <strong>Finding:</strong> {{ $case->outcome ?? 'Not determined' }}</p>
<p><strong>Reported complaint:</strong> {{ $case->description }}</p>
@if($errors->any())<div style="color:#a22">{{ implode(' | ',$errors->all()) }}</div>@endif
<form method="post" action="{{ route('erp.rot-work-complaints.review',$case->id) }}">@csrf
<label>Investigation outcome</label><select name="outcome" required style="display:block;margin:10px 0">
<option value="UNDER_REVIEW">Under Review (no fault confirmed)</option>
<option value="WORKMANSHIP_CONFIRMED">Confirmed Workmanship Issue</option>
<option value="PART_FAILURE">Part Failure</option>
<option value="CUSTOMER_MISUSE">Customer Misuse</option>
<option value="NOT_WORKMANSHIP">Not Workmanship</option>
<option value="INCONCLUSIVE">Inconclusive</option></select>
<label>Supervisor investigation findings</label>
<textarea name="investigation_notes" minlength="12" maxlength="3000" rows="4" required style="display:block;width:100%;margin:10px 0">{{ old('investigation_notes',$case->investigation_notes) }}</textarea>
<label>Employee response (optional, do not fabricate)</label>
<textarea name="employee_response" maxlength="3000" rows="3" style="display:block;width:100%;margin:10px 0">{{ old('employee_response',$case->employee_response) }}</textarea>
<button type="submit">Save Audited Decision</button></form>
<p><a href="{{ route('erp.job-cards.show',$case->job_card_id) }}">Back to Job Card</a></p></body></html>
