<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Job Card Amount Editor | Assam Motors</title>
<style>
:root{font-family:system-ui,-apple-system,Segoe UI,Arial,sans-serif;color:#172d46;background:#f0f5fa}*{box-sizing:border-box}
body{margin:0}.wrap{max-width:1420px;margin:auto;padding:24px}.head{background:#0b315b;color:white;padding:22px 28px;border-radius:16px;display:flex;justify-content:space-between;gap:15px;align-items:center}.head h1{font-size:24px;margin:0}.head p{color:#c9dbee;margin:5px 0 0}.head a{background:white;color:#0b315b;padding:10px 15px;border-radius:8px;font-weight:700;text-decoration:none}
.nav{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0}.nav a{padding:9px 15px;border:1px solid #cbd9e6;background:white;text-decoration:none;color:#12385d;border-radius:8px;font-weight:700}.note{padding:15px 18px;border-radius:10px;margin:12px 0;background:#fff7e3;border:1px solid #f2dca2;color:#715115}.danger{background:#fff0ed;border-color:#f8bfb6;color:#933527}.success{background:#e8f6ed;border-color:#b8ddc8;color:#136a36}
.panel{background:#fff;margin:16px 0;border:1px solid #dbe5ef;border-radius:12px;padding:18px;box-shadow:0 3px 12px #1739600c}.panel h2{font-size:19px;margin:0 0 10px;color:#0a325b}.subtitle{color:#6a7d92;font-size:13px;margin-bottom:16px}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:1080px;font-size:13px}th{background:#0d345e;color:white;text-align:left;position:sticky;top:0}th,td{padding:10px;border-bottom:1px solid #e4edf4;vertical-align:top}tr:nth-child(even){background:#f8fbfe}td b{color:#173d64}input,textarea{font:inherit;border:1px solid #b9ccda;border-radius:7px;padding:9px;background:#fff;color:#1c354c}input[type=number]{width:108px}input[type=text]{width:195px}textarea{width:220px;min-height:52px;resize:vertical}label{display:block;font-size:11px;color:#627891;font-weight:700;margin-bottom:4px}.green{background:#128342;color:white;border:0;border-radius:8px;padding:11px 15px;font-size:13px;font-weight:800;cursor:pointer}.green:disabled{background:#bbc8d2;cursor:not-allowed}.mini{font-size:12px;color:#536e88}.pill{display:inline-block;background:#e3effd;border-radius:20px;padding:4px 9px;color:#185396;font-size:12px;font-weight:700}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.stat{padding:15px;background:#f3f8fc;border:1px solid #dbe7f1;border-radius:10px}.stat small{display:block;color:#678}.stat strong{font-size:19px}.foot{color:#6d8296;font-size:12px;margin:25px 0}@media(max-width:800px){.wrap{padding:9px}.head{border-radius:0;flex-wrap:wrap}.summary{grid-template-columns:1fr 1fr}}@media print{form,.nav,.no-print{display:none!important}}
</style>@include('workshop-controls.enterprise-theme')</head><body class="am-modern">@include('workshop-controls.enterprise-navigation')<main class="wrap">
<div class="head"><div><span class="am-eyebrow">WORKSHOP CONTROL / BILLING</span><h1>Job Card Amount Editor</h1><p>Job Card {{ $card->job_no ?? $card->id }} · {{ strtoupper($card->status ?? 'OPEN') }} · Parts + Labour/ROT</p></div><a href="{{ url('/erp/job-cards/'.$card->id) }}">← Back to Job Card</a></div>
<div class="nav am-subnav"><a href="{{ url('/erp/job-cards') }}">Job Cards</a><a href="{{ route('erp.fix48.osl-index') }}">OSL Purchases</a><a href="{{ route('erp.fix48.vendor-database') }}">Vendor Database</a></div>
@if(session('success'))<div class="note success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="note danger">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
@if($invoiced)
<div class="note danger"><b>Issued invoice is linked to this Job Card.</b> Direct overwriting is blocked. An audited invoice financial revision must update the original invoice reference, GST breakup and customer ledger together. You can inspect values here, but Save is disabled until that revision workflow is available.</div>
@elseif($taxBasisUnverified)
<div class="note danger"><b>Historical GST requires review.</b> The stored Job Card GST is zero although lines show taxable GST rates. The existing gross amount may ALREADY INCLUDE GST. Save is disabled rather than adding GST a second time. Compare original Invoice/Estimate and approve a tax-basis correction first.</div>
@elseif(strtolower((string)$card->status)==='closed')
<div class="note">Closed but not invoiced: Admin financial correction is allowed only with modification reason. Previous amount is preserved in the change audit.</div>
@else<div class="note">Enter taxable rate (before GST). Display is Rate → Discount → GST → Final. Part Qty and ROT Standard Hours are readonly; use stock/ROT operational workflow to adjust quantity or performance time.</div>@endif
<div class="summary" aria-label="Current financial totals">
<div class="stat"><small>Parts (stored taxable)</small><strong>₹{{ number_format((float)($card->parts_total ?? 0),2) }}</strong></div>
<div class="stat"><small>Labour (stored taxable)</small><strong>₹{{ number_format((float)($card->labour_total ?? 0),2) }}</strong></div>
<div class="stat"><small>GST (stored)</small><strong>₹{{ number_format((float)($card->gst_total ?? 0),2) }}</strong></div>
<div class="stat"><small>Grand Total</small><strong>₹{{ number_format((float)($card->grand_total ?? 0),2) }}</strong></div></div>
@foreach(['parts'=>$parts,'labour'=>$labour] as $kind=>$rows)
<section class="panel"><h2>{{ $kind==='parts'?'🔩 Parts & Materials':'🔧 Labour / ROT' }}</h2><div class="subtitle">Item-wise edit. Each Save runs a locked database transaction and writes Admin change audit. Quantity/ROT standard hours cannot be edited as a financial price adjustment.</div>
<div class="tablewrap"><table><thead><tr><th>Item</th><th>Qty / Std Hours</th><th>Rate</th><th>Discount %</th><th>GST %</th><th>Stored Amount</th><th>Corrected Final Preview</th><th>Reason / Action</th></tr></thead><tbody>
@forelse($rows as $r)
@php($qty=$kind==='parts'?($r->qty ?? 1):($r->standard_hours ?? 1))
@php($rate=$kind==='parts'?($r->rate ?? 0):($r->labour_rate ?? 0))
<tr><td><b>{{ $kind==='parts'?($r->part_name ?? 'Part'):($r->description ?? 'Labour') }}</b><br><span class="pill">{{ $kind==='parts'?($r->part_code ?? 'PART'):($r->rot_code ?? 'ROT') }}</span><br><span class="mini">#{{ $r->id }}</span></td>
<td colspan="7" style="padding:0"><form class="editline" method="post" action="{{ route('erp.fix48.job-line',[$card->id,$kind,$r->id]) }}" style="display:grid;grid-template-columns:repeat(5,minmax(105px,1fr)) minmax(105px,1fr) minmax(135px,1fr) minmax(215px,1.6fr);align-items:start;gap:8px;padding:9px">
@csrf
<input type="hidden" name="expected_old_total" value="{{ $r->total ?? 0 }}">
<div><label>{{ $kind==='parts'?'Qty':'Std Hours' }}</label><input name="quantity" type="number" min="0.01" step="0.01" value="{{ $qty }}" required readonly title="Change quantity from Stock/ROT workflow, not billing editor" {{ ($invoiced||$taxBasisUnverified)?'disabled':'' }}></div>
<div><label>Taxable Rate</label><input name="rate" type="number" min="0" step="0.01" value="{{ $rate }}" required {{ ($invoiced||$taxBasisUnverified)?'disabled':'' }}></div>
<div><label>Discount %</label><input name="discount_percent" type="number" min="0" max="100" step="0.01" value="{{ $r->discount_percent ?? 0 }}" required {{ ($invoiced||$taxBasisUnverified)?'disabled':'' }}></div>
<div><label>GST %</label><input name="gst_percent" type="number" min="0" max="100" step="0.01" value="{{ $r->gst_percent ?? 0 }}" required {{ ($invoiced||$taxBasisUnverified)?'disabled':'' }}></div>
<div><label>Stored taxable</label><strong>₹{{ number_format((float)($r->total ?? 0),2) }}</strong></div>
<div><label>New GST</label><b data-gst>—</b></div>
<div><label>New Final</label><strong data-final style="color:#12783b">—</strong></div>
<div><label>Modification Reason *</label><textarea name="reason" minlength="5" maxlength="1000" required placeholder="Reason for correcting amount" {{ ($invoiced||$taxBasisUnverified)?'disabled':'' }}></textarea><button class="green am-success-action" type="submit" {{ ($invoiced||$taxBasisUnverified)?'disabled':'' }}>Save Line Amount</button></div>
</form></td></tr>
@empty<tr><td colspan="8">No {{ $kind }} entries on this Job Card.</td></tr>@endforelse
</tbody></table></div></section>
@endforeach
<div class="foot">Assam Motors ERP · Audited financial adjustment · Server totals are authoritative · Do not edit issued tax documents or reverse stock through this screen.</div>
</main>
<script>document.querySelectorAll('.editline').forEach(f=>{const n=s=>Number(f.querySelector('[name="'+s+'"]').value)||0;const upd=()=>{const taxable=Math.round(((n('quantity')*n('rate'))*(1-n('discount_percent')/100))*100)/100;const tax=Math.round(taxable*n('gst_percent'))/100;f.querySelector('[data-gst]').textContent='₹'+tax.toFixed(2);f.querySelector('[data-final]').textContent='₹'+(taxable+tax).toFixed(2)};f.querySelectorAll('input').forEach(x=>x.addEventListener('input',upd));upd()})</script>
</body></html>