#!/usr/bin/env python3
"""FIX98: eliminate duplicate native ROT select; keep ONE searchable combobox.

Render native <select> as hidden form data only. Existing FIX96 visible search
with arrow continues to operate; all original change, pricing and AJAX events
stay in place. The visible input becomes the required user-facing control, and
submit is blocked until a real native option is selected.

No routes/controllers/DB rows changed. Supports check/apply on STAGING.
"""
from pathlib import Path
import hashlib,os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file(),'FIX98_NOT_LARAVEL_ROOT'
p=root/'resources/views/job-cards/show.blade.php'
raw=p.read_bytes()
s=raw.decode('utf8','surrogateescape')
sha=hashlib.sha256(raw).hexdigest()
print('FIX98_SOURCE_SHA256',sha)
marker='id="FIX98_ONE_SEARCHABLE_ROT_ONLY"'
if marker in s:
 if s.count(marker)==1 and 'data-am98-hidden-native="1"' in s:
  print('FIX98_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX98_PARTIAL_PATCH_ABORT')
for required in ['id="FIX97_ONE_VISIBLE_ROT_COMBO"',
                 'id="FIX96_SINGLE_ROW_LABOUR_COMBO"',
                 'id="am82-rot-search"',
                 'id="am82-rot-select"',
                 'am96-visible-search',
                 'native.dispatchEvent(new Event(\'change\'',
                 'id="am91-add-osl"',
                 'id="am90-labour-billing"',
                 "route('erp.job-cards.labour.direct',$job->id)"]:
 if required not in s:raise SystemExit('FIX98_REQUIRED_ROT_FUNCTION_MISSING: '+required)
old='id="am82-rot-select" name="labour_master_id" required'
if s.count(old)!=1:
 raise SystemExit('FIX98_EXPECTED_SINGLE_NATIVE_SELECT_NOT_FOUND '+str(s.count(old)))
new='id="am82-rot-select" name="labour_master_id" hidden aria-hidden="true" tabindex="-1" data-am98-hidden-native="1" style="display:none !important;"'
s=s.replace(old,new,1)
# Keep original native select in DOM, connected to FIX82 billing price/GST logic.
# The user must see ONLY FIX96 searchable input with a single caret.
addon=r'''
{{-- FIX98_ONE_SEARCHABLE_ROT_ONLY: native select is private form data only.
     Exactly one visible searchable input and one arrow remain. --}}
<style id="FIX98_ONE_SEARCHABLE_ROT_ONLY">
#am82-rot-select,
.jc-labour-block .am83-inline-add #am82-rot-select,
.jc-labour-block .am83-inline-add .am96-shell select#am82-rot-select,
.jc-labour-block .am83-inline-add form.jc-entry-form #am82-rot-select{
 display:none!important;visibility:hidden!important;pointer-events:none!important;
 width:0!important;min-width:0!important;max-width:0!important;
 height:0!important;min-height:0!important;max-height:0!important;
 padding:0!important;margin:0!important;border:0!important;
 position:absolute!important;overflow:hidden!important;
}
.jc-labour-block .am83-inline-add .am96-shell{
 display:flex!important;align-items:center!important;
 grid-template-columns:none!important;gap:0!important;
 width:100%!important;min-width:0!important;
}
.jc-labour-block .am83-inline-add .am96-shell input.am96-visible-search{
 display:block!important;flex:1 1 auto!important;
 min-width:0!important;width:100%!important;
 box-sizing:border-box!important;padding-right:27px!important
}
.jc-labour-block .am83-inline-add .am96-shell button.am96-arrow{
 position:absolute!important;top:0!important;right:0!important;
 width:24px!important;min-width:24px!important;
 border:0!important;box-shadow:none!important;
 background:transparent!important
}
</style>
<script>
(function(){
 'use strict';
 function fix(){
  var native=document.getElementById('am82-rot-select');
  var visible=document.querySelector('.jc-labour-block .am96-shell input.am96-visible-search');
  if(!native||!visible||native.dataset.am98Done==='1')return;
  native.dataset.am98Done='1';
  // Hiding a REQUIRED native select can block focus during HTML5 validation.
  // One user-visible input is now the required form control; the original
  // selected <option value> remains the only submitted labour_master_id.
  native.required=false;
  native.hidden=true;
  native.tabIndex=-1;
  native.setAttribute('aria-hidden','true');
  native.style.setProperty('display','none','important');
  native.style.setProperty('visibility','hidden','important');
  visible.required=true;
  visible.placeholder='Select Labour / ROT…';
  visible.setAttribute('aria-required','true');
  visible.setAttribute('aria-label','Select Labour / ROT by typing or clicking arrow');
  var form=native.closest('form');
  if(!form)return;
  // Even if user typed an arbitrary name, never submit without selecting a
  // real ROT record. Capture-phase blocks original AJAX/form submit handlers.
  form.addEventListener('submit',function(e){
   if(!native.value){
    e.preventDefault();e.stopImmediatePropagation();
    visible.setCustomValidity('Select a Labour / ROT from the dropdown list.');
    visible.focus({preventScroll:true});visible.reportValidity();return false;
   }
   visible.setCustomValidity('');
  },true);
  visible.addEventListener('input',function(){
    visible.setCustomValidity('');
  });
  // FIX96 may populate a native option via another control. The input sync
  // listener in FIX96 still handles visible text and required validity.
  native.addEventListener('change',function(){
    if(native.value)visible.setCustomValidity('');
  });
 }
 if(document.readyState==='loading')
  document.addEventListener('DOMContentLoaded',fix,{once:true});
 else fix();
})();
</script>
'''
s+='\n'+addon
for check in ['id="FIX98_ONE_SEARCHABLE_ROT_ONLY"',
              'data-am98-hidden-native="1"',
              'native.required=false;',
              'visible.required=true;',
              'style="display:none !important;"']:
 if check not in s:raise SystemExit('FIX98_INVALID_FINAL_SOURCE '+check)
if s.count(marker)!=1 or s.count('data-am98-hidden-native="1"')!=1:
 raise SystemExit('FIX98_OUTPUT_DUPLICATE_MARKERS')
if mode=='check':
 if os.environ.get('FIX98_PREVIEW_PATH'):
  Path(os.environ['FIX98_PREVIEW_PATH']).write_text(s,encoding='utf8',errors='surrogateescape')
  print('FIX98_PROPOSED_VIEW_TEMP_WRITTEN_ONLY')
 print('FIX98_NO_WRITES_PREFLIGHT_PASS');sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix98_',dir=p.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX98_ONE_VISIBLE_SEARCHABLE_ROT_SELECT_APPLIED_UI_ONLY')
