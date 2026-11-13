<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $doc->return_no }} — Purchase Return — Assam Motors</title>
<style>
@page{size:A4;margin:12mm}
*{box-sizing:border-box}
body{margin:0;padding:18px;font-family:Arial,system-ui,sans-serif;color:#19334a;font-size:11px}
.sheet{max-width:1000px;margin:auto}
header{display:flex;align-items:center;justify-content:space-between;gap:18px;border-bottom:2px solid #1c4f7c;padding-bottom:13px}
header img{height:67px;max-width:100px;object-fit:contain}
header .brand{text-align:center;flex:1}
header h1{font-size:22px;margin:1px 0 5px;letter-spacing:.055em;color:#174d7d}
header small{display:block;font-size:10px;line-height:1.55}
.doc-title{text-align:center;font-size:19px;font-weight:850;letter-spacing:.035em;margin:16px 0 5px}
.doc-sub{text-align:center;color:#62768b;font-size:10px;margin-bottom:12px}
.status{color:#9f2635;font-weight:850;text-align:center;margin-bottom:10px}
.meta{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid #bfcbd7;margin:13px 0}
.meta div{padding:9px 10px;border:1px solid #d5dfe8}
.meta small{display:block;color:#6d8296;font-size:9px}
.meta strong{display:block;margin-top:4px;font-size:11px}
table{width:100%;border-collapse:collapse;table-layout:fixed}
th{background:#e8f1fa;text-align:left;font-size:9px}
td,th{border:1px solid #becbd8;padding:8px 5px;vertical-align:top;overflow-wrap:anywhere}
.num{text-align:right;font-variant-numeric:tabular-nums}
.reason{margin:15px 0;border:1px solid #d6e1ec;padding:11px;line-height:1.6}
.totals{margin:15px 0 0 auto;width:min(100%,330px);border:1px solid #c3d1de}
.totals div{display:flex;justify-content:space-between;padding:8px 11px;border-bottom:1px solid #e5edf2}
.totals div:last-child{background:#e8f1fa;font-weight:850;font-size:13px;border:0}
.notes{margin-top:14px;font-size:9px;color:#6e7e8d;line-height:1.5}
.sign{display:flex;justify-content:space-between;margin-top:50px;color:#5c6c7e}
.actions{text-align:right;margin:0 0 12px}.actions button{padding:8px 15px;border-radius:5px;cursor:pointer}
@media print{.actions{display:none}body{padding:0}.sheet{max-width:none}header,th,.totals div:last-child{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
</head>
<body>
<div class="sheet">
  <div class="actions"><button onclick="window.print()">Print / Save PDF</button></div>
  <header>
    <img src="{{ asset('legacy/images/logo.png') }}" alt="Assam Motors Logo">
    <div class="brand">
      <h1>ASSAM MOTORS</h1>
      <small>Bishnu Rabha Path, Hockey Stadium Rd, Sawkuchi, Guwahati-781040, Assam</small>
      <small>GSTIN: 18CAHPS7155P1ZQ · MSME: UDYAM-AS-03-0045843</small>
      <small>Phone: 9864043620 · service@assammotors.com</small>
    </div>
    <div style="width:67px"></div>
  </header>
  <div class="doc-title">PURCHASE RETURN / SUPPLIER DEBIT NOTE</div>
  <div class="doc-sub">Reference statement against original supplier Purchase Bill</div>
  @if($doc->record_status==='VOID')<div class="status">VOID / CANCELLED · NOT VALID FOR ACCOUNTING</div>@endif
  <div class="meta">
    <div><small>Return No.</small><strong>{{ $doc->return_no }}</strong></div>
    <div><small>Return Date</small><strong>{{ date('d M Y',strtotime($doc->return_date)) }}</strong></div>
    <div><small>Supplier / Vendor</small><strong>{{ $doc->vendor_name }}</strong></div>
    <div><small>Against Original Purchase Bill</small><strong>{{ $doc->source_batch_no ?: ($original->purchase_no??'—') }}</strong></div>
    <div><small>Supplier Credit Note Ref.</small><strong>{{ $doc->supplier_credit_note?:'—' }}</strong></div>
    <div><small>Purchase Type</small><strong>{{ $doc->purchase_type }}</strong></div>
  </div>
  <table>
    <thead><tr>
      <th style="width:5%">SL</th>
      <th style="width:30%">Item / Service</th>
      <th style="width:10%">HSN / SAC</th>
      <th style="width:7%" class="num">Qty</th>
      <th style="width:10%" class="num">Rate</th>
      <th style="width:10%" class="num">Disc.</th>
      <th style="width:9%" class="num">Taxable</th>
      <th style="width:9%" class="num">GST</th>
      <th style="width:10%" class="num">Value (−)</th>
    </tr></thead>
    <tbody>
    @foreach($lines as $idx=>$line)
      <tr>
        <td>{{ $idx+1 }}</td>
        <td>{{ $line->item_description }}</td>
        <td>{{ $line->hsn_code?:'—' }}</td>
        <td class="num">{{ number_format((float)$line->qty,3) }}</td>
        <td class="num">{{ number_format((float)$line->unit_cost,2) }}</td>
        <td class="num">{{ number_format((float)$line->discount_amount,2) }}</td>
        <td class="num">{{ number_format((float)$line->taxable_amount,2) }}</td>
        <td class="num">{{ number_format((float)$line->gst_amount,2) }}</td>
        <td class="num">−{{ number_format((float)$line->total_amount,2) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
  <div class="totals">
    <div><span>Taxable Return Value</span><strong>−₹{{ number_format((float)$doc->taxable_amount,2) }}</strong></div>
    <div><span>GST Reversal Reference</span><strong>−₹{{ number_format((float)$doc->gst_amount,2) }}</strong></div>
    <div><span>Round Off</span><strong>{{ number_format((float)$doc->round_off,2) }}</strong></div>
    <div><span>Purchase Return Total</span><strong>−₹{{ number_format((float)$doc->grand_total,2) }}</strong></div>
  </div>
  <div class="reason"><strong>Reason:</strong> {{ $doc->reason }}</div>
  @if($doc->record_status==='VOID')<div class="reason"><strong>VOID REASON:</strong> {{ $doc->void_reason }}</div>@endif
  <div class="notes">
    Original Purchase Bill, supplier invoice and vendor payments remain on record. This return is an internal purchase-return/supplier-debit statement based on original saved values; supplier GST credit-note compliance must be separately verified. For OSL returns, this document does not reverse customer invoices.
  </div>
  <div class="sign"><span>Prepared by: {{ $doc->created_by }}</span><span>Authorized Signature / Supplier Acknowledgement</span></div>
</div>
</body>
</html>
