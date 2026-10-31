/* AM237 PAYMENT TO: one searchable party selector, one reference mode.
 * Reuses existing vendor ID, staff ID, customer ID and AM226 multi-bill allocation.
 * No fictitious staff/customer ledger entries, no duplicate financial inputs.
 */
(function(){
'use strict';
function init(){
 const $=id=>document.getElementById(id);
 const form=$('payment-form'),search=$('am237-payee-search'),results=$('am237-payee-results');
 const hiddenType=$('am230-payee-type'),vendor=$('payment-vendor');
 const staff=$('am231-staff-select'),customer=$('am231-customer-select');
 const feature=$('am234-feature'),purpose=$('am231-payment-purpose');
 const source=$('source-type'),multi=$('p133-multi-toggle'),multiVendor=$('p133-multi-vendor');
 const amount=$('payment-value'),key=$('source-key'),refInput=form?.querySelector('input[name="source_reference"]');
 const date=form?.querySelector('input[name="payment_date"]');
 const requestKey=form?.querySelector('input[name="request_key"]');
 const billArea=$('p133-multi-area'),amountSlot=$('am237-amount-slot'),billSlot=$('am237-bills-slot');
 const refSlot=$('am237-reference-slot'),dateSlot=$('am237-date-slot');
 const mode=$('am237-mode'),status=$('am237-ledger-status'),selected=$('am237-payee-selected');
 const save=$('p133-save');
 if([form,search,results,hiddenType,vendor,staff,customer,feature,purpose,source,multi,
     multiVendor,amount,key,refInput,date,requestKey,billArea,amountSlot,billSlot,
     refSlot,dateSlot,mode,status,selected,save].some(x=>!x))return;
 const dispatch=el=>el.dispatchEvent(new Event('change',{bubbles:true}));
 const oslIds=new Set(Array.from(($('am237-osl-vendor-ids')?.textContent||'').split(',')).filter(Boolean));
 const entries=[];
 function addOptions(select,kind,group){
   Array.from(select.options).filter(o=>o.value).forEach(o=>{
     const name=(o.dataset.name||o.textContent||'').trim();
     if(name)entries.push({kind,id:String(o.value),name,group,search:name.toLocaleLowerCase('en-IN')});
   });
 }
 // Restore canonical original vendor options from AM235 and prevent native filtering from limiting search.
 addOptions(vendor,'VENDOR','Vendor');
 addOptions(staff,'STAFF','Staff');
 addOptions(customer,'CUSTOMER','Customer');
 // OSL Vendor uses the SAME workshop_vendors master ID and ledger as Vendor.
 entries.filter(x=>x.kind==='VENDOR'&&oslIds.has(x.id))
        .forEach(x=>entries.push({...x,kind:'OSL_VENDOR',group:'OSL Vendor'}));
 let active=null,matching=[],focused=-1;
 function textName(e){return e.group+' · '+e.name;}
 function close(){results.hidden=true;focused=-1;search.setAttribute('aria-expanded','false');}
 function show(){
   const q=search.value.toLocaleLowerCase('en-IN').trim();
   results.replaceChildren();
   matching=entries.filter(x=>!q||x.search.includes(q)||x.group.toLocaleLowerCase().includes(q)).slice(0,70);
   focused=-1;
   if(!matching.length){
     const p=document.createElement('div');p.className='am237-empty';p.textContent='No matching person or vendor found';results.append(p);
   }
   matching.forEach((e,i)=>{
     const button=document.createElement('button');button.type='button';button.className='am237-result';
     button.id='am237-party-'+i;button.setAttribute('role','option');
     const tag=document.createElement('span');tag.className='am237-tag';tag.textContent=e.group;
     const name=document.createElement('span');name.textContent=e.name;
     button.append(tag,name);
     button.addEventListener('pointerdown',ev=>ev.preventDefault());
     button.addEventListener('click',()=>choose(e));
     results.append(button);
   });
   results.hidden=false;search.setAttribute('aria-expanded','true');
 }
 function highlight(i){
   if(!matching.length)return;
   focused=(i+matching.length)%matching.length;
   Array.from(results.querySelectorAll('.am237-result')).forEach((node,index)=>{
     node.classList.toggle('is-focused',index===focused);
     if(index===focused){node.scrollIntoView({block:'nearest'});search.setAttribute('aria-activedescendant',node.id);}
   });
 }
 function choose(e){
   active=e;search.value=textName(e);selected.textContent=textName(e);
   hiddenType.value=e.kind==='OSL_VENDOR'?'VENDOR':e.kind;
   // Keep legacy AM234 script on its existing verified Vendor branch only.
   feature.value=(e.kind==='VENDOR'||e.kind==='OSL_VENDOR')?'VENDOR':e.kind==='CUSTOMER'?'CUSTOMER':'STAFF_EXPENSE';
   dispatch(feature);
   hiddenType.value=e.kind==='OSL_VENDOR'?'VENDOR':e.kind;
   dispatch(hiddenType);
   if(e.kind==='VENDOR'||e.kind==='OSL_VENDOR'){
     // AM230 must receive the actual vendor_id and never a free-text payee.
     // The original select is not recreated; only the matching canonical option is chosen.
     vendor.value=e.id;
     dispatch(vendor);
     const payee=$('payment-payee');if(payee)payee.value=e.name;
     staff.value='';customer.value='';
   }else if(e.kind==='STAFF'){
     staff.value=e.id;dispatch(staff);
     customer.value='';vendor.value='';
   }else{
     customer.value=e.id;dispatch(customer);
     staff.value='';vendor.value='';
   }
   close();
   syncMode();
 }
 function referenceNumber(){
   const uuid=String(requestKey.value||'').replace(/[^0-9a-f]/gi,'').toUpperCase();
   const day=String(date.value||'').replace(/-/g,'')||'00000000';
   const prefix=mode.value==='ADVANCE'?'ADV-':'NR-';
   return prefix+day+'-'+uuid.slice(0,12);
 }
 function setModeSource(){
   if(!active)return;
   const vendorPayee=['VENDOR','OSL_VENDOR'].includes(active.kind);
   // AM236 routes both New Reference and Advance through validated Vendor ledgers.
   if(vendorPayee){
     purpose.value=mode.value==='AGAINST'?'REFERENCE':mode.value;
     dispatch(purpose);
     source.value=mode.value==='AGAINST'?'PURCHASE_INVOICE':mode.value;
     dispatch(source);
   }
 }
 function relocate(){
   // AM225 may move the original amount on multi-toggle change; move it back.
   const label=amount.closest('label');
   if(label&&label.parentElement!==amountSlot)amountSlot.append(label);
   const refLabel=refInput.closest('label');
   if(refLabel&&refLabel.parentElement!==refSlot)refSlot.append(refLabel);
   const dateLabel=date.closest('label');
   if(dateLabel&&dateLabel.parentElement!==dateSlot)dateSlot.append(dateLabel);
   if(billArea.parentElement!==billSlot)billSlot.append(billArea);
 }
 function syncMode(){
   const vendorPayee=active&&['VENDOR','OSL_VENDOR'].includes(active.kind);
   const against=mode.value==='AGAINST';
   const generated=['NEW_REFERENCE','ADVANCE'].includes(mode.value);
   const isReady=!!active&&vendorPayee;
   form.classList.toggle('am237-against',against&&!!vendorPayee);
   form.classList.toggle('am237-ref-number',generated);
   form.classList.toggle('am237-no-ref',mode.value==='ON_ACCOUNT');
   setModeSource();
   if(vendorPayee){
     if(against){
       // Existing AM226 multi-bill engine validates one payee and amount=sum(allocations).
       if(!multi.checked){multi.checked=true;dispatch(multi);}
       // Source type is PURCHASE_INVOICE parent for Parts+OSL allocations.
       if(source.value!=='PURCHASE_INVOICE'){source.value='PURCHASE_INVOICE';dispatch(source);}
       multiVendor.value=active.id;dispatch(multiVendor);
     }else{
       if(multi.checked){multi.checked=false;dispatch(multi);}
       if(source.value!==mode.value){source.value=mode.value;dispatch(source);}
       key.value='';dispatch(key);
     }
   }else{
     if(multi.checked){multi.checked=false;dispatch(multi);}
   }
   relocate();
   if(generated){
     refInput.value=referenceNumber();refInput.readOnly=true;refInput.required=true;
     refInput.placeholder='Automatically generated';
   }else{
     refInput.value='';refInput.readOnly=true;refInput.required=false;
   }
   refSlot.hidden=!generated;
   billSlot.hidden=!against||!vendorPayee;
   if(!active){status.textContent='Select Vendor, OSL Vendor, Staff or Customer from Payment To.';}
   else if(!vendorPayee){
     status.textContent=active.group+' selected. Its payment ledger/bill settlement is not active yet. Posting is locked until audited integration.';
   }else if(against){
     status.textContent='Select one or multiple outstanding Parts / OSL bills for '+active.name+'. Amount and allocated total must match.';
   }else if(mode.value==='ON_ACCOUNT'){
     status.textContent='On Account: payment goes to this Vendor ledger without any bill/reference allocation.';
   }else if(mode.value==='ADVANCE'){
     status.textContent='Advance reference generated automatically. Future bill adjustment is a separate audited step.';
   }else{
     status.textContent='New Reference generated automatically. Existing outstanding bills are not selected.';
   }
   const blocked=!isReady;
   form.dataset.am237Blocked=blocked?'1':'0';
   if(blocked){save.disabled=true;save.textContent='POSTING LOCKED · '+(active?.group||'SELECT PAYEE');}
   else{amount.dispatchEvent(new Event('input',{bubbles:true}));}
 }
 search.addEventListener('focus',show);
 search.addEventListener('input',()=>{
   // Prevent stale party identity when typed text changes.
   active=null;selected.textContent='No payee selected';
   vendor.value='';staff.value='';customer.value='';
   form.dataset.am237Blocked='1';save.disabled=true;show();
 });
 search.addEventListener('keydown',e=>{
   if(e.key==='Escape'){close();return;}
   if(e.key==='ArrowDown'){e.preventDefault();if(results.hidden)show();highlight(focused+1);}
   if(e.key==='ArrowUp'){e.preventDefault();if(results.hidden)show();highlight(focused<0?matching.length-1:focused-1);}
   if(e.key==='Enter'&&!results.hidden&&matching.length){e.preventDefault();choose(matching[focused>=0?focused:0]);}
 });
 document.addEventListener('pointerdown',e=>{if(!results.contains(e.target)&&e.target!==search)close();});
 mode.addEventListener('change',syncMode);
 // Prevent accidental post of unsupported payees; server AM230 also guards.
 form.addEventListener('submit',e=>{
   if(!active||!['VENDOR','OSL_VENDOR'].includes(active.kind)||form.dataset.am237Blocked==='1'){
     e.preventDefault();e.stopImmediatePropagation();status.textContent='Select a supported Vendor/OSL Vendor. Staff/Customer ledger posting is not yet active.';
     search.focus();return;
   }
   if(vendor.value!==active.id){e.preventDefault();e.stopImmediatePropagation();status.textContent='Vendor does not match selected Payment To.';search.focus();return;}
   if(mode.value==='AGAINST'&&!multi.checked){e.preventDefault();e.stopImmediatePropagation();status.textContent='Choose at least one outstanding bill.';return;}
   if(mode.value==='ON_ACCOUNT'&&refInput.value){e.preventDefault();e.stopImmediatePropagation();status.textContent='On Account must not use a bill reference.';}
 },true);
 relocate();
 // Default: no hidden feature choice, no accidental vendor posting.
 form.dataset.am237Blocked='1';save.disabled=true;save.textContent='SELECT PAYMENT TO';
 refSlot.hidden=true;billSlot.hidden=true;
 selected.textContent='No payee selected';
 status.textContent='Select a payee to view available payment references.';
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
