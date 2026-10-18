<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>QC Review — {{ $card->job_no }} — ASSAM MOTORS</title>
<style>
:root{--navy:#092c51;--blue:#155c97;--edge:#dae5f0;--text:#163754;--sub:#687e94;--bg:#eff4f9}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif}
a{color:inherit}
header{background:linear-gradient(115deg,#092c51,#12668e);color:white;padding:18px max(18px,calc((100vw - 1120px)/2));border-bottom:3px solid #e6a629}
header strong{display:block;letter-spacing:.06em;font-size:11px}header h1{font-size:24px;margin:7px 0}
header small{color:#b9dbf0}
header nav{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}header a{display:inline-block;padding:8px 13px;border:1px solid #ffffff50;border-radius:9px;text-decoration:none;font-size:12px;color:#fff}
main{max-width:1120px;margin:auto;padding:16px 18px 40px}
.note{padding:11px 14px;border-left:4px solid #2a7fb7;background:#e7f4fc;color:#235b83;border-radius:10px;margin:13px 0;font-size:12px}
.note.warning{background:#fff8e7;border-color:#db9b21;color:#875c12}
.note.success{background:#e9f8ef;border-color:#18a05d;color:#216744}
.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px;margin:13px 0}
.field{background:white;border:1px solid var(--edge);border-radius:11px;padding:12px;min-width:0;word-wrap:break-word}
.field small{display:block;text-transform:uppercase;font-size:10px;font-weight:800;color:var(--sub);letter-spacing:.045em}
.field strong{display:block;margin-top:6px;font-size:14px}
.card{background:#fff;border:1px solid var(--edge);border-radius:14px;padding:15px 17px;margin:14px 0;box-shadow:0 4px 18px #16365708}
.card h2{margin:0 0 9px;font-size:17px;color:#153f67}
.card p{margin:5px 0;font-size:13px}
.scroll{max-width:100%;overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:620px;font-size:12px}
th{text-align:left;background:#edf4fa;color:#315676;text-transform:uppercase;font-size:10px}
th,td{padding:10px 8px;border-bottom:1px solid #e3eaf1;vertical-align:top}
.num{text-align:right;white-space:nowrap}
.qty{font-variant-numeric:tabular-nums}
.muted{color:var(--sub)}
form{max-width:100%}
form label{display:block;font-weight:800;color:#2d5679;font-size:12px;margin:11px 0 5px}
form textarea{display:block;width:100%;min-height:74px;border-radius:10px;border:1px solid #cad8e6;padding:11px;background:#fcfeff;color:#193c5a;font:inherit}
form textarea:focus{outline:3px solid #a9d5fc}
.buttons{display:flex;flex-wrap:wrap;gap:10px;margin-top:15px}
button{font:800 13px Arial,sans-serif;min-height:44px;border:0;border-radius:10px;padding:11px 18px;cursor:pointer}
button.pass{background:#148449;color:white}button.fail{background:#b33236;color:white}
button:focus-visible,a:focus-visible{outline:3px solid #eda827;outline-offset:3px}
.check{display:flex;align-items:flex-start;gap:9px!important;font-size:12px!important}
.check input{margin-top:3px;width:17px;height:17px}
.role{border-radius:999px;padding:4px 9px;background:#eaf7ee;color:#146636;font-weight:850;font-size:10px}
pre{white-space:pre-wrap;font:inherit}
@media(max-width:800px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){header h1{font-size:19px}main{padding:12px}.grid{grid-template-columns:1fr}.card{padding:12px}}
@media print{header nav,.buttons,.review-form,.note.warning{display:none}body{background:#fff}main{max-width:none;padding:0}.card,.field{box-shadow:none;break-inside:avoid}}
</style>
</head><body>
<header>
 <strong>ASSAM MOTORS · QUALITY CONTROL</strong>
 <h1>Read-only Job Card Summary</h1>
 <small>{{ $card->job_no }} · {{ $card->vehicle_reg_no }} · Staff ID {{ $user->staff_id }}</small>
 <nav><a href="{{ route('staff.portal') }}">← My Staff Portal</a><a href="#qc-decision">Quality Decision ↓</a></nav>
</header>
<main>
 @if(session('success'))<div class="note success" role="status">{{ session('success') }}</div>@endif
 @if($errors->any())<div class="note warning" role="alert">{{ $errors->first() }}</div>@endif
 <div class="note"><strong>QC Staff: {{ $user->name }}</strong> · Assigned staff can review only; no Parts, Labour, ROT, Estimate or amount edits from this page.</div>
 <section class="grid" aria-label="Job Card overview">
   <div class="field"><small>Customer</small><strong>{{ $card->customer_name ?: '—' }}</strong><span class="muted">{{ $card->customer_phone ?: '—' }}</span></div>
   <div class="field"><small>Vehicle</small><strong>{{ $card->vehicle_reg_no }}</strong><span class="muted">{{ trim((string)($card->vehicle_make??'').' '.(string)($card->vehicle_model??'')) }}</span></div>
   <div class="field"><small>Job / Inspection</small><strong>{{ strtoupper($card->status) }}</strong><span class="muted">Inspection: {{ $card->inspection_status??'Pending' }}</span></div>
   <div class="field"><small>QC Assignment</small><strong>{{ strtoupper($qc->result) }}</strong><span class="muted">{{ $qc->checked_at ? \Carbon\Carbon::parse($qc->checked_at)->format('d M Y h:i A') : '—' }}</span></div>
 </section>
 <section class="card">
  <h2>Inspection / Final Diagnosis / Technician Decision</h2>
  <p><strong>Customer Complaint:</strong> {{ $card->customer_complaint??$card->customer_voice??'—' }}</p>
  <p><strong>Inspection:</strong> {{ strtoupper((string)($card->inspection_status??'PENDING')) }}</p>
  <p><strong>Diagnosis:</strong> {{ strtoupper((string)($card->diagnosis_status??'PENDING')) }} · {{ $card->final_diagnosis??'—' }}</p>
  <p><strong>Technician Decision:</strong> {{ strtoupper((string)($card->decision_status??'PENDING')) }}</p>
 </section>
 <section class="card">
  <h2>Parts / Materials</h2><div class="scroll">
  <table><thead><tr><th>Part / HSN</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Disc.</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
   @forelse($parts as $p)
   <tr><td><strong>{{ $p->part_name }}</strong><br><small>{{ $p->part_code }} · HSN: {{ $p->hsn_code??'—' }}</small>@if($p->part_note)<div class="muted">{{ $p->part_note }}</div>@endif</td>
       <td class="num">{{ number_format((float)$p->qty,2) }}</td><td class="num">₹{{ number_format((float)$p->rate,2) }}</td>
       <td class="num">{{ number_format((float)($p->discount_percent??0),2) }}%</td><td class="num">₹{{ number_format((float)$p->total,2) }}</td>
       <td>{{ $p->issue_status??'—' }}</td></tr>
   @empty <tr><td colspan="6" class="muted">No Parts recorded.</td></tr>@endforelse
  </tbody></table></div>
 </section>
 <section class="card">
  <h2>Labour / ROT Work</h2><div class="scroll">
  <table><thead><tr><th>ROT / Description</th><th>Mechanic</th><th class="num">Std Hrs</th><th class="num">Rate</th><th class="num">Amount</th><th>Status</th></tr></thead><tbody>
   @forelse($labour as $l)
    <tr><td><strong>{{ $l->rot_code??'—' }}</strong><br>{{ $l->description??'—' }}@if($l->rot_note)<div class="muted">{{ $l->rot_note }}</div>@endif</td>
    <td>{{ $l->mechanic_name??'—' }}</td><td class="num">{{ number_format((float)($l->standard_hours??0),2) }}</td>
    <td class="num">₹{{ number_format((float)($l->labour_rate??0),2) }}</td><td class="num">₹{{ number_format((float)($l->total??0),2) }}</td><td>{{ $l->execution_status??'—' }}</td></tr>
   @empty<tr><td colspan="6" class="muted">No Labour/ROT recorded.</td></tr>@endforelse
  </tbody></table></div>
 </section>
 <section class="grid">
  <div class="field"><small>Parts Total</small><strong>₹{{ number_format((float)($card->parts_total??0),2) }}</strong></div>
  <div class="field"><small>Labour Total</small><strong>₹{{ number_format((float)($card->labour_total??0),2) }}</strong></div>
  <div class="field"><small>Round Off</small><strong>₹{{ number_format((float)($card->round_off??0),2) }}</strong></div>
  <div class="field"><small>Recorded Final Total</small><strong>₹{{ number_format((float)($card->grand_total??0),2) }}</strong></div>
 </section>
 @if($previous->isNotEmpty())<section class="card"><h2>Previous Quality Check History</h2>
  @foreach($previous as $row)<p>{{ strtoupper($row->result) }} · {{ $row->checked_at ? \Carbon\Carbon::parse($row->checked_at)->format('d M Y h:i A') : '—' }} · {{ $row->notes??'' }}</p>@endforeach
 </section>@endif
 <section class="card" id="qc-decision">
  <h2>QC Result · Assigned to You <span class="role">AUTHORISED QC STAFF</span></h2>
  @if($pending)
   <p class="muted">After checking the vehicle and read-only Job Card Summary, submit one QC decision. It is linked to your login automatically. No re-selection of QC staff.</p>
   <form class="review-form" method="post" action="{{ route('staff.qc.submit',$card->id) }}">
     @csrf
     <label for="qc-failed-item">Failed Item / Defect (required for FAIL)</label>
     <textarea id="qc-failed-item" name="failed_item" rows="2" maxlength="2000" placeholder="Describe failed part, ROT, symptom or rework needed."></textarea>
     <label for="qc-notes">QC Notes (optional)</label>
     <textarea id="qc-notes" name="notes" rows="2" maxlength="3000" placeholder="Test drives, electrical/visual checks, observations, etc."></textarea>
     <label class="check"><input type="checkbox" name="reviewed" value="1" required> I inspected the vehicle and reviewed this Job Card Summary before submitting QC.</label>
     <div class="buttons">
       <button class="pass" type="submit" name="result" value="PASS">✓ QUALITY PASS</button>
       <button class="fail" type="submit" name="result" value="FAIL">✕ QUALITY FAIL / REWORK</button>
     </div>
   </form>
  @else
   <div class="note success">Quality Check is currently {{ strtoupper((string)$qc->result) }}. No further QC result can be submitted for this assignment.</div>
  @endif
 </section>
</main>
</body></html>
