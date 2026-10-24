
{{-- AM124: In-dialog Alteration Reason picker. Retains original <select> name,
     values, server validator, audit history, unlock and save logic unchanged.
     Fixes native drop list drawing BEHIND dialog::backdrop at bottom of screen. --}}
@once
<style id="AM124_P55_REASON_PICKER">
#am116-rot-dialog .am124-reason-trigger{
  display:flex!important;align-items:center!important;justify-content:space-between!important;
  gap:8px!important;width:100%!important;min-height:34px!important;padding:7px 9px!important;
  border:1px solid #679bd8!important;border-radius:6px!important;background:#fff!important;
  color:#173c66!important;font-size:11px!important;font-weight:700!important;
  text-align:left!important;cursor:pointer!important;box-sizing:border-box!important;
}
#am116-rot-dialog .am124-reason-trigger:focus-visible{
  outline:2px solid #2f73cc!important;outline-offset:2px!important;
}
#am116-rot-dialog .am124-reason-trigger:disabled{
  opacity:.55!important;cursor:not-allowed!important;background:#edf1f6!important;
}
#am116-rot-dialog .am124-reason-popup{
  position:fixed!important;z-index:2147483600!important;
  background:#fff!important;border:1px solid #87a8ca!important;
  box-shadow:0 14px 28px rgba(21,48,76,.28)!important;
  border-radius:7px!important;padding:5px!important;margin:0!important;
  box-sizing:border-box!important;display:flex!important;flex-direction:column!important;
  gap:4px!important;max-width:calc(100vw - 22px)!important;
  overflow:hidden!important;isolation:isolate!important;
}
#am116-rot-dialog .am124-reason-search{
  display:block!important;flex:0 0 auto!important;width:100%!important;box-sizing:border-box!important;
  font-size:12px!important;padding:7px 8px!important;border:1px solid #bed0e2!important;
  background:white!important;color:#153e64!important;border-radius:5px!important;
}
#am116-rot-dialog .am124-reason-options{
  display:block!important;flex:1 1 auto!important;min-height:0!important;
  overflow-y:auto!important;overscroll-behavior:contain!important;
}
#am116-rot-dialog .am124-reason-option{
  display:block!important;width:100%!important;box-sizing:border-box!important;
  text-align:left!important;border:0!important;border-radius:4px!important;
  color:#1d395c!important;background:#fff!important;padding:7px 9px!important;
  line-height:1.45!important;font-size:11px!important;cursor:pointer!important;
}
#am116-rot-dialog .am124-reason-option:hover,
#am116-rot-dialog .am124-reason-option:focus-visible{
  background:#e8f2ff!important;outline:0!important;
}
#am116-rot-dialog .am124-reason-empty{
  padding:10px!important;color:#65758a!important;font-size:11px!important;
}
</style>
<script>
(function(){
  'use strict';
  // Only operates within the existing Admin Full ROT Correction <dialog>.
  // The ORIGINAL select is kept and still submitted to the SAME server endpoint.
  var dialogId='am116-rot-dialog', active=null;

  function closePicker(){
    if(!active)return;
    var x=active;active=null;
    if(x.popup&&x.popup.parentNode)x.popup.remove();
    if(x.trigger){
      x.trigger.setAttribute('aria-expanded','false');
      x.trigger.focus({preventScroll:true});
    }
  }

  function locate(dialog,trigger,popup){
    var area=dialog.getBoundingClientRect(), r=trigger.getBoundingClientRect();
    var pad=8;
    var availableHeight=Math.max(110,Math.min(215,area.height-35));
    var below=area.bottom-r.bottom-pad,above=r.top-area.top-pad;
    var popupH=Math.min(availableHeight,Math.max(110,Math.max(above,below)-4));
    var flip=below<popupH && above>below;
    var top=flip?r.top-popupH-4:r.bottom+4;
    top=Math.max(area.top+pad,Math.min(top,area.bottom-popupH-pad));
    var width=Math.max(195,Math.min(Math.max(r.width,265),area.width-2*pad,window.innerWidth-20));
    var left=Math.max(area.left+pad,Math.min(r.left,area.right-width-pad));
    popup.style.left=Math.round(left)+'px';
    popup.style.top=Math.round(top)+'px';
    popup.style.width=Math.round(width)+'px';
    popup.style.height=Math.round(popupH)+'px';
  }

  function openPicker(dialog,select,trigger){
    closePicker();
    if(select.disabled)return;
    var popup=document.createElement('div');
    popup.className='am124-reason-popup';
    popup.setAttribute('role','dialog');
    popup.setAttribute('aria-label','Select alteration reason');
    popup.id='am124-reason-popup';
    var search=document.createElement('input');
    search.type='search';
    search.placeholder='Type to search reason...';
    search.className='am124-reason-search';
    search.setAttribute('aria-label','Search alteration reason');
    var opts=document.createElement('div');
    opts.className='am124-reason-options';
    opts.setAttribute('role','listbox');
    popup.appendChild(search);
    popup.appendChild(opts);
    dialog.appendChild(popup); // Inside dialog top-layer, never behind backdrop.
    active={dialog:dialog,select:select,trigger:trigger,popup:popup,search:search,options:opts};
    trigger.setAttribute('aria-expanded','true');
    trigger.setAttribute('aria-controls',popup.id);
    locate(dialog,trigger,popup);

    function render(){
      opts.replaceChildren();
      var needle=search.value.trim().toLocaleLowerCase();
      var count=0;
      Array.from(select.options).forEach(function(original){
        if(original.disabled || !original.value)return;
        var name=String(original.textContent||'').trim();
        if(needle && !name.toLocaleLowerCase().includes(needle))return;
        var b=document.createElement('button');
        b.type='button';
        b.className='am124-reason-option';
        b.setAttribute('role','option');
        b.setAttribute('aria-selected',String(select.value===original.value));
        b.textContent=name;
        b.addEventListener('click',function(){
          select.value=original.value;
          select.dispatchEvent(new Event('input',{bubbles:true}));
          select.dispatchEvent(new Event('change',{bubbles:true}));
          closePicker();
        });
        opts.appendChild(b);count++;
      });
      if(!count){
        var empty=document.createElement('div');empty.className='am124-reason-empty';
        empty.textContent='No matching reasons';opts.appendChild(empty);
      }
    }
    search.addEventListener('input',render);
    search.addEventListener('keydown',function(event){
      if(event.key==='Escape'){event.preventDefault();closePicker();}
      if(event.key==='Enter' || event.key==='ArrowDown'){
        var first=opts.querySelector('button.am124-reason-option');
        if(first){event.preventDefault();first.focus();}
      }
    });
    opts.addEventListener('keydown',function(event){
      if(event.key==='Escape'){event.preventDefault();closePicker();}
      if(event.key==='Enter' || event.key===' '){
        if(event.target.matches('button.am124-reason-option')){
          event.preventDefault();event.target.click();
        }
      }
      if(event.key==='ArrowDown' || event.key==='ArrowUp'){
        var btns=Array.from(opts.querySelectorAll('button.am124-reason-option'));
        var index=btns.indexOf(event.target);
        if(index>=0 && btns.length){
          event.preventDefault();
          btns[(index+(event.key==='ArrowDown'?1:-1)+btns.length)%btns.length].focus();
        }
      }
    });
    render();
    search.focus({preventScroll:true});
  }

  function mount(){
    var dialog=document.getElementById(dialogId);
    if(!dialog)return;
    var selects=Array.from(dialog.querySelectorAll('select[name="alteration_reason"]'));
    selects.forEach(function(select){
      if(select.dataset.am124Picker==='1')return;
      select.dataset.am124Picker='1';
      var trigger=document.createElement('button');
      trigger.type='button';
      trigger.className='am124-reason-trigger';
      trigger.setAttribute('aria-label','Alteration Reason');
      trigger.setAttribute('aria-haspopup','dialog');
      trigger.setAttribute('aria-expanded','false');
      var txt=document.createElement('span');
      var chevron=document.createElement('span');
      chevron.textContent='▾';
      chevron.setAttribute('aria-hidden','true');
      trigger.appendChild(txt);trigger.appendChild(chevron);
      select.insertAdjacentElement('afterend',trigger);
      // Keep select in form for unchanged POST and P55 validation.
      // P55 scripts use the same exact select node and can set its value.
      select.style.setProperty('display','none','important');
      function sync(){
        var option=select.selectedOptions[0];
        txt.textContent=option&&select.value?String(option.textContent||'').trim():'— Select Reason —';
        trigger.disabled=select.disabled;
      }
      select.addEventListener('change',sync);
      new MutationObserver(sync).observe(select,{attributes:true,attributeFilter:['disabled']});
      trigger.addEventListener('click',function(){
        if(active && active.trigger===trigger){closePicker();return;}
        openPicker(dialog,select,trigger);
      });
      sync();
    });
    if(!dialog.dataset.am124Bound){
      dialog.dataset.am124Bound='1';
      dialog.addEventListener('close',function(){closePicker();});
      dialog.addEventListener('scroll',function(e){
        if(active && e.target===dialog)closePicker();
      },true);
      document.addEventListener('pointerdown',function(e){
        if(active && !active.popup.contains(e.target) && !active.trigger.contains(e.target)){
          closePicker();
        }
      },true);
      document.addEventListener('keydown',function(e){
        if(e.key==='Escape'&&active)closePicker();
      },true);
      window.addEventListener('resize',function(){if(active)locate(active.dialog,active.trigger,active.popup);});
    }
  }

  function boot(){
    mount();
    var root=document.body;
    if(root && window.MutationObserver){
      var observer=new MutationObserver(function(records){
        if(records.some(function(r){return r.addedNodes&&r.addedNodes.length;}))mount();
      });
      observer.observe(root,{childList:true});
    }
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
  else boot();
})();
</script>
@endonce
