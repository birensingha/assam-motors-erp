/* AM231 Recipient-specific accounting voucher UI.
   Preserve the existing posted source_type, vendor_id and financial services.
   Non-Vendor payments are visually distinct and fail-closed until ledgers exist. */
(function(){
'use strict';
function init(){
 const $=id=>document.getElementById(id);
 const form=$('payment-form'), category=$('am230-payee-type');
 const purpose=$('am231-payment-purpose'), source=$('source-type');
 const ref=$('am231-reference-choices'), vendorSlot=$('am230-vendor-slot');
 const staffArea=$('am231-staff-area'),customerArea=$('am231-customer-area');
 const staffSelect=$('am231-staff-select'),customerSelect=$('am231-customer-select');
 const vendor=$('payment-vendor'), payee=$('payment-payee');
 const search=$('am218-source-search'),multi=$('p133-multi-toggle');
 const amount=$('payment-value'), save=$('p133-save'), notice=$('am230-payee-info');
 const note=$('am231-context-help'), preview=$('am231-recipient-preview');
 const sourceKey=$('source-key');
 if([form,category,purpose,source,ref,vendorSlot,staffArea,customerArea,
     staffSelect,customerSelect,vendor,payee,amount,save,notice,note,preview].some(v=>!v))return;
 const oldSourceLabel=source.closest('label');
 if(!oldSourceLabel)return;
 // Move the original backend source_type SELECT rather than duplicating it.
 const oldSourceText=Array.from(oldSourceLabel.childNodes).find(n=>n.nodeType===3);
 if(oldSourceText)oldSourceText.nodeValue='Reference Type *\\n';
 ref.appendChild(oldSourceLabel);
 let previousCategory=category.value;
 let lastVendorSource='PURCHASE_INVOICE';
 let lastVendorPurpose='REFERENCE';
 const vendorSearch=$('am230-payee-box')?.querySelector('input[aria-label="Filter Vendor Master"]');
 const restoreReference=()=>['PURCHASE_INVOICE','OSL_JOB','EXPENSE_VOUCHER'].includes(source.value);
 const dropdowns=[
  {type:'STAFF',select:staffSelect,search:$('am231-staff-search'),area:staffArea},
  {type:'CUSTOMER',select:customerSelect,search:$('am231-customer-search'),area:customerArea}
 ];
 dropdowns.forEach(function(d){
  if(!d.search)return;
  const items=Array.from(d.select.options).map(o=>o.cloneNode(true));
  d.search.addEventListener('input',function(){
    const q=d.search.value.toLocaleLowerCase().trim(),old=d.select.value;
    const matching=items.filter(o=>!o.value||o.value===old||o.textContent.toLocaleLowerCase().includes(q));
    d.select.replaceChildren.apply(d.select,matching.map(o=>o.cloneNode(true)));
    if(Array.from(d.select.options).some(o=>o.value===old))d.select.value=old;
  });
  d.select.addEventListener('change',syncIdentity);
 });
 function selectedName(s){
  const o=s.options[s.selectedIndex];return o&&o.value ? (o.dataset.name||o.textContent.trim()):'';
 }
 function syncIdentity(){
  const who=category.value;
  const name=who==='VENDOR' ? selectedName(vendor)
   :who==='STAFF' ? selectedName(staffSelect):selectedName(customerSelect);
  if(who!=='VENDOR')payee.value=name;
  preview.textContent=who+' · '+(name||'Select '+(who==='VENDOR'?'Vendor':who)+' Name');
  if(who==='VENDOR'){
    if(name && !payee.value)payee.value=name;
  }
 }
 vendor.addEventListener('change',syncIdentity);
 function dispatch(el){el.dispatchEvent(new Event('change',{bubbles:true}));}
 function switchMode(){
  const who=category.value,isVendor=who==='VENDOR';
  form.classList.toggle('am231-non-vendor',!isVendor);
  form.classList.toggle('am231-category-vendor',isVendor);
  vendorSlot.hidden=!isVendor;
  vendor.disabled=!isVendor;
  staffArea.hidden=who!=='STAFF';staffSelect.disabled=who!=='STAFF';
  customerArea.hidden=who!=='CUSTOMER';customerSelect.disabled=who!=='CUSTOMER';
  if(!isVendor){
    if(purpose.value==='REFERENCE')purpose.value='ADVANCE';
    if(search){search.value='';search.dispatchEvent(new Event('input',{bubbles:true}));}
    if(multi&&multi.checked){multi.checked=false;dispatch(multi);}
    if(source.value!=='ADVANCE'&&source.value!=='ON_ACCOUNT'){
      source.value='ADVANCE';dispatch(source);
    }
    if(sourceKey){sourceKey.value='';dispatch(sourceKey);}
    notice.textContent=who+' Name must be selected from '+who+' Master. This payee category has no linked audited disbursement ledger yet; posting is disabled.';
    note.textContent=who+' · Select Name and Advance / On Account to prepare the voucher. Against Reference and actual posting require approved '+who+' accounting ledger integration.';
    save.disabled=true;
    save.textContent='POSTING LOCKED · '+who+' LEDGER PENDING';
  }else{
    notice.textContent='Select Vendor Master once. Changing Payment Against does not remove the selected vendor.';
    note.textContent='Vendor · Against Reference pays outstanding Parts / OSL bills; Advance and On Account remain unallocated pending adjustment.';
    // Recompute original AM226 validity; never force-enable invalid allocations.
    amount.dispatchEvent(new Event('input',{bubbles:true}));
    if(purpose.value==='REFERENCE'){
      if(!restoreReference()){
        source.value=lastVendorSource==='OSL_JOB'?'OSL_JOB':'PURCHASE_INVOICE';
        dispatch(source);
      }
    }else{
      if(source.value!==(purpose.value==='ON_ACCOUNT'?'ON_ACCOUNT':'ADVANCE')){
        source.value=purpose.value==='ON_ACCOUNT'?'ON_ACCOUNT':'ADVANCE';dispatch(source);
      }
    }
  }
  // Never hide original amount input in Staff / Customer contexts.
  // AM226 moves it into a vendor-only allocation slot when multi is checked.
  if(!isVendor){
    const original=$('am225-amount-first-slot');
    const details=Array.from(document.querySelectorAll('.p133-group')).find(el=>
      el.querySelector('#p133-payment-mode')&&el.querySelector('.p133-grid'));
    const grid=details&&details.querySelector('.p133-grid');
    const amountLabel=amount.closest('label');
    if(grid&&amountLabel&&original&&original.contains(amountLabel)){
      grid.appendChild(amountLabel);
      original.hidden=true;
    }
  }
  ref.hidden=!isVendor||purpose.value!=='REFERENCE';
  const expense=source.querySelector('option[value="EXPENSE_VOUCHER"]');
  if(expense && source.value==='EXPENSE_VOUCHER' && isVendor){
    // Expenses -> +Payment deep link is still a valid financial reference.
    purpose.value='REFERENCE';ref.hidden=false;
  }
  const choice=source.querySelector('option[value="PURCHASE_INVOICE"]');
  if(choice)choice.textContent='Parts Purchase Bill';
  const osl=source.querySelector('option[value="OSL_JOB"]');
  if(osl)osl.textContent='OSL Supplier Purchase';
  syncIdentity();
 }
 function switchPurpose(){
   const isVendor=category.value==='VENDOR';
   if(isVendor){
     if(purpose.value==='REFERENCE'){
       source.value=lastVendorSource==='OSL_JOB'?'OSL_JOB':'PURCHASE_INVOICE';
     }else source.value=purpose.value==='ON_ACCOUNT'?'ON_ACCOUNT':'ADVANCE';
     dispatch(source);
   }else{
     if(purpose.value==='REFERENCE')purpose.value='ADVANCE';
     source.value=purpose.value==='ON_ACCOUNT'?'ON_ACCOUNT':'ADVANCE';
     dispatch(source);
   }
   switchMode();
 }
 source.addEventListener('change',function(){
   if(restoreReference()){lastVendorSource=source.value;lastVendorPurpose='REFERENCE';}
 });
 category.addEventListener('change',function(){
   if(category.value!==previousCategory){
     amount.value='';
     payee.value='';
     previousCategory=category.value;
   }
   if(category.value!=='VENDOR'){
     if(purpose.value==='REFERENCE')purpose.value='ADVANCE';
   }else if(lastVendorPurpose==='REFERENCE'){
     purpose.value='REFERENCE';
   }
   switchMode();
 });
 purpose.addEventListener('change',function(){
   if(category.value==='VENDOR')lastVendorPurpose=purpose.value;
   switchPurpose();
 });
 // AM230 attaches its own first submit handler; this captures unsupported
 // payee submissions before other handlers. A second server guard remains.
 form.addEventListener('submit',function(e){
   if(category.value!=='VENDOR'){
     e.preventDefault();e.stopImmediatePropagation();switchMode();
     return;
   }
   if(purpose.value==='REFERENCE'&&sourceKey&&!multi?.checked&&!sourceKey.value){
     // The existing service will validate; preserve selected bill flow.
     return;
   }
   if(['ADVANCE','ON_ACCOUNT'].includes(source.value)&&!vendor.value){
     e.preventDefault();e.stopImmediatePropagation();
     note.textContent='Select Vendor Name before posting this Advance / On Account payment.';
     vendor.focus();
   }
 },true);
 // Keep Expense payment preselected on entry from Expense's own +Payment link.
 const preset=window.AM_PAYMENT_PRESET||{};
 if(preset.source==='EXPENSE_VOUCHER'&&source.querySelector('option[value="EXPENSE_VOUCHER"]')){
   purpose.value='REFERENCE';lastVendorSource='EXPENSE_VOUCHER';
 }else if(source.value==='ADVANCE'||source.value==='ON_ACCOUNT'){
   purpose.value=source.value;
 }else purpose.value='REFERENCE';
 switchMode();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
