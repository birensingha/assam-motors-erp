@php
$invoice=(object)($snapshot['invoice']??[]);
$job=(object)($snapshot['job']??[]);
$parts=collect($snapshot['parts']??[]);
$labour=collect($snapshot['labour']??[]);
@endphp
<!doctype html><html><head><meta charset="utf-8"><title>Credit Note {{ $cn->credit_note_no }}</title>
<style>body{font:13px Arial;margin:20px;color:#183651}main{max-width:850px;margin:auto}
h1{color:#124678}header{border-bottom:3px solid #124678}table{border-collapse:collapse;width:100%;margin:20px 0}
th,td{padding:9px;border:1px solid #cbd8e4}th{background:#e6f0fa;text-align:left}
.num{text-align:right}.grand{font-size:20px;background:#124678;color:white;padding:15px;text-align:right}
@media print{button{display:none}}</style></head><body><main>
<header><h1>ASSAM MOTORS</h1><h2>FULL CREDIT NOTE</h2>
<p>Credit Note: <strong>{{ $cn->credit_note_no }}</strong> · Date: {{ $cn->credit_date }}</p>
<p>Against Invoice: <strong>{{ $invoice->invoice_no??'—' }}</strong> · Job Card: {{ $job->job_no??'—' }}</p>
<p>Customer: {{ $invoice->customer_name??'—' }} · Vehicle: {{ $job->vehicle_reg_no??'—' }}</p></header>
<table><thead><tr><th>Part / Labour / OSL</th><th class="num">Qty</th><th class="num">Taxable</th><th class="num">GST %</th><th class="num">GST ₹</th></tr></thead><tbody>
@foreach($parts as $p)
<tr><td>{{ $p['part_name']??'Part' }}</td><td class="num">{{ $p['qty']??1 }}</td><td class="num">{{ number_format((float)($p['total']??0),2) }}</td><td class="num">{{ $p['gst_percent']??0 }}</td><td class="num">{{ number_format(round((float)($p['total']??0)*(float)($p['gst_percent']??0)/100,2),2) }}</td></tr>
@endforeach
@foreach($labour as $l)
@if((int)($l['billing_included']??1)===1)
<tr><td>{{ $l['description']??'Labour' }}</td><td class="num">{{ $l['standard_hours']??1 }}</td><td class="num">{{ number_format((float)($l['total']??0),2) }}</td><td class="num">{{ $l['gst_percent']??0 }}</td><td class="num">{{ number_format(round((float)($l['total']??0)*(float)($l['gst_percent']??0)/100,2),2) }}</td></tr>
@endif
@endforeach
</tbody></table>
<p>Taxable: ₹{{ number_format((float)$cn->taxable_amount,2) }} · GST: ₹{{ number_format((float)$cn->gst_amount,2) }}</p>
<div class="grand">Total Credit ₹{{ number_format((float)$cn->credit_amount,2) }}</div>
<p><strong>Reason:</strong> {{ $cn->reason }}</p>
<p style="font-size:11px">ERP accounting document. Any required GST/e-invoicing portal reporting must be handled separately.</p>
<button onclick="window.print()">Print / Save PDF</button>
</main></body></html>
