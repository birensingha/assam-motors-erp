<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Vehicle Health Report | {{ $job->job_no ?? $job->id }} | Assam Motors</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f2f5f9;color:#17324d;font-family:Arial,sans-serif}
.wrap{max-width:1050px;margin:25px auto;padding:0 14px 28px}
.sheet{background:#fff;border:1px solid #e0e6ee;border-radius:15px;overflow:hidden;box-shadow:0 10px 28px #1d41601a;margin-bottom:16px}
.header{background:#103961;color:white;padding:24px 28px}.brand{font-size:29px;letter-spacing:2px;font-weight:900}.brand small{font-size:12px;letter-spacing:0;font-weight:400;display:block;margin-top:4px;line-height:1.6}
.page-title{background:#e9f0f9;padding:15px 27px;border-bottom:2px solid #b2c5dc}
h1{margin:0;font-size:23px}h2{font-size:17px;color:#103961;margin:0 0 14px}
.kicker{font-size:11px;font-weight:700;color:#647c91;text-transform:uppercase;letter-spacing:.8px}
.area{padding:22px 27px}.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.item{background:#f6f8fb;border:1px solid #e0e6ee;padding:12px;border-radius:10px;min-width:0}
.item strong{display:block;margin-top:6px;word-break:break-word}
.score{display:flex;gap:20px;align-items:center;padding:16px;border:1px solid #ccddec;border-radius:12px}
.number{font-size:46px;color:#148565;font-weight:900}.muted{color:#687e90;font-size:12px;line-height:1.5}
table{width:100%;border-collapse:collapse;font-size:13px}th{text-align:left;background:#edf3fa;color:#164367}td,th{border:1px solid #dce5ef;padding:10px;vertical-align:top}
.bad{color:#b33030;font-weight:800}.fair{color:#a56b13;font-weight:800}.good{color:#17835a;font-weight:800}
.btn{border:0;text-decoration:none;display:inline-block;padding:12px 16px;border-radius:9px;background:#0d446e;color:#fff;font-weight:700;font-size:13px;cursor:pointer}
.btn.green{background:#118568}.btn.gray{background:#e5edf5;color:#154368}
.actions{display:flex;gap:9px;flex-wrap:wrap;margin:12px 0}.note{background:#fff8e7;border-left:4px solid #dba62d;padding:12px;font-size:13px}
form .fields{display:grid;grid-template-columns:repeat(3,1fr);gap:11px}label{font-weight:700;font-size:12px}
input,textarea{display:block;width:100%;border:1px solid #c5d5e3;border-radius:8px;margin-top:5px;padding:11px;font:inherit}
.error{color:#a52727;font-size:13px;margin:9px 0}
.footer{padding:12px 27px;border-top:1px solid #d7e2ed;color:#62788c;font-size:11px}
@media(max-width:700px){.grid{grid-template-columns:repeat(2,1fr)}form .fields{grid-template-columns:1fr}.area{padding:15px}.brand{font-size:24px}}
</style></head>
<body><main class="wrap">
<div class="actions"><a class="btn gray" href="{{ url()->previous() }}">← Back</a>
<a class="btn" href="{{ $is_admin ? route('erp.job-cards.vehicle-health-pdf',$job->id) : route('staff.vehicle-health-pdf',$job->id) }}" target="_blank" rel="noopener">View PDF</a>
<a class="btn green" href="{{ ($is_admin ? route('erp.job-cards.vehicle-health-pdf',$job->id) : route('staff.vehicle-health-pdf',$job->id)).'?download=1' }}">Download PDF</a>
<button id="amShareHealthPdf" class="btn" type="button">Share PDF (WhatsApp)</button>
</div>
<div id="amShareHint" class="muted" aria-live="polite">Sharing uses your device share sheet. Select WhatsApp and the intended customer yourself.</div>
@if(session('success'))<p class="note">{{ session('success') }}</p>@endif
@if($errors->any())<p class="error" role="alert">{{ $errors->first() }}</p>@endif
<section class="sheet">
<div class="header"><div class="brand">ASSAM MOTORS<small>Bishnu Rabha Path, Hockey Stadium Rd, Sawkuchi, Guwahati-781040, Assam<br>9864043620 · service@assammotors.com · www.assammotors.com · GSTIN 18CAHPS7155P1ZQ</small></div></div>
<div class="page-title"><h1>VEHICLE HEALTH REPORT</h1><span class="muted">{{ $report_no }} · Generated {{ $generated_at }}</span></div>
<div class="area">
<div class="grid">
<div class="item"><span class="kicker">Job Card</span><strong>{{ $job->job_no ?? $job->id }}</strong></div>
<div class="item"><span class="kicker">Vehicle No.</span><strong>{{ $job->vehicle_reg_no ?? '—' }}</strong></div>
<div class="item"><span class="kicker">Vehicle</span><strong>{{ trim(($job->vehicle_make ?? '').' '.($job->vehicle_model ?? '')) }}</strong></div>
<div class="item"><span class="kicker">Customer</span><strong>{{ $job->customer_name ?? '—' }}</strong></div>
</div>
<div style="height:16px"></div>
<div class="score"><div class="number">{{ $health }}%</div><div><h2>Overall Vehicle Health</h2><div class="muted">Based on {{ count($points) }} submitted inspection points. Scoreable points are weighted; N/A and text readings are excluded.</div>
<div class="{{ count($defects) ? 'bad':'good' }}">{{ count($defects) }} issue(s) observed · {{ $safety_open }} safety-critical · Repair verification may be required</div></div></div>
</div></section>
<section class="sheet"><div class="area"><h2>Category-wise Condition</h2>
<table><thead><tr><th>Inspection Category</th><th>Checked Points</th><th>Health</th><th>Issues</th></tr></thead><tbody>
@foreach($categories as $category)<tr><td>{{ $category['name'] }}</td><td>{{ $category['points'] }}</td><td>{{ $category['score']===null?'N/A':$category['score'].'%' }}</td><td>{{ $category['defects'] }}</td></tr>@endforeach
</tbody></table></div></section>
<section class="sheet"><div class="area"><h2>Vehicle Valuation Assessment</h2>
<div class="note">Indicative valuation is benchmark-based, not an independently verified market quotation. Admin must enter a reference price and approve a final value. Health percentage is not the same as resale value.</div>
<div class="grid" style="margin-top:12px">
<div class="item"><span class="kicker">Market Benchmark</span><strong>{{ $valuation ? '₹'.number_format((float)$valuation->reference_market_price,0) : 'Not entered' }}</strong></div>
<div class="item"><span class="kicker">Estimated Repairs</span><strong>{{ $valuation ? '₹'.number_format((float)$valuation->estimated_repair_cost,0) : '—' }}</strong></div>
<div class="item"><span class="kicker">System Suggested</span><strong>{{ $system_value===null ? 'Pending benchmark' : '₹'.number_format($system_value,0) }}</strong></div>
<div class="item"><span class="kicker">Admin Approved</span><strong>{{ $is_final ? '₹'.number_format((float)$valuation->admin_final_value,0) : 'Not approved' }}</strong></div>
</div>
@if($valuation && $valuation->admin_note)<p class="muted"><b>Admin remarks:</b> {{ $valuation->admin_note }}</p>@endif
@if($is_admin)
<form method="post" action="{{ route('erp.job-cards.vehicle-health-valuation',$job->id) }}" style="margin-top:18px">
@csrf
<h2>Admin Valuation Review</h2>
<div class="fields">
<label>Reference market benchmark (₹)<input type="number" min="1" step=".01" name="reference_market_price" required value="{{ old('reference_market_price',$valuation->reference_market_price ?? '') }}"></label>
<label>Odometer (km)<input type="number" min="0" name="odometer_km" required value="{{ old('odometer_km',$valuation->odometer_km ?? 0) }}"></label>
<label>Expected repair cost (₹)<input type="number" min="0" step=".01" name="estimated_repair_cost" required value="{{ old('estimated_repair_cost',$valuation->estimated_repair_cost ?? 0) }}"></label>
<label>Admin final market value (₹) — optional<input type="number" min="0" step=".01" name="admin_final_value" value="{{ old('admin_final_value',$valuation->admin_final_value ?? '') }}"></label>
<label style="grid-column:span 2">Final valuation justification / market reference<textarea name="admin_note" rows="2" maxlength="1000">{{ old('admin_note',$valuation->admin_note ?? '') }}</textarea></label>
</div>
<p class="muted">Leave Admin final value empty to save a preliminary system suggestion only. Reason is mandatory to approve a value.</p>
<button class="btn green" type="submit">Save Admin Valuation (audited)</button>
</form>
@endif
</div></section>
<section class="sheet"><div class="area"><h2>Detailed Inspection & Observed Issues</h2>
<table><thead><tr><th>#</th><th>Inspection Point</th><th>Condition</th><th>Observation</th></tr></thead><tbody>
@foreach($points as $i=>$point)<tr><td>{{ $i+1 }}</td><td>{{ $point['name'] }}</td>
<td class="{{ $point['defect']?'bad':'good' }}">{{ strtoupper(str_replace('_',' ',$point['status'])) }}@if($point['critical']) · Safety @endif</td>
<td>{{ $point['remarks'] ?: '—' }}</td></tr>@endforeach</tbody></table>
@if($defects)<p class="note"><b>Open findings:</b> The {{ count($defects) }} observed issue(s) must be tracked for rectification and re-inspection in the Job Card before QC Pass, per Assam Motors policy. This report does not itself mark any defect as resolved.</p>@endif
</div><div class="footer">ASSAM MOTORS · Report {{ $report_no }} · This inspection is based on observed/supplied checks; it does not certify roadworthiness, ownership or market value. Generated from submitted ERP data, without vehicle photos.</div></section>
</main>
<script>
(function(){
 const button=document.getElementById('amShareHealthPdf'),hint=document.getElementById('amShareHint');
 const pdfUrl=@json(($is_admin ? route('erp.job-cards.vehicle-health-pdf',$job->id) : route('staff.vehicle-health-pdf',$job->id)).'?download=1');
 button.addEventListener('click',async()=>{
   if(!navigator.share||typeof File==='undefined'){
     hint.textContent='Device sharing is unavailable here. Download the PDF and attach it in WhatsApp manually.';return;
   }
   button.disabled=true;hint.textContent='Preparing protected report PDF…';
   try{
     const r=await fetch(pdfUrl,{credentials:'same-origin'});
     if(!r.ok)throw new Error('Report PDF unavailable (HTTP '+r.status+').');
     const blob=await r.blob();
     if(!blob.type.includes('pdf'))throw new Error('Unexpected download format.');
     const file=new File([blob],'Assam-Motors-Vehicle-Health.pdf',{type:'application/pdf'});
     if(navigator.canShare&&!navigator.canShare({files:[file]}))throw new Error('This device does not support PDF file sharing.');
     await navigator.share({files:[file],title:'Assam Motors Vehicle Health Report'});
     hint.textContent='Share sheet opened. Please confirm recipient and sending in WhatsApp.';
   }catch(e){hint.textContent=(e.name==='AbortError'?'Sharing cancelled.':e.message+' Download PDF and attach through WhatsApp if needed.');}
   finally{button.disabled=false;}
 });
})();
</script></body></html>
