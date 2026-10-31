// AM241_JC_OSL_MASTER_LIVE: populate Add/Edit JC OSL modal from live shared catalog.
(function(){
'use strict';
const url=window.AM241_OSL_MASTER_OPTIONS_URL||'';
if(!url)return;
let attached=new WeakSet();
function install(){
 const dialog=document.querySelector('dialog.am91-osl-dialog[open]');
 const select=dialog?.querySelector('.am146-master-row select[name="osl_master_id"]');
 if(!select||attached.has(select))return;
 attached.add(select);
 const row=select.closest('.am146-master-row');
 if(!row)return;
 const status=document.createElement('span');
 status.className='am241-live-status';status.setAttribute('role','status');
 const reload=document.createElement('button');
 reload.type='button';reload.className='am241-refresh-master';reload.textContent='Reload List';
 row.append(status,reload);
 let request=0,lastLoaded=0;
 function normalized(v){return String(v||'').trim().replace(/\s+/g,' ').toLocaleLowerCase('en-IN');}
 async function load(){
   const seq=++request;
   reload.disabled=true;status.textContent='Loading OSL Master services…';
   try{
     const response=await fetch(url,{method:'GET',credentials:'same-origin',cache:'no-store',
       headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
     if(!response.ok)throw Error('HTTP '+response.status);
     const payload=await response.json();
     if(!payload.ok||!Array.isArray(payload.items))throw Error('Invalid OSL Master response');
     if(seq!==request||!select.isConnected)return;
     const previous=select.value;
     const oldDescription=dialog.querySelector('form.am91-osl-form [name="description"]')?.value||'';
     const placeholder=select.querySelector('option[value=""]')||new Option('Select OSL Master Item','');
     placeholder.textContent='Select OSL Master Item';
     select.replaceChildren(placeholder);
     const seen=new Set();
     for(const item of payload.items){
       const id=Number(item.id);
       if(!Number.isSafeInteger(id)||id<1||seen.has(id))continue;
       seen.add(id);
       const option=new Option(String(item.code||'OSL')+' — '+String(item.name||''),String(id));
       option.dataset.name=String(item.name||'');
       option.dataset.gst=String(item.gst_percent??0);
       select.add(option);
     }
     if(previous&&[...select.options].some(o=>o.value===previous)){
       select.value=previous;
     }else{
       const found=[...select.options].find(o=>o.value&&normalized(o.dataset.name)===normalized(oldDescription));
       if(found)select.value=found.value;
     }
     const count=select.options.length-1;
     if(count){
       status.textContent=count+' active OSL Master item'+(count===1?'':'s')+' loaded. Select the service.';
     }else{
       status.textContent='No active OSL Master item found. Open Configuration → OSL Master and create/activate a service.';
     }
     lastLoaded=Date.now();
   }catch(error){
     if(seq!==request||!select.isConnected)return;
     status.textContent='OSL Master list could not be loaded ('+String(error.message||'error')+'). Click Reload List.';
   }finally{
     if(seq===request)reload.disabled=false;
   }
 }
 reload.addEventListener('click',load);
 select.addEventListener('focus',()=>{if(Date.now()-lastLoaded>15000)load();});
 load();
}
const observer=new MutationObserver(install);
observer.observe(document.body,{childList:true,subtree:true});
install();
})();
