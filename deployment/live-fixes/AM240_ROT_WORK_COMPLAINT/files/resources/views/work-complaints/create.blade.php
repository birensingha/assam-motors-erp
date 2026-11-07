<!doctype html><html><head><meta charset="utf-8"><title>Report Work Complaint</title></head><body style="font-family:Arial;max-width:760px;margin:32px auto;padding:12px">
<h2>ASSAM MOTORS — Report Work Complaint</h2>
<p>Job Card #{{ $job }} · Completed ROT #{{ $rot }} · Staff: {{ $staff->name ?? 'Unknown' }} ({{ $staff->staff_code ?? '' }})</p>
<p>Original ROT, times and billing will not be changed. A reported complaint is not a confirmed mechanic error.</p>
@if($errors->any())<div style="color:#a22">{{ implode(' | ',$errors->all()) }}</div>@endif
<form method="post" action="{{ route('erp.rot-work-complaints.store',[$job,$rot]) }}">@csrf
<label>Complaint description (actual customer report)</label>
<textarea name="description" rows="5" minlength="12" maxlength="3000" required style="display:block;width:100%;margin:10px 0">{{ old('description') }}</textarea>
<button type="submit">Register Complaint — Under Review</button></form>
<p><a href="{{ route('erp.job-cards.show',$job) }}">Back to Job Card</a></p></body></html>
