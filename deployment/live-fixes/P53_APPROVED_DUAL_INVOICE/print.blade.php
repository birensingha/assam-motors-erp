<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $inv->invoice_no }} — {{ request()->query('format')==='summary' ? 'Customer Cost Summary' : 'Tax / Service Invoice' }} — Assam Motors</title>
<style>
@page{size:A4 portrait;margin:9mm 11mm 11mm}
*{box-sizing:border-box}
:root{--blue:#123f78;--ink:#173653;--line:#d9e3ec;--pale:#edf4fa;--muted:#64768a}
body{margin:0;background:#eef2f7;color:#1b324a;font-family:Arial,Helvetica,sans-serif;font-size:10.4px;line-height:1.38;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.print-actions{max-width:1010px;margin:15px auto 10px;display:flex;align-items:center;justify-content:space-between;gap:9px;flex-wrap:wrap;padding:10px 12px;background:#fff;border:1px solid #dce6ee;border-radius:12px}
.print-actions .actions{display:flex;gap:8px;flex-wrap:wrap}
.print-actions a,.print-actions button{font:700 12px Arial,sans-serif;display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:9px 13px;text-decoration:none;border-radius:9px;border:1px solid #c9d9ea;color:#194f84;background:#f5f9ff;cursor:pointer}
.print-actions button,.print-actions .selected{color:#fff;background:#175ba0;border-color:#175ba0}
.sheet{max-width:1010px;min-height:1180px;margin:0 auto 23px;background:#fff;box-shadow:0 8px 28px #16365718;padding:28px 32px 25px}
.brand{display:grid;grid-template-columns:minmax(0,1fr) 225px;gap:13px;align-items:stretch;border-radius:9px;overflow:hidden;border:1px solid #1c4e86;background:#f7faff}
.brand-info{display:flex;align-items:flex-start;gap:11px;padding:12px 13px}
.logo{width:61px;height:61px;max-width:61px;max-height:61px;object-fit:contain;flex:none}
.logo-fallback{display:grid;place-items:center;width:61px;height:61px;flex:none;background:#19599b;border-radius:9px;color:#fff;font-size:23px;font-weight:900;letter-spacing:-2px}
.brand-copy h1{margin:0 0 6px;font-size:22px;color:#16477e;letter-spacing:.025em;line-height:1}
.brand-copy .address{font-size:9.1px;color:#3e5873;line-height:1.32}
.brand-copy .address div{margin:1px 0}
.doc-head{background:linear-gradient(160deg,#194f89,#0e346b);color:#fff;padding:13px 13px 9px;display:flex;flex-direction:column;justify-content:center;text-align:right}
.doc-head h2{font-size:17px;line-height:1.14;margin:0 0 10px;text-transform:uppercase;letter-spacing:.025em}
.doc-head .doc-number{font-size:12px;font-weight:bold;overflow-wrap:anywhere}
.doc-head .doc-date{margin-top:6px;font-size:9.4px}
.document-description{background:#f0f6fc;padding:7px 11px;border-left:3px solid #2f6daf;margin-top:9px;color:#305674;font-size:9px}
.info-panels{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:13px}
.info{border:1px solid #dbe5ee;border-radius:7px;overflow:hidden;min-height:91px;break-inside:avoid}
.info h3{background:#edf3f9;color:#224b73;font-size:9.3px;padding:6px 9px;margin:0;text-transform:uppercase;letter-spacing:.075em}
.info dl{padding:6px 9px;margin:0;display:grid;grid-template-columns:90px minmax(0,1fr);gap:3px 4px;line-height:1.38}
.info dt{color:#64768a}.info dd{margin:0;font-weight:600;overflow-wrap:anywhere}
.section{margin-top:12px;break-inside:auto}
.section h3{margin:0 0 6px;font-size:11.4px;font-weight:800;color:#123e75;letter-spacing:.012em;border-bottom:2px solid #c8d8e9;padding:5px 0}
.section table{width:100%;border-collapse:collapse;table-layout:fixed}
.section th{background:#eaf1f9;color:#21466d;text-align:left;font-size:8.2px;letter-spacing:.035em;text-transform:uppercase;padding:7px 5px;border-top:1px solid #bbd0e3;border-bottom:1px solid #bbd0e3}
.section td{padding:7px 5px;vertical-align:top;border-bottom:1px solid #e4ebf2;overflow-wrap:anywhere}
.section .num{text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums}
.section small{font-size:8px;color:#64788b}
.section .name{font-weight:700;color:#183951}
.section .empty{text-align:center;color:#7c8998;font-style:italic;padding:10px}
.qty-col{width:9%}.index-col{width:4.5%}.code-col{width:13%}.rate-col{width:12.5%}.disc-col{width:10%}.gst-col{width:9%}.amount-col{width:14%}
.line-note{margin:4px 0 0;line-height:1.35;color:#526982;font-size:8.5px}
.osl-included{font-size:8.4px;color:#6d8297;margin-top:3px}
.money-area{display:grid;grid-template-columns:minmax(0,1fr) 47%;gap:13px;align-items:start;margin-top:14px}
.money-message{border-left:3px solid #2f6ca5;background:#f1f6fb;padding:10px;font-size:9px;color:#4c6982;line-height:1.55}
.amounts{border:1px solid #d4e1ee;border-radius:8px;overflow:hidden;break-inside:avoid}
.amounts .trow{display:flex;justify-content:space-between;gap:13px;padding:5px 9px;border-bottom:1px solid #e5edf4;font-size:9.4px}
.amounts .trow b{text-align:right;white-space:nowrap}
.amounts .total{background:#16477f;color:#fff;display:flex;justify-content:space-between;gap:12px;padding:10px;font-size:13.2px;font-weight:900}
.amounts .total b{white-space:nowrap}
.tax-warning{border:1px solid #e7b976;background:#fff9e9;color:#6e4f1f;font-size:9px;padding:8px;border-radius:6px;margin-top:9px}
.bank-sign{display:grid;grid-template-columns:1fr .75fr;gap:13px;margin-top:14px;break-inside:avoid}
.bank,.sign{border:1px solid #d9e4ef;border-radius:8px;padding:10px 11px;min-height:111px}
.bank h3,.terms h3{margin:0 0 6px;color:#174576;font-size:11.3px}
.bank p{margin:2px 0;font-size:9.3px}
.sign{display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between;text-align:right}
.sign b{color:#194b80;font-size:12px}
.sign span{display:block;border-top:1px solid #bdcddd;padding-top:5px;width:160px;font-size:9.5px;color:#536d83}
.terms{border:1px solid #dce5ee;border-radius:8px;padding:9px 12px;margin-top:10px;break-inside:auto}
.terms ol{margin:4px 0 0;padding-left:18px;font-size:8.6px;color:#53677b}
.terms li{margin:3px 0}
.legal{margin-top:13px;border-top:1px solid #d9e3ee;padding-top:7px;color:#5b738c;font-size:8.6px;text-align:center}
.ledger-note{font-size:8.2px;color:#657b91;margin-top:5px}
@media screen and (max-width:760px){
 .sheet{min-height:0;margin:0 8px 20px;padding:15px 12px;box-shadow:0 4px 20px #173e6521}
 .brand{grid-template-columns:1fr}.doc-head{text-align:left}
 .info-panels{grid-template-columns:1fr}
 .section{overflow-x:auto}.section table{min-width:660px}
 .money-area,.bank-sign{grid-template-columns:1fr}
 .print-actions{margin:8px;padding:9px}
 .brand-copy h1{font-size:20px}
}
@media print{
 html,body{margin:0;padding:0;background:#fff}
 .print-actions{display:none!important}
 .sheet{width:auto;min-height:0;max-width:none;margin:0;box-shadow:none;padding:0}
 .section{overflow:visible!important}
 .section table{min-width:0!important}
 .section thead{display:table-header-group}
 .section tr{break-inside:avoid;page-break-inside:avoid}
 .info,.amounts,.bank-sign{break-inside:avoid;page-break-inside:avoid}
 a{color:inherit;text-decoration:none}
 .brand{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style>
</head>
<body>
@php
  // Only presentation changes. Never rewrite GST, invoice totals or customer ledger.
  $summaryMode = request()->query('format')==='summary';
  $tax=(float)($inv->gst_total??$inv->tax_total??0);
  $preRound=(float)($inv->pre_round_total??$inv->grand_total??0);
  $roundOff=(float)($inv->round_off??0);
  $grand=(float)($inv->grand_total??0);
  $paid=(float)($inv->paid_amount??$inv->paid_total??0);
  $balance=(float)($inv->balance_due??$inv->balance_total??0);
  $invoiceNo=(string)($inv->invoice_no??'');
  $rotLines=$labour->reject(fn($line)=>strtoupper(trim((string)($line->mechanic_name??'')))==='OSL');
  $oslLines=$labour->filter(fn($line)=>strtoupper(trim((string)($line->mechanic_name??'')))==='OSL');
  $oslAmount=$oslLines->sum(fn($line)=>(float)($line->total??0));
  $hasRateAndNoTax=!$summaryMode && abs($tax)<0.005 &&
     ($parts->contains(fn($x)=>(float)($x->gst_percent??0)>0&&(float)($x->total??0)>0)
      || $labour->contains(fn($x)=>(float)($x->gst_percent??0)>0&&(float)($x->total??0)>0));
  $vehicleName=trim((string)($job->vehicle_make??'').' '.(string)($job->vehicle_model??'').' '.(string)($job->vehicle_variant??''));
  $logoPath=null;
  foreach(['assets/logo.png','images/logo.png','logo.png','assets/images/logo.png'] as $path) {
    if(is_file(public_path($path))){$logoPath=asset($path);break;}
  }
@endphp
<div class="print-actions" role="navigation" aria-label="Invoice print formats">
  <div><strong>ASSAM MOTORS</strong> · {{ $invoiceNo }} · Saved grand total ₹{{ number_format($grand,2) }}</div>
  <div class="actions">
    <a href="{{ route('erp.invoices.show',$inv->id) }}">← Invoice</a>
    <a class="{{ !$summaryMode ? 'selected':'' }}" href="{{ route('erp.invoices.print',$inv->id) }}?format=tax">GST Invoice</a>
    <a class="{{ $summaryMode ? 'selected':'' }}" href="{{ route('erp.invoices.print',$inv->id) }}?format=summary">Cost Summary (no GST breakup)</a>
    <button type="button" onclick="window.print()">Print / Save PDF</button>
  </div>
</div>
<main class="sheet">
 <header class="brand">
  <div class="brand-info">
    @if($logoPath)<img class="logo" src="{{ $logoPath }}" alt="Assam Motors logo">
    @else<span class="logo-fallback" aria-hidden="true">AM</span>@endif
    <div class="brand-copy">
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
    <h2>{{ $summaryMode ? 'Customer Cost Summary' : 'Tax / Service Invoice' }}</h2>
    @if($summaryMode)<div style="font-weight:700;font-size:9px;margin:-5px 0 7px">TAX INCLUDED — NOT A TAX INVOICE</div>@endif
    <span class="doc-number">DOCUMENT NO. {{ $invoiceNo }}</span>
    <span class="doc-date">DATE {{ date('d M Y',strtotime((string)($inv->invoice_date??'now'))) }}</span>
  </div>
 </header>
 @if($summaryMode)
  <div class="document-description">Tax is included in the final amount where applicable. This simplified customer document is <strong>not a Tax Invoice.</strong></div>
 @endif
 <section class="info-panels">
   <div class="info"><h3>Bill To / Customer</h3><dl>
     <dt>Name</dt><dd>{{ $inv->customer_name ?: '—' }}</dd>
     <dt>Phone</dt><dd>{{ $inv->customer_phone ?: '—' }}</dd>
     <dt>Email</dt><dd>{{ $inv->customer_email ?? $job->customer_email ?? '—' }}</dd>
     <dt>Address</dt><dd>{{ $inv->customer_address ?? $job->customer_address ?? '—' }}</dd>
   </dl></div>
   <div class="info"><h3>Vehicle / Job</h3><dl>
     <dt>Job Card</dt><dd>{{ $job->job_no }}</dd>
     <dt>Registration</dt><dd>{{ $inv->vehicle_reg_no ?? $job->vehicle_reg_no ?? '—' }}</dd>
     <dt>Vehicle</dt><dd>{{ $vehicleName !== '' ? $vehicleName : '—' }}</dd>
     <dt>Odometer</dt><dd>{{ $job->odometer ?? $job->odometer_reading ?? '—' }}</dd>
   </dl></div>
   <div class="info"><h3>{{ $summaryMode ? 'Document Details' : 'Invoice Details' }}</h3><dl>
     <dt>Invoice No.</dt><dd>{{ $invoiceNo }}</dd>
     <dt>Date</dt><dd>{{ date('d M Y',strtotime((string)($inv->invoice_date??'now'))) }}</dd>
     <dt>Status</dt><dd>{{ strtoupper((string)($inv->status??'—')) }}</dd>
     <dt>{{ $summaryMode ? 'Tax' : 'State of Supply' }}</dt><dd>{{ $summaryMode ? 'Included where applicable' : '18-Assam' }}</dd>
   </dl></div>
   <div class="info"><h3>Vehicle Identity</h3><dl>
     <dt>Chassis / VIN</dt><dd>{{ $job->chassis_no ?? $job->chassis_number ?? '—' }}</dd>
     <dt>Engine No.</dt><dd>{{ $job->engine_no ?? $job->engine_number ?? '—' }}</dd>
     <dt>Fuel Type</dt><dd>{{ $job->vehicle_fuel_type ?? $job->fuel_type ?? '—' }}</dd>
     <dt>Service</dt><dd>{{ $job->service_type ?? 'Maintenance' }}</dd>
   </dl></div>
 </section>

 <section class="section">
   <h3>Parts / Materials</h3>
   <table><thead><tr><th class="index-col">#</th><th>Part</th><th class="code-col">Code / HSN</th><th class="num qty-col">Qty</th><th class="num rate-col">Rate</th><th class="num disc-col">Disc.</th>@unless($summaryMode)<th class="num gst-col">GST</th>@endunless<th class="num amount-col">Amount</th></tr></thead><tbody>
   @forelse($parts as $p)
    <tr>
     <td>{{ $loop->iteration }}</td>
     <td><span class="name">{{ $p->part_name??'Part' }}</span>@if(!empty($p->part_note))<div class="line-note">Note: {{ $p->part_note }}</div>@endif</td>
     <td>{{ $p->part_number ?: ($p->part_code??'—') }}@if(!empty($p->hsn_code)||!empty($p->hsn))<br><small>HSN: {{ $p->hsn_code??$p->hsn }}</small>@endif</td>
     <td class="num">{{ number_format((float)($p->qty??0),2) }}</td>
     <td class="num">₹{{ number_format((float)($p->rate??0),2) }}</td>
     <td class="num">{{ number_format((float)($p->discount_percent??$p->discount_pct??0),2) }}%</td>
     @unless($summaryMode)<td class="num">{{ number_format((float)($p->gst_percent??0),2) }}%</td>@endunless
     <td class="num">₹{{ number_format((float)($p->total??0),2) }}</td>
    </tr>
   @empty <tr><td class="empty" colspan="{{ $summaryMode?7:8 }}">No parts / materials lines.</td></tr>
   @endforelse
   </tbody></table>
 </section>

 <section class="section">
   <h3>Labour / ROT Charges</h3>
   <table><thead><tr><th class="index-col">#</th><th>ROT / Labour</th><th class="num qty-col">Std Hrs</th><th class="num rate-col">Rate</th><th class="num disc-col">Disc.</th>@unless($summaryMode)<th class="num gst-col">GST</th>@endunless<th class="num amount-col">Amount</th></tr></thead><tbody>
    @forelse($rotLines as $l)
    <tr>
     <td>{{ $loop->iteration }}</td>
     <td><span class="name">{{ $l->rot_code ?? 'LABOUR' }} — {{ $l->description ?? 'Labour Charges' }}</span>@if(!empty($l->notes)||!empty($l->labour_note))<div class="line-note">Note: {{ $l->notes??$l->labour_note }}</div>@endif</td>
     <td class="num">{{ number_format((float)($l->standard_hours??0),2) }}</td>
     <td class="num">₹{{ number_format((float)($l->labour_rate??0),2) }}</td>
     <td class="num">{{ number_format((float)($l->discount_percent??$l->discount_pct??0),2) }}%</td>
     @unless($summaryMode)<td class="num">{{ number_format((float)($l->gst_percent??0),2) }}%</td>@endunless
     <td class="num">₹{{ number_format((float)($l->total??0),2) }}</td>
    </tr>
    @empty <tr><td class="empty" colspan="{{ $summaryMode?6:7 }}">No labour / ROT lines.</td></tr>
    @endforelse
   </tbody></table>
 </section>

 <section class="section">
   <h3>OSL Jobs / Outside Services</h3>
   <table><thead><tr><th class="index-col">#</th><th>Outside Service / Job</th><th class="num qty-col">Qty</th><th class="num rate-col">Rate</th><th class="num disc-col">Disc.</th>@unless($summaryMode)<th class="num gst-col">GST</th>@endunless<th class="num amount-col">Amount</th></tr></thead><tbody>
   @forelse($oslLines as $l)
    <tr><td>{{ $loop->iteration }}</td><td><span class="name">{{ $l->description ?? $l->rot_code ?? 'Outside Service' }}</span>
    @if(!empty($l->notes)||!empty($l->labour_note))<div class="line-note">{{ $l->notes??$l->labour_note }}</div>@endif</td>
    <td class="num">{{ number_format((float)($l->standard_hours??1),2) }}</td><td class="num">₹{{ number_format((float)($l->labour_rate??0),2) }}</td>
    <td class="num">{{ number_format((float)($l->discount_percent??$l->discount_pct??0),2) }}%</td>
    @unless($summaryMode)<td class="num">{{ number_format((float)($l->gst_percent??0),2) }}%</td>@endunless
    <td class="num">₹{{ number_format((float)($l->total??0),2) }}</td></tr>
   @empty<tr><td class="empty" colspan="{{ $summaryMode?6:7 }}">No OSL / outside service lines recorded for this invoice.</td></tr>@endforelse
   </tbody></table>
 </section>

 <section class="money-area">
  <div class="money-message">
    <strong>Invoice No. {{ $invoiceNo }}</strong><br>
    All amounts below come from the saved invoice record. Printing another format does not create another invoice or ledger entry.
    @if($oslAmount>0)<div class="osl-included">OSL charges shown separately in the table are included in recorded Labour Total; not added twice.</div>@endif
    @if($summaryMode)<div class="osl-included"><strong>Tax included where applicable — not a tax invoice.</strong></div>@endif
  </div>
  <div class="amounts">
   <div class="trow"><span>Parts Total</span><b>₹{{ number_format((float)($inv->parts_total??0),2) }}</b></div>
   <div class="trow"><span>Labour / ROT Total</span><b>₹{{ number_format((float)($inv->labour_total??0),2) }}</b></div>
   @if($oslAmount>0)<div class="trow"><span>OSL (included above)</span><b>₹{{ number_format($oslAmount,2) }}</b></div>@endif
   @if($summaryMode)
    <div class="trow"><span>Tax</span><b>Included where applicable</b></div>
   @else
    <div class="trow"><span>Tax / GST (recorded)</span><b>₹{{ number_format($tax,2) }}</b></div>
   @endif
   <div class="trow"><span>Total Before Round Off</span><b>₹{{ number_format($preRound,2) }}</b></div>
   <div class="trow"><span>Round Off</span><b>{{ $roundOff>=0?'+':'−' }}₹{{ number_format(abs($roundOff),2) }}</b></div>
   <div class="total"><span>Invoice Total</span><b>₹{{ number_format($grand,2) }}</b></div>
  </div>
 </section>
 @if($hasRateAndNoTax)
   <div class="tax-warning"><strong>Accounting verification required:</strong> Some saved lines show a GST rate while the invoice records ₹0.00 GST. Rates and amounts are shown exactly as saved; no tax has been recomputed. Verify the correct GST-inclusive/exclusive basis before issuing this as a tax invoice.</div>
 @endif
 <div class="ledger-note">Ledger reference: {{ $invoiceNo }} &nbsp;·&nbsp; Status: {{ $inv->status ?? '—' }} &nbsp;·&nbsp; Amount payable: ₹{{ number_format($grand,2) }}</div>
 <section class="bank-sign">
   <div class="bank"><h3>Bank Details</h3>
    <p><strong>Bank:</strong> HDFC BANK, HATIGAON</p>
    <p><strong>Account No.:</strong> 50200100195339</p>
    <p><strong>IFSC:</strong> HDFC0005671</p>
    <p><strong>Account Holder:</strong> ASSAM MOTORS</p>
   </div>
   <div class="sign"><b>For: ASSAM MOTORS</b><span>Authorized Signatory</span></div>
 </section>
 <section class="terms"><h3>Terms and Conditions</h3>
  <strong>E. &amp; O. E.</strong>
  <ol>
   <li>Goods once sold will not be taken back.</li>
   <li>Interest @ 5% per month will be charged if payment is not made within the stipulated time.</li>
   <li>Parking charges of ₹110/- may apply if the vehicle is not collected within 7 days after the bill or notification.</li>
   <li>If the vehicle remains uncollected for 3 months after billing, recovery action may be taken as per applicable terms and law.</li>
  </ol>
 </section>
 <footer class="legal">
  @if($summaryMode)Customer Cost Summary — Tax included where applicable. This document is NOT A TAX INVOICE.
  @else Computer-generated Tax / Service Invoice. Verify that the stored GST breakdown is correct before tax use.
  @endif
 </footer>
</main>
</body>
</html>
