<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin QC Review · JC #{{ $job->id }} · Assam Motors</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#1a3550;font:13px/1.55 Arial,sans-serif}
main{max-width:1020px;margin:22px auto;padding:0 16px 36px}h1{margin:0 0 5px;font-size:23px;color:#133e65}
h2{font-size:16px;color:#163b5d;margin:0 0 12px}p{margin:7px 0}a{color:#165c9a;font-weight:700;text-decoration:none}
a:hover{text-decoration:underline}.nav{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:17px}
.card{background:white;border:1px solid #d8e4ee;border-radius:11px;margin:12px 0;padding:17px 19px}
.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
.cell{background:#f6f9fd;border:1px solid #e4ebf2;border-radius:7px;padding:10px;min-width:0}
.cell small{font-size:10px;text-transform:uppercase;color:#647b91;display:block}.cell strong{overflow-wrap:anywhere}
.notice{padding:12px 14px;border:1px solid #ebcda4;border-radius:8px;background:#fff9ed;margin:12px 0}
.success{padding:12px 14px;border:1px solid #b0d9bd;border-radius:8px;background:#f0faf2}
label{display:block;font-size:11px;font-weight:700;margin:12px 0 5px}
textarea,input[type=text]{width:100%;padding:9px 11px;border:1px solid #bacddd;border-radius:7px;font:inherit;resize:vertical}
button{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;font-weight:750;font-size:12px;border-radius:7px;border:0;color:white;background:#165f9c;cursor:pointer}
button.fail{background:#9f4436}.two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.hint{color:#657c91;font-size:11px}.checkline{display:flex;align-items:flex-start;gap:8px;line-height:1.5}
.checkline input{margin-top:3px}hr{border:0;border-top:1px solid #e6edf3;margin:20px 0}
@media(max-width:720px){.grid,.two{grid-template-columns:1fr}h1{font-size:20px}.card{padding:14px}}
</style></head><body><main>
<nav class="nav">
<a href="{{ route('erp.job-cards.show',$job->id) }}">← Back to Job Card</a>
@if(\Illuminate\Support\Facades\Route::has('erp.job-cards.inspection-defects'))
<a href="{{ route('erp.job-cards.inspection-defects',$job->id) }}">Inspection Defects & Independent Verification</a>
@endif
</nav>
<h1>Admin Quality Check Review</h1>
<p class="hint">ASSAM MOTORS · Job Card #{{ $job->id }} · Admin ERP session (not Staff Portal impersonation)</p>
@if(session('success'))<div class="success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="notice"><strong>QC result was not saved:</strong>
@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>@endif
<div class="card">
<h2>Existing QC Assignment</h2>
<div class="grid">
<div class="cell"><small>Job Card Status</small><strong>{{ $job->status }}</strong></div>
<div class="cell"><small>Public Status</small><strong>{{ $job->customer_public_status }}</strong></div>
<div class="cell"><small>Latest QC</small><strong>{{ $qc ? '#'.$qc->id.' / '.$qc->result : 'Not started' }}</strong></div>
<div class="cell"><small>Assigned Reviewer</small><strong>{{ $assigned->name ?? 'Not assigned' }}{{ $assigned ? ' · '.$assigned->staff_code : '' }}</strong></div>
<div class="cell"><small>Assigned Staff ID</small><strong>{{ $qc->assigned_staff_id ?? '—' }}</strong></div>
<div class="cell"><small>Logged-in Admin Linked Staff ID</small><strong>{{ $adminStaffId ?: 'Not linked' }}</strong></div>
</div>
@if($qc && !empty($qc->notes))<p><strong>Saved QC notes:</strong> {{ $qc->notes }}</p>@endif
</div>
@if(!$qc)
<div class="notice">QC is not started. Return to the Job Card and use the existing Start Quality Check workflow.</div>
@elseif(!$sameAssignedReviewer)
<div class="notice"><strong>Review belongs to another Staff ID.</strong> This Admin account is not linked to Staff #{{ $qc->assigned_staff_id }}. The QC result cannot be submitted here. An authorised Admin must correct the assignment using the normal audited QC workflow.</div>
@elseif(!$canRecord)
<div class="notice">Admin QC submission is unavailable: the latest QC must be PENDING and Job Card must be in the completed / Quality Check stage.</div>
@else
<div class="card">
<h2>Review the vehicle and record the result</h2>
<div class="notice"><strong>Safety requirement:</strong> Inspect the actual vehicle and its inspection defects before recording PASS. A prior technician's completion is not itself proof of independent verification. Existing server-side QC PASS safeguards remain mandatory.</div>
<div class="two">
<form method="post" action="{{ route('erp.job-cards.admin-qc-review.submit',$job->id) }}" id="am249-qc-pass">
@csrf
<input type="hidden" name="result" value="PASS">
<input type="hidden" name="expected_qc_id" value="{{ $qc->id }}">
<label for="pass_note">QC PASS note</label>
<textarea id="pass_note" name="notes" rows="5" maxlength="3000" required placeholder="Actual tests performed; evidence reviewed">{{ old('notes') }}</textarea>
<label for="pass_reason">Decision reason *</label>
<textarea id="pass_reason" name="reason" rows="3" maxlength="1000" required placeholder="Why does this job pass QC?">{{ old('reason') }}</textarea>
<label class="checkline"><input type="checkbox" name="checked_physical_vehicle" value="1" required> I personally reviewed this QC and the vehicle's actual work; I am authorised to record the outcome.</label>
<button type="submit" onclick="return confirm('Save QC PASS? Server safety/defect verification will still be enforced.')">Record QC PASS</button>
</form>
<form method="post" action="{{ route('erp.job-cards.admin-qc-review.submit',$job->id) }}" id="am249-qc-fail">
@csrf
<input type="hidden" name="result" value="FAIL">
<input type="hidden" name="expected_qc_id" value="{{ $qc->id }}">
<label for="fail_item">Failed item / complaint *</label>
<textarea id="fail_item" name="failed_item" rows="3" maxlength="2000" required placeholder="Specific item requiring rework">{{ old('failed_item') }}</textarea>
<label for="fail_note">QC FAIL note</label>
<textarea id="fail_note" name="notes" rows="3" maxlength="3000" required placeholder="What did independent QC observe?">{{ old('notes') }}</textarea>
<label for="fail_reason">Decision reason *</label>
<textarea id="fail_reason" name="reason" rows="3" maxlength="1000" required placeholder="Why does this job fail QC?">{{ old('reason') }}</textarea>
<label class="checkline"><input type="checkbox" name="checked_physical_vehicle" value="1" required> I checked the vehicle and confirm this failure finding.</label>
<button type="submit" class="fail" onclick="return confirm('Record QC FAIL and return the job to rework?')">Record QC FAIL / Rework</button>
</form>
</div>
<p class="hint">All QC writes are delegated to the existing Admin QC result handler. The original Staff-only QC page and its access restrictions remain unchanged. No automatic QC PASS occurs from opening this page.</p>
</div>
@endif
</main></body></html>
