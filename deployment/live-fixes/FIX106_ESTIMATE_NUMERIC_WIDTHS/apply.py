#!/usr/bin/env python3
"""FIX106: mildly compact Estimate input cells from both sides, view CSS only.

QTY/UNIT/RATE/DIS%/DISCOUNT amount/GST%/GST amount become slightly narrower;
Reference and Description get room; Final Amount stays fully visible.
No JavaScript, financial calculations, print template, or DB modifications.
"""
from pathlib import Path
import hashlib,os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file(),'FIX106_NOT_LARAVEL'
p=root/'resources/views/estimates/create.blade.php'
raw=p.read_bytes()
s=raw.decode('utf8','surrogateescape')
print('FIX106_SOURCE_SHA256',hashlib.sha256(raw).hexdigest())
marker='FIX106_COMPACT_ESTIMATE_INPUT_COLUMNS'
if marker in s:
 if s.count(marker)==1:
  print('FIX106_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX106_PARTIAL_PATCH_STOP')
for token in ['id="FIX104_12COL_LAYOUT"','id="FIX103_ESTIMATE_TABLE_COMPACT_CSS"',
              'FIX105_EDITOR_LOAD_TAB_AND_WO_GST_RATE',
              '<div class="line-scroll estimate-lines-area"><table',
              'id="estimate-lines"','id="estimate-preview-total"',
              'Discount ₹','GST ₹','Final Amt (Incl. Tax)',
              'id="estimate-form"']:
 if token not in s:raise SystemExit('FIX106_BASELINE_MARKER_MISSING '+token)
old='<div class="line-scroll estimate-lines-area"><table'
if s.count(old)!=1:raise SystemExit('FIX106_TABLE_ANCHOR_UNEXPECTED')
# 12 column percentages sum to exactly 100%. Existing widths were
# [6.5,16.5,18,7,5.5,8.5,6,7.5,6,7.5,8.5,3].
# This is deliberately a mild change, not a dramatic redesign:
widths=[6,18,20,6.5,5,8,5.5,6.5,5.5,6.5,9.5,3]
assert sum(widths)==100
css_lines=[
'{{-- FIX106_COMPACT_ESTIMATE_INPUT_COLUMNS: mild left/right tightening of',
'     numeric boxes, preserving full tax-inclusive final amount. --}}',
'<style id="FIX106_COMPACT_ESTIMATE_INPUT_COLUMNS">',
'#estimate-form .estimate-line-table{table-layout:fixed!important;width:100%!important;max-width:100%!important;}',
]
for i,w in enumerate(widths,1):
 css_lines.append(f'#estimate-form .estimate-line-table thead th:nth-child({i})'+'{width:'+f'{w:g}'+'%!important;}')
css_lines += [
'#estimate-form .estimate-line-table tbody td{padding-left:2px!important;padding-right:2px!important;}',
'#estimate-form .estimate-line-table tbody td:nth-child(4) input,',
'#estimate-form .estimate-line-table tbody td:nth-child(5) input,',
'#estimate-form .estimate-line-table tbody td:nth-child(6) input,',
'#estimate-form .estimate-line-table tbody td:nth-child(7) input,',
'#estimate-form .estimate-line-table tbody td:nth-child(9) input{',
'  box-sizing:border-box!important;width:calc(100% - 6px)!important;',
'  max-width:calc(100% - 6px)!important;min-width:0!important;',
'  margin-inline:3px!important;padding-inline:3px!important;',
'}',
'#estimate-form .estimate-line-table tbody td:nth-child(8) .e-disc-amt,',
'#estimate-form .estimate-line-table tbody td:nth-child(10) .e-gst-amt{',
'  box-sizing:border-box!important;width:calc(100% - 4px)!important;',
'  max-width:calc(100% - 4px)!important;min-width:0!important;',
'  margin-inline:2px!important;padding-inline:2px!important;',
'}',
'#estimate-form .estimate-line-table tbody td:nth-child(11) .e-total{',
'  box-sizing:border-box!important;max-width:100%!important;',
'  width:100%!important;padding-inline:1px!important;',
'  overflow:visible!important;white-space:nowrap!important;',
'}',
'#estimate-form .estimate-line-table tbody td:nth-child(12){',
'  padding-inline:1px!important;text-align:center!important;',
'}',
'</style>',
]
css='\n'.join(css_lines)+'\n'
updated=s.replace(old,css+old,1)
if updated.count('id="'+marker+'"')!=1:
 raise SystemExit('FIX106_MARKER_FAIL')
if mode=='check':
 dest=os.environ.get('FIX106_PREVIEW_PATH')
 if dest:
  Path(dest).write_text(updated,encoding='utf8',errors='surrogateescape')
  print('FIX106_TEMP_CANDIDATE_WRITTEN')
 print('FIX106_PREFLIGHT_NO_LIVE_WRITES');sys.exit(0)
fd,temp=tempfile.mkstemp(prefix='.fix106_',dir=p.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(updated)
 os.chmod(temp,p.stat().st_mode & 0o777)
 os.replace(temp,p)
finally:
 if os.path.exists(temp):os.unlink(temp)
print('FIX106_ONLY_ESTIMATE_CREATE_CSS_APPLIED_NO_DATA_WRITES')
