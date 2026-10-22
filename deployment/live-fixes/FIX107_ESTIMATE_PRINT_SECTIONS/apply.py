#!/usr/bin/env python3
"""FIX107: display 'As per Applicable' tax basis and print Parts above Labour/ROT/OSL.

Change only resources/views/estimates/print.blade.php (presentation).
Group the SAVED estimate $items in two view-only sections. Do not change any
pricing, qty, GST mode, totals, estimates, DB records, controller or invoice.
"""
from pathlib import Path
import os,sys,hashlib,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file(),'FIX107_NOT_LARAVEL'
p=root/'resources/views/estimates/print.blade.php'
raw=p.read_bytes()
s=raw.decode('utf8','surrogateescape')
print('FIX107_SOURCE_SHA256',hashlib.sha256(raw).hexdigest())
mark='FIX107_SPLIT_PARTS_LABOUR_ESTIMATE_PRINT'
if mark in s:
 if s.count(mark)==1:
  print('FIX107_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX107_PARTIAL_OR_DUPLICATE_ABORT')
for tok in ['FIX103_ESTIMATE_TABLE_GST_INVOICE_STYLE',
            'FIX104_ESTIMATE_DUAL_PRINT_AND_12_COLUMNS',
            'FIX105_EDITOR_LOAD_TAB_AND_WO_GST_RATE',
            'am103-estimate-lines',
            '@if($am104ShowGst)',
            'number_format((float)$i->line_total,2)',
            'number_format((float)$estimate->grand_total,2)',
            '$am103PartsGross','$am103LabourGross',
            'Workshop Estimate']:
 if tok not in s:raise SystemExit('FIX107_REQUIRED_BASELINE_MISSING '+tok)
def change(old,new,label):
 global s
 count=s.count(old)
 print('FIX107_ANCHOR',label,count)
 if count!=1:raise SystemExit('FIX107_CHANGED_TEMPLATE_ABORT_'+label)
 s=s.replace(old,new,1)
change('<dt>Tax Basis</dt><dd>Included in Final Amount</dd>',
       '<dt>Tax Basis</dt><dd>As per Applicable</dd>',
       'tax_basis_text')
change('''<section class="section">
<h3>Parts / Labour / ROT / OSL — Estimate Items</h3>''',
'''@php
// FIX107_SPLIT_PARTS_LABOUR_ESTIMATE_PRINT: grouping only, no financial changes.
// Keep original item order inside each group. Everything not PART is classified
// as labour, ROT or OSL exactly like the existing Parts/Labour gross totals.
$am107PartLines=$items->filter(fn($row)=>in_array(strtoupper(trim((string)$row->item_type)),['PART','PARTS'],true));
$am107LabourLines=$items->reject(fn($row)=>in_array(strtoupper(trim((string)$row->item_type)),['PART','PARTS'],true));
$am107Groups=[
 ['heading'=>'PARTS','lines'=>$am107PartLines,'empty'=>'No Parts Items'],
 ['heading'=>'LABOUR / ROT / OSL','lines'=>$am107LabourLines,'empty'=>'No Labour / ROT / OSL Items'],
];
@endphp
@foreach($am107Groups as $am107Group)
<section class="section am107-estimate-section">
<h3>{{ $am107Group['heading'] }}</h3>''',
       'two_sections_start')
change('@forelse($items as $i)',
       "@forelse($am107Group['lines'] as $i)",
       'group_specific_items')
change('No Estimate Items</td>',
       "{{ $am107Group['empty'] }}</td>",
       'group_empty_message')
change('''</tbody></table>
</section>
<section class="money-area">''',
'''</tbody></table>
</section>
@endforeach
<section class="money-area">''',
       'group_loop_close')
# Styling is the same professional invoice-style header/table typography.
# Draw a thin separator between groups and maintain existing repeatable thead.
change('</style></head>',
'''/* FIX107: Parts and Labour/ROT/OSL separated, same Invoice print columns. */
.am107-estimate-section{margin-top:14px}
.am107-estimate-section h3{font-size:11.5px;letter-spacing:.03em;
 color:#12467c;padding:6px 1px;border-bottom:2px solid #b7cee4}
.am107-estimate-section table{width:100%}
@media print{
 .am107-estimate-section{break-inside:auto;page-break-inside:auto}
 .am107-estimate-section thead{display:table-header-group}
 .am107-estimate-section tr{break-inside:avoid;page-break-inside:avoid}
}
</style></head>''',
       'print_css')
assert s.count(mark)==1
assert s.count('@foreach($am107Groups as $am107Group)')==1
assert s.count('@forelse($am107Group[\\'lines\\'] as $i)')==1
assert s.count('@endforeach')>=1
assert s.count('am103-estimate-lines')>=2
assert 'number_format((float)$i->line_total,2)' in s
assert 'number_format((float)$estimate->grand_total,2)' in s
if mode=='check':
 candidate=os.environ.get('FIX107_PREVIEW_PATH')
 if candidate:
  Path(candidate).write_text(s,encoding='utf8',errors='surrogateescape')
  print('FIX107_TEMP_PREVIEW_ONLY')
 print('FIX107_PREFLIGHT_PASS_NO_LIVE_WRITES');sys.exit(0)
fd,temp=tempfile.mkstemp(dir=p.parent,prefix='.fix107_')
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(temp,p.stat().st_mode & 0o777)
 os.replace(temp,p)
finally:
 if os.path.exists(temp):os.unlink(temp)
print('FIX107_SPLIT_PRINT_VIEW_APPLIED_NO_FINANCIAL_WRITES')
