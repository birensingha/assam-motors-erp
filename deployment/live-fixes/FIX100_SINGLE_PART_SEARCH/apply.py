#!/usr/bin/env python3
"""FIX100: SINGLE Parts search/select matching FIX98 Labour combobox.

STAGING only: hide the original native <select> as a data and price source,
keep FIX89 searchable Parts input with arrow inside the SAME bordered field.
Preserve rate/GST autofill, Qty focus, discount/tax totals, Part notes, AJAX,
selection ID and server-side Parts authorization/validation.
"""
from pathlib import Path
import hashlib,sys,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file(), 'FIX100_NOT_LARAVEL'
p=root/'resources/views/job-cards/show.blade.php'
raw=p.read_bytes();sha=hashlib.sha256(raw).hexdigest()
s=raw.decode('utf8','surrogateescape')
print('FIX100_SOURCE_SHA256',sha)
marker='id="FIX100_SINGLE_PART_SEARCH_AND_ARROW"'
if marker in s:
 if s.count(marker)==1 and 'data-am100-native-hidden="1"' in s:
  print('FIX100_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX100_PARTIAL_PATCH_ABORT')
expected='04a019de1bfc2eb1bbe9f76da403cb2263b58e590ecf6778eb925247558c851f'
if sha!=expected:raise SystemExit('FIX100_CONCURRENT_VIEW_CHANGE_STOP')
for k in ['id="FIX89_ARROW_TYPE_PARTS_DROPDOWN"','id="am85-part-search"',
          'class="am89-combo"','className=\\'am89-combo\\'',
          'am89-query','am89-arrow','am89-dropdown',
          'id="am85-part-id"','id="am85-part-form"',
          'id="am85-qty"','id="am85-rate"','id="am85-gst"',
          'id="am85-discount"','id="am85-discount-rs"',
          'id="am85-taxable"','id="am85-gst-rs"','id="am85-grand"',
          "route('erp.job-cards.parts.direct',$job->id)"]:
 if k not in s:raise SystemExit('FIX100_REQUIRED_PARTS_FUNCTION_MISSING '+k)
original='''<select id="am85-part-search" form="am85-part-form" required
           aria-label="Select Part from master" title="Select Part code or name">'''
replacement='''<select id="am85-part-search" form="am85-part-form" hidden
           data-am100-native-hidden="1" aria-hidden="true" tabindex="-1"
           style="display:none !important;" 
           aria-label="Parts master data source" title="Parts master data source">'''
if s.count(original)!=1:raise SystemExit('FIX100_EXPECTED_ONE_NATIVE_SELECT_'+str(s.count(original)))
s=s.replace(original,replacement,1)
addon=r'''
{{-- FIX100_SINGLE_PART_SEARCH_AND_ARROW: ONLY the FIX89 custom searchable
 input is visible. Original select remains DOM data source for FIX87/FIX88. --}}
<style id="FIX100_SINGLE_PART_SEARCH_AND_ARROW">
.jc-parts-block #am85-part-search,
.jc-parts-block .am87-page-table .am85-part-add-row select#am85-part-search,
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo select#am85-part-search{
 display:none!important;visibility:hidden!important;pointer-events:none!important;
 position:absolute!important;left:-9999px!important;top:auto!important;
 width:0!important;min-width:0!important;max-width:0!important;
 height:0!important;min-height:0!important;max-height:0!important;
 margin:0!important;padding:0!important;border:0!important;
 overflow:hidden!important;opacity:0!important;appearance:none!important;
 -webkit-appearance:none!important
}
/* No second native box, no separate arrow control border. */
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo{
 display:flex!important;flex-direction:row!important;position:relative!important;
 width:100%!important;min-width:0!important;max-width:100%!important;
 height:29px!important;min-height:29px!important;gap:0!important;
 margin:0!important;padding:0!important;box-sizing:border-box!important;
 border:1px solid #90b1d5!important;border-radius:5px!important;
 background:#fff!important;overflow:hidden!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo input.am89-query{
 display:block!important;flex:1 1 auto!important;position:relative!important;
 min-width:0!important;width:100%!important;max-width:none!important;
 height:27px!important;min-height:27px!important;
 box-sizing:border-box!important;margin:0!important;
 border:0!important;border-radius:4px!important;
 padding:3px 26px 3px 6px!important;
 background:#fff!important;box-shadow:none!important;
 outline:none!important;text-overflow:ellipsis!important;
 font-size:10px!important;color:#173957!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo button.am89-arrow{
 position:absolute!important;right:0!important;left:auto!important;top:0!important;
 width:23px!important;min-width:23px!important;max-width:23px!important;
 height:27px!important;min-height:27px!important;
 display:flex!important;flex:0 0 23px!important;
 align-items:center!important;justify-content:center!important;
 padding:0!important;margin:0!important;gap:0!important;
 background:transparent!important;box-shadow:none!important;
 border:0!important;border-left:0!important;
 border-radius:0 4px 4px 0!important;
 font-size:12px!important;color:#245a85!important;z-index:2!important;
 cursor:pointer!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo button.am89-arrow:hover,
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo button.am89-arrow:focus-visible{
 background:#eaf4ff!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo:focus-within{
 border-color:#1475bc!important;
 box-shadow:0 0 0 2px rgba(10,110,209,.16)!important
}
</style>
<script>
(function(){
 'use strict';
 function fix(){
  var native=document.getElementById('am85-part-search');
  var visible=document.querySelector('.jc-parts-block .am89-combo input.am89-query');
  var partId=document.getElementById('am85-part-id');
  var form=document.getElementById('am85-part-form');
  if(!native||!visible||!form||!partId||native.dataset.am100Ready==='1')return;
  native.dataset.am100Ready='1';
  // Required validation belongs to ONE visible selection field.
  native.required=false;
  native.hidden=true;
  native.tabIndex=-1;
  native.setAttribute('aria-hidden','true');
  native.style.setProperty('display','none','important');
  native.style.setProperty('visibility','hidden','important');
  visible.required=true;
  visible.placeholder='Select / Search Part Code or Name';
  visible.setAttribute('aria-required','true');
  visible.setAttribute('aria-label','Select Part by code or name');
  // Preflight selection prevents hidden native-select reportValidity failures.
  // Preserve the genuine native value and part_master_id as source of truth.
  function check(e){
   if(native.value && native.value===partId.value){
    native.setCustomValidity('');
    visible.setCustomValidity('');
    return;
   }
   if(e){e.preventDefault();e.stopImmediatePropagation();}
   native.setCustomValidity('');
   visible.setCustomValidity('Select a Part from the suggestions, not text alone.');
   visible.focus({preventScroll:true});
   visible.reportValidity();
  }
  form.addEventListener('submit',check,true);
  var button=document.querySelector('button[form="am85-part-form"][type="submit"]');
  if(button)button.addEventListener('click',function(e){
    if(!native.value||native.value!==partId.value)check(e);
  },true);
  visible.addEventListener('input',function(){
    visible.setCustomValidity('');
    native.setCustomValidity('');
  });
  native.addEventListener('change',function(){
    if(native.value&&native.value===partId.value){
      visible.setCustomValidity('');native.setCustomValidity('');
    }
  });
  native.addEventListener('am89-reset',function(){
    native.setCustomValidity('');
    visible.setCustomValidity('');
  });
 }
 if(document.readyState==='loading')
  document.addEventListener('DOMContentLoaded',fix,{once:true});
 else fix();
})();
</script>
'''
s+='\n'+addon
if s.count(marker)!=1 or s.count('data-am100-native-hidden="1"')!=1:
 raise SystemExit('FIX100_RESULT_VALIDATION_FAILED')
if mode=='check':
 if os.environ.get('FIX100_PREVIEW_PATH'):
  Path(os.environ['FIX100_PREVIEW_PATH']).write_text(s,encoding='utf8',errors='surrogateescape')
  print('FIX100_TEMP_BLADE_PREVIEW_NO_WRITES')
 print('FIX100_PREFLIGHT_PASS_NO_LIVE_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.fix100_')
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX100_SINGLE_PART_SEARCH_VISIBLE_AND_OLD_SELECT_HIDDEN_VIEW_ONLY')
