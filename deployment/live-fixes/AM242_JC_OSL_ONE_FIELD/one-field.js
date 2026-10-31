/* AM242_JC_ONE_OSL_FIELD: one searchable OSL service/master input in Add/Edit JC OSL.
 * Original hidden select (osl_master_id) and description remain authoritative for
 * server-side validation. No supplier purchase list or purchase mutation.
 */
(function(){
'use strict';
const match=(s)=>String(s||'').normalize('NFKC').toLocaleLowerCase('en-IN').trim().replace(/\s+/g,' ');
let mounted=new WeakSet();
function attach(){
 const dialog=document.querySelector('dialog.am91-osl-dialog[open]');
 const form=dialog?.querySelector('form.am91-osl-form');
 const original=form?.elements.namedItem('description');
 const select=form?.querySelector('.am146-master-row select[name="osl_master_id"]');
 if(!dialog||!form||!original||!select||mounted.has(form))return;
 mounted.add(form);
 const masterRow=select.closest('.am146-master-row');
 if(!masterRow)return;
 const masterLabel=select.closest('label');
 if(masterLabel){masterLabel.hidden=true;masterLabel.setAttribute('aria-hidden','true');}
 const originalLabel=original.closest('label');
 if(originalLabel){originalLabel.hidden=true;originalLabel.setAttribute('aria-hidden','true');}
 original.tabIndex=-1;
 const reload=masterRow.querySelector('.am241-refresh-master');
 if(reload){reload.hidden=true;reload.setAttribute('aria-hidden','true');}
 const combo=document.createElement('div');combo.className='am242-combo';
 const label=document.createElement('label');label.className='am242-label';label.textContent='OSL Service / Master *';
 const input=document.createElement('input');input.type='search';input.className='am242-search';
 input.placeholder='Search OSL Master by code or service name…';
 input.autocomplete='off';input.spellcheck=false;
 input.setAttribute('role','combobox');input.setAttribute('aria-autocomplete','list');
 input.setAttribute('aria-expanded','false');
 input.setAttribute('aria-label','Search and select OSL service from Master');
 const list=document.createElement('div');list.className='am242-options';list.hidden=true;list.setAttribute('role','listbox');
 const help=document.createElement('div');help.className='am242-help';help.setAttribute('role','status');
 label.append(input);combo.append(label,list,help);
 masterRow.insertBefore(combo,masterRow.firstChild);
 // The master row remains one visible search and the existing Create OSL button.
 const add=masterRow.querySelector('.am146-new-master');
 if(add){add.textContent='+ Create OSL Master';}
 const isNew=!/\/\d+\/?$/.test(form.action);
 function masterOptions(){
  return [...select.options].filter(o=>o.value&&o.dataset.name)
   .map(o=>({id:o.value,name:o.dataset.name,code:(o.textContent||'').split(' — ')[0]}));
 }
 function close(){list.hidden=true;input.setAttribute('aria-expanded','false');}
 function selectedName(){
  const option=[...select.options].find(o=>o.value===select.value);
  return option?.dataset?.name||'';
 }
 function updateTextFromMaster(){
  const selected=selectedName();
  if(selected){input.value=selected;original.value=selected;help.textContent='Master selected';}
  else if(original.value && !input.value){input.value=original.value;}
 }
 let focusIndex=0;
 function choose(x){
  select.value=x.id;
  select.dispatchEvent(new Event('change',{bubbles:true}));
  input.value=x.name;original.value=x.name;
  original.dispatchEvent(new Event('input',{bubbles:true}));
  help.textContent='Selected: '+x.code;
  input.setCustomValidity('');
  close();
 }
 function render(){
  const q=match(input.value);
  const found=masterOptions().filter(x=>!q||match(x.code+' '+x.name).includes(q)).slice(0,75);
  list.replaceChildren();focusIndex=0;
  if(found.length){
    for(const item of found){
      const button=document.createElement('button');button.type='button';button.className='am242-option';
      button.setAttribute('role','option');
      const code=document.createElement('small');code.textContent=item.code;
      const name=document.createElement('strong');name.textContent=item.name;
      button.append(code,name);
      button.addEventListener('pointerdown',e=>e.preventDefault());
      button.addEventListener('click',()=>choose(item));
      list.append(button);
    }
  }else{
    const empty=document.createElement('div');empty.className='am242-empty';
    empty.textContent=q?'No matching OSL Master. Use + Create OSL Master for new service.'
      :'No active OSL Master items loaded. Check Configuration → OSL Master.';
    list.append(empty);
  }
  // A small refresh control lives INSIDE the dropdown, not beside the Create button.
  const refresh=document.createElement('button');refresh.type='button';refresh.className='am242-refresh';
  refresh.textContent='↻ Reload OSL Master list';
  refresh.addEventListener('pointerdown',e=>e.preventDefault());
  refresh.addEventListener('click',e=>{e.stopPropagation();reload?.click();help.textContent='Reloading active OSL services…';});
  list.append(refresh);
  list.hidden=false;input.setAttribute('aria-expanded','true');
 }
 input.addEventListener('focus',render);
 input.addEventListener('click',render);
 input.addEventListener('input',()=>{
  const exact=masterOptions().find(x=>match(x.name)===match(input.value)||match(x.code+' — '+x.name)===match(input.value));
  if(exact){
    choose(exact);
  }else{
    select.value='';
    original.value=input.value.trim();
    input.setCustomValidity('');
    help.textContent='Select a matching Master item, or create a new one.';
    render();
  }
 });
 input.addEventListener('keydown',e=>{
   const buttons=[...list.querySelectorAll('.am242-option')];
   if(e.key==='Escape'){close();return;}
   if(e.key==='ArrowDown'||e.key==='ArrowUp'){
     e.preventDefault();if(list.hidden)render();
     if(buttons.length){
       focusIndex=(focusIndex+(e.key==='ArrowDown'?1:-1)+buttons.length)%buttons.length;
       buttons[focusIndex].scrollIntoView({block:'nearest'});
       buttons.forEach((b,i)=>b.classList.toggle('active',focusIndex===i));
     }
   }else if(e.key==='Enter'&&!list.hidden&&buttons.length){
     e.preventDefault();buttons[focusIndex]?.click();
   }
 });
 const observer=new MutationObserver(()=>{
  // AM241 refresh and AM146 Quick Create replace/add options to the underlying select.
  const found=selectedName();
  if(found){input.value=found;original.value=found;input.setCustomValidity('');}
  if(!list.hidden)render();
 });
 observer.observe(select,{childList:true,subtree:true});
 const docClick=e=>{if(!combo.contains(e.target))close();};
 document.addEventListener('pointerdown',docClick);
 dialog.addEventListener('close',()=>{observer.disconnect();document.removeEventListener('pointerdown',docClick);},{once:true});
 // Capture submit before the existing AJAX handler and before any financial action.
 form.addEventListener('submit',e=>{
  if(isNew && !select.value){
    e.preventDefault();e.stopImmediatePropagation();
    help.textContent='Choose an active OSL Master service or create it first.';
    input.setCustomValidity('Select an OSL Master service before saving.');
    input.reportValidity();input.focus();render();
  }else{
    input.setCustomValidity('');
    if(select.value && selectedName())original.value=selectedName();
  }
 },true);
 if(original.value)input.value=original.value;
 if(select.value)updateTextFromMaster();
 else if(!input.value)help.textContent='Type to find a service in OSL Master.';
 // Allow the existing "+ Create" implementation to use the typed text.
 if(add){
   add.addEventListener('click',()=>{original.value=input.value.trim();},{capture:true});
 }
}
const watcher=new MutationObserver(attach);
watcher.observe(document.body,{childList:true,subtree:true});
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',attach,{once:true});
else attach();
})();
