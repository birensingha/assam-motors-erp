/* AM234 universal payment feature selector.
   UI composition only. Unsupported accounting routes stay fail-closed.
   Do not modify vendor allocations, source_id, tender or ledger payloads. */
(function(){
  'use strict';
  function init(){
    const el=id=>document.getElementById(id);
    const feature=el('am234-feature'), form=el('payment-form');
    const category=el('am230-payee-type'), purpose=el('am231-payment-purpose');
    const source=el('source-type'), save=el('p133-save');
    const banner=el('am234-status'), title=el('am234-feature-title');
    if(!feature||!form||!category||!purpose||!source||!save||!banner||!title)return;
    const options={
      VENDOR:{title:'Vendor Payment',category:'VENDOR',source:'PURCHASE_INVOICE',active:true,
        note:'Existing vendor-ledger payment: one or multiple purchase bills, advance or on-account.'},
      CUSTOMER:{title:'Customer Refund / Payment',category:'CUSTOMER',source:'ADVANCE',active:false,
        note:'Requires approved refund or credit-note linkage and customer posting/reversal ledger. Currently locked.'},
      SALARY_ADVANCE:{title:'Staff Salary Advance',category:'STAFF',source:'ADVANCE',active:false,
        note:'Requires payroll recoverable-advance journal, staff approval and recovery tracking. Currently locked.'},
      STAFF_EXPENSE:{title:'Staff Expense Reimbursement',category:'STAFF',source:'EXPENSE_VOUCHER',active:false,
        note:'Tour, petrol, lunch and Lubricash must reference an approved staff expense. Use the existing Expense Detail linked payment until unified posting is verified.'},
      DIRECT_OSL:{title:'Direct OSL Payment',category:'VENDOR',source:'OSL_JOB',active:false,
        note:'Direct JC OSL settlement requires cost/payable document checks to prevent duplicate payment. Currently locked.'},
      OSL_VENDOR:{title:'OSL Vendor Payment',category:'VENDOR',source:'OSL_JOB',active:true,
        note:'Existing OSL vendor outstanding reference can be paid using the supported vendor-ledger source.'}
    };
    const dispatch=element=>element.dispatchEvent(new Event('change',{bubbles:true}));
    function syncFeature(resetSource){
      const selected=options[feature.value]||options.VENDOR;
      title.textContent=selected.title;
      if(resetSource){
        category.value=selected.category; dispatch(category);
        if(selected.active){
          purpose.value='REFERENCE'; dispatch(purpose);
          if(Array.from(source.options).some(o=>o.value===selected.source)){
            source.value=selected.source;dispatch(source);
          }
        }
      }
      const sourceAllowed=feature.value!=='OSL_VENDOR'||source.value==='OSL_JOB';
      const enabled=selected.active&&sourceAllowed&&category.value==='VENDOR';
      form.dataset.am234Locked=enabled?'0':'1';
      banner.textContent=enabled?selected.note:
        (selected.active?'Please select the OSL vendor outstanding reference before posting.':selected.note);
      banner.dataset.status=enabled?'ready':'blocked';
      banner.setAttribute('role','status');
      // The original AM226/AM231 validator controls vendor submit readiness.
      // Never force-enable. Only lock unsupported categories.
      if(!enabled){save.disabled=true;save.textContent='POSTING LOCKED — ACCOUNTING LINK PENDING';}
      else{
        save.textContent='POST PAYMENT VOUCHER';
        const amount=el('payment-value');
        if(amount)amount.dispatchEvent(new Event('input',{bubbles:true}));
      }
    }
    feature.addEventListener('change',()=>syncFeature(true));
    source.addEventListener('change',()=>syncFeature(false));
    form.addEventListener('submit',event=>{
      const chosen=options[feature.value]||options.VENDOR;
      if(!chosen.active||form.dataset.am234Locked!=='0'){
        event.preventDefault();event.stopImmediatePropagation();
        syncFeature(false);feature.focus();
      }
    },true);
    // Existing AM230/231 document-ready handlers set their own values.
    // Default to already-supported vendor flow; retain their validation.
    syncFeature(false);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
  else init();
})();
