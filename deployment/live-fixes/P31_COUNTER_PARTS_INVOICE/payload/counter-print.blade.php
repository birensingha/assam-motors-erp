<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $inv->invoice_no }} — Direct Parts Sale Invoice</title>
<style>
@page{size:A4;margin:14mm}*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;color:#182d42;font-size:11px;margin:0;background:#fff}
.header{display:flex;justify-content:space-between;align-items:start;border-bottom:3px solid #14558a;padding-bottom:13px;gap:15px}
.brand{font-size:23px;font-weight:900;color:#154a7c;letter-spacing:.035em}.subbrand{font-size:10px;color:#6c849b}
.doc{text-align:right}.doc h1{font-size:19px;margin:0 0 7px;color:#145588}.doc p{margin:4px 0}
.billing{display:flex;justify-content:space-between;gap:15px;margin:18px 0}
.billing>div{border:1px solid #c8d6e4;border-radius:8px;padding:12px;flex:1;min-height:73px}
.billing small{display:block;color:#657d94;margin-bottom:8px}.billing strong{font-size:13px}
table{width:100%;border-collapse:collapse;margin:14px 0}
th{background:#e8f1fa;color:#234c70;border-block:1px solid #bfd0e0;font-size:10px;text-align:left;padding:9px 6px}
td{border-bottom:1px solid #dce6f0;vertical-align:top;padding:9px 6px}
.num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.totals{margin-left:auto;max-width:290px;border:1px solid #cadae8;border-radius:8px;overflow:hidden}
.totals>div{display:flex;align-items:center;justify-content:space-between;padding:9px 11px;border-bottom:1px solid #e5ebf1}
.totals>div:last-child{background:#deefff;color:#144977;font-size:15px;font-weight:900}
.foot{margin-top:30px;display:flex;justify-content:space-between;gap:15px;border-top:1px solid #c5d9e7;padding-top:13px;color:#6c8399}
.print-tools{max-width:200mm;margin:10px auto 16px;text-align:right}
.print-tools button{padding:10px 16px;background:#175790;border:0;color:#fff;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer}
@media print{.print-tools{display:none} .header,.billing,.totals{break-inside:avoid} table{break-inside:auto} tr{break-inside:avoid}}
</style></head><body>
<div class="print-tools"><button onclick="window.print()">🖨 Print / Save PDF</button></div>
<div class="header">
 <div><div class="brand">ASSAM MOTORS</div><div class="subbrand">Parts Counter · assammotors.com</div></div>
 <div class="doc"><h1>DIRECT PARTS SALE INVOICE</h1><p><strong>{{ $inv->invoice_no }}</strong></p><p>Date: {{ date('d-m-Y',strtotime($inv->invoice_date)) }}</p></div>
</div>
<div class="billing">
 <div><small>Bill To</small><strong>{{ $inv->customer_name }}</strong><p>{{ $inv->customer_phone ?: 'Phone not recorded' }}</p></div>
 <div><small>Document Type</small><strong>Counter Parts Sale — No Job Card</strong>
   <p>Item-wise GST calculated from Parts Master at invoice creation.</p></div>
</div>
<table><thead><tr><th>#</th><th>Part / Code</th><th>HSN</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Disc%</th><th class="num">GST%</th><th class="num">Amount</th></tr></thead>
<tbody>
 @foreach($lines as $l)<tr>
 <td>{{ $loop->iteration }}</td>
 <td><strong>{{ $l->part_name }}</strong><br><span style="color:#68829b">{{ $l->part_code }} @if($l->note)· {{ $l->note }}@endif</span></td>
 <td>{{ $l->hsn_code ?: '—' }}</td>
 <td class="num">{{ number_format((float)$l->qty,3) }} {{ $l->unit }}</td>
 <td class="num">{{ number_format((float)$l->rate,2) }}</td>
 <td class="num">{{ number_format((float)$l->discount_percent,2) }}</td>
 <td class="num">{{ number_format((float)$l->gst_percent,2) }}</td>
 <td class="num">{{ number_format((float)$l->total_amount,2) }}</td>
 </tr>@endforeach
</tbody></table>
<div class="totals">
 <div><span>Taxable Parts Value</span><b>₹{{ number_format((float)$inv->subtotal,2) }}</b></div>
 <div><span>GST Total</span><b>₹{{ number_format((float)$inv->gst_total,2) }}</b></div>
 <div><span>Round Off</span><b>₹{{ number_format((float)$inv->round_off,2) }}</b></div>
 <div><span>Paid</span><b>₹{{ number_format((float)$inv->paid_amount,2) }}</b></div>
 <div><span>Balance Due</span><b>₹{{ number_format((float)$inv->balance_due,2) }}</b></div>
 <div><span>GRAND TOTAL</span><b>₹{{ number_format((float)$inv->grand_total,2) }}</b></div>
</div>
<div class="foot"><div>Stock issued under Invoice reference {{ $inv->invoice_no }}.<br>Payment receipt is issued separately once payment is recorded.</div>
<div style="text-align:right">Authorised by Assam Motors<br><strong>Computer-generated invoice</strong></div></div>
</body></html>
