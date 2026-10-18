<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $inv->invoice_no }}</title>
<style>
body{font:13px Arial;color:#111;margin:22px}.head{display:flex;justify-content:space-between;border-bottom:3px solid #173c72;padding-bottom:12px}.head h1{margin:0;color:#173c72}.meta{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:14px 0}.meta div{border:1px solid #ccc;padding:8px}.meta span{display:block;font-size:9px;text-transform:uppercase;color:#666}h2{font-size:14px;margin:18px 0 8px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #bbb;padding:7px}th{background:#eee;text-align:left}.num{text-align:right}.tot{width:380px;margin:12px 0 0 auto}.tot div{display:flex;justify-content:space-between;border-bottom:1px solid #ddd;padding:5px}.grand{font-size:16px;font-weight:bold}.no-print{margin-bottom:12px}@media print{.no-print{display:none}body{margin:0}}
</style>
</head>
<body>
@php
$isCostSummary = request()->query('mode') === 'cost_summary';
@endphp
<div class="no-print" style="display:flex;gap:8px;margin-bottom:14px">
<a href="{{ request()->fullUrlWithQuery(['mode'=>'tax']) }}">GST / Tax Invoice</a>
<a href="{{ request()->fullUrlWithQuery(['mode'=>'cost_summary']) }}">Customer Cost Summary (no GST breakup)</a>
<button onclick="window.print()">Print / Save PDF</button>
</div>
@php
// Shared document identity: only load a logo located inside the public site tree.
// A missing logo leaves a readable text fallback; never access private uploads.
$amLogoFile = public_path('images/assam-motors-logo.png');
$amLogoUrl = is_file($amLogoFile) ? asset('images/assam-motors-logo.png') : null;
@endphp
<div class="head"><div style="display:flex;align-items:center;gap:12px">
@if($amLogoUrl)
<img src="{{ $amLogoUrl }}" alt="Assam Motors logo" style="width:58px;height:58px;object-fit:contain" />
@else
<div style="width:58px;height:58px;display:flex;align-items:center;justify-content:center;background:#174678;color:#fff;font-weight:bold;border-radius:6px">AM</div>
@endif
<div><h1>ASSAM MOTORS</h1><div>{{ $isCostSummary ? "Customer Cost Summary — Tax Included — Not a Tax Invoice" : "Tax / Service Invoice" }}</div></div></div><div><strong>{{ $inv->invoice_no }}</strong><br>Job: {{ $job->job_no }}</div></div>
@if($isCostSummary)
<p style="background:#edf4fb;padding:8px">Customer cost summary. Amounts include applicable taxes. This document is not a tax invoice.</p>
@endif
<div class="meta">
  <div><span>Customer</span>{{ $inv->customer_name }}<br>{{ $inv->customer_phone }}</div>
  <div><span>Vehicle</span>{{ $inv->vehicle_reg_no }}<br>{{ trim(($job->vehicle_make??'').' '.($job->vehicle_model??'')) }}</div>
  <div><span>Invoice Date</span>{{ date('d-m-Y',strtotime($inv->invoice_date)) }}</div>
  <div><span>Status</span>{{ strtoupper((string)$inv->status) }}</div>
</div>

<h2>Parts</h2>
<table><thead><tr><th>#</th><th>Part</th><th>Part No.</th><th class="num">Qty</th><th class="num">Rate</th>@unless($isCostSummary)<th class="num">GST</th>@endunless<th class="num">Total</th></tr></thead><tbody>
@forelse($parts as $p)<tr><td>{{ $loop->iteration }}</td><td>{{ $p->part_name }}</td><td>{{ $p->part_number ?: $p->part_code }}</td><td class="num">{{ $p->qty }}</td><td class="num">₹{{ number_format($isCostSummary && (float)$p->qty > 0 ? (float)$p->total / (float)$p->qty : (float)$p->rate,2) }}</td>@unless($isCostSummary)<td class="num">{{ number_format((float)($p->gst_percent??0),2) }}%</td>@endunless<td class="num">₹{{ number_format((float)$p->total,2) }}</td></tr>@empty<tr><td colspan="{{ $isCostSummary ? 6 : 7 }}">No Parts.</td></tr>@endforelse
</tbody></table>

<h2>Labour / ROT</h2>
<table><thead><tr><th>#</th><th>ROT</th><th>Description</th><th class="num">Std Hrs</th><th class="num">Rate</th><th class="num">GST</th><th class="num">Total</th></tr></thead><tbody>
@forelse($labour as $l)<tr><td>{{ $loop->iteration }}</td><td>{{ $l->rot_code }}</td><td>{{ $l->description }}</td><td class="num">{{ number_format((float)$l->standard_hours,2) }}</td><td class="num">₹{{ number_format($isCostSummary ? (float)$l->total : (float)$l->labour_rate,2) }}</td>@unless($isCostSummary)<td class="num">{{ number_format((float)($l->gst_percent??0),2) }}%</td>@endunless<td class="num">₹{{ number_format((float)$l->total,2) }}</td></tr>@empty<tr><td colspan="{{ $isCostSummary ? 6 : 7 }}">No Labour.</td></tr>@endforelse
</tbody></table>

<div class="tot">
<div><span>Parts</span><b>₹{{ number_format((float)$inv->parts_total,2) }}</b></div>
<div><span>Labour</span><b>₹{{ number_format((float)$inv->labour_total,2) }}</b></div>
@unless($isCostSummary)<div><span>GST</span><b>₹{{ number_format((float)($inv->gst_total??$inv->tax_total??0),2) }}</b></div>@endunless
<div><span>Round Off</span><b>₹{{ number_format((float)($inv->round_off??0),2) }}</b></div>
<div class="grand"><span>Grand Total</span><b>₹{{ number_format((float)$inv->grand_total,2) }}</b></div>
<div><span>Paid</span><b>₹{{ number_format((float)($inv->paid_amount??$inv->paid_total??0),2) }}</b></div>
<div><span>Balance Due</span><b>₹{{ number_format((float)($inv->balance_due??$inv->balance_total??0),2) }}</b></div>
</div>
@unless($isCostSummary)
<h2>HSN / SAC Tax Classification</h2>
<table><thead><tr><th>HSN / SAC</th><th>Item / Service</th><th class="num">Tax Rate</th><th class="num">Line Amount</th></tr></thead><tbody>
@foreach($parts as $p)
<tr><td>{{ $p->hsn_code ?? $p->hsn ?? '—' }}</td><td>{{ $p->part_name }}</td><td class="num">{{ number_format((float)($p->gst_percent??0),2) }}%</td><td class="num">₹{{ number_format((float)$p->total,2) }}</td></tr>
@endforeach
@foreach($labour as $l)
<tr><td>{{ $l->sac_code ?? $l->sac ?? '—' }}</td><td>{{ $l->description }}</td><td class="num">{{ number_format((float)($l->gst_percent??0),2) }}%</td><td class="num">₹{{ number_format((float)$l->total,2) }}</td></tr>
@endforeach
</tbody></table>
@endunless
</body>
</html>
