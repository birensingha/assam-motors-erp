/* AM233 Staff Lubricash Coupon staff+JC association in Expense form.
   These are UX guards only. Controller/service validates linked IDs again. */
(function(){
'use strict';
function initialise(){
 const form=document.getElementById('expense-form');
 if(!form||form.dataset.am233Ready==='1')return;
 const category=form.querySelector('select[name="expense_category"]');
 const against=document.getElementById('expense-against');
 const job=document.getElementById('expense-job');
 const jobField=document.getElementById('job-field');
 const vendor=document.getElementById('expense-vendor');
 const payee=document.getElementById('expense-payee');
 const pane=document.getElementById('am233-lubricash');
 const staff=document.getElementById('am233-staff-id');
 const staffSearch=document.getElementById('am233-staff-search');
 const company=document.getElementById('am233-company');
 const couponNo=form.querySelector('input[name="coupon_no"]');
 const desc=form.querySelector('textarea[name="description"]');
 if([category,against,job,jobField,vendor,payee,pane,staff,staffSearch,company,couponNo,desc].some(x=>!x))return;
 form.dataset.am233Ready='1';
 const allStaff=Array.from(staff.options).map(o=>o.cloneNode(true));
 const allJob=Array.from(job.options).map(o=>o.cloneNode(true));
 const jobSearch=document.createElement('input');
 jobSearch.id='am233-jc-search';jobSearch.type='search';
 jobSearch.placeholder='Search JC No. / Vehicle Registration';
 jobSearch.autocomplete='off';
 jobSearch.setAttribute('aria-label','Search linked Job Card / Vehicle');
 jobSearch.className='am233-filter';
 jobField.insertBefore(jobSearch,job);
 let preCouponPayee=payee.value;
 function filterSelect(select,all,needle){
   const current=select.value;
   const q=needle.toLocaleLowerCase().trim();
   const filtered=all.filter(o=>!o.value||o.value===current||o.textContent.toLocaleLowerCase().includes(q));
   select.replaceChildren.apply(select,filtered.map(o=>o.cloneNode(true)));
   if(Array.from(select.options).some(o=>o.value===current))select.value=current;
 }
 staffSearch.addEventListener('input',()=>filterSelect(staff,allStaff,staffSearch.value));
 jobSearch.addEventListener('input',()=>filterSelect(job,allJob,jobSearch.value));
 function staffName(){
   const opt=staff.options[staff.selectedIndex];
   return (opt&&opt.dataset.name)||'';
 }
 function syncEmployee(){
   if(category.value==='LUBRICASH_COUPON')payee.value=staffName();
 }
 staff.addEventListener('change',syncEmployee);
 function changed(el){el.dispatchEvent(new Event('change',{bubbles:true}));}
 function sync(){
   const isCoupon=category.value==='LUBRICASH_COUPON';
   pane.hidden=!isCoupon;
   staff.disabled=!isCoupon;
   staff.required=isCoupon;
   company.disabled=!isCoupon;
   company.required=isCoupon;
   couponNo.disabled=!isCoupon;
   staffSearch.disabled=!isCoupon;
   jobSearch.hidden=!isCoupon;
   jobSearch.disabled=!isCoupon;
   vendor.disabled=isCoupon;
   if(isCoupon){
     if(against.value!=='JOB'){against.value='JOB';changed(against);}
     against.disabled=true;
     // Disabled controls aren't posted; hidden mirror below guarantees JOB.
     document.getElementById('am233-expense-against-hidden').disabled=false;
     if(vendor.value)vendor.value='';
     payee.readOnly=true;payee.placeholder='Automatically selected Staff Name';
     syncEmployee();
     job.required=true;
     const serviceDesc=desc.placeholder||'';
     desc.placeholder='Narration: Oil company, grade, litres, coupon use, staff & vehicle remarks';
   }else{
     against.disabled=false;
     document.getElementById('am233-expense-against-hidden').disabled=true;
     payee.readOnly=false;
     payee.placeholder='Person / Shop / Service Provider';
     if(preCouponPayee && !payee.value)payee.value=preCouponPayee;
   }
 }
 category.addEventListener('change',sync);
 // A hidden mirror is used only for LUBRICASH because the category fixes JOB.
 const hidden=document.createElement('input');
 hidden.type='hidden';hidden.id='am233-expense-against-hidden';
 hidden.name='expense_against';hidden.value='JOB';hidden.disabled=true;
 against.after(hidden);
 // Ensure no stale vendor financial account can be associated with coupon.
 form.addEventListener('submit',function(e){
   if(category.value!=='LUBRICASH_COUPON')return;
   syncEmployee();
   if(!staff.value||!job.value||!company.value.trim()){
     e.preventDefault();
     company.setCustomValidity(company.value.trim()?'':'Enter the coupon / oil company.');
     if(!staff.value)staff.focus();
     else if(!job.value)job.focus();
     else company.reportValidity();
   }else company.setCustomValidity('');
 },true);
 company.addEventListener('input',()=>company.setCustomValidity(''));
 sync();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initialise,{once:true});
else initialise();
})();