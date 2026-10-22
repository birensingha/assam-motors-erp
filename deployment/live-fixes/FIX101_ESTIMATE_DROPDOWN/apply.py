#!/usr/bin/env python3
"""FIX101: remove clipping of Estimate Parts/ROT selection suggestions.
The Estimate Lines table and JS search/save scripts stay intact.
No estimate data writes; guarded STAGING Blade-only change.
"""
from pathlib import Path
import hashlib,sys,tempfile,os
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file(),'FIX101_NOT_LARAVEL'
p=root/'resources/views/estimates/create.blade.php'
raw=p.read_bytes();actual=hashlib.sha256(raw).hexdigest()
print('FIX101_SOURCE_SHA256',actual)
src=raw.decode('utf8','surrogateescape')
marker='id="FIX101_ESTIMATE_VISIBLE_ITEM_DROPDOWN"'
if marker in src:
 if src.count(marker)==1:
  print('FIX101_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX101_PARTIAL_PATCH_ABORT')
expected='fce2959a36dfab1975f193f10dc0c2730e6c1edebf57ed41d7df2687fb844a82'
if actual!=expected:raise SystemExit('FIX101_SOURCE_CHANGED_ABORT')
target='<div class="line-scroll estimate-lines-area">'
if src.count(target)!=1:raise SystemExit('FIX101_ESTIMATE_TABLE_ANCHOR_MISMATCH')
# Critical: overflow-x:auto silently forces overflow-y:auto in CSS. This 
# removes the table's isolated vertical scroller entirely so Parts/ROT dropdowns
# can escape to the card's full page flow; original suggestion JS is untouched.
css=r'''
{{-- FIX101_ESTIMATE_VISIBLE_ITEM_DROPDOWN: Parts/ROT choice list no longer
     clipped by the old internal Estimate Lines vertical scrollbar.
     Keep reference search, item autofill, totals and Save Draft unchanged. --}}
<style id="FIX101_ESTIMATE_VISIBLE_ITEM_DROPDOWN">
#estimate-form .estimate-lines-area,
#estimate-form .line-scroll.estimate-lines-area{
  overflow:visible!important;
  overflow-x:visible!important;
  overflow-y:visible!important;
  max-height:none!important;
  height:auto!important;
  min-height:0!important;
  position:relative!important;
  clip-path:none!important;
  contain:none!important
}
#estimate-form .estimate-lines-area .estimate-line-table,
#estimate-form .estimate-lines-area .estimate-line-table tbody,
#estimate-form .estimate-lines-area .estimate-line-table tr,
#estimate-form .estimate-lines-area .estimate-line-table td{
  overflow:visible!important;
  clip-path:none!important
}
/* A focused reference needs to paint over later lines and the footer. */
#estimate-form .estimate-line-table tbody tr:focus-within{
 position:relative!important;z-index:120!important
}
#estimate-form .estimate-line-table td:focus-within{
 position:relative!important;z-index:121!important
}
/* Keep the old page header and footer beneath the search choices. */
#estimate-form .estimate-lines-area:focus-within{z-index:122!important}
#estimate-form .estimate-line-table [role="listbox"],
#estimate-form .estimate-line-table [class*="suggest"],
#estimate-form .estimate-line-table [class*="dropdown"],
#estimate-form .estimate-line-table [class*="autocomplete"]{
 z-index:125!important;
 max-height:min(42vh,350px)!important;
 overflow-y:auto!important;
 overscroll-behavior:contain!important;
 box-shadow:0 10px 28px rgba(16,54,99,.18)!important
}
/* Match the existing scrollable table width only on very narrow devices,
   but allow suggestion lists to paint outside that scroll container on desktop. */
@media(max-width:700px){
 #estimate-form .estimate-lines-area{min-width:0!important}
 #estimate-form .estimate-line-table{min-width:980px}
}
</style>
'''
src=src.replace(target,css+target,1)
for k in ['id="FIX101_ESTIMATE_VISIBLE_ITEM_DROPDOWN"',
 'id="estimate-lines"','id="add-estimate-line"','id="estimate-form"',
 'AM_ESTIMATE_DATA','estimate-preview-total','estimate-line-table',
 "route('erp.estimates.store',$jobCard->id)"]:
 if k not in src:raise SystemExit('FIX101_CRITICAL_ESTIMATE_FORM_CHANGED '+k)
if src.count(marker)!=1:raise SystemExit('FIX101_BAD_OUTPUT')
if mode=='check':
 if os.environ.get('FIX101_PREVIEW_PATH'):
  Path(os.environ['FIX101_PREVIEW_PATH']).write_text(src,encoding='utf8',errors='surrogateescape')
  print('FIX101_PREVIEW_WRITTEN_TO_TEMP_ONLY')
 print('FIX101_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix101_',dir=p.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(src)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX101_ESTIMATE_DROPDOWN_CLIPPING_CSS_APPLIED_NO_DATA_WRITES')
