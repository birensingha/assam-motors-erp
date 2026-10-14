<!doctype html>
<html>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $inv->invoice_no }}</title>
<style>
*{box-sizing:border-box}body{font:12px Arial,sans-serif;color:#142846;background:#fff;margin:20px}
.toolbar{display:flex;gap:8px;margin-bottom:16px}.toolbar button{padding:10px 16px;border:0;border-radius:6px;background:#0c2545;color:white;font-weight:700;cursor:pointer}
.head{display:grid;grid-template-columns:3fr 1.7fr;border:1px solid #102b50;border-radius:10px;overflow:hidden;min-height:125px}
.brand{padding:16px 20px}.brand h1{font-size:25px;letter-spacing:1px;color:#102b50;margin:0 0 5px}.brand small{display:block;line-height:1.5}
.invoice-meta{background:#0c2545;color:white;padding:20px;display:flex;flex-direction:column;justify-content:center;gap:12px}.invoice-meta strong{letter-spacing:1px;font-size:11px}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}.meta>div{border:1px solid #cbd6e2;border-radius:8px;padding:13px;min-height:110px;line-height:1.65}.meta span{display:block;font-size:10px;text-transform:uppercase;color:#0c2545;font-weight:800;letter-spacing:1px;margin-bottom:5px}
h2{font-size:12px;background:#0c2545;color:#fff;display:inline-block;padding:9px 14px;margin:12px 0 0}h2.parts-heading{background:#cf3444}
table{width:100%;border-collapse:collapse;margin-bottom:8px}th,td{border:1px solid #ced8e4;padding:8px;vertical-align:top}th{background:#0c2545;color:white;text-align:left;font-size:10px}.num{text-align:right;white-space:nowrap}
.tot{width:420px;max-width:100%;margin:16px 0 0 auto;background:#f7f9fc;border:1px solid #e0e7ef;padding:12px}
.tot div{display:flex;justify-content:space-between;border-bottom:1px solid #ddd;padding:7px}.tot .grand{border-top:3px solid #102b50;border-bottom:0;font-size:17px;font-weight:bold;margin-top:6px}
.footer{border-top:3px solid #102b50;margin-top:25px;padding-top:12px;display:grid;grid-template-columns:2fr 1fr;gap:12px}.footer>div{border:1px solid #ddd;padding:12px}.terms{margin-top:12px;border:1px solid #ddd;padding:12px}.terms p{margin:5px 0}
@page{size:A4;margin:11mm}@media print{.toolbar{display:none}body{margin:0;font-size:10px}.head{min-height:105px}.meta>div{min-height:90px}.footer,.terms{break-inside:avoid}tr{break-inside:avoid}}
</style>
</head>
<body>
<div class="toolbar"><button onclick="window.print()">Print Tax Invoice / Save PDF</button></div>
<div class="head">
  <div class="brand"><h1>ASSAM MOTORS</h1>
    <small>Bishnu Rabha Path, Hockey Stadium Rd, Sawkuchi, Guwahati-781040, Assam.</small>
    <small>MSME: UDYAM-AS-03-0045843 &nbsp; | &nbsp; GSTIN: 18CAHPS7155P1ZQ</small>
    <small>Phone: 9864043620 &nbsp; | &nbsp; Email: service@assammotors.com</small>
    <small>State: 18-Assam &nbsp; | &nbsp; Website: www.assammotors.com</small>
  </div>
  <div class="invoice-meta"><strong>TAX / SERVICE INVOICE</strong>
    <div>DOCUMENT NO. &nbsp; <b>{{ $inv->invoice_no }}</b></div>
    <div>DATE &nbsp; <b>{{ date('d M Y',strtotime($inv->invoice_date)) }}</b></div>
  </div>
</div>
@php
  $billName=$inv->customer_name ?: ($customer->name ?? $customer->customer_name ?? '—');
  $billPhone=$inv->customer_phone ?: ($customer->phone ?? $customer->mobile ?? '—');
  $billEmail=$inv->customer_email ?: ($customer->email ?? '—');
  $billAddress=collect([
    $customer->address ?? $customer->address_line1 ?? $customer->address1 ?? null,
    $customer->address_line2 ?? $customer->address2 ?? null,
    $customer->city ?? null,
    $customer->state ?? null,
    $customer->pincode ?? $customer->pin_code ?? null,
  ])->filter(fn($value)=>trim((string)$value)!=='')->unique()->implode(', ');
@endphp
<div class="meta">
  <div><span>Bill To / Customer</span><b>Name:</b> {{ $billName }}<br><b>Phone:</b> {{ $billPhone }}<br><b>Email:</b> {{ $billEmail }}<br><b>Address:</b> {{ $billAddress ?: '—' }}</div>
  <div><span>Vehicle / Job</span><b>Job Card:</b> {{ $job->job_no }}<br><b>Registration:</b> {{ $inv->vehicle_reg_no }}<br><b>Vehicle:</b> {{ trim(($job->vehicle_make??'').' '.($job->vehicle_model??'')) }}<br><b>Odometer:</b> {{ $job->odometer ?? $job->odometer_km ?? '—' }}</div>
  <div><span>Invoice Details</span><b>Invoice No:</b> {{ $inv->invoice_no }}<br><b>Date:</b> {{ date('d M Y',strtotime($inv->invoice_date)) }}<br><b>Status:</b> {{ $inv->status }}<br><b>State of Supply:</b> {{ $job->state_of_supply ?? '—' }}</div>
  <div><span>Vehicle Identity</span><b>Chassis / VIN:</b> {{ $job->chassis_no ?? '—' }}<br><b>Engine No:</b> {{ $job->engine_no ?? '—' }}<br><b>Fuel Type:</b> {{ $job->fuel_type ?? '—' }}<br><b>Service:</b> {{ $job->service_type ?? '—' }}</div>
</div>
<h2 class="parts-heading">Parts / Materials</h2>
<table><thead><tr><th>#</th><th>Part</th><th>Part No.</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">GST</th><th class="num">Total</th></tr></thead><tbody>
@forelse($parts as $p)<tr><td>{{ $loop->iteration }}</td><td>{{ $p->part_name }}</td><td>{{ $p->part_number ?: $p->part_code }}</td><td class="num">{{ $p->qty }}</td><td class="num">₹{{ number_format((float)$p->rate,2) }}</td><td class="num">{{ number_format((float)($p->gst_percent??0),2) }}%</td><td class="num">₹{{ number_format((float)$p->total,2) }}</td></tr>@empty<tr><td colspan="7">No Parts.</td></tr>@endforelse
</tbody></table>

<h2>Labour / ROT Charges</h2>
<table><thead><tr><th>#</th><th>ROT</th><th>Description</th><th class="num">Std Hrs</th><th class="num">Rate</th><th class="num">GST</th><th class="num">Total</th></tr></thead><tbody>
@forelse($labour as $l)<tr><td>{{ $loop->iteration }}</td><td>{{ $l->rot_code }}</td><td>{{ $l->description }}</td><td class="num">{{ number_format((float)$l->standard_hours,2) }}</td><td class="num">₹{{ number_format((float)$l->labour_rate,2) }}</td><td class="num">{{ number_format((float)($l->gst_percent??0),2) }}%</td><td class="num">₹{{ number_format((float)$l->total,2) }}</td></tr>@empty<tr><td colspan="7">No Labour.</td></tr>@endforelse
</tbody></table>

<div class="tot">
<div><span>Parts</span><b>₹{{ number_format((float)$inv->parts_total,2) }}</b></div>
<div><span>Labour</span><b>₹{{ number_format((float)$inv->labour_total,2) }}</b></div>
<div><span>GST</span><b>₹{{ number_format((float)($inv->gst_total??$inv->tax_total??0),2) }}</b></div>
<div><span>Round Off</span><b>₹{{ number_format((float)($inv->round_off??0),2) }}</b></div>
<div class="grand"><span>Grand Total</span><b>₹{{ number_format((float)$inv->grand_total,2) }}</b></div>
<div><span>Paid</span><b>₹{{ number_format((float)($inv->paid_amount??$inv->paid_total??0),2) }}</b></div>
<div><span>Balance Due</span><b>₹{{ number_format((float)($inv->balance_due??$inv->balance_total??0),2) }}</b></div>
</div>
<div class="footer">
 <div><b>Bank Details</b><p>HDFC BANK, HATIGAON<br>Account No.: 50200100195339<br>IFSC: HDFC0005671<br>Account Holder: ASSAM MOTORS</p></div>
 <div style="text-align:center"><b>For ASSAM MOTORS</b><p style="margin-top:65px">Authorised Signatory</p></div>
</div>
<div class="terms"><b>Terms and Conditions — E. &amp; O.E.</b>
<p>1. Goods once sold will not be taken back.</p>
<p>2. Interest @ 5% per month may be charged if payment is not made within the stipulated time.</p>
<p>3. Parking charges of ₹110 may apply if the vehicle is not collected within 7 days after intimation.</p>
<p>4. Uncollected vehicles are subject to the workshop's applicable storage and recovery terms.</p>
</div>
</body>
</html>
