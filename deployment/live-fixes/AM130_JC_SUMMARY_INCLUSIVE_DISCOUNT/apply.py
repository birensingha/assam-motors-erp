#!/usr/bin/env python3
"""AM130 guarded summary print patch. No database or billing writes."""
from pathlib import Path
import sys,re,shutil,datetime
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'): raise SystemExit('Usage: apply.py check|apply STAGING_ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).expanduser().resolve()
path=root/'resources/views/job-cards/summary-print.blade.php'
if not (root/'artisan').is_file() or not path.is_file(): raise SystemExit('STOP: Laravel Summary file not found')
s=path.read_text()
marker='AM130_SUMMARY_CUSTOMER_INCLUSIVE'
if marker in s: print('ALREADY_APPLIED');sys.exit(0)
# Limit to AM124 version of summary. Avoid changing unknown live template.
anchors=["$am124ShowGst","$am124PartFinal","$am124LabourLineFinal","$restParts","$restLabour","</head>","</body>"]
if not all(a in s for a in anchors):raise SystemExit('STOP: summary template differs; no change')
# Force existing AM124 inclusive-only branch in all rows; hide GST breakup.
# Leave fiscal calculations and taxable/GST storage untouched.
n=s.count('@if($am124ShowGst)')
if n<2:raise SystemExit('STOP: GST conditional anchors unexpected')
# Insert mode assignment after the last preamble marker rather than overriding global tax logic.
# Capture top-level property: use existing conditional print branch with a per-view display flag.
# Existing boolean may be reassigned only in view, after its original definition.
matches=list(re.finditer(r'\$am124ShowGst\s*=\s*[^;]+;',s))
if len(matches)!=1:raise SystemExit('STOP: Cannot locate unique GST display assignment')
m=matches[0];s=s[:m.end()]+'\n/* AM130_SUMMARY_CUSTOMER_INCLUSIVE: summary intentionally hides GST breakdown */\n$am124ShowGst=false;'+s[m.end():]
# Existing condensed totals can be rendered inclusive where AM124 already supports it.
# Add show discount option for non-GST view, using row-local existing line fields.
# Rather than risk malformed columns, fail closed if exact repeated patterns absent.
rate_p=r'<td class="num">{{ $printPartMoney($am124PartQty>0?$am124PartFinal/$am124PartQty:$am124PartFinal) }}</td>'
rate_l=r'<td class="num">{{ $printPartMoney($am124StdHrs>0?$am124LabourLineFinal/$am124StdHrs:$am124LabourLineFinal) }}</td>'
if s.count(rate_p)!=1 or s.count(rate_l)!=1:raise SystemExit('STOP: inclusive rate cells differ')
# discount columns controlled entirely on client to preserve print values.
# Display columns added only within the non-GST branches.
head_pat=r'(<th class="num" style="width:12%">Final ₹</th>)'
if len(re.findall(head_pat,s))!=2:raise SystemExit('STOP: final headings differ')
# Each non-GST amount is inclusive. Discount % is the saved discount field.
# NOTE: discount amount reflects stored nominal discount on pre-tax line rates.
s=s.replace(rate_p,rate_p+'''\n<td class="num am130-discount">{{ number_format((float)($part->discount_percent??0),1) }}</td>
<td class="num am130-discount">{{ $printPartMoney($part->discount_amount??0) }}</td>''')
s=s.replace(rate_l,rate_l+'''\n<td class="num am130-discount">{{ number_format((float)($line->discount_percent??0),1) }}</td>
<td class="num am130-discount">{{ $printPartMoney($line->discount_amount??0) }}</td>''')
# Headings appear in two summary tables.
s=re.sub(head_pat,'''<th class="num am130-discount">Dis%</th><th class="num am130-discount">Dis Amt</th>\n\\1''',s)
# Ensure empty and condensed rows span added fields only when enabled.
s=s.replace('$am124ShowGst?10:6', '$am124ShowGst?10:8')
s=s.replace('$am124ShowGst?9:5', '$am124ShowGst?9:7')
# Hidden by default; toggled via checkbox in print top area.
css='''<style id="am130-discount-style">
.am130-discount{display:none}
body.am130-show-discount .am130-discount{display:table-cell}
@media print{.am130-control{display:none!important}}
</style>
'''
s=s.replace('</head>',css+'</head>',1)
toolbar='''<div class="am130-control" style="margin:8px 0;text-align:right;font:13px Arial">
<label><input type="checkbox" id="am130-discount-toggle" onchange="document.body.classList.toggle('am130-show-discount',this.checked)"> Show Discount</label>
</div>
'''
s=s.replace('<body>', '<body>\n'+toolbar,1) if '<body>' in s else s
if toolbar not in s:raise SystemExit('STOP: body tag differs')
if mode=='check':print('AM130_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
backup=path.with_name(path.name+'.AM130_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S')+'.bak')
shutil.copy2(path,backup)
path.write_text(s)
print('AM130_VIEW_UPDATED',path,'BACKUP',backup)
