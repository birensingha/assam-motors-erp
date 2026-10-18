<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $receipt->receipt_no }} — Money Receipt — ASSAM MOTORS</title>
<style>
@page{size:A4 portrait;margin:11mm 12mm}
*{box-sizing:border-box}
:root{--blue:#12477d;--navy:#0e346b;--ink:#173653;--line:#d9e3ec;--pale:#edf4fa;--muted:#64768a}
body{margin:0;background:#ecf2f9;color:var(--ink);font:10.4px/1.5 Arial,Helvetica,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.print-actions{margin:15px auto;max-width:950px;padding:10px 13px;display:flex;gap:10px;justify-content:space-between;align-items:center;flex-wrap:wrap;background:#fff;border:1px solid #d8e4ee;border-radius:10px}
.print-actions a,.print-actions button{padding:9px 12px;border-radius:8px;border:1px solid #bfd4e6;background:#f6faff;color:#145184;font-size:12px;font-weight:800;text-decoration:none;cursor:pointer}
.print-actions button{background:#185c9a;border-color:#185c9a;color:#fff}
.sheet{max-width:950px;margin:0 auto 25px;min-height:1130px;background:#fff;padding:28px 29px;box-shadow:0 9px 28px #112f5321}
.brand{display:grid;grid-template-columns:minmax(0,1fr) 228px;gap:13px;align-items:stretch;border:1px solid #1b5189;border-radius:9px;overflow:hidden;background:#f8fbff}
.brand-info{display:flex;gap:11px;padding:13px 12px;align-items:flex-start}
.logo{width:61px;height:61px;max-width:61px;max-height:61px;object-fit:contain;flex:none}
.logo-fallback{background:#19599b;color:white;font-size:23px;font-weight:900;border-radius:9px;width:61px;height:61px;display:grid;place-items:center;letter-spacing:-2px;flex:none}
.brand h1{font-size:22px;color:#16477e;letter-spacing:.025em;margin:0 0 5px;line-height:1.12}
.brand .address{font-size:9.2px;color:#47617b;line-height:1.4}
.brand .address div{margin:1px 0}
.doc-head{display:flex;flex-direction:column;justify-content:center;background:linear-gradient(150deg,#194f89,#0e346b);color:white;text-align:right;padding:13px}
.doc-head h2{font-size:18px;text-transform:uppercase;line-height:1.12;margin:0 0 8px;letter-spacing:.035em}
.doc-head .docno{font-size:13px;font-weight:800;overflow-wrap:anywhere}
.doc-head small{font-size:9px;color:#ddedff;margin-top:6px}
.sub-title{border-left:3px solid #2d74b9;background:#f0f6fc;padding:7px 11px;margin-top:11px;color:#345e82;font-size:9px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin:16px 0}
.block{border:1px solid #dbe7f1;border-radius:7px;min-height:129px;break-inside:avoid}
.block h3{font-size:10.5px;color:#224f7c;letter-spacing:.035em;background:#edf4fa;margin:0;padding:8px 11px;text-transform:uppercase}
dl{display:grid;grid-template-columns:103px minmax(0,1fr);gap:5px 6px;margin:0;padding:10px 11px;font-size:10px}
dt{color:#688096}dd{margin:0;font-weight:700;color:#25435d;overflow-wrap:anywhere}
.section-title{font-size:11.5px;color:#154778;margin:15px 0 7px;padding:5px 0;border-bottom:2px solid #d3e2ee}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th{background:#eaf1f9;color:#21466d;text-align:left;text-transform:uppercase;font-size:9px;letter-spacing:.03em;padding:9px;border-top:1px solid #bbd0e3;border-bottom:1px solid #bbd0e3}
td{border-bottom:1px solid #e4ecf3;padding:10px 9px;font-size:10.7px}
.num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.receipt-total{display:flex;justify-content:space-between;align-items:center;gap:20px;color:#fff;background:#16477f;border-radius:9px;padding:16px 14px;margin:12px 0 8px}
.receipt-total span{font-size:12px;font-weight:800}.receipt-total strong{font-size:21px}
.words{padding:10px 11px;background:#f0f6fb;border-radius:7px;font-size:10px;color:#31587a;border-left:3px solid #2f6daf;break-inside:avoid}
.note{font-size:9.4px;color:#526c84;padding:10px 0;min-height:45px}
.bank-sign{display:grid;grid-template-columns:1fr .78fr;gap:12px;margin-top:19px;break-inside:avoid}
.bank,.sign{border:1px solid #dae6f0;border-radius:8px;padding:10px 12px;min-height:126px}
.bank h3,.terms h3{margin:0 0 5px;color:#174576;font-size:11px}.bank p{margin:2px 0;font-size:9.4px}
.sign{display:flex;flex-direction:column;justify-content:space-between;align-items:flex-end;text-align:right}
.sign b{font-size:13px;color:#184c7e}.sign span{width:170px;border-top:1px solid #b5cadb;padding-top:6px;font-size:10px;color:#536d83}
.terms{border:1px solid #dbe6ee;border-radius:8px;padding:10px 12px;margin:12px 0;break-inside:avoid}
.terms p{font-size:9px;margin:3px 0;color:#58748b}
.footer{border-top:1px solid #dbe4ef;padding-top:9px;font-size:9px;color:#628097;text-align:center;margin-top:16px}
@media(max-width:740px){.brand,.grid,.bank-sign{grid-template-columns:1fr}.doc-head{text-align:left}.sheet{min-height:0;padding:12px;margin:0 8px 15px}.brand h1{font-size:19px} .print-actions{margin:8px}}
@media print{
 html,body{margin:0;background:#fff}
 .sheet{max-width:none;min-height:0;margin:0;padding:0;box-shadow:none}
 .print-actions{display:none}
 .brand{-webkit-print-color-adjust:exact;print-color-adjust:exact}
 .block,.bank-sign,.terms{page-break-inside:avoid;break-inside:avoid}
}
</style>
</head><body>
@php
 $date=date('d M Y',strtotime((string)($receipt->receipt_date??$receipt->created_at)));
 $kind=match((string)$receipt->reference_type){
    'against_reference'=>'Against Reference (Invoice)',
    'new_reference'=>'New Reference / Advance',
    'on_account'=>'On Account',
    default=>ucwords(str_replace('_',' ',(string)$receipt->reference_type))
 };
 $logo=null;
 foreach(['assets/logo.png','images/logo.png','logo.png','assets/images/logo.png'] as $p){
   if(is_file(public_path($p))){$logo=asset($p);break;}
 }
 $paymentDate=(string)($receipt->receipt_date??'');
 $balance=$invoice?(float)($invoice->balance_due??$invoice->balance_total??0):null;
@endphp
<div class="print-actions">
 <strong>ASSAM MOTORS · {{ $receipt->receipt_no }}</strong>
 <div style="display:flex;gap:8px;flex-wrap:wrap">
  <a href="{{ route('erp.money-receipts.show',$receipt->id) }}">← Receipt</a>
  <a href="{{ route('erp.money-receipts.index') }}">Receipt Register</a>
  <button onclick="window.print()" type="button">Print / Save PDF</button>
 </div>
</div>
<main class="sheet">
 <header class="brand">
   <div class="brand-info">
     @if($logo)<img class="logo" src="{{ $logo }}" alt="Assam Motors logo">
     @else<span class="logo-fallback" aria-hidden="true">AM</span>@endif
     <div>
       <h1>ASSAM MOTORS</h1>
       <div class="address">
         <div>Bishnu Rabha Path, Hockey Stadium Rd, Sawkuchi, Guwahati-781040, Assam.</div>
         <div>MSME: UDYAM-AS-03-0045843 &nbsp;|&nbsp; GSTIN: 18CAHPS7155P1ZQ</div>
         <div>Phone: 9864043620 &nbsp;|&nbsp; Email: service@assammotors.com</div>
         <div>State: 18-Assam &nbsp;|&nbsp; Website: www.assammotors.com</div>
       </div>
     </div>
   </div>
   <div class="doc-head">
     <h2>Money Receipt</h2>
     <span class="docno">{{ $receipt->receipt_no }}</span>
     <small>RECEIPT DATE: {{ $date }}</small>
     <small>{{ strtoupper((string)$receipt->payment_status) }}@if($receipt->reversed_at) · REVERSED @endif</small>
   </div>
 </header>
 <div class="sub-title">Acknowledgement of customer payment · Same approved ASSAM MOTORS layout as the new Invoice print. This is <strong>not a Tax Invoice</strong> and does not add or recalculate GST.</div>
 <div class="grid">
  <div class="block">
   <h3>Received From / Customer</h3>
   <dl>
    <dt>Customer</dt><dd>{{ $receipt->customer_name?:'—' }}</dd>
    <dt>Customer Code</dt><dd>{{ $receipt->customer_code?:'—' }}</dd>
    <dt>Mobile</dt><dd>{{ $receipt->customer_phone?:'—' }}</dd>
    <dt>Address</dt><dd>{{ $receipt->customer_address?:'—' }}</dd>
   </dl>
  </div>
  <div class="block">
   <h3>Receipt Reference / Vehicle</h3>
   <dl>
    <dt>Payment Type</dt><dd>{{ $kind }}</dd>
    <dt>Against Ref.</dt><dd>{{ $receipt->reference_no?:'—' }}</dd>
    <dt>Invoice</dt><dd>{{ $invoice->invoice_no??'—' }}</dd>
    <dt>Vehicle / JC</dt><dd>{{ $invoice->vehicle_reg_no??$job->vehicle_reg_no??'—' }} · {{ $job->job_no??'—' }}</dd>
   </dl>
  </div>
 </div>
 <h3 class="section-title">Mode of Payment &amp; Collection</h3>
 <table><thead><tr><th>Details</th><th>Payment Method</th><th>Reference / Transaction ID</th><th class="num">Amount Received</th></tr></thead><tbody>
   @if((float)$receipt->cash_amount>0)
   <tr><td>Cash Collection</td><td>Cash</td><td>—</td><td class="num">₹{{ number_format((float)$receipt->cash_amount,2) }}</td></tr>
   @endif
   @if((float)$receipt->online_amount>0)
   <tr><td>Online / Bank Collection</td><td>{{ $receipt->payment_mode }}</td><td>{{ $receipt->online_reference?:'—' }}</td><td class="num">₹{{ number_format((float)$receipt->online_amount,2) }}</td></tr>
   @endif
   @if((float)$receipt->cash_amount<=0 && (float)$receipt->online_amount<=0)
   <tr><td>Received Amount</td><td>{{ $receipt->payment_mode }}</td><td>{{ $receipt->online_reference?:'—' }}</td><td class="num">₹{{ number_format($receiptAmount,2) }}</td></tr>
   @endif
  </tbody></table>
 <div class="receipt-total"><span>AMOUNT RECEIVED</span><strong>₹{{ number_format($receiptAmount,2) }}</strong></div>
 <div class="words"><strong>Amount in Words:</strong> {{ $words }}</div>
 <div class="grid" style="margin-top:15px">
  <div class="block">
   <h3>Payment Allocation</h3>
   <dl>
     <dt>Allocated to Invoice</dt><dd>₹{{ number_format((float)$allocation,2) }}</dd>
     <dt>Unallocated</dt><dd>₹{{ number_format(max(0,$receiptAmount-(float)$allocation),2) }}</dd>
     <dt>Receipt Type</dt><dd>{{ $kind }}</dd>
   </dl>
  </div>
  <div class="block">
   <h3>Invoice / Ledger Reference</h3>
   <dl>
    <dt>Against Invoice</dt><dd>{{ $invoice->invoice_no??'—' }}</dd>
    <dt>Invoice Amount</dt><dd>{{ $invoice?'₹'.number_format((float)$invoice->grand_total,2):'—' }}</dd>
    <dt>Current Invoice Due</dt><dd>{{ $invoice?'₹'.number_format((float)$balance,2):'—' }}</dd>
    <dt>Customer Ledger</dt><dd>{{ $ledger?'Payment Credit Recorded':'Please Verify Ledger' }}</dd>
   </dl>
  </div>
 </div>
 <p class="note"><strong>Narration:</strong> {{ $receipt->notes?:'Payment received from customer against the reference shown.' }}<br>
 Received by: {{ $receipt->received_by?:'Assam Motors' }} &nbsp;·&nbsp; Receipt No. {{ $receipt->receipt_no }}</p>
 <div class="bank-sign">
   <div class="bank"><h3>Bank Details</h3>
    <p><strong>Bank:</strong> HDFC BANK, HATIGAON</p>
    <p><strong>Account No.:</strong> 50200100195339</p>
    <p><strong>IFSC:</strong> HDFC0005671</p>
    <p><strong>Account Holder:</strong> ASSAM MOTORS</p>
   </div>
   <div class="sign"><b>For: ASSAM MOTORS</b><span>Authorised Signatory</span></div>
 </div>
 <div class="terms"><h3>Receipt Notes &amp; Terms</h3>
   <p>This receipt acknowledges payment received only; it is not a Tax Invoice. Refer to the Tax/Service Invoice for GST details.</p>
   <p>Cheque/bank instruments are subject to realisation. If the receipt is reversed, the original receipt and accounting trail must be retained.</p>
 </div>
 <footer class="footer">ASSAM MOTORS · Customer Money Receipt · {{ $receipt->receipt_no }} · Payment acknowledgement only · No GST levied again</footer>
</main>
</body></html>
