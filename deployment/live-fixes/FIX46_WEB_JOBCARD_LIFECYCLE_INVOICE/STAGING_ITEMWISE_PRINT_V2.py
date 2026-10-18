#!/usr/bin/env python3
"""Apply the 2026-10-18 invoice print-only row and GST layout to a VERIFIED staging Blade file.
No migrations, invoices, ledger or payment records are modified. Backup + atomic write.
"""
import hashlib, os, shutil, stat, sys, time
from pathlib import Path

root = Path(sys.argv[1]).resolve()
p = root / 'resources/views/invoices/print.blade.php'
if not (root / 'artisan').is_file() or not p.is_file():
    raise SystemExit('SAFE_STOP: unexpected Laravel app location')
old = p.read_text(encoding='utf-8')
if 'AM_INVOICE_ROW_V2' in old:
    print('ROW_PATCH_ALREADY_APPLIED')
    raise SystemExit(0)
digest = hashlib.sha256(old.encode()).hexdigest()
print('ORIGINAL_HASH_PREFIX', digest[:16])
if digest[:16] != '90560153e69e498f':
    raise SystemExit('SAFE_STOP: invoice print template differs from last verified Hostinger view')
s = old

def one(oldtext, newtext):
    global s
    count = s.count(oldtext)
    if count != 1:
        raise SystemExit('SAFE_STOP: expected exactly one old template anchor; found ' + str(count) + ': ' + oldtext[:70])
    s = s.replace(oldtext, newtext)

one("$summaryMode = request()->query('format')==='summary';",
    "$summaryMode = request()->query('format')==='summary';\n  $showDiscount = request()->query('show_discount') === '1'; // AM_INVOICE_ROW_V2")
one("@endphp\n<div class=\"print-actions\"",
"""  // Audit against persisted invoice totals. Never post or modify tax/ledger amounts.
  $amTaxOnLine = static fn($line) => round((float)($line->total??0) * (float)($line->gst_percent??0) / 100, 2);
  $amPartsDerivedTax=$parts->sum($amTaxOnLine);
  $amLabourDerivedTax=$labour->sum($amTaxOnLine);
  $amTaxCalculated=$amPartsDerivedTax+$amLabourDerivedTax;
  $amTaxReconciled=abs($amTaxCalculated-$tax)<=0.05
      && abs($parts->sum(fn($p)=>(float)($p->total??0))-(float)($inv->parts_total??0))<=0.05
      && abs($labour->sum(fn($l)=>(float)($l->total??0))-(float)($inv->labour_total??0))<=0.05
      && abs((float)($inv->parts_total??0)+(float)($inv->labour_total??0)+$tax+$roundOff-$grand)<=0.06;
  $amFinalLine=static fn($line) => round((float)($line->total??0) + ((!$summaryMode || $amTaxReconciled) ? $amTaxOnLine($line) : 0),2);
  $amPartsPrintedTotal=$summaryMode && $amTaxReconciled ? (float)($inv->parts_total??0)+$amPartsDerivedTax : (float)($inv->parts_total??0);
  $amLabourPrintedTotal=$summaryMode && $amTaxReconciled ? (float)($inv->labour_total??0)+$amLabourDerivedTax : (float)($inv->labour_total??0);
@endphp
<div class="print-actions" """ .rstrip() )
# Undo intentional harmless spacing alteration in class opener
s = s.replace('<div class="print-actions" >','<div class="print-actions"')
one("{{ route('erp.invoices.print',$inv->id) }}?format=tax", "{{ route('erp.invoices.print',$inv->id) }}?format=tax&amp;show_discount={{ $showDiscount?1:0 }}")
one("{{ route('erp.invoices.print',$inv->id) }}?format=summary", "{{ route('erp.invoices.print',$inv->id) }}?format=summary&amp;show_discount={{ $showDiscount?1:0 }}")
one('    <button type="button" onclick="window.print()">Print / Save PDF</button>',
"""    <a href="{{ request()->fullUrlWithQuery(['show_discount'=>$showDiscount?0:1]) }}">{{ $showDiscount ? 'Hide Item Discounts' : 'Show Item Discounts' }}</a>
    @if($summaryMode || $amTaxReconciled)
    <button type="button" onclick="window.print()">Print / Save PDF</button>
    @else
    <span style="color:#991b1b;font-weight:700">GST printing blocked: saved tax mismatch</span>
    @endif""")
one('<main class="sheet">',
"""@if(!$summaryMode && !$amTaxReconciled)
<style>@media print {.sheet{display:none!important}.tax-print-blocked{display:block!important}}</style>
<div class="tax-print-blocked" style="border:2px solid #991b1b;background:white;color:#991b1b;padding:14px;margin:12px auto;max-width:1010px">
  GST Invoice printing blocked: item-wise calculated tax does not match the saved invoice tax/total. Review the GST-inclusive or exclusive basis and correct the recorded invoice before issuing.
</div>
@endif
<main class="sheet">""")
one('</style>\n</head>', """/* Narrow financial columns fit A4 while the existing blue letterhead remains unchanged. */
.section table{table-layout:auto}
.section th{font-size:7.3px;padding:6px 3px}
.section td{font-size:8.2px;padding:6px 3px}
.section .num{white-space:nowrap}
@media print{.section th{font-size:7px}.section td{font-size:7.8px}}
</style>
</head>""")

parts = """<table><thead><tr>
<th>SL</th><th>Part</th><th>Code</th><th>HSN</th>
<th class="num">Qty</th><th class="num">Rate</th>
@if($showDiscount)<th class="num">Dis%</th><th class="num">Discount ₹</th>@endif
@unless($summaryMode)<th class="num">Taxable ₹</th><th class="num">GST%</th><th class="num">GST ₹</th>@endunless
<th class="num">Final Amt ₹</th>
</tr></thead><tbody>
@forelse($parts as $p)
@php
$pBase=(float)($p->total??0);
$pTax=$amTaxOnLine($p);
$pGross=$amFinalLine($p);
$pQty=(float)($p->qty??0);
$pRate=$summaryMode && !$showDiscount && $pQty>0 ? $pGross/$pQty : (float)($p->rate??0);
@endphp
<tr>
<td>{{ $loop->iteration }}</td>
<td><span class="name">{{ $p->part_name??'Part' }}</span>@if(!empty($p->part_note))<div class="line-note">Note: {{ $p->part_note }}</div>@endif</td>
<td>{{ $p->part_number ?: ($p->part_code??'—') }}</td>
<td>{{ $p->hsn_code??$p->hsn??'—' }}</td>
<td class="num">{{ number_format($pQty,2) }}</td>
<td class="num">{{ number_format($pRate,2) }}</td>
@if($showDiscount)
<td class="num">{{ number_format((float)($p->discount_percent??0),2) }}%</td>
<td class="num">{{ number_format((float)($p->discount_amount??0),2) }}</td>
@endif
@unless($summaryMode)
<td class="num">{{ number_format($pBase,2) }}</td>
<td class="num">{{ number_format((float)($p->gst_percent??0),2) }}%</td>
<td class="num">{{ number_format($pTax,2) }}</td>
@endunless
<td class="num"><b>{{ number_format($pGross,2) }}</b></td>
</tr>
@empty<tr><td class="empty" colspan="{{ 7+($showDiscount?2:0)+($summaryMode?0:3) }}">No parts / materials lines.</td></tr>
@endforelse
</tbody></table>"""

labour = """<table><thead><tr>
<th>SL</th><th>Labour</th><th>ROT</th><th>SAC</th>
<th class="num">Std Hrs</th><th class="num">Rate</th>
@if($showDiscount)<th class="num">Dis%</th><th class="num">Discount ₹</th>@endif
@unless($summaryMode)<th class="num">Taxable ₹</th><th class="num">GST%</th><th class="num">GST ₹</th>@endunless
<th class="num">Final Amt ₹</th>
</tr></thead><tbody>
@forelse($rotLines as $l)
@php
$lBase=(float)($l->total??0);
$lTax=$amTaxOnLine($l);
$lGross=$amFinalLine($l);
@endphp
<tr><td>{{ $loop->iteration }}</td>
<td><span class="name">{{ $l->description??'Labour Charges' }}</span>@if(!empty($l->notes)||!empty($l->labour_note))<div class="line-note">Note: {{ $l->notes??$l->labour_note }}</div>@endif</td>
<td>{{ $l->rot_code??'—' }}</td><td>{{ $l->sac_code??$l->sac??'—' }}</td>
<td class="num">{{ number_format((float)($l->standard_hours??0),2) }}</td>
<td class="num">{{ number_format($summaryMode && !$showDiscount ? $lGross : (float)($l->labour_rate??0),2) }}</td>
@if($showDiscount)
<td class="num">{{ number_format((float)($l->discount_percent??0),2) }}%</td>
<td class="num">{{ number_format((float)($l->discount_amount??0),2) }}</td>
@endif
@unless($summaryMode)
<td class="num">{{ number_format($lBase,2) }}</td>
<td class="num">{{ number_format((float)($l->gst_percent??0),2) }}%</td>
<td class="num">{{ number_format($lTax,2) }}</td>
@endunless
<td class="num"><b>{{ number_format($lGross,2) }}</b></td></tr>
@empty<tr><td class="empty" colspan="{{ 7+($showDiscount?2:0)+($summaryMode?0:3) }}">No labour / ROT lines.</td></tr>
@endforelse
</tbody></table>"""

osl = """<table><thead><tr>
<th>SL</th><th>Outside Service</th><th>ROT</th><th>SAC</th>
<th class="num">Std Hrs</th><th class="num">Rate</th>
@if($showDiscount)<th class="num">Dis%</th><th class="num">Discount ₹</th>@endif
@unless($summaryMode)<th class="num">Taxable ₹</th><th class="num">GST%</th><th class="num">GST ₹</th>@endunless
<th class="num">Final Amt ₹</th></tr></thead><tbody>
@forelse($oslLines as $l)
@php
$lBase=(float)($l->total??0);
$lTax=$amTaxOnLine($l);
$lGross=$amFinalLine($l);
@endphp
<tr><td>{{ $loop->iteration }}</td>
<td><span class="name">{{ $l->description??'Outside Service' }}</span>@if(!empty($l->notes)||!empty($l->labour_note))<div class="line-note">Note: {{ $l->notes??$l->labour_note }}</div>@endif</td>
<td>{{ $l->rot_code??'—' }}</td><td>{{ $l->sac_code??$l->sac??'—' }}</td>
<td class="num">{{ number_format((float)($l->standard_hours??1),2) }}</td>
<td class="num">{{ number_format($summaryMode && !$showDiscount ? $lGross : (float)($l->labour_rate??0),2) }}</td>
@if($showDiscount)
<td class="num">{{ number_format((float)($l->discount_percent??0),2) }}%</td>
<td class="num">{{ number_format((float)($l->discount_amount??0),2) }}</td>
@endif
@unless($summaryMode)
<td class="num">{{ number_format($lBase,2) }}</td>
<td class="num">{{ number_format((float)($l->gst_percent??0),2) }}%</td>
<td class="num">{{ number_format($lTax,2) }}</td>
@endunless
<td class="num"><b>{{ number_format($lGross,2) }}</b></td></tr>
@empty<tr><td class="empty" colspan="{{ 7+($showDiscount?2:0)+($summaryMode?0:3) }}">No OSL / outside service lines.</td></tr>
@endforelse
</tbody></table>"""

for heading, template in [('Parts / Materials', parts), ('Labour / ROT Charges', labour), ('OSL Jobs / Outside Services', osl)]:
    marker = '<h3>'+heading+'</h3>'
    if s.count(marker)!=1:
        raise SystemExit('SAFE_STOP: section marker mismatch: '+heading)
    at=s.index(marker)+len(marker)
    first=s.index('<table>',at)
    last=s.index('</table>',first)+len('</table>')
    if last-first>4500:
        raise SystemExit('SAFE_STOP: unexpected financial table length '+heading)
    s=s[:first]+template+s[last:]
# Footer in cost summary must not have visible ex-tax Parts/Labour subtotals.
one("number_format((float)($inv->parts_total??0),2)", "number_format($amPartsPrintedTotal,2)")
one("number_format((float)($inv->labour_total??0),2)", "number_format($amLabourPrintedTotal,2)")
# Guard summary-mode printed totals by original grand reconciliation.
if s.count('AM_INVOICE_ROW_V2') != 1:
    raise SystemExit('SAFE_STOP: missing version marker')
if any(s.count(x)!=3 for x in ('Taxable ₹','GST ₹','Final Amt ₹')):
    raise SystemExit('SAFE_STOP: GST row headings do not match Parts, Labour and OSL')
backup=p.with_name(p.name+'.before-itemwise-gst-'+time.strftime('%Y%m%d%H%M%S')+'.bak')
shutil.copy2(p,backup)
tmp=p.with_name(p.name+'.itemwise-gst.tmp')
tmp.write_text(s,encoding='utf-8')
os.chmod(tmp,stat.S_IMODE(p.stat().st_mode))
os.replace(tmp,p)
print('ITEMWISE_GST_PATCH_APPLIED',p.name,'BACKUP',backup.name,'NEW_HASH_PREFIX',hashlib.sha256(s.encode()).hexdigest()[:16])
