#!/usr/bin/env python3
"""FIX89: Parts combo - arrow shows entire dropdown, typing filters options.
UI-only patch over audited FIX87/88. Original select and authorized billing
handlers remain authoritative. No schema, stock, or controller writes.
"""
from pathlib import Path
import sys,os,tempfile,hashlib
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply APP_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
view=root/'resources/views/job-cards/show.blade.php'
raw=view.read_bytes()
s=raw.decode('utf8','surrogateescape')
print('FIX89_VIEW_SHA256',hashlib.sha256(raw).hexdigest())
marker='id="FIX89_ARROW_TYPE_PARTS_DROPDOWN"'
if marker in s:
 if s.count(marker)==1:
  print('FIX89_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX89_INCOMPLETE_SOURCE')
for k in ['id="FIX87_NATIVE_PARTS_NO_SCROLL_AJAX"',
          'id="FIX88_COMPACT_EDITABLE_AMOUNTS"',
          '<select id="am85-part-search"',
          'id="am85-part-id"', 'id="am85-discount"',
          'id="am85-grand"', 'class="am85-part-add-row"']:
 if s.count(k)!=1:raise SystemExit('FIX89_EXPECTED_UNIQUE_ANCHOR '+k+': '+str(s.count(k)))
old="recalc();select.focus({preventScroll:true});"
if s.count(old)!=1:raise SystemExit('FIX89_RESET_HANDLER_CHANGED')
# Existing AJAX add handler clears select without change event. Notify combobox.
s=s.replace(old,"recalc();select.dispatchEvent(new Event('am89-reset'));",1)
addon=r'''
{{-- FIX89_ARROW_TYPE_PARTS_DROPDOWN: native-looking searchable Parts combo.
     Underlying select is preserved for FIX87/FIX88 validations and financial routes. --}}
<style id="FIX89_ARROW_TYPE_PARTS_DROPDOWN">
.jc-parts-block .am89-combo{
  position:relative!important;width:100%!important;display:flex!important;
  box-sizing:border-box!important;min-width:0!important;height:29px!important;
  border:1px solid #90b1d5!important;background:white!important;
  border-radius:5px!important;align-items:center!important;
}
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo select#am85-part-search{
  position:absolute!important;left:0!important;top:0!important;
  opacity:0!important;pointer-events:none!important;
  width:1px!important;height:1px!important;min-height:0!important;
  min-width:0!important;padding:0!important;margin:0!important;border:0!important;
  appearance:none!important;z-index:-1!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo input.am89-query{
  display:block!important;flex:1 1 auto!important;min-width:0!important;width:0!important;
  height:27px!important;min-height:27px!important;border:0!important;
  padding:3px 5px!important;box-shadow:none!important;outline:0!important;
  margin:0!important;font-size:10px!important;
  color:#173957!important;background:#fff!important;
}
.jc-parts-block .am89-combo:focus-within{
  outline:2px solid rgba(10,110,209,.18)!important;
  border-color:#0a6ed1!important
}
.jc-parts-block .am87-page-table .am85-part-add-row .am89-combo button.am89-arrow{
  box-sizing:border-box!important;display:flex!important;align-items:center!important;
  justify-content:center!important;flex:0 0 23px!important;
  height:27px!important;min-height:27px!important;padding:0!important;margin:0!important;
  border:0!important;border-left:1px solid #e1eaf3!important;
  background:#f8fbff!important;color:#1e527d!important;cursor:pointer!important;
  border-radius:0 4px 4px 0!important;font-size:13px!important
}
.am89-dropdown{
  display:none;position:fixed;box-sizing:border-box;z-index:2147483000;
  background:#fff;color:#1b3855;border:1px solid #9db6d4;
  border-radius:5px;box-shadow:0 9px 22px rgba(18,52,85,.2);
  overflow:hidden;font-family:Arial,sans-serif;min-width:170px
}
.am89-dropdown.am89-open{display:block}
.am89-results{overflow-y:auto;max-height:255px;overscroll-behavior:contain}
.am89-option{
  display:block;width:100%;padding:8px 10px;
  border:0;border-bottom:1px solid #e9eef5;
  background:white;text-align:left;color:#183c61;
  font-size:11px;line-height:1.3;cursor:pointer;font-family:inherit
}
.am89-option:hover,.am89-option.am89-active{
  background:#e8f3ff!important;color:#0a4d91!important
}
.am89-option.am89-selected{font-weight:700}
.am89-empty{padding:12px;font-size:11px;color:#687d91}
.am89-footer{background:#f5f8fc;border-top:1px solid #e1e7f0;
  display:flex;justify-content:space-between;gap:6px;
  padding:6px 8px;font-size:9px;color:#56718d}
</style>
<script>
(function(){
'use strict';
function init(){
  var native=document.getElementById('am85-part-search');
  var form=document.getElementById('am85-part-form');
  var id=document.getElementById('am85-part-id');
  if(!native||!form||!id||native.tagName!=='SELECT'||native.dataset.am89Done)return;
  native.dataset.am89Done='1';
  var options=Array.from(native.options).filter(function(o){return Boolean(o.value);});
  var wrap=document.createElement('div');wrap.className='am89-combo';
  var search=document.createElement('input');
  search.type='search';search.className='am89-query';
  search.placeholder='Select or type Part…';
  search.setAttribute('aria-label','Search Parts by code or name');
  search.setAttribute('aria-autocomplete','list');
  search.setAttribute('role','combobox');
  search.setAttribute('aria-haspopup','listbox');
  search.setAttribute('aria-expanded','false');
  search.setAttribute('form',form.id);
  search.required=true;
  search.autocomplete='off';
  var arrow=document.createElement('button');
  arrow.type='button';arrow.className='am89-arrow';arrow.textContent='▾';
  arrow.setAttribute('aria-label','Show all Parts');
  arrow.setAttribute('aria-controls','am89-options');
  native.parentNode.insertBefore(wrap,native);
  wrap.appendChild(native);wrap.appendChild(search);wrap.appendChild(arrow);
  var popup=document.createElement('div');popup.className='am89-dropdown';
  popup.id='am89-options';popup.setAttribute('role','listbox');
  popup.setAttribute('aria-label','Parts Master suggestions');
  popup.innerHTML='<div class="am89-results"></div>'+
    '<div class="am89-footer"><span>↑ ↓ Select · Enter Confirm</span><span class="am89-count"></span></div>';
  document.body.appendChild(popup);
  var results=popup.querySelector('.am89-results'),counter=popup.querySelector('.am89-count');
  var shown=[],active=0,opened=false;
  function label(opt){return opt.textContent.replace(/\s+/g,' ').trim();}
  function invalidate(){
    if(native.value!==''){native.value='';id.value='';}
    search.setCustomValidity('');
    ['am85-discount-rs','am85-taxable','am85-gst-rs','am85-grand'].forEach(function(k){
      var f=document.getElementById(k);if(f)f.value='';
    });
  }
  function close(){
    opened=false;popup.classList.remove('am89-open');
    search.setAttribute('aria-expanded','false');
    search.removeAttribute('aria-activedescendant');
  }
  function position(){
    if(!opened)return;
    var box=wrap.getBoundingClientRect();
    if(box.bottom<0||box.top>innerHeight){close();return;}
    var width=Math.min(Math.max(box.width,240),innerWidth-14);
    popup.style.width=width+'px';
    popup.style.left=Math.max(7,Math.min(box.left,innerWidth-width-7))+'px';
    var h=Math.min(290,popup.offsetHeight||280),below=innerHeight-box.bottom;
    var above=below<h&&box.top>below;
    popup.style.top=(above?Math.max(7,box.top-h-4):Math.min(box.bottom+3,innerHeight-60))+'px';
  }
  function mark(i){
    if(!shown.length)return;
    active=(i+shown.length)%shown.length;
    var items=results.querySelectorAll('.am89-option');
    items.forEach(function(el,j){
      var selected=j===active;el.classList.toggle('am89-active',selected);
      el.setAttribute('aria-selected',selected?'true':'false');
    });
    if(items[active]){
      search.setAttribute('aria-activedescendant',items[active].id);
      items[active].scrollIntoView({block:'nearest'});
    }
  }
  function choose(opt){
    if(!opt)return;
    native.value=opt.value;id.value=opt.value;
    search.value=label(opt);search.setCustomValidity('');
    close();
    // FIX87 sets rate/GST, moves to Qty; FIX88 recalculates all 4 amounts.
    native.dispatchEvent(new Event('change',{bubbles:true}));
  }
  function draw(full){
    var q=full?'':search.value.trim().toLocaleLowerCase();
    var matched=options.filter(function(x){return !q||label(x).toLocaleLowerCase().includes(q);});
    shown=matched.slice(0,30);
    results.replaceChildren();
    shown.forEach(function(opt,i){
      var item=document.createElement('button');item.type='button';
      item.id='am89-opt-'+i;item.className='am89-option';
      if(native.value===opt.value)item.classList.add('am89-selected');
      item.setAttribute('role','option');item.textContent=label(opt);
      item.addEventListener('mousedown',function(e){e.preventDefault();});
      item.addEventListener('click',function(){choose(opt);});
      item.addEventListener('mousemove',function(){mark(i);});
      results.appendChild(item);
    });
    if(!shown.length){
      var empty=document.createElement('div');empty.className='am89-empty';
      empty.textContent='No matching Part. Try another code or name.';
      results.appendChild(empty);
    }
    counter.textContent=matched.length+' match'+(matched.length===1?'':'es')+
      (matched.length>shown.length?' · first '+shown.length:'');
    opened=true;popup.classList.add('am89-open');
    search.setAttribute('aria-expanded','true');
    mark(0);position();
  }
  function toggle(){
    if(opened){close();return;}
    search.focus({preventScroll:true});draw(true);
  }
  arrow.addEventListener('click',toggle);
  search.addEventListener('click',function(){if(!opened)draw(false);});
  search.addEventListener('input',function(){invalidate();draw(false);});
  search.addEventListener('keydown',function(e){
    if(e.key==='ArrowDown'||e.key==='ArrowUp'){
      e.preventDefault();if(!opened)draw(false);
      else mark(active+(e.key==='ArrowDown'?1:-1));return;
    }
    if(e.key==='Enter'){
      if(opened){e.preventDefault();
        if(shown[active])choose(shown[active]);
        else {search.setCustomValidity('Select a matching Part.');search.reportValidity();}
      }
    }else if(e.key==='Escape'){if(opened){e.preventDefault();close();}}
    else if(e.key==='Tab')close();
  },true);
  native.addEventListener('change',function(){
    var opt=native.selectedOptions[0];
    if(opt&&opt.value){search.value=label(opt);search.setCustomValidity('');}
  });
  native.addEventListener('am89-reset',function(){
    search.value='';search.setCustomValidity('');close();
    search.focus({preventScroll:true});
  });
  native.addEventListener('invalid',function(e){
    e.preventDefault();
    search.setCustomValidity('Please select a Part from the dropdown.');
    search.focus({preventScroll:true});search.reportValidity();
  });
  search.addEventListener('input',function(){search.setCustomValidity('');});
  document.addEventListener('pointerdown',function(e){
    if(!wrap.contains(e.target)&&!popup.contains(e.target))close();
  });
  window.addEventListener('resize',position);
  window.addEventListener('scroll',function(e){
    if(popup.contains(e.target))return;position();
  },true);
  if(native.value&&native.selectedOptions[0])search.value=label(native.selectedOptions[0]);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
</script>
'''
# Place after FIX88 JS (end of this specific template). Correct initialization order
# is enforced by the browser's DOMContentLoaded registration order.
s+='\n'+addon
for expected in ["route('erp.job-cards.parts.direct',$job->id)",
                 "route('erp.job-cards.parts.update',[$job->id,$p->id])",
                 "route('erp.job-cards.parts.delete',[$job->id,$p->id])"]:
 if expected not in s:raise SystemExit('FIX89_CRITICAL_ROUTE_MISSING')
if s.count(marker)!=1 or s.count('am89-reset')<2:
 raise SystemExit('FIX89_FINAL_VALIDATION_FAILED')
if mode=='check':
 print('FIX89_COMBO_PREFLIGHT_PASS_NO_WRITES');sys.exit(0)
fd,tmp=tempfile.mkstemp(dir=view.parent,prefix='.fix89_')
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,view.stat().st_mode&0o777)
 os.replace(tmp,view)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX89_SEARCH_ARROW_FILTER_UI_APPLIED_NO_DB_WRITES')
