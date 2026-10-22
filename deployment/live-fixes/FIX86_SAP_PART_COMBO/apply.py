#!/usr/bin/env python3
"""FIX86 STAGING: replace the browser-native Parts datalist popup with an
accessible SAP Fiori-inspired, white search menu. Presentation-only patch:
existing FIX85 part selection, discount calculation, POST handlers, saved
line-item edit/delete, Labour and OSL remain unchanged.
"""
from pathlib import Path
import os
import sys
import tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ('check', 'apply'):
    raise SystemExit("usage: apply.py check|apply ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).resolve()
assert (root / 'artisan').is_file(), "FIX86_NOT_LARAVEL_ROOT"
view = root / 'resources/views/job-cards/show.blade.php'
raw = view.read_bytes()
s = raw.decode('utf8', 'surrogateescape')
marker = 'FIX86_SAP_PART_COMBO'
if marker in s:
    if s.count('id="FIX86_SAP_PART_COMBO"') == 1 and 'list="am85-part-options"' not in s:
        print('FIX86_ALREADY_APPLIED')
        sys.exit(0)
    raise SystemExit('FIX86_PARTIALLY_APPLIED_ABORT')
for needle in ('id="am85-part-form"', 'id="am85-part-search"',
               'id="am85-part-options"', 'id="am85-discount"',
               'id="am85-part-id"', 'class="am85-part-add-row"',
               'name="discount_percent"', "route('erp.job-cards.parts.direct',$job->id)",
               "route('erp.job-cards.parts.update',[$job->id,$p->id])",
               "route('erp.job-cards.parts.delete',[$job->id,$p->id])",
               'id="FIX85_COMPACT_PARTS_TABLE_ROW"'):
    if s.count(needle) != 1 and needle != 'name="discount_percent"':
        raise SystemExit('FIX86_ANCHOR_COUNT_ERROR '+needle+'='+str(s.count(needle)))
    if needle == 'name="discount_percent"' and needle not in s:
        raise SystemExit('FIX86_DISCOUNT_MISSING')
needle='list="am85-part-options" autocomplete="off" spellcheck="false"'
if s.count(needle) != 1: raise SystemExit('FIX86_NATIVE_DATALIST_ANCHOR_MISMATCH')
s=s.replace(needle, 'autocomplete="off" spellcheck="false"', 1)
# Keep the datalist as a hidden, authoritative list of the existing Parts master;
# removing the input's "list" attribute disables the dark system popup.
enhancement = r'''
{{-- FIX86_SAP_PART_COMBO: Fiori-inspired visual picker for existing FIX85 row. --}}
<style id="FIX86_SAP_PART_COMBO">
.jc-parts-block .am85-part-add-row>td{padding:3px 3px!important}
.jc-parts-block .am85-part-add-row input{height:29px!important;min-height:29px!important;padding:4px 5px!important}
.jc-parts-block .am85-part-add-row #am85-part-search{
  padding-right:52px!important;background:#fff!important;border-color:#95acc5!important;
  font-size:11px!important;
}
.jc-parts-block .am85-part-add-row #am85-part-search:focus{
  border-color:#0a6ed1!important;outline:2px solid rgba(10,110,209,.17)!important
}
.jc-parts-block .am85-part-add-row td.am85-first-cell{position:relative}
.jc-parts-block .am85-part-add-row .am85-part-note{
  position:absolute;right:9px;top:9px;margin:0!important;padding:0!important;
  font-size:9px!important;z-index:1;background:#fff;
}
.jc-parts-block .am85-part-add-row .am85-part-note summary{color:#0a6ed1}
.jc-parts-block .am85-part-add-row .am85-part-note[open] input{
  position:absolute;top:24px;left:auto;right:0;width:280px;z-index:1001
}
.jc-parts-block .am85-part-add-row .am85-add-button{min-height:29px!important;padding:6px 8px!important}
.am86-sap-picker{
  position:fixed;z-index:2147483000;display:none;box-sizing:border-box;
  background:#fff;color:#223548;border:1px solid #c7d4e1;border-radius:8px;
  box-shadow:0 13px 40px rgba(28,49,78,.20);font-family:Arial,sans-serif;
  overflow:hidden;text-align:left;min-width:280px;
}
.am86-sap-picker.am86-open{display:block}
.am86-head{
  display:flex;justify-content:space-between;align-items:center;
  padding:10px 13px;background:#f5f8fc;border-bottom:1px solid #d9e2ef;gap:8px
}
.am86-head strong{font-size:12px;color:#163b65}
.am86-head span{color:#556b82;font-size:10px;white-space:nowrap}
.am86-results{max-height:268px;overflow-y:auto;overscroll-behavior:contain}
.am86-option{
  display:flex;width:100%;box-sizing:border-box;align-items:center;
  justify-content:space-between;gap:8px;text-align:left;border:0;border-bottom:1px solid #edf2f7;
  background:#fff;color:#223548;padding:8px 12px;cursor:pointer;font-family:inherit
}
.am86-option:hover,.am86-option.am86-active{
  background:#eaf3ff;box-shadow:inset 3px 0 0 #0a6ed1
}
.am86-title{display:block;font-size:12px;font-weight:600;line-height:1.25;overflow-wrap:anywhere}
.am86-code{color:#0a6ed1;font-weight:700;margin-right:5px}
.am86-meta{display:block;color:#64748b;font-size:10px;margin-top:3px}
.am86-price{color:#355673;font-size:11px;white-space:nowrap;font-weight:600}
.am86-empty{padding:16px;text-align:center;font-size:12px;color:#697e93}
.am86-foot{padding:7px 12px;font-size:10px;color:#536b85;background:#f8fafd;
  border-top:1px solid #e7edf5;display:flex;justify-content:space-between;gap:8px}
@media(max-width:600px){
  .am86-sap-picker{min-width:0}
  .am86-option{padding:9px}
  .am86-price{font-size:10px}
}
</style>
<script>
(function(){
'use strict';
function boot(){
  var search=document.getElementById('am85-part-search');
  var datalist=document.getElementById('am85-part-options');
  var form=document.getElementById('am85-part-form');
  var qty=document.getElementById('am85-qty');
  if(!search||!datalist||!form||!qty)return;
  var options=Array.from(datalist.options);
  var popup=document.createElement('div');
  popup.id='am86-sap-listbox';
  popup.className='am86-sap-picker';
  popup.setAttribute('role','listbox');
  popup.setAttribute('aria-label','Select Part by code or name');
  popup.innerHTML='<div class="am86-head"><strong>Part Selection</strong><span>Search by code or name</span></div>'+
    '<div class="am86-results"></div>'+
    '<div class="am86-foot"><span>↑ ↓ Navigate · Enter Select</span><span class="am86-count"></span></div>';
  document.body.appendChild(popup);
  var results=popup.querySelector('.am86-results'), count=popup.querySelector('.am86-count');
  var shown=[], index=0, opened=false;
  search.setAttribute('role','combobox');
  search.setAttribute('aria-autocomplete','list');
  search.setAttribute('aria-controls',popup.id);
  search.setAttribute('aria-expanded','false');
  function close(){
    opened=false;popup.classList.remove('am86-open');
    search.setAttribute('aria-expanded','false');search.removeAttribute('aria-activedescendant');
  }
  function position(){
    if(!opened)return;
    var rect=search.getBoundingClientRect();
    if(rect.bottom<0||rect.top>window.innerHeight){close();return;}
    var width=Math.min(Math.max(rect.width+160,380),Math.min(window.innerWidth-16,540));
    var left=Math.max(8,Math.min(rect.left,window.innerWidth-width-8));
    popup.style.width=width+'px';popup.style.left=left+'px';
    var height=Math.min(355,popup.offsetHeight||330);
    var below=window.innerHeight-rect.bottom;
    popup.style.top=(below>=Math.min(height,245)||below>=rect.top?
      Math.min(rect.bottom+4,window.innerHeight-110):
      Math.max(8,rect.top-height-4))+'px';
  }
  function activate(n){
    if(!shown.length)return;
    index=(n+shown.length)%shown.length;
    var els=results.querySelectorAll('.am86-option');
    els.forEach(function(el,i){
      el.classList.toggle('am86-active',i===index);
      el.setAttribute('aria-selected',i===index?'true':'false');
    });
    if(els[index]){
      search.setAttribute('aria-activedescendant',els[index].id);
      els[index].scrollIntoView({block:'nearest'});
    }
  }
  function choose(opt){
    if(!opt)return;
    search.value=opt.value;
    search.dispatchEvent(new Event('input',{bubbles:true}));
    search.dispatchEvent(new Event('change',{bubbles:true}));
    close();qty.focus();qty.select();
  }
  function draw(){
    var query=search.value.trim().toLocaleLowerCase();
    var all=options.filter(function(o){
      return !query||o.value.toLocaleLowerCase().includes(query);
    });
    shown=all.slice(0,9);
    results.replaceChildren();
    index=0;
    shown.forEach(function(opt,i){
      var pieces=opt.value.split(' · ');
      var code=pieces[0]||'', title=pieces.slice(1,-1).join(' · ')||opt.value;
      var item=document.createElement('button');
      item.type='button';item.id='am86-opt-'+i;item.className='am86-option';
      item.setAttribute('role','option');
      var left=document.createElement('span');
      var heading=document.createElement('span');heading.className='am86-title';
      var codeSpan=document.createElement('span');codeSpan.className='am86-code';
      codeSpan.textContent=code;heading.appendChild(codeSpan);
      heading.appendChild(document.createTextNode(title));
      var meta=document.createElement('span');meta.className='am86-meta';
      meta.textContent='ID '+(opt.dataset.id||'—')+' · GST '+(opt.dataset.gst||'0')+
        '% · Stock '+(opt.dataset.stock||'0');
      left.appendChild(heading);left.appendChild(meta);
      var right=document.createElement('span');right.className='am86-price';
      var rate=Number(opt.dataset.rate);
      right.textContent=Number.isFinite(rate)?'₹'+rate.toFixed(2):'';
      item.appendChild(left);item.appendChild(right);
      item.addEventListener('mousedown',function(e){e.preventDefault();});
      item.addEventListener('click',function(){choose(opt);});
      item.addEventListener('mousemove',function(){activate(i);});
      results.appendChild(item);
    });
    if(!shown.length){
      var empty=document.createElement('div');empty.className='am86-empty';
      empty.textContent='No matching Part. Try another code or name.';
      results.appendChild(empty);
    }
    count.textContent=all.length+' match'+(all.length===1?'':'es')+
      (all.length>shown.length?' · first '+shown.length:'');
    opened=true;popup.classList.add('am86-open');
    search.setAttribute('aria-expanded','true');
    activate(0);position();
  }
  search.addEventListener('focus',draw);
  search.addEventListener('input',draw);
  search.addEventListener('keydown',function(e){
    if(e.key==='ArrowDown'||e.key==='ArrowUp'){
      e.preventDefault();e.stopImmediatePropagation();
      if(!opened)draw();
      else activate(index+(e.key==='ArrowDown'?1:-1));
      return;
    }
    if(e.key==='Escape'&&opened){
      e.preventDefault();e.stopImmediatePropagation();close();return;
    }
    if(e.key==='Enter'&&opened){
      e.preventDefault();e.stopImmediatePropagation();
      if(shown[index])choose(shown[index]);
      else {search.setCustomValidity('Select a matching Part.');search.reportValidity();}
      return;
    }
    if(e.key==='Tab')close();
  },true);
  document.addEventListener('pointerdown',function(e){
    if(e.target!==search&&!popup.contains(e.target))close();
  });
  window.addEventListener('resize',position);
  window.addEventListener('scroll',function(e){
    if(popup.contains(e.target))return;
    position();
  },true);
  form.addEventListener('submit',close);
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
else boot();
})();
</script>
'''
anchor='<style id="FIX85_COMPACT_PARTS_TABLE_ROW">'
if s.count(anchor)!=1:raise SystemExit('FIX86_STYLE_ANCHOR_NOT_UNIQUE')
s=s.replace(anchor,enhancement+anchor,1)
if s.count('id="FIX86_SAP_PART_COMBO"')!=1 or 'list="am85-part-options"' in s:
    raise SystemExit('FIX86_VALIDATION_FAILED')
if mode=='check':
    print('FIX86_SAP_COMBO_PREFLIGHT_PASS_NO_WRITES')
    sys.exit(0)
fd,tmp=tempfile.mkstemp(dir=view.parent,prefix='.fix86_')
try:
    with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:
        f.write(s)
    os.chmod(tmp,view.stat().st_mode & 0o777)
    os.replace(tmp,view)
finally:
    if os.path.exists(tmp):os.unlink(tmp)
print('FIX86_SAP_PARTS_COMBO_APPLIED_VIEW_ONLY')
