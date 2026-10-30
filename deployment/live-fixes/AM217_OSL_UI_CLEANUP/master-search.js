/* AM217_SEARCHABLE_OSL_MASTER — enhances existing, populated AM146 native select.
   Same select name/osl_master_id, same quick-create endpoint, no financial writes. */
(function(){
  'use strict';
  function upgrade(){
    var d=document.querySelector('dialog.am91-osl-dialog[open]');
    var f=d&&d.querySelector('form.am91-osl-form');
    var s=f&&f.querySelector('select[name="osl_master_id"]');
    if(!s||s.dataset.am217Ready==='1')return;
    var label=s.closest('label'),desc=f.elements.namedItem('description');
    if(!label||!desc)return;
    s.dataset.am217Ready='1';
    var box=document.createElement('div');box.className='am217-search-wrap';
    var input=document.createElement('input');input.type='search';
    input.className='am217-master-search';
    input.placeholder='Type OSL Master name or code';
    input.setAttribute('role','combobox');
    input.setAttribute('aria-label','Search OSL Master');
    input.setAttribute('aria-expanded','false');input.autocomplete='off';
    var list=document.createElement('div');list.className='am217-results';
    list.setAttribute('role','listbox');list.hidden=true;
    var hint=document.createElement('small');hint.className='am217-hint';
    var opts=Array.from(s.options).filter(function(o){return o.value&&o.dataset.name;});
    hint.textContent=opts.length+' active OSL Master items · select a result, or type a new service and press Enter';
    box.append(input,list,hint);label.insertBefore(box,s);
    s.classList.add('am217-native-select');
    function hide(){list.hidden=true;input.setAttribute('aria-expanded','false');}
    function selectItem(o){
       s.value=o.value;
       s.dispatchEvent(new Event('change',{bubbles:true}));
       input.value=o.dataset.name||o.textContent.trim();
       hide();
    }
    function customItem(){
       var text=input.value.trim();
       if(!text){hide();return;}
       s.value='';desc.value=text;
       desc.dispatchEvent(new Event('input',{bubbles:true}));
       hide();
    }
    function render(){
       var q=input.value.trim().toLocaleLowerCase(),found=opts.filter(function(o){
         return (o.textContent+' '+o.dataset.name).toLocaleLowerCase().includes(q);
       });
       list.replaceChildren();
       found.slice(0,40).forEach(function(o){
         var button=document.createElement('button');
         button.type='button';button.className='am217-result';button.textContent=o.textContent;
         button.setAttribute('role','option');
         button.addEventListener('mousedown',function(e){e.preventDefault();selectItem(o);});
         button.addEventListener('click',function(e){e.preventDefault();selectItem(o);});
         list.appendChild(button);
       });
       if(!found.length){
         var empty=document.createElement('div');empty.className='am217-no-result';
         empty.textContent='No matching Master. Press Enter to use this as a new OSL Service.';
         list.appendChild(empty);
       }
       list.hidden=false;input.setAttribute('aria-expanded','true');
    }
    input.addEventListener('focus',render);
    input.addEventListener('input',function(){s.value='';render();});
    input.addEventListener('keydown',function(e){
      if(e.key==='Escape'){hide();return;}
      if(e.key==='Enter'){
         e.preventDefault();
         var first=list.querySelector('.am217-result');
         if(first){var option=opts.find(function(o){return o.textContent===first.textContent;});
                    if(option)selectItem(option);}
         else customItem();
      }
      if(e.key==='ArrowDown'){
         var first=list.querySelector('.am217-result');
         if(first){e.preventDefault();first.focus();}
      }
    });
    list.addEventListener('keydown',function(e){
       if(e.key==='Escape'){e.preventDefault();hide();input.focus();}
    });
    s.addEventListener('change',function(){
       var opt=s.options[s.selectedIndex];
       if(opt&&opt.dataset.name)input.value=opt.dataset.name;
    });
    var selected=s.options[s.selectedIndex];
    input.value=(selected&&selected.dataset.name)||desc.value||'';
    d.addEventListener('click',function(e){if(!box.contains(e.target))hide();});
  }
  var observer=new MutationObserver(upgrade);
  function init(){observer.observe(document.body,{childList:true,subtree:true});upgrade();}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
