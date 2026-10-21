<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $job->job_no }} — One-Page Job Card Summary — Assam Motors</title>
<style>
@page{size:A4 portrait;margin:8mm 10mm}
*{box-sizing:border-box}
:root{--blue:#123f78;--text:#173653;--line:#d9e3ec;--pale:#edf4fa}
html,body{margin:0;color:var(--text);background:#edf3f9;font:9.1px/1.32 Arial,Helvetica,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.toolbar{margin:11px auto;max-width:190mm;background:#fff;border:1px solid #d5e1ef;border-radius:8px;padding:9px 11px;display:flex;gap:7px;align-items:center;justify-content:space-between}
.toolbar a,.toolbar button{background:#18558f;color:#fff;border:0;border-radius:6px;font-weight:800;padding:9px 11px;cursor:pointer;text-decoration:none}
.page{background:#fff;width:190mm;min-height:275mm;margin:0 auto 15px;padding:0 1px;box-shadow:0 5px 25px #193d5922}
.brand{display:grid;grid-template-columns:1fr 60mm;border:1px solid #1c4e86;border-radius:7px;overflow:hidden}
.brand-info{display:flex;align-items:center;gap:10px;background:#f7faff;padding:9px}
.logo{width:62px;height:62px;object-fit:contain;flex:none}
.brand-copy h1{margin:0 0 4px;font-size:20px;color:#16477e}
.address{font-size:8.15px;color:#3e5873;line-height:1.32}
.address div{margin:1px 0}
.doc-head{background:linear-gradient(160deg,#194f89,#0e346b);color:white;display:flex;flex-direction:column;justify-content:center;text-align:right;padding:8px 11px}
.doc-head h2{margin:0 0 4px;font-size:15px;text-transform:uppercase}
.doc-head div{font-size:8.6px;margin:2px 0}
.disclaimer{font-weight:800;color:#865708;background:#fff8e6;border-left:3px solid #c99321;margin:8px 0 0;padding:6px 8px}
.disclaimer.alert{color:#9b1e24;background:#fff1f2;border-color:#dc2626}
.summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px;margin:9px 0}
.info{border:1px solid var(--line);border-radius:6px;overflow:hidden}
.info h3{margin:0;background:var(--pale);color:#244a70;text-transform:uppercase;padding:5px 7px;font-size:9px;letter-spacing:.04em}
.info dl{margin:0;padding:5px 7px;display:grid;grid-template-columns:74px 1fr;gap:3px 4px}
.info dt{color:#6b7f90}.info dd{margin:0;font-weight:700;overflow-wrap:anywhere}
.section{margin-top:9px}
.section h3{font-size:10.4px;color:var(--blue);margin:0 0 4px;padding:3px 0;border-bottom:2px solid #c7d9e8}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th{background:#ebf2f9;color:#244c74;font-size:7.65px;text-transform:uppercase;text-align:left;border:1px solid #dce7ef;padding:5px 4px}
td{font-size:8.1px;border-bottom:1px solid #e7edf4;padding:4px;vertical-align:top;overflow-wrap:anywhere}
tr{break-inside:avoid}
.num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.desc{font-weight:700;line-height:1.25}
.note{color:#63768a;font-size:7.5px;line-height:1.2}
.more td{font-style:italic;background:#f7faff;color:#4a6686}
.money{display:flex;justify-content:flex-end;margin-top:10px}
.totals{width:76mm;border:1px solid #bed2e5;border-radius:5px;overflow:hidden}
.totals div{display:flex;justify-content:space-between;gap:13px;padding:4px 8px;border-bottom:1px solid #e7edf4;font-size:8.9px}
.totals .grand{background:var(--blue);color:#fff;font-weight:900;font-size:12px;padding:8px}
.footer{display:flex;justify-content:space-between;gap:12px;align-items:flex-end;border-top:1px solid #d5e0ed;margin-top:13px;padding-top:7px;color:#60748b;font-size:8.2px}
.footer .sign{text-align:right;min-width:165px}
.print-note{font-size:7.5px;color:#6a7e94;padding-top:7px}
@media print{
html,body{background:#fff!important;width:190mm;height:auto}
.toolbar{display:none!important}
.page{width:190mm;min-height:0;height:auto;box-shadow:none;margin:0;padding:0}
}
@media screen and (max-width:760px){.page{width:100%;min-height:0;padding:9px}.brand{grid-template-columns:1fr}.doc-head{text-align:left}.summary-grid{grid-template-columns:1fr}.toolbar{margin:7px}.money{justify-content:stretch}.totals{width:100%}}
</style></head><body>
@php
 $allParts=$parts;
 $billableLabour=$labour->filter(fn($l)=>!isset($l->billing_included)||(int)$l->billing_included===1)->values();
 $headParts=$allParts->take(9);$restParts=$allParts->skip(9);
 $headLabour=$billableLabour->take(9);$restLabour=$billableLabour->skip(9);
 $printPartMoney=static fn($x)=>number_format((float)$x,2);
 $partTotal=(float)($job->parts_total??0);
 $labourTotal=(float)($job->labour_total??0);
 $gstTotal=(float)($job->gst_total??0);
 $round=(float)($job->round_off??0);
 $grand=(float)($job->grand_total??0);
 $calc=\App\Services\JobCardTaxTotals::forJob((int)$job->id);
 $amMismatch=abs((float)$calc['grand_total']-$grand)>0.02 || abs((float)$calc['gst_total']-$gstTotal)>0.02;
@endphp
<div class="toolbar"><b>ASSAM MOTORS · One-page Job Card Summary (not a Tax Invoice)</b><span>
<a href="{{ route('erp.job-cards.show',$job->id) }}">← Job Card</a>
<button type="button" onclick="window.print()">Print / Save as PDF</button></span></div>
<main class="page">
<header class="brand">
<div class="brand-info">
<img class="logo" src="https://assammotors.com/images/logo.png" alt="Assam Motors logo">
<div class="brand-copy"><h1>ASSAM MOTORS</h1><div class="address">
<div>Bishnu Rabha Path, Hockey Stadium Rd, Sawkuchi, Guwahati-781040, Assam.</div>
<div>MSME: UDYAM-AS-03-0045843 &nbsp;|&nbsp; GSTIN: 18CAHPS7155P1ZQ</div>
<div>Phone: 9864043620 &nbsp;|&nbsp; Email: service@assammotors.com</div>
<div>State: 18-Assam &nbsp;|&nbsp; Website: www.assammotors.com</div>
</div></div></div>
<div class="doc-head"><h2>Job Card Summary</h2><div>Customer Cost Summary</div><div><b>{{ $job->job_no }}</b></div>
<div>Date: {{ $job->job_date ? date('d M Y',strtotime((string)$job->job_date)) : '—' }}</div>
<div>TAX INCLUDED WHERE APPLICABLE</div><div>NOT A TAX INVOICE</div></div>
</header>
@if($amMismatch)
<div class="disclaimer alert">DRAFT · FINANCE REVIEW REQUIRED — saved JC tax/total differs from current item-wise calculation. Do not use this summary as an approved bill.</div>
@else
<div class="disclaimer">Customer Cost Summary — not a Tax Invoice. Totals are the saved Job Card values; any final Invoice remains authoritative.</div>
@endif
<div class="summary-grid">
<section class="info"><h3>Customer / Work</h3><dl>
<dt>Customer</dt><dd>{{ $job->customer_name?:'—' }}</dd>
<dt>Phone</dt><dd>{{ $job->customer_phone?:'—' }}</dd>
<dt>Job Status</dt><dd>{{ strtoupper((string)$job->status) }}</dd>
<dt>Service</dt><dd>{{ $job->service_type?:'—' }}</dd>
</dl></section>
<section class="info"><h3>Vehicle / Dates</h3><dl>
<dt>Vehicle No.</dt><dd>{{ $job->vehicle_reg_no?:'—' }}</dd>
<dt>Vehicle</dt><dd>{{ trim(($job->vehicle_make??'').' '.($job->vehicle_model??''))?:'—' }}</dd>
<dt>Vehicle In</dt><dd>{{ !empty($job->vehicle_inward_at) ? date('d M Y, h:i A',strtotime((string)$job->vehicle_inward_at)):'—' }}</dd>
<dt>JC Date</dt><dd>{{ !empty($job->job_date)?date('d M Y',strtotime((string)$job->job_date)):'—' }}</dd>
</dl></section></div>

<section class="section"><h3>Parts & Consumables ({{ $allParts->count() }})</h3>
<table><thead><tr><th style="width:5%">#</th><th>Part / Description</th><th style="width:11%">Part No.</th><th class="num" style="width:8%">Qty</th><th class="num" style="width:13%">Rate</th><th class="num" style="width:10%">Disc %</th><th class="num" style="width:15%">Taxable</th></tr></thead>
<tbody>
@forelse($headParts as $part)
<tr><td>{{ $loop->iteration }}</td><td><span class="desc">{{ $part->part_name??$part->description??'Part' }}</span>
@if(!empty($part->note))<div class="note">{{ \Illuminate\Support\Str::limit($part->note,105) }}</div>@endif</td>
<td>{{ $part->part_number??$part->part_code??'—' }}</td><td class="num">{{ number_format((float)($part->qty??0),2) }}</td>
<td class="num">{{ $printPartMoney($part->rate??0) }}</td><td class="num">{{ number_format((float)($part->discount_percent??0),1) }}</td>
<td class="num">{{ $printPartMoney($part->total??0) }}</td></tr>
@empty <tr><td colspan="7" class="note">No Parts entered.</td></tr>@endforelse
@if($restParts->count())<tr class="more"><td colspan="6">Other {{ $restParts->count() }} Part line(s) (condensed for one-page summary; full JC retains item details)</td><td class="num">{{ $printPartMoney($restParts->sum(fn($p)=>(float)($p->total??0))) }}</td></tr>@endif
</tbody></table></section>
<section class="section"><h3>Labour / ROT & OSL ({{ $billableLabour->count() }} billable)</h3>
<table><thead><tr><th style="width:5%">#</th><th>Labour / Work</th><th style="width:15%">ROT Code</th><th class="num" style="width:9%">Std Hrs</th><th class="num" style="width:14%">Rate</th><th class="num" style="width:9%">GST %</th><th class="num" style="width:15%">Taxable</th></tr></thead>
<tbody>
@forelse($headLabour as $line)
<tr><td>{{ $loop->iteration }}</td>
<td><span class="desc">{{ \Illuminate\Support\Str::limit($line->description?:'Service Labour',68) }}</span>
@if(!empty($line->note))<div class="note">{{ \Illuminate\Support\Str::limit($line->note,90) }}</div>@endif</td>
<td>{{ $line->rot_code??'LABOUR' }}</td><td class="num">{{ number_format((float)($line->standard_hours??0),2) }}</td>
<td class="num">{{ $printPartMoney($line->labour_rate??0) }}</td><td class="num">{{ number_format((float)($line->gst_percent??0),1) }}</td>
<td class="num">{{ $printPartMoney($line->total??0) }}</td></tr>
@empty <tr><td colspan="7" class="note">No Labour / ROT added.</td></tr>@endforelse
@if($restLabour->count())<tr class="more"><td colspan="6">Other {{ $restLabour->count() }} Labour line(s) (condensed; full JC retains item details)</td><td class="num">{{ $printPartMoney($restLabour->sum(fn($l)=>(float)($l->total??0))) }}</td></tr>@endif
</tbody></table></section>
<div class="money"><div class="totals">
<div><span>Parts Taxable</span><b>₹{{ $printPartMoney($partTotal) }}</b></div>
<div><span>Labour Taxable</span><b>₹{{ $printPartMoney($labourTotal) }}</b></div>
<div><span>GST Total</span><b>₹{{ $printPartMoney($gstTotal) }}</b></div>
<div><span>Round Off</span><b>₹{{ $printPartMoney($round) }}</b></div>
<div class="grand"><span>Job Card Grand Total</span><b>₹{{ $printPartMoney($grand) }}</b></div>
</div></div>
<div class="footer"><div>Document Type: Workshop Job Card Summary · {{ strtoupper((string)$job->status) }}<br>Data is based on saved JC records; see original invoice for final billed amount.</div><div class="sign"><b>For ASSAM MOTORS</b><br><br>Authorised Signatory</div></div>
<div class="print-note">Full line details remain in Job Card {{ $job->job_no }}. Customer summary only; NOT an invoice, receipt or GST document.</div>
</main></body></html>
