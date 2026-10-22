#!/usr/bin/env python3
"""FIX96: one-row compact additional Labour/ROT form; no duplicate search field
or instructional strips. Single integrated ROT code/name searchable dropdown.
Source JS sync, financial totals, and no-full-page-refresh AJAX preserved.
STAGING Blade-only patch; no DB, route, stock or billing controller changes.
"""
from pathlib import Path
import sys,hashlib,os,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
raw=view.read_bytes();sha=hashlib.sha256(raw).hexdigest()
print('FIX96_SOURCE_SHA256',sha)
marker='id="FIX96_SINGLE_ROW_LABOUR_COMBO"'
s=raw.decode('utf8','surrogateescape')
if marker in s:
 if s.count(marker)==1 and 'am96-visible-search' in s:
  print('FIX96_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX96_PARTIAL_PATCH_ABORT')
expected='a45f18c398c1af50977e926290198837e47bd087ea0b85d009003c8a5880f96f'
if sha!=expected:raise SystemExit('FIX96_CONCURRENT_SOURCE_CHANGE_STOP')
def once(old,new,label):
 global s
 n=s.count(old);print('FIX96_ANCHOR',label,n)
 if n!=1:raise SystemExit('FIX96_MISSING_UNIQUE_ANCHOR_'+label)
 s=s.replace(old,new,1)

once('''<div class="section-head"><div><h2 class="section-title">Labour Item Selection</h2><p class="muted">ONE WORKSPACE · Approved Estimate → SEND TO JOB CARD → Planned ROT · Additional ROT as required in WIP · Admin + Staff share original session records.</p></div></div>''',
'''<div class="section-head"><div><h2 class="section-title">Labour Item Selection</h2></div></div>''',
'remove_one_workspace_text')
once('''    <div class="am83-workflow-strip">
      <strong>ROT / Labour Search → Mechanic → Start / Pause / Resume / Complete → Billing</strong>
      <span>Estimate release: {{ $am83FromEstimate }} ROT · Additional Direct ROT: {{ $am83AddedDirect }}</span>
    </div>''','''    {{-- FIX96: duplicate workflow/estimate statistics strip removed for compact workspace. --}}''',
'remove_workflow_stats_strip')
once(''' <p class="muted">Approved Estimate se transferred ROTs niche hain. Extra ROT aur OSL isi workspace se add karein.</p>
''','', 'remove_additional_rot_instructions')
# Legacy script uses id=am82-rot-search for filtering and preexisting AJAX resets it.
# Keep this hidden element as a compatibility bridge but not a second visible input.
once('''        <label class="wide">Search ROT by name/code<input type="search" id="am82-rot-search" autocomplete="off" placeholder="Type ROT name/code, Enter to select" aria-label="Search ROT"></label>''',
'''        <input type="hidden" id="am82-rot-search" value="" autocomplete="off">''',
'hide_obsolete_search_box_keep_js_bridge')

# Billing & selection form and its original native select remain unchanged.
ui=r'''
{{-- FIX96: ROT selection search lives INSIDE one dropdown; all additional ROT
     billing controls and Add Labour/Add OSL buttons on one desktop row. --}}
<style id="FIX96_SINGLE_ROW_LABOUR_COMBO">
.jc-labour-block>.section-head{
 padding:5px 10px!important;margin:0!important;min-height:0!important
}
.jc-labour-block>.section-head .section-title{
 font-size:12px!important;line-height:1.2!important;margin:0!important
}
.jc-labour-block .am83-workspace{
 padding:5px 9px 7px!important;margin:0!important
}
.jc-labour-block .am83-workflow-strip{display:none!important}
.jc-labour-block .am83-inline-add{
 padding:5px 2px 8px!important;margin:0!important;border-bottom:1px solid #dce8e2!important;
 overflow:visible!important
}
.jc-labour-block .am83-inline-add h3{
 margin:0 0 6px!important;font-size:11px!important;line-height:1.25!important;color:#08613e!important
}
.jc-labour-block .am83-inline-add>p.muted{display:none!important}
.jc-labour-block .am83-inline-add form.jc-entry-form{
 display:grid!important;
 grid-template-columns:minmax(190px,2.5fr) minmax(66px,.66fr) minmax(80px,.84fr) minmax(59px,.6fr) minmax(58px,.6fr) minmax(89px,.92fr) minmax(112px,1.2fr) max-content!important;
 align-items:end!important;gap:6px!important;
 width:100%!important;max-width:100%!important;
 margin:0!important;padding:0!important;box-sizing:border-box!important
}
.jc-labour-block .am83-inline-add form.jc-entry-form>label,
.jc-labour-block .am83-inline-add form.jc-entry-form>label.wide{
 display:flex!important;flex-direction:column!important;align-items:stretch!important;
 grid-column:auto!important;grid-row:auto!important;
 min-width:0!important;width:100%!important;max-width:100%!important;
 gap:4px!important;margin:0!important;padding:0!important;
 font-size:9px!important;font-weight:750!important;line-height:1.15!important;
 white-space:nowrap!important;color:#214e75!important
}
.jc-labour-block .am83-inline-add form.jc-entry-form>label input,
.jc-labour-block .am83-inline-add form.jc-entry-form>label select{
 width:100%!important;min-width:0!important;max-width:100%!important;
 height:29px!important;min-height:29px!important;box-sizing:border-box!important;
 border-radius:5px!important;font-size:10px!important;
 padding:4px 5px!important;margin:0!important;line-height:1.1!important;
 border:1px solid #b7cfe5!important;background:#fff!important;
 box-shadow:none!important
}
.jc-labour-block .am83-inline-add form.jc-entry-form>label input[readonly]{
 background:#eef3f9!important;color:#426282!important
}
.jc-labour-block .am83-inline-add #am82-rot-search{display:none!important}
.jc-labour-block .am83-inline-add form.jc-entry-form>.actions.am91-add-actions{
 grid-column:auto!important;grid-row:auto!important;
 display:flex!important;flex-direction:row!important;
 flex-wrap:nowrap!important;align-items:center!important;justify-content:flex-start!important;
 width:max-content!important;min-width:0!important;margin:0!important;padding:0!important;gap:5px!important;
 align-self:end!important
}
.jc-labour-block .am83-inline-add .am91-add-actions>button,
.jc-labour-block .am83-inline-add .am91-add-actions>.btn{
 min-height:29px!important;height:29px!important;border-radius:5px!important;
 font-size:9px!important;font-weight:800!important;padding:4px 7px!important;
 line-height:1.1!important;white-space:nowrap!important;width:auto!important;
 box-sizing:border-box!important;margin:0!important
}
/* The original required native select remains authoritative; the visible
   input and side arrow share the same selection box. */
.jc-labour-block .am96-shell{
 display:flex!important;align-items:center!important;position:relative!important;
 border:1px solid #9fbedc!important;background:#fff!important;
 border-radius:5px!important;height:29px!important;min-height:29px!important;
 box-sizing:border-box!important;width:100%!important;min-width:0!important;
 overflow:visible!important
}
.jc-labour-block .am83-inline-add .am96-shell select#am82-rot-select{
 position:absolute!important;left:0!important;top:0!important;opacity:0!important;
 pointer-events:none!important;width:1px!important;height:1px!important;
 min-width:0!important;min-height:0!important;
 border:0!important;padding:0!important;margin:0!important;z-index:-1!important
}
.jc-labour-block .am83-inline-add .am96-shell input.am96-visible-search{
 flex:1 1 auto!important;display:block!important;min-width:0!important;width:0!important;
 height:27px!important;min-height:27px!important;max-width:none!important;
 padding:3px 5px!important;margin:0!important;border:0!important;
 background:#fff!important;box-shadow:none!important;outline:0!important;
 font-size:10px!important;color:#214a73!important
}
.jc-labour-block .am96-shell:focus-within{outline:2px solid rgba(7,108,198,.20);border-color:#0d72c7!important}
.jc-labour-block .am83-inline-add .am96-shell button.am96-arrow{
 display:flex!important;flex:0 0 24px!important;align-items:center!important;
 justify-content:center!important;min-width:24px!important;
 height:27px!important;min-height:27px!important;
 padding:0!important;margin:0!important;border:0!important;
 border-left:1px solid #d8e4ef!important;background:#f6faff!important;
 color:#14527f!important;border-radius:0 4px 4px 0!important;
 font-size:12px!important;cursor:pointer!important;box-shadow:none!important
}
.am96-options-popup{display:none;position:fixed;box-sizing:border-box;z-index:2147483000;
 background:#fff;border:1px solid #9bb7d6;border-radius:6px;box-shadow:0 10px 22px rgba(14,48,85,.22);
 color:#18436a;min-width:200px;overflow:hidden;font-family:Arial,sans-serif}
.am96-options-popup.am96-open{display:block}
.am96-options-popup .am96-list{overflow-y:auto;max-height:230px;overscroll-behavior:contain}
.am96-options-popup .am96-option{
 display:block;width:100%;padding:8px 10px;background:#fff;color:#214363;
 border:0;border-bottom:1px solid #edf1f6;text-align:left;
 line-height:1.3;font-size:11px;cursor:pointer;font-family:inherit
}
.am96-options-popup .am96-option:hover,.am96-options-popup .am96-option.am96-active{
 background:#e8f2ff!important;color:#07539a!important
}
.am96-options-popup .am96-option.am96-selected{font-weight:800}
.am96-options-popup .am96-empty{padding:12px 9px;font-size:11px;color:#677e92}
.am96-options-popup .am96-meta{padding:6px 9px;background:#f6f9fc;font-size:9px;color:#55738e}
@media(max-width:1000px){
 .jc-labour-block .am83-inline-add form.jc-entry-form{
  grid-template-columns:minmax(210px,2fr) repeat(3,minmax(85px,1fr))!important;
  row-gap:8px!important
 }
 .jc-labour-block .am83-inline-add form.jc-entry-form>.actions.am91-add-actions{
  grid-column:span 2!important;justify-content:flex-end!important
 }
}
@media(max-width:650px){
 .jc-labour-block .am83-inline-add form.jc-entry-form{grid-template-columns:repeat(2,minmax(0,1fr))!important}
 .jc-labour-block .am83-inline-add form.jc-entry-form>label:first-of-type{grid-column:span 2!important}
 .jc-labour-block .am83-inline-add form.jc-entry-form>.actions.am91-add-actions{grid-column:span 2!important}
}
</style>
<script>
(function(){
 'use strict';
 function init(){
  var native=document.getElementById('am82-rot-select');
  var searchBridge=document.getElementById('am82-rot-search');
  if(!native||!searchBridge||native.dataset.am96Ready)return;
  native.dataset.am96Ready='1';
  var choices=Array.from(native.options).filter(function(o){return Boolean(o.value);});
  var shell=document.createElement('span');shell.className='am96-shell';
  native.parentElement.insertBefore(shell,native);
  shell.appendChild(native);
  var visible=document.createElement('input');
  visible.type='search';visible.className='am96-visible-search';
  visible.placeholder='Select / type ROT code or name';
  visible.autocomplete='off';
  visible.setAttribute('role','combobox');
  visible.setAttribute('aria-label','Select or type Labour ROT code or name');
  visible.setAttribute('aria-autocomplete','list');
  visible.setAttribute('aria-expanded','false');
  visible.setAttribute('aria-controls','am96-rot-options');
  shell.appendChild(visible);
  var arrow=document.createElement('button');
  arrow.type='button';arrow.className='am96-arrow';arrow.textContent='▾';
  arrow.setAttribute('aria-label','Show all Labour and ROT codes');
  arrow.setAttribute('aria-controls','am96-rot-options');
  shell.appendChild(arrow);
  var popup=document.createElement('div');popup.className='am96-options-popup';
  popup.id='am96-rot-options';popup.setAttribute('role','listbox');
  popup.setAttribute('aria-label','Available ROT / Labour items');
  popup.innerHTML='<div class="am96-list"></div><div class="am96-meta"></div>';
  document.body.appendChild(popup);
  var list=popup.querySelector('.am96-list'),meta=popup.querySelector('.am96-meta');
  var shown=[],active=0,opened=false,form=native.closest('form');
  function label(opt){return (opt.textContent||'').replace(/\s+/g,' ').trim();}
  function position(){
   if(!opened)return;
   var box=shell.getBoundingClientRect();
   if(box.top>window.innerHeight||box.bottom<0){close();return;}
   var width=Math.min(Math.max(box.width,230),window.innerWidth-16);
   popup.style.width=width+'px';
   popup.style.left=Math.max(8,Math.min(box.left,window.innerWidth-width-8))+'px';
   var height=Math.min(270,popup.offsetHeight||270);
   var above=window.innerHeight-box.bottom<height && box.top>height;
   popup.style.top=(above?Math.max(7,box.top-height-4):Math.min(window.innerHeight-45,box.bottom+4))+'px';
  }
  function close(){opened=false;popup.classList.remove('am96-open');visible.setAttribute('aria-expanded','false');visible.removeAttribute('aria-activedescendant');}
  function moveTo(n){
   if(!shown.length)return;
   active=(n+shown.length)%shown.length;
   var buttons=Array.from(list.querySelectorAll('button.am96-option'));
   buttons.forEach(function(b,i){b.classList.toggle('am96-active',i===active);b.setAttribute('aria-selected',i===active?'true':'false');});
   if(buttons[active]){buttons[active].scrollIntoView({block:'nearest'});visible.setAttribute('aria-activedescendant',buttons[active].id);}
  }
  function clearSelection(){
   native.value='';
   var std=document.getElementById('am82-new-std'),total=document.getElementById('am82-add-labour-total');
   if(std)std.value='';
   if(total)total.value='';
   if(form){
    var rate=form.querySelector('[name="labour_rate"]');
    var gst=form.querySelector('[name="gst_percent"]');
    if(rate)rate.value='';
    if(gst)gst.value='';
   }
  }
  function pick(opt){
   if(!opt)return;
   searchBridge.value='';
   searchBridge.dispatchEvent(new Event('input',{bubbles:true}));
   native.value=opt.value;
   visible.value=label(opt);
   visible.setCustomValidity('');
   close();
   // Original FIX82 change handler populates Std Hours, Rate, GST and total.
   native.dispatchEvent(new Event('change',{bubbles:true}));
   if(form){var rate=form.querySelector('[name="labour_rate"]');if(rate)rate.focus({preventScroll:true});}
  }
  function render(full){
   var q=(full?'':visible.value).trim().toLocaleLowerCase();
   var all=choices.filter(function(o){return !q||label(o).toLocaleLowerCase().includes(q);});
   shown=all.slice(0,40);active=0;list.replaceChildren();
   shown.forEach(function(o,i){
    var b=document.createElement('button');b.type='button';
    b.className='am96-option';b.id='am96-rot-opt-'+i;
    if(o.value===native.value)b.classList.add('am96-selected');
    b.setAttribute('role','option');b.textContent=label(o);
    b.addEventListener('mousedown',function(e){e.preventDefault();});
    b.addEventListener('click',function(){pick(o);});
    b.addEventListener('mousemove',function(){moveTo(i);});
    list.appendChild(b);
   });
   if(!shown.length){
    var empty=document.createElement('div');empty.className='am96-empty';
    empty.textContent='No matching ROT code / Labour name';list.appendChild(empty);
   }
   meta.textContent=all.length+' matching ROT'+(all.length===1?'':'s')+(all.length>40?' · showing first 40':'')+' · ↑↓ Enter to select';
   opened=true;popup.classList.add('am96-open');visible.setAttribute('aria-expanded','true');
   moveTo(0);position();
  }
  arrow.addEventListener('click',function(){
   if(opened){close();return;}
   visible.focus({preventScroll:true});
   searchBridge.value='';
   searchBridge.dispatchEvent(new Event('input',{bubbles:true}));
   render(true);
  });
  visible.addEventListener('click',function(){if(!opened)render(false);});
  visible.addEventListener('input',function(){
   clearSelection();visible.setCustomValidity('');
   searchBridge.value=visible.value;searchBridge.dispatchEvent(new Event('input',{bubbles:true}));
   render(false);
  });
  visible.addEventListener('keydown',function(e){
   if(e.key==='ArrowDown'||e.key==='ArrowUp'){
    e.preventDefault();
    if(!opened)render(false);else moveTo(active+(e.key==='ArrowDown'?1:-1));
   }else if(e.key==='Enter'){
    e.preventDefault();
    if(!opened)render(false);
    else if(shown[active])pick(shown[active]);
    else {visible.setCustomValidity('Select a matching ROT.');visible.reportValidity();}
   }else if(e.key==='Escape'){if(opened){e.preventDefault();close();}}
   else if(e.key==='Tab')close();
  },true);
  native.addEventListener('change',function(){
   var opt=native.selectedOptions[0];
   if(opt&&opt.value){visible.value=label(opt);visible.setCustomValidity('');}
  });
  native.addEventListener('invalid',function(e){
   e.preventDefault();visible.setCustomValidity('Select Labour / ROT from dropdown.');
   visible.focus({preventScroll:true});visible.reportValidity();
  });
  if(form)form.addEventListener('reset',function(){
   requestAnimationFrame(function(){
    visible.value='';visible.setCustomValidity('');searchBridge.value='';
    searchBridge.dispatchEvent(new Event('input',{bubbles:true}));close();
   });
  });
  document.addEventListener('pointerdown',function(e){
   if(!shell.contains(e.target)&&!popup.contains(e.target))close();
  });
  window.addEventListener('resize',position);
  window.addEventListener('scroll',function(e){if(!popup.contains(e.target))position();},true);
  if(native.value&&native.selectedOptions[0])visible.value=label(native.selectedOptions[0]);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
 else init();
})();
</script>
'''
s+='\n'+ui
for k in ['id="FIX95_ROT_ONE_ROW_NO_BILLING_BOX"',
          'id="FIX96_SINGLE_ROW_LABOUR_COMBO"',
          'id="am82-rot-search"','id="am82-rot-select"',
          'id="am82-new-std"','id="am82-add-labour-total"',
          'id="am91-add-osl"','class="jc-entry-form"',
          "route('erp.job-cards.labour.direct',$job->id)",
          'id="am90-labour-billing"',
          "route('erp.rot.complete'"]:
 if k not in s:raise SystemExit('FIX96_LOST_REQUIRED_FUNCTION '+k)
for removed in ['ONE WORKSPACE · Approved Estimate',
                'ROT / Labour Search → Mechanic → Start / Pause',
                'Approved Estimate se transferred ROTs niche hain.',
                'Search ROT by name/code<input']:
 if removed in s:raise SystemExit('FIX96_OLD_VISIBLE_TEXT_PRESENT '+removed)
if s.count(marker)!=1:raise SystemExit('FIX96_BAD_OUTPUT_MARKER')
if mode=='check':
 if os.environ.get('FIX96_PREVIEW_PATH'):
  Path(os.environ['FIX96_PREVIEW_PATH']).write_text(s,encoding='utf8',errors='surrogateescape')
  print('FIX96_TEMP_VIEW_PREVIEW_ONLY')
 print('FIX96_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix96_',dir=view.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,view.stat().st_mode&0o777)
 os.replace(tmp,view)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX96_ONE_ROW_COMBO_AND_TEXT_CLEANUP_VIEW_ONLY_APPLIED')
