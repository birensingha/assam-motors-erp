<script id="am242-customer-payout-script">
/* AM242_CUSTOMER_PAYOUT_LEDGER
 * Customer Money Out from Universal Payment Voucher:
 * AGAINST = refund verified net customer credit, ADVANCE = receivable.
 * Other vendor-only form listeners must never turn Customer into Vendor.
 */
(function(){
'use strict';
function init(){
 const el=id=>document.getElementById(id);
 const form=el('payment-form'),category=el('am230-payee-type'),
       selected=el('am237-payee-selected'),customer=el('am231-customer-select'),
       search=el('am237-payee-search'),mode=el('am237-mode'),
       amount=el('payment-value'),date=form?.querySelector('[name="payment_date"]'),
       cash=form?.querySelector('[name="account_name"]'),
       notes=form?.querySelector('[name="notes"]'),
       submit=el('p133-save'),panel=el('am242-customer-panel'),
       creditEl=el('am242-credit'),notice=el('am242-customer-notice'),
       hidden=el('am242-customer-against'),source=el('am242-customer-balances'),
       preview=el('p133-preview-total'),status=el('am237-ledger-status');
 if([form,category,selected,customer,search,mode,amount,date,cash,notes,
     submit,panel,creditEl,notice,hidden,source].some(x=>!x))return;
 let rows=[];
 try{rows=JSON.parse(source.textContent||'[]');if(!Array.isArray(rows))rows=[];}catch(e){}
 const balances=new Map(rows.map(x=>[String(x.customer_id),Number(x.credit_balance)||0]));
 const money=n=>Number(n||0).toLocaleString('en-IN',{style:'currency',currency:'INR'});
 const isCustomer=()=>category.value==='CUSTOMER'&&!!customer.value&&
    selected.textContent.trim().toLowerCase().startsWith('customer');
 const credit=()=>Math.max(0,balances.get(String(customer.value))||0);
 function valid(){
   if(!isCustomer())return false;
   if(!['ADVANCE','AGAINST'].includes(mode.value))return false;
   const amt=Number(amount.value);
   if(!Number.isFinite(amt)||amt<=0||amt>99999999.99)return false;
   if(mode.value==='AGAINST'&&amt>credit()+0.009)return false;
   if(!cash.value.trim()||notes.value.trim().length<6||!date.value)return false;
   return true;
 }
 let syncing=false;
 function refresh(){
  if(syncing)return;
  syncing=true;
  try{
    const active=isCustomer();
    form.classList.toggle('am242-customer-active',active);
    panel.hidden=!active;
    hidden.disabled=!active;
    if(!active)return;
    customer.disabled=false;hidden.value=mode.value;
    const available=credit();
    creditEl.textContent='Verified Customer Ledger net credit available: '+money(available);
    if(mode.value==='AGAINST'){
      notice.textContent=available>0
       ?'Refund Against Credit: customer credit se refund hoga. Maximum '+money(available)+'. Amount se zyada refund blocked.'
       :'Customer Ledger mein available credit nahi hai. Agar company paise advance de rahi hai, Payment Against = Advance select karein.';
    }else if(mode.value==='ADVANCE'){
      notice.textContent='Advance Paid to Customer: Customer Receivable (debit) / Cash-Bank out. Ye Expense nahi hai. Recovery Customer Ledger mein track hogi.';
    }else{
      notice.textContent='Customer New Reference / On Account outgoing payments abhi supported nahi. Use Refund Against Customer Credit or Advance Paid to Customer.';
    }
    const ready=valid(),label=ready?'POST CUSTOMER PAYMENT VOUCHER':'COMPLETE CUSTOMER PAYMENT DETAILS';
    if(submit.disabled===ready)submit.disabled=!ready;
    if(submit.textContent!==label)submit.textContent=label;
    if(preview)preview.textContent=money(amount.value);
    if(status)status.textContent='Customer · '+(mode.value==='AGAINST'?'Refund Against Verified Credit':'Advance Receivable')+' · '+money(amount.value);
    const old=el('am231-posting-alert');if(old)old.hidden=true;
  }finally{syncing=false;}
 }
 const obs=new MutationObserver(()=>{if(isCustomer())refresh();});
 obs.observe(selected,{childList:true,characterData:true,subtree:true});
 obs.observe(submit,{attributes:true,attributeFilter:['disabled'],childList:true,characterData:true,subtree:true});
 // This document CAPTURE listener runs before old Vendor-only form submit
 // guards. Server-side CustomerPayoutVoucherService still validates everything.
 document.addEventListener('submit',e=>{
   if(e.target!==form||!isCustomer())return;
   hidden.disabled=false;hidden.value=mode.value;customer.disabled=false;
   if(!valid()){
     e.preventDefault();e.stopPropagation();
     refresh();
     notice.textContent+=' Required: valid Amount, Cash/Bank Account, Payment Note, and correct Payment Against.';
     panel.scrollIntoView({block:'nearest'});
     return;
   }
   e.stopPropagation();
 },true);
 for(const item of [amount,cash,notes,date])item.addEventListener('input',refresh);
 mode.addEventListener('change',refresh);
 customer.addEventListener('change',()=>queueMicrotask(refresh));
 category.addEventListener('change',()=>queueMicrotask(refresh));
 search.addEventListener('input',()=>queueMicrotask(refresh));
 refresh();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
</script>
