#!/usr/bin/env python3
"""FIX103 Estimate table & Invoice-style estimate print. Presentation only."""
from pathlib import Path
import hashlib,sys,os,tempfile,re
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):raise SystemExit('check|apply APP')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();assert (root/'artisan').is_file()
paths={
 'entry':'resources/views/estimates/create.blade.php',
 'js':'public/js/estimate-entry.js',
 'print':'resources/views/estimates/print.blade.php',
 'invoice':'resources/views/invoices/print.blade.php'
}
expected={
 'entry':'ac54ba8d68a1ed3c8e147c7183ae9deb8e199714d036bbf0d1fd9db51ee648c9',
 'js':'41633a3f0d2afe7acacb01bece4d286d984cbca654a1a4163e650bf903d63e0d',
 'print':'48c02b214c86742e6be6286ad19b8ee7fa16f87403b19d6804957c9b1bae8786',
 'invoice':'6a78266f7b607991e5f20d1c983ccf05b7eb1ad9adabd8a6894a56d1648b1df8'
}
data={k:(root/v).read_text(encoding='utf8',errors='surrogateescape') for k,v in paths.items()}
for k,v in paths.items():
 sha=hashlib.sha256((root/v).read_bytes()).hexdigest()
 print('FIX103_SOURCE',k,sha)
 if sha!=expected[k]:
  if k!='invoice' and 'FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE' in data[k]:
   print('FIX103_ALREADY_APPLIED');sys.exit(0)
  raise SystemExit('FIX103_CONCURRENT_CHANGE_STOP '+k)
def swap(s,old,new,label):
 n=s.count(old);print('FIX103_ANCHOR',label,n)
 if n!=1:raise SystemExit('FIX103_ANCHOR_MISMATCH_'+label)
 return s.replace(old,new,1)
entry=data['entry']
old='<th>Type</th><th>Reference</th><th>Description</th><th>Qty</th><th>Unit</th><th>Rate</th><th>Disc %</th><th>GST %</th><th>Note</th><th>Total Preview</th><th></th>'
new='<th>Type</th><th>Reference</th><th>Description</th><th>Qty</th><th>Unit</th><th>Rate</th><th>Disc %</th><th>Discount ₹</th><th>GST %</th><th>GST ₹</th><th>Final Amt (Incl. Tax)</th><th>Action</th>'
entry=swap(entry,old,new,'headers')
entry=swap(entry,"asset('js/estimate-entry.js')","asset('js/estimate-entry.js') }}?v=fix103{{ ''",'asset_cache') if False else entry
entry=swap(entry,"<script src=\"{{ asset('js/estimate-entry.js') }}\"></script>","<script src=\"{{ asset('js/estimate-entry.js') }}?v=fix103\"></script>",'asset_cache')
style=r'''
{{-- FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE: 12 compact columns without clipping Final Amt.
     The existing FIX101 autocomplete dropdown keeps overflow visible. --}}
<style id="FIX103_ESTIMATE_TABLE_COMPACT_CSS">
#estimate-form .estimate-lines-area{
 width:100%!important;max-width:100%!important;min-width:0!important;box-sizing:border-box!important;
 overflow:visible!important;overflow-x:visible!important;overflow-y:visible!important;
 max-height:none!important;height:auto!important;min-height:0!important;padding:5px 4px 9px!important
}
#estimate-form .estimate-lines-area .estimate-line-table{
 width:100%!important;min-width:0!important;max-width:100%!important;table-layout:fixed!important;
 border-collapse:separate!important;border-spacing:0 4px!important
}
#estimate-form .estimate-line-table thead th{
 padding:5px 2px!important;line-height:1.15!important;white-space:normal!important;
 font-size:9px!important;word-break:normal!important;overflow-wrap:break-word!important
}
#estimate-form .estimate-line-table tbody td{
 min-width:0!important;width:auto!important;padding:3px 2px!important;
 vertical-align:middle!important;box-sizing:border-box!important
}
#estimate-form .estimate-line-table th:nth-child(1){width:6.5%}
#estimate-form .estimate-line-table th:nth-child(2){width:18%}
#estimate-form .estimate-line-table th:nth-child(3){width:19%}
#estimate-form .estimate-line-table th:nth-child(4){width:7%}
#estimate-form .estimate-line-table th:nth-child(5){width:5.5%}
#estimate-form .estimate-line-table th:nth-child(6){width:8.5%}
#estimate-form .estimate-line-table th:nth-child(7){width:6%}
#estimate-form .estimate-line-table th:nth-child(8){width:7%}
#estimate-form .estimate-line-table th:nth-child(9){width:5.5%}
#estimate-form .estimate-line-table th:nth-child(10){width:7%}
#estimate-form .estimate-line-table th:nth-child(11){width:8.5%}
#estimate-form .estimate-line-table th:nth-child(12){width:2.5%}
#estimate-form .estimate-line-table input:not([type=hidden]),
#estimate-form .estimate-line-table select{
 width:100%!important;min-width:0!important;max-width:100%!important;
 min-height:30px!important;height:30px!important;box-sizing:border-box!important;
 padding:3px!important;font-size:10px!important;border-radius:5px!important
}
#estimate-form .estimate-line-table .estimate-ref-wrap{width:100%!important;min-width:0!important;position:relative!important}
#estimate-form .estimate-line-table .estimate-ref-menu{
 width:max(100%,min(340px,calc(100vw - 65px)))!important;
 max-width:min(440px,calc(100vw - 65px))!important;
 max-height:280px!important;overflow:auto!important;z-index:600!important
}
#estimate-form .estimate-line-table tr:focus-within{position:relative!important;z-index:350!important}
#estimate-form .estimate-line-table td:focus-within{position:relative!important;z-index:351!important}
#estimate-form .estimate-line-table .e-disc-amt,
#estimate-form .estimate-line-table .e-gst-amt{
 display:block!important;background:#f1f7ff!important;color:#214e7c!important;
 border-radius:4px!important;text-align:right!important;box-sizing:border-box!important;
 font:700 10px Arial!important;font-variant-numeric:tabular-nums!important;
 padding:7px 2px!important;white-space:nowrap!important;max-width:100%!important;overflow:hidden!important
}
#estimate-form .estimate-line-table .e-total{
 display:block!important;white-space:nowrap!important;text-align:right!important;
 max-width:100%!important;overflow:hidden!important;font:800 10px Arial!important;
 font-variant-numeric:tabular-nums!important
}
#estimate-form .estimate-line-table .e-remove{height:22px!important;width:22px!important;
 min-width:0!important;padding:0!important;margin:0!important;font-size:12px!important}
#estimate-form .purchase-foot{display:flex!important;gap:8px!important;flex-wrap:wrap!important;
 justify-content:space-between!important;align-items:center!important;max-width:100%!important;min-width:0!important}
#estimate-form .purchase-foot .preview-total{margin-left:auto!important;min-width:0!important;max-width:100%!important;
 overflow-wrap:anywhere!important;white-space:normal!important;text-align:right!important;font-size:12px!important}
@media(max-width:1050px){
 #estimate-form .estimate-line-table thead th{font-size:8px!important}
 #estimate-form .estimate-line-table input:not([type=hidden]),#estimate-form .estimate-line-table select{font-size:9px!important;padding:2px!important}
}
</style>
'''
entry=swap(entry,'<div class="line-scroll estimate-lines-area"><table',style+'<div class="line-scroll estimate-lines-area"><table','styles')
js=data['js']
ls=js.splitlines(keepends=True)
out=[];count={'disc':0,'tax':0,'note':0,'total':0}
note=None
for line in ls:
 if 'class="e-disc"' in line and '<td>' in line:
  out.append(line);out.append('      <td class="num"><output class="e-disc-amt" aria-label="Discount Amount">0.00</output></td>\n');count['disc']+=1;continue
 if 'class="e-tax"' in line and '<td>' in line:
  out.append(line);out.append('      <td class="num"><output class="e-gst-amt" aria-label="GST Amount">0.00</output></td>\n');count['tax']+=1;continue
 if '][line_note]' in line and '<input ' in line:
  match=re.search(r'<input.*?>',line)
  if not match:raise SystemExit('FIX103_NOTE_INPUT_NOT_FOUND')
  note=match.group().replace('<input ','<input type="hidden" ',1)
  count['note']+=1;continue
 if 'class="e-total"' in line and '</strong></td>' in line:
  if not note:raise SystemExit('FIX103_NOTE_ORDER_INVALID')
  line=line.replace('</strong></td>','</strong>'+note+'</td>')
  count['total']+=1
 out.append(line)
print('FIX103_JS_FIELDS',count)
if set(count.values())!={1}:raise SystemExit('FIX103_JS_FIELD_AMBIGUOUS')
js=''.join(out)
js=swap(js,'      const net = subtotal - subtotal * disc / 100;',
       '      const discountAmount = subtotal * disc / 100;\n      const net = subtotal - discountAmount;', 'discount_math')
js=swap(js,'      const line = net + net * tax / 100;',
       '      const gstAmount = net * tax / 100;\n      const line = net + gstAmount;', 'gst_math')
js=swap(js,"      tr.querySelector('.e-total').textContent = line.toFixed(2);",
       "      tr.querySelector('.e-disc-amt').textContent = discountAmount.toFixed(2);\n      tr.querySelector('.e-gst-amt').textContent = gstAmount.toFixed(2);\n      tr.querySelector('.e-total').textContent = line.toFixed(2);",'js_preview')
js=swap(js,"  'use strict';","  'use strict';\n  // FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE: calculated Discount and GST amounts.",'js_marker')
# Pull current 2026 invoice print CSS to ensure exact brand typography/spacing.
css_match=re.search(r'<style>(.*?)</style>',data['invoice'],re.S)
if not css_match:raise SystemExit('FIX103_INVOICE_STYLES_MISSING')
invoice_css=css_match.group(1)
print_body=r'''
<body>
@php
$am103Logo=null;
foreach(['assets/logo.png','images/logo.png','logo.png','assets/images/logo.png'] as $am103Path){
 if(is_file(public_path($am103Path))){$am103Logo=asset($am103Path);break;}
}
$am103PartsGross=$items->filter(fn($i)=>in_array(strtoupper((string)$i->item_type),['PART','PARTS'],true))->sum(fn($i)=>(float)$i->line_total);
$am103LabourGross=$items->reject(fn($i)=>in_array(strtoupper((string)$i->item_type),['PART','PARTS'],true))->sum(fn($i)=>(float)$i->line_total);
@endphp
<div class="print-actions"><strong>ASSAM MOTORS · Workshop Estimate · {{ $estimate->estimate_no }}</strong>
<div class="actions"><button type="button" onclick="window.print()">Print / Save PDF</button></div></div>
<main class="sheet">
<header class="brand">
<div class="brand-info">
@if($am103Logo)<img class="logo" src="{{ $am103Logo }}" alt="Assam Motors logo">
@else<img class="logo" src="https://assammotors.com/images/logo.png" alt="Assam Motors logo">@endif
<div class="brand-copy"><h1>ASSAM MOTORS</h1><div class="address">
<div>Bishnu Rabha Path, Hockey Stadium Rd, Sawkuchi, Guwahati-781040, Assam.</div>
<div>MSME: UDYAM-AS-03-0045843 &nbsp;|&nbsp; GSTIN: 18CAHPS7155P1ZQ</div>
<div>Phone: 9864043620 &nbsp;|&nbsp; Email: service@assammotors.com</div>
<div>State: 18-Assam &nbsp;|&nbsp; Website: www.assammotors.com</div>
</div></div></div>
<div class="doc-head">
<h2>Workshop Estimate</h2>
<div style="font-weight:800;font-size:9px;margin:0 0 7px">TAX INCLUDED — NOT A TAX INVOICE</div>
<span class="doc-number">DOCUMENT NO. {{ $estimate->estimate_no }}</span>
<span class="doc-date">DATE {{ optional($estimate->estimate_date)->format('d M Y') }}</span>
</div>
</header>
<div class="document-description">Quotation for proposed workshop work. Each item's <strong>Final Amount includes applicable tax</strong>. This document is an Estimate, not a Tax Invoice.</div>
<section class="info-panels">
<div class="info"><h3>Customer Details</h3><dl>
<dt>Customer</dt><dd>{{ $job->customer_name }}</dd>
<dt>Phone</dt><dd>{{ $job->customer_phone?:'—' }}</dd>
<dt>Vehicle No.</dt><dd>{{ $job->vehicle_reg_no }}</dd>
</dl></div>
<div class="info"><h3>Vehicle Details</h3><dl>
<dt>Registration</dt><dd>{{ $job->vehicle_reg_no }}</dd>
<dt>Make / Model</dt><dd>{{ trim((string)($job->vehicle_make??'').' '.(string)($job->vehicle_model??'')) }}</dd>
<dt>Variant</dt><dd>{{ $job->vehicle_variant??'—' }}</dd>
</dl></div>
<div class="info"><h3>Estimate Details</h3><dl>
<dt>Estimate No.</dt><dd>{{ $estimate->estimate_no }}</dd>
<dt>Date</dt><dd>{{ optional($estimate->estimate_date)->format('d-m-Y') }}</dd>
<dt>Status</dt><dd>{{ strtoupper((string)$estimate->status) }}</dd>
</dl></div>
<div class="info"><h3>Job Card Details</h3><dl>
<dt>Job Card No.</dt><dd>{{ $job->job_no }}</dd>
<dt>Document</dt><dd>Workshop Estimate</dd>
<dt>Tax Basis</dt><dd>Included in Final Amount</dd>
</dl></div>
</section>
<section class="section">
<h3>Parts / Labour / ROT / OSL — Estimate Items</h3>
<table class="am103-estimate-lines">
<thead><tr>
<th class="index-col">SL</th><th class="code-col">Type</th><th>Item Description / Code</th>
<th class="num qty-col">Qty</th><th class="num">Unit</th>
<th class="num rate-col">Rate ₹</th>
<th class="num disc-col">Dis%</th><th class="num">Disc ₹</th>
<th class="num gst-col">GST%</th><th class="num">GST ₹</th>
<th class="num amount-col">Final Amt (Incl. Tax) ₹</th>
</tr></thead><tbody>
@forelse($items as $i)
@php
$am103Sub=(float)$i->qty*(float)$i->rate;
$am103Disc=round($am103Sub*(float)$i->discount_percent/100,2);
$am103Taxable=$am103Sub-$am103Disc;
// GST Amount is reconciled FROM the saved tax-inclusive line total.
// Never add GST again to printed amount.
$am103Gst=round((float)$i->line_total-$am103Taxable,2);
@endphp
<tr><td>{{ $i->line_no }}</td>
<td>{{ strtoupper((string)$i->item_type) }}</td>
<td><span class="name">{{ $i->description }}</span>
@if($i->item_code)<div><small>{{ $i->item_code }}</small></div>@endif
@if($i->line_note)<div class="line-note">{{ $i->line_note }}</div>@endif</td>
<td class="num">{{ number_format((float)$i->qty,3) }}</td>
<td class="num">{{ $i->unit }}</td>
<td class="num">{{ number_format((float)$i->rate,2) }}</td>
<td class="num">{{ number_format((float)$i->discount_percent,2) }}%</td>
<td class="num">{{ number_format($am103Disc,2) }}</td>
<td class="num">{{ number_format((float)$i->tax_percent,2) }}%</td>
<td class="num">{{ number_format($am103Gst,2) }}</td>
<td class="num"><strong>{{ number_format((float)$i->line_total,2) }}</strong></td>
</tr>
@empty<tr><td colspan="11" class="empty">No Estimate Items</td></tr>
@endforelse
</tbody></table>
</section>
<section class="money-area">
<div class="money-message">
<strong>Tax included in each estimate line's Final Amount.</strong>
<p>Rates, discounts, taxes, and totals are based on the saved estimate. This document does not create an Invoice or payment obligation.</p>
@if($estimate->customer_note)<p><strong>Customer Note:</strong> {{ $estimate->customer_note }}</p>@endif
</div>
<div class="amounts">
<div class="trow"><span>Parts (Incl. GST)</span><b>₹{{ number_format($am103PartsGross,2) }}</b></div>
<div class="trow"><span>Labour / ROT / OSL (Incl. GST)</span><b>₹{{ number_format($am103LabourGross,2) }}</b></div>
<div class="trow"><span>Taxable Amount</span><b>₹{{ number_format((float)$estimate->taxable_total,2) }}</b></div>
<div class="trow"><span>CGST</span><b>₹{{ number_format((float)$estimate->cgst_total,2) }}</b></div>
<div class="trow"><span>SGST</span><b>₹{{ number_format((float)$estimate->sgst_total,2) }}</b></div>
<div class="trow"><span>Round Off</span><b>{{ (float)$estimate->round_off>=0?'+':'−' }}₹{{ number_format(abs((float)$estimate->round_off),2) }}</b></div>
<div class="total"><span>Grand Total</span><b>₹{{ number_format((float)$estimate->grand_total,2) }}</b></div>
</div>
</section>
<section class="bank-sign">
<div class="bank"><h3>Bank Details</h3>
<p><strong>Bank:</strong> HDFC BANK, HATIGAON</p>
<p><strong>Account No.:</strong> 50200100195339</p>
<p><strong>IFSC:</strong> HDFC0005671</p>
<p><strong>Account Holder:</strong> ASSAM MOTORS</p>
</div>
<div class="sign"><b>For: ASSAM MOTORS</b><span>Authorized Signatory</span></div>
</section>
<section class="terms"><h3>Estimate Terms and Conditions</h3>
<ol>
<li>This preliminary estimate covers the proposed work and is subject to customer approval.</li>
<li>Additional faults, parts or labour may require a separate supplementary estimate and approval.</li>
<li>Final invoice will show actual approved work and applicable taxes.</li>
</ol>
</section>
<footer class="legal">ASSAM MOTORS — Workshop Estimate — Tax included where applicable — NOT A TAX INVOICE — www.assammotors.com</footer>
</main>
</body></html>
'''
print_css=r'''
/* FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE: same invoice shell with denser estimate lines. */
.section table.am103-estimate-lines{width:100%;table-layout:fixed!important;border-collapse:collapse}
.am103-estimate-lines th{font-size:7px!important;padding:6px 3px!important}
.am103-estimate-lines td{font-size:7.8px!important;padding:6px 3px!important}
.am103-estimate-lines th:nth-child(1){width:4%}
.am103-estimate-lines th:nth-child(2){width:8%}
.am103-estimate-lines th:nth-child(3){width:27%}
.am103-estimate-lines th:nth-child(4){width:7%}
.am103-estimate-lines th:nth-child(5){width:6%}
.am103-estimate-lines th:nth-child(6){width:10%}
.am103-estimate-lines th:nth-child(7){width:6%}
.am103-estimate-lines th:nth-child(8){width:8%}
.am103-estimate-lines th:nth-child(9){width:6%}
.am103-estimate-lines th:nth-child(10){width:8%}
.am103-estimate-lines th:nth-child(11){width:10%}
@media print{.am103-estimate-lines th{font-size:6.7px!important}.am103-estimate-lines td{font-size:7.6px!important}}
'''
pr='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{{ $estimate->estimate_no }} — Workshop Estimate — Assam Motors</title><style>'+invoice_css+'\n'+print_css+'</style></head>'+print_body
for k in ['FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE','line_total','NOT A TAX INVOICE','DOCUMENT NO.','Bank Details']:
 if k not in pr:raise SystemExit('FIX103_BAD_PRINT_'+k)
for k in ['FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE','Discount ₹','GST ₹','Final Amt (Incl. Tax)','id="estimate-lines"','id="estimate-preview-total"']:
 if k not in entry:raise SystemExit('FIX103_BAD_ENTRY_'+k)
for k in ['FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE','.e-disc-amt','.e-gst-amt','line_note']:
 if k not in js:raise SystemExit('FIX103_BAD_JS_'+k)
out={'entry':entry,'js':js,'print':pr}
if mode=='check':
 for k,content in out.items():
  dest=os.environ.get('FIX103_PREVIEW_'+k.upper())
  if dest:Path(dest).write_text(content,encoding='utf8',errors='surrogateescape')
 print('FIX103_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
for k,content in out.items():
 p=root/paths[k];fd,tmp=tempfile.mkstemp(prefix='.fix103_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(content)
  os.chmod(tmp,p.stat().st_mode&0o777)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
 print('FIX103_APPLIED',paths[k])
print('FIX103_VIEW_JS_PRINT_APPLIED_NO_FINANCIAL_WRITES')
