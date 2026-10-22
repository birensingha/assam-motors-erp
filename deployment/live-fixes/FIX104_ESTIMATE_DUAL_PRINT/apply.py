#!/usr/bin/env python3
"""FIX104 two Estimate print types and strict 12-column entry alignment.
Only estimate create/show/print views + a newly named JS asset; no DB writes.
"""
from pathlib import Path
import hashlib,os,sys,tempfile,re
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):raise SystemExit('Usage: apply.py check|apply APP')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();assert (root/'artisan').is_file()
files={'create':root/'resources/views/estimates/create.blade.php',
       'show':root/'resources/views/estimates/show.blade.php',
       'print':root/'resources/views/estimates/print.blade.php',
       'js':root/'public/js/estimate-entry.js'}
newasset=root/'public/js/estimate-entry-fix104.js'
data={k:p.read_text(encoding='utf8',errors='surrogateescape') for k,p in files.items()}
mark='FIX104_ESTIMATE_DUAL_PRINT_AND_12_COLUMNS'
for k,p in files.items():
 print('FIX104_SHA',k,hashlib.sha256(p.read_bytes()).hexdigest())
if all(mark in data[k] for k in ['create','show','print']) and newasset.exists():
 print('FIX104_ALREADY_APPLIED');sys.exit(0)
if any(mark in data[k] for k in ['create','show','print']) or newasset.exists():
 raise SystemExit('FIX104_PARTIAL_PATCH_ABORT')
def replace(s,a,b,label):
 n=s.count(a);print('FIX104_ANCHOR',label,n)
 if n!=1:raise SystemExit('FIX104_ANCHOR_MISMATCH_'+label)
 return s.replace(a,b,1)
js=data['js']
chunk=js.split('function rowHtml(i, r={})',1)
if len(chunk)!=2:raise SystemExit('FIX104_ROW_SOURCE_MISSING')
row=chunk[1].split('function add(r=',1)[0]
count=len(re.findall(r'<td(?:\s|>)',row))
print('FIX104_VERIFIED_ROW_TD_COUNT',count)
if count!=12:raise SystemExit('FIX104_ROW_NOT_12_COLUMNS')
for x in ['class="e-disc-amt"','class="e-gst-amt"','class="e-total"',
          'line_note','type="hidden"','discountAmount','gstAmount']:
 if x not in js:raise SystemExit('FIX104_MISSING_JS_FUNCTION_'+x)
js=replace(js,'// FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE: calculated Discount and GST amounts.',
           '// FIX104_ESTIMATE_DUAL_PRINT_AND_12_COLUMNS: unique versioned file to bypass stale ten-column cache.','js_marker')
c=data['create']
c=replace(c,"asset('js/estimate-entry.js') }}?v=fix103",
            "asset('js/estimate-entry-fix104.js') }}?v=104",'asset_new_path')
css=r'''
{{-- FIX104_ESTIMATE_DUAL_PRINT_AND_12_COLUMNS: exactly 12 proportional columns
     aligned with rowHtml() output, no hidden/overflowed final amount. --}}
<style id="FIX104_12COL_LAYOUT">
#estimate-form .estimate-lines-area{width:100%!important;max-width:100%!important;min-width:0!important;
 overflow:visible!important;box-sizing:border-box!important}
#estimate-form .estimate-line-table{table-layout:fixed!important;width:100%!important;max-width:100%!important;min-width:0!important}
#estimate-form .estimate-line-table thead th:nth-child(1){width:6.5%!important}
#estimate-form .estimate-line-table thead th:nth-child(2){width:16.5%!important}
#estimate-form .estimate-line-table thead th:nth-child(3){width:18%!important}
#estimate-form .estimate-line-table thead th:nth-child(4){width:7%!important}
#estimate-form .estimate-line-table thead th:nth-child(5){width:5.5%!important}
#estimate-form .estimate-line-table thead th:nth-child(6){width:8.5%!important}
#estimate-form .estimate-line-table thead th:nth-child(7){width:6%!important}
#estimate-form .estimate-line-table thead th:nth-child(8){width:7.5%!important}
#estimate-form .estimate-line-table thead th:nth-child(9){width:6%!important}
#estimate-form .estimate-line-table thead th:nth-child(10){width:7.5%!important}
#estimate-form .estimate-line-table thead th:nth-child(11){width:8.5%!important}
#estimate-form .estimate-line-table thead th:nth-child(12){width:3%!important}
#estimate-form .estimate-line-table tbody td{min-width:0!important;max-width:100%!important;width:auto!important;
 box-sizing:border-box!important;vertical-align:middle!important;padding:2px!important}
#estimate-form .estimate-line-table input:not([type=hidden]),
#estimate-form .estimate-line-table select{min-width:0!important;max-width:100%!important;width:100%!important;box-sizing:border-box!important}
#estimate-form .estimate-line-table td:last-child{text-align:center!important;padding:1px!important}
#estimate-form .estimate-line-table .e-remove{display:inline-flex!important;align-items:center!important;
 justify-content:center!important;width:19px!important;min-width:19px!important;height:20px!important;
 padding:0!important;margin:0!important}
#estimate-form .estimate-line-table .e-disc-amt,
#estimate-form .estimate-line-table .e-gst-amt,
#estimate-form .estimate-line-table .e-total{
 display:block!important;text-align:right!important;max-width:100%!important;width:100%!important;
 font-size:9px!important;white-space:nowrap!important;font-variant-numeric:tabular-nums!important;overflow:visible!important
}
#estimate-form .purchase-foot,#estimate-form .preview-total{
 max-width:100%!important;min-width:0!important;box-sizing:border-box!important
}
#estimate-form .preview-total{overflow-wrap:anywhere!important;white-space:normal!important}
</style>
'''
c=replace(c,'<div class="line-scroll estimate-lines-area"><table',
           css+'<div class="line-scroll estimate-lines-area"><table','column_style')
show=data['show']
show=replace(show,
 '''<a class="btn" href="{{ route('erp.estimates.print',$estimate->id) }}" target="_blank">Print</a>''',
 '''{{-- FIX104_ESTIMATE_DUAL_PRINT_AND_12_COLUMNS --}}
    <a class="btn" href="{{ route('erp.estimates.print',$estimate->id) }}?gst=1" target="_blank" rel="noopener">Print With GST</a>
    <a class="btn" href="{{ route('erp.estimates.print',$estimate->id) }}?gst=0" target="_blank" rel="noopener">Print Without GST</a>''','two_show_buttons')
p=data['print']
p=replace(p,'<body>\n@php',
 '''<body>
@php
// FIX104_ESTIMATE_DUAL_PRINT_AND_12_COLUMNS: print-only GST visibility.
$am104ShowGst=(string)request()->query('gst','1')!=='0';
@endphp
@php''','mode_flag')
p=replace(p,
 '<div style="font-weight:800;font-size:9px;margin:0 0 7px">TAX INCLUDED — NOT A TAX INVOICE</div>\n',
 '','remove_badge')
p=replace(p,
 '''<div class="document-description">Quotation for proposed workshop work. Each item's <strong>Final Amount includes applicable tax</strong>. This document is an Estimate, not a Tax Invoice.</div>''',
 '''<div class="document-description">Estimate for proposed workshop work. Both print versions show the same saved final amount for every item.</div>''','document_description')
p=replace(p,
 '''<div class="print-actions"><strong>ASSAM MOTORS · Workshop Estimate · {{ $estimate->estimate_no }}</strong>
<div class="actions"><button type="button" onclick="window.print()">Print / Save PDF</button></div></div>''',
 '''<div class="print-actions"><strong>ASSAM MOTORS · Workshop Estimate · {{ $estimate->estimate_no }}</strong>
<div class="actions">
<a class="{{ $am104ShowGst?'selected':'' }}" href="{{ route('erp.estimates.print',$estimate->id) }}?gst=1">With GST</a>
<a class="{{ !$am104ShowGst?'selected':'' }}" href="{{ route('erp.estimates.print',$estimate->id) }}?gst=0">Without GST</a>
<button type="button" onclick="window.print()">Print / Save PDF</button>
</div></div>''','print_mode_selector')
p=replace(p,'<table class="am103-estimate-lines">',
  '<table class="am103-estimate-lines {{ $am104ShowGst ? \'with-gst\' : \'without-gst\' }}">','format_class')
p=replace(p,
 '<th class="num gst-col">GST%</th><th class="num">GST ₹</th>',
 '@if($am104ShowGst)<th class="num gst-col">GST%</th><th class="num">GST ₹</th>@endif','print_gst_headers')
p=replace(p,'<th class="num amount-col">Final Amt (Incl. Tax) ₹</th>',
 '<th class="num amount-col">Final Amt ₹</th>','print_final_heading')
p=replace(p,
 '''<td class="num">{{ number_format((float)$i->tax_percent,2) }}%</td>
<td class="num">{{ number_format($am103Gst,2) }}</td>''',
 '''@if($am104ShowGst)
<td class="num">{{ number_format((float)$i->tax_percent,2) }}%</td>
<td class="num">{{ number_format($am103Gst,2) }}</td>
@endif''','gst_row_cells')
p=replace(p,'colspan="11" class="empty"',
  'colspan="{{ $am104ShowGst?11:9 }}" class="empty"','empty_row_colspan')
p=replace(p,'<span>Parts (Incl. GST)</span>','<span>Parts Total</span>','parts_total_label')
p=replace(p,'<span>Labour / ROT / OSL (Incl. GST)</span>','<span>Labour / ROT / OSL Total</span>','labour_total_label')
p=replace(p,
 '''<div class="trow"><span>Taxable Amount</span><b>₹{{ number_format((float)$estimate->taxable_total,2) }}</b></div>
<div class="trow"><span>CGST</span><b>₹{{ number_format((float)$estimate->cgst_total,2) }}</b></div>
<div class="trow"><span>SGST</span><b>₹{{ number_format((float)$estimate->sgst_total,2) }}</b></div>''',
 '''@if($am104ShowGst)
<div class="trow"><span>Taxable Amount</span><b>₹{{ number_format((float)$estimate->taxable_total,2) }}</b></div>
<div class="trow"><span>CGST</span><b>₹{{ number_format((float)$estimate->cgst_total,2) }}</b></div>
<div class="trow"><span>SGST</span><b>₹{{ number_format((float)$estimate->sgst_total,2) }}</b></div>
@endif''','print_tax_totals')
p=replace(p,
 '''<strong>Tax included in each estimate line's Final Amount.</strong>
<p>Rates, discounts, taxes, and totals are based on the saved estimate. This document does not create an Invoice or payment obligation.</p>''',
 '''<strong>Estimate for proposed workshop work.</strong>
<p>Saved line amounts and final total are identical in both print formats.</p>''','customer_note_box')
p=replace(p,
 '<footer class="legal">ASSAM MOTORS — Workshop Estimate — Tax included where applicable — NOT A TAX INVOICE — www.assammotors.com</footer>',
 '<footer class="legal">ASSAM MOTORS — Workshop Estimate — www.assammotors.com</footer>',
 'footer_text')
p=replace(p,'</style></head>',
 '''/* FIX104 two print column counts: GST 11, simplified 9. */
.am103-estimate-lines.without-gst th:nth-child(1){width:4%!important}
.am103-estimate-lines.without-gst th:nth-child(2){width:9%!important}
.am103-estimate-lines.without-gst th:nth-child(3){width:31%!important}
.am103-estimate-lines.without-gst th:nth-child(4){width:7%!important}
.am103-estimate-lines.without-gst th:nth-child(5){width:7%!important}
.am103-estimate-lines.without-gst th:nth-child(6){width:13%!important}
.am103-estimate-lines.without-gst th:nth-child(7){width:7%!important}
.am103-estimate-lines.without-gst th:nth-child(8){width:10%!important}
.am103-estimate-lines.without-gst th:nth-child(9){width:12%!important}
</style></head>''','print_nine_columns')
for k in ['?gst=0','?gst=1','@if($am104ShowGst)',
          'number_format((float)$i->line_total,2)',
          'number_format((float)$estimate->grand_total,2)',
          'FIX104_ESTIMATE_DUAL_PRINT_AND_12_COLUMNS']:
 if k not in p:raise SystemExit('FIX104_PRINT_MISSING_'+k)
if 'NOT A TAX INVOICE' in p:raise SystemExit('FIX104_DISCLAIMER_STILL_PRESENT')
result={'create':c,'show':show,'print':p,'newjs':js}
if mode=='check':
 for k,v in result.items():
  temp=os.environ.get('FIX104_PREVIEW_'+k.upper())
  if temp:Path(temp).write_text(v,encoding='utf8',errors='surrogateescape')
 print('FIX104_PREFLIGHT_NO_LIVE_WRITES');sys.exit(0)
for k in ['create','show','print']:
 target=files[k];fd,tmp=tempfile.mkstemp(dir=target.parent,prefix='.fix104_')
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as out:out.write(result[k])
  os.chmod(tmp,target.stat().st_mode&0o777)
  os.replace(tmp,target)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
with newasset.open('x',encoding='utf8',errors='surrogateescape') as out:
 out.write(js)
os.chmod(newasset,files['js'].stat().st_mode&0o777)
print('FIX104_DUAL_PRINT_AND_12COL_ASSET_APPLIED_NO_DB_WRITES')
