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
<a class="btn secondary" href="{{ route('erp.job-cards.show',$job->id) }}">← Back to Job Card</a>
@if(session('success'))<div class="panel success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="panel error">{{ $errors->first() }}</div>@endif
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
<label>Re-inspection and test findings</label>
<textarea name="verification_note" rows="3" minlength="8" maxlength="2000" required placeholder="Describe actual recheck: test performed, results and observations"></textarea>
<button type="submit">Save Re-inspection Result</button>
<p class="muted">Safety repairs need an independent reviewer, not the same repairing technician.</p>
</form>
@endif
</div>
@endif
</div>
@empty
<div class="panel success">No Fair/Poor inspection findings recorded for this Job Card. Existing QC workflow remains unchanged.</div>
@endforelse
</main></body></html>
