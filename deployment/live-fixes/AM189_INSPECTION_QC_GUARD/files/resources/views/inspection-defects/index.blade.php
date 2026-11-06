<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vehicle Inspection Defects · Assam Motors</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2f6fa;font-family:Arial,sans-serif;color:#1b354f;font-size:13px}
header{background:#12385e;color:white;padding:19px 24px}header h1{margin:0;font-size:22px}header p{margin:5px 0 0;color:#cbddea}
main{max-width:1120px;padding:18px;margin:auto}.panel{background:white;border:1px solid #d7e2ee;border-radius:12px;padding:18px;margin:13px 0}
h2{font-size:16px;margin:0 0 12px;color:#153b60}.muted{color:#657b8e}.notice{padding:12px;background:#fff7e5;border-left:4px solid #dda83a;margin:10px 0}
.success{padding:12px;background:#e6f4ea;color:#195e3b}.error{padding:12px;background:#fff0f0;color:#992a2a}
button,.btn{border:0;background:#115181;color:white;padding:10px 13px;border-radius:7px;font-weight:bold;cursor:pointer;text-decoration:none;display:inline-block}
button.secondary,.btn.secondary{background:#e4edf5;color:#16456e}
label{display:block;font-weight:700;margin:9px 0 4px}select,textarea{width:100%;padding:10px;border:1px solid #c5d5e2;border-radius:8px;font:inherit}
.finding{border:1px solid #d8e1eb;border-left:5px solid #b54c45;border-radius:9px;padding:13px;margin:12px 0}
.finding.verified{border-left-color:#19855f}.top{display:flex;justify-content:space-between;gap:10px;align-items:center}
.status{font-size:11px;font-weight:bold;color:#a1322d}.verified .status{color:#197d54}
.forms{display:grid;grid-template-columns:1fr 1fr;gap:14px}.pill{padding:5px 8px;background:#edf1f5;border-radius:7px}
p{line-height:1.6}
.identity-bar{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px;max-width:1120px}
.identity-field{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:9px;padding:11px 13px;min-width:0}
.identity-field .caption{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#bed4ea;font-weight:700;margin-bottom:4px}
.identity-field .value{font-size:17px;color:#fff;font-weight:700;overflow-wrap:anywhere}
.identity-field .missing{color:#fff1bc;font-size:13px}
@media(max-width:700px){.forms{grid-template-columns:1fr}.top{align-items:flex-start;flex-direction:column}.identity-bar{grid-template-columns:1fr}.identity-field{padding:8px 11px}}
</style></head><body>
<header>
<h1>ASSAM MOTORS · Inspection Defects</h1>
<p>Job Card ID #{{ $job->id }} · Quality Check protection</p>
<div class="identity-bar">
  <div class="identity-field">
    <div class="caption">Job Card No.</div>
    <div class="value {{ $identity['job_no']==='Not recorded' ? 'missing' : '' }}">{{ $identity['job_no'] }}</div>
  </div>
  <div class="identity-field">
    <div class="caption">Vehicle No.</div>
    <div class="value {{ $identity['vehicle_no']==='Not recorded' ? 'missing' : '' }}">{{ $identity['vehicle_no'] }}</div>
  </div>
  <div class="identity-field">
    <div class="caption">Customer Name</div>
    <div class="value {{ $identity['customer_name']==='Not recorded' ? 'missing' : '' }}">{{ $identity['customer_name'] }}</div>
  </div>
</div>
</header>
<main>
{{-- AM202_INSPECTION_HISTORY_AWARENESS — advisory only, preserves all prior inspection data --}}
<div class="panel">
  <div class="top">
    <h2>Saved Inspection · {{ $inspectionMeta['saved_count'] }} points</h2>
    <span class="pill">{{ $inspectionMeta['current_30'] ? 'Current canonical 30-point' : 'Historical / noncanonical format' }}</span>
  </div>
  @if(!$inspectionMeta['current_30'])
    <p class="notice">This Job Card has {{ $inspectionMeta['saved_count'] }} saved inspection points; the current checklist contains {{ $inspectionMeta['official_count'] }}. Existing 10/20-point or other historical answers are preserved exactly as submitted. This does <strong>not</strong> automatically add missing points, change Completed status, or bypass unresolved defects before QC PASS.</p>
  @else
    <p class="muted">Saved point names match the current official 30-point checklist. Inspection findings and QC verification remain independently controlled.</p>
  @endif
  @if($inspectionMeta['missing_defect_notes']>0)
    <p class="notice"><strong>Historical observation gap:</strong> {{ $inspectionMeta['missing_defect_notes'] }} Fair/Poor finding(s) have no saved inspector remarks. Do not invent or silently rewrite original observations. Record only actual repairs and independent re-inspection evidence.</p>
  @endif
</div>
<a class="btn secondary" href="{{ route('erp.job-cards.show',$job->id) }}">← Back to Job Card</a>
@if(session('success'))<div class="panel success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="panel error">{{ $errors->first() }}</div>@endif
<div class="panel" id="approved-work-qc">
<h2>QC of Approved Work — Separate from Full Vehicle QC</h2>
<p class="muted">Customer-declined items are <strong>unrepaired</strong>. Complete QC for genuinely approved work separately. This record does NOT trigger Full QC PASS, Ready for Delivery, or automatically release an unsafe vehicle.</p>
@if($conditionalReview)
<p class="notice">Saved approved-work check: <strong>{{ $conditionalReview->approved_work_result }}</strong> · Risk review: <strong>{{ $conditionalReview->assessment }}</strong>. Full vehicle QC remains subject to the existing guard.</p>
@endif
<form method="post" action="{{ route('erp.job-cards.inspection-defects.approved-work-qc',$job->id) }}">
@csrf
<label>Actual QC Result — Customer Approved Work Only</label>
<select name="approved_work_result" required><option value="PASS">PASS — Approved work checked</option><option value="FAIL">FAIL — Further work needed</option></select>
<label>Work checked / test findings</label>
<textarea name="approved_work_findings" rows="3" minlength="12" maxlength="2000" required placeholder="Describe actual work checked, tests performed, readings/results and any outstanding complaint"></textarea>
<label>Conditional Release Risk Assessment</label>
<select name="assessment" required><option value="HOLD">HOLD — No conditional risk clearance</option><option value="RISK_REVIEWED">RISK REVIEWED — Documentary assessment only (NOT delivery approval)</option></select>
<label>Safety / Risk Assessment (required for Risk Reviewed)</label>
<textarea name="risk_assessment" rows="3" maxlength="2000" placeholder="Actual vehicle risk assessment, roadworthiness concerns and mitigations"></textarea>
<label>Signed Customer Acknowledgement Reference (required for Risk Reviewed)</label>
<textarea name="customer_acknowledgement_ref" rows="2" maxlength="500" placeholder="Customer signed notice/reference number/date; must match recorded written refusal"></textarea>
<button type="submit">Save Approved Work QC &amp; Risk Assessment</button>
</form>
<p class="notice"><strong>Important:</strong> RISK REVIEWED is not vehicle-release authorization. Keep Full QC PASS blocked for unrepaired defects; release requires a separate approved delivery workflow and safety/legal assessment.</p>
</div>
<div class="panel">
<h2>Inspection Findings & QC PASS Control</h2>
<p>Inspection submit can finish with Fair/Poor findings. QC PASS remains blocked until each finding has a recorded repair and an independently checked verification. Original inspection answers stay unchanged.</p>
<form method="post" action="{{ route('erp.job-cards.inspection-defects.sync',$job->id) }}">
@csrf
<button type="submit">Register Saved Inspection Defects</button>
</form>
<div class="notice"><strong>Safety rule:</strong> Status OPEN or RECTIFIED is not verified. Select actual repairing staff and enter repair details; then a qualified independent reviewer must verify. This does not bypass QC by changing the original inspection.</div>
</div>
@forelse($findings as $f)
<div class="panel finding {{ $f['verified'] ? 'verified' : '' }}">
<div class="top">
<div><h2>{{ $f['label'] }}</h2><span class="muted">Original {{ strtoupper($f['condition']) }} · {{ strtoupper($f['severity']) }}</span></div>
<span class="status pill">{{ $f['status'] }}</span>
</div>
<p><strong>Inspector finding:</strong> {{ $f['remarks'] ?: 'No observation provided' }}</p>
@if(!$f['record'])
<p class="notice">This point is not registered. Click “Register Saved Inspection Defects” above before recording a repair.</p>
@elseif(!$f['same'])
<p class="error">Inspection findings changed since registration. Admin review required; QC PASS remains blocked.</p>
@elseif($f['verified'])
<p class="success">Verified after repair. Historical defect and audit are retained.</p>
@else
<div class="forms">
@if(in_array($f['record']->status,['OPEN','RECTIFIED'],true))
<form method="post" action="{{ route('erp.job-cards.inspection-defects.decline',$f['record']->id) }}" style="border:1px solid #e8c987;border-radius:9px;padding:12px;margin:10px 0;background:#fffbf0">
@csrf
<h2>Customer Declined Repair — separate from QC PASS</h2>
<p class="muted">Document an actual refusal. This does not mean the problem was fixed and will not release the Full QC PASS safety block.</p>
<label>Customer decision / refusal reason</label>
<textarea name="decline_reason" rows="3" minlength="12" maxlength="2000" required placeholder="What work was advised, what did customer decline, how and when were risks explained?"></textarea>
<label>Customer Acknowledgement</label>
<select name="decline_acknowledgement" required>
<option value="PENDING">Pending</option>
<option value="WRITTEN">Signed / documented written acknowledgement</option>
<option value="VERBAL">Verbal only</option>
<option value="REFUSED_TO_SIGN">Customer refused to sign</option>
</select>
<label>Evidence reference (required for written acknowledgement)</label>
<textarea name="decline_evidence_ref" rows="2" maxlength="500" placeholder="Customer signed form reference / date, if available"></textarea>
<button type="submit">Save Customer Declined — Keep QC Hold</button>
</form>
@endif
@if($f['record']->status==='DECLINED')
<div class="notice"><strong>Customer Declined — Unrepaired.</strong> Full QC PASS remains blocked. Safety release requires a separate authorised risk decision and customer disclosure.</div>
<p><strong>Recorded refusal:</strong> {{ $f['record']->decline_reason ?? '' }}</p>
<p><strong>Customer acknowledgement:</strong> {{ $f['record']->decline_acknowledgement ?? 'PENDING' }}</p>
<details style="margin-top:12px">
<summary class="btn secondary" style="cursor:pointer">Reopen for Repair (only if customer changes decision)</summary>
<form method="post" action="{{ route('erp.job-cards.inspection-defects.reopen-declined',$f['record']->id) }}" style="margin-top:10px">
@csrf
<label>Why is repair being reopened?</label>
<textarea name="reopen_reason" minlength="8" maxlength="2000" required rows="2"></textarea>
<button type="submit">Confirm Reopen — OPEN</button>
</form>
</details>
@endif
@if($f['record']->status==='OPEN')
<form method="post" action="{{ route('erp.job-cards.inspection-defects.repair',$f['record']->id) }}">
@csrf
<h2>1 · Record Repair</h2>
<label>Repairing technician</label>
<select name="repair_staff_id" required><option value="">Select active staff</option>
@foreach($staff as $person)<option value="{{ $person->id }}">{{ $person->name }} ({{ $person->staff_code ?? '' }})</option>@endforeach
</select>
<label>Actual repair work performed</label>
<textarea name="repair_note" rows="3" minlength="8" maxlength="2000" required placeholder="Which work was completed, when and on which component?"></textarea>
<button type="submit">Save Repair — RECTIFIED</button>
</form>
@endif
@if($f['record']->status==='RECTIFIED')
<form method="post" action="{{ route('erp.job-cards.inspection-defects.verify',$f['record']->id) }}">
@csrf
<h2>2 · Independent Re-inspection</h2>
<label>Test result</label>
<select name="result" required><option value="PASS">PASS — Defect resolved</option><option value="FAIL">FAIL — Reopen defect</option></select>
@if($f['severity']==='safety')
<label>Independent Reviewer (actual person who checked repair)</label>
<select name="reviewer_staff_id">
<option value="">Choose reviewer — different from repair technician</option>
@foreach($staff as $person)
@if((int)$person->id !== (int)($f['record']->repair_staff_id ?? 0))
<option value="{{ $person->id }}">{{ $person->name }} ({{ $person->staff_code ?? '' }})</option>
@endif
@endforeach
</select>
<label style="font-weight:normal"><input type="checkbox" name="independent_attestation" value="1" style="width:auto"> I confirm the named reviewer personally performed an independent test, and I am recording their actual findings as the responsible Admin. Required for PASS only.</label>
@endif
<label>Re-inspection and test findings</label>
<textarea name="verification_note" rows="3" minlength="8" maxlength="2000" required placeholder="Describe actual recheck: test performed, results and observations"></textarea>
<button type="submit">Save Re-inspection Result</button>
@if($f['severity']==='safety')
<p class="muted">Safety repairs need an independent reviewer, not the same repairing technician.</p>
@else
<p class="muted">Record actual re-inspection findings. Safety reviewer attestation is not required for this normal defect.</p>
@endif
</form>
@endif
</div>
@endif
</div>
@empty
<div class="panel success">No Fair/Poor inspection findings recorded for this Job Card. Existing QC workflow remains unchanged.</div>
@endforelse
</main></body></html>
