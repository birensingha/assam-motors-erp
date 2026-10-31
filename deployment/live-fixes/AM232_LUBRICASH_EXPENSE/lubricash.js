/* AM232_LUBRICASH_EXPENSE_UI: Staff Oil-Sale Coupon expense tracking.
   Only new expense category context, no financial calculations changed. */
(function(){
'use strict';
function setup(){
 var form=document.getElementById('expense-form');
 var cat=form&&form.querySelector('select[name="expense_category"]');
 var against=document.getElementById('expense-against');
 var staffField=document.getElementById('am232-staff-field');
 var staff=document.getElementById('am232-staff-id');
 var staffSearch=document.getElementById('am232-staff-search');
 var job=document.getElementById('expense-job');
 var jobSearch=document.getElementById('am232-job-search');
 var guide=document.getElementById('am232-coupon-guidance');
 var label=document.getElementById('am232-description-label');
 var desc=form&&form.querySelector('textarea[name="description"]');
 var payee=document.getElementById('expense-payee');
 if(!form||!cat||!against||!staffField||!staff||!staffSearch||!job||!jobSearch||!guide||!label||!desc)return;
 var staffItems=Array.from(staff.options).map(o=>o.cloneNode(true));
 var jobItems=Array.from(job.options).map(o=>o.cloneNode(true));
 function filter(source,items,input){
    var q=input.value.toLocaleLowerCase().trim();
    var prior=source.value;
    var matches=items.filter(function(o){
      return !o.value||o.value===prior||o.textContent.toLocaleLowerCase().includes(q);
    });
    source.replaceChildren.apply(source,matches.map(o=>o.cloneNode(true)));
    if(Array.from(source.options).some(o=>o.value===prior))source.value=prior;
 }
 staffSearch.addEventListener('input',function(){filter(staff,staffItems,staffSearch);});
 jobSearch.addEventListener('input',function(){filter(job,jobItems,jobSearch);});
 jobSearch.addEventListener('keydown',function(e){
    if(e.key==='Enter'){
      e.preventDefault();
      var first=Array.from(job.options).find(o=>o.value&&o.textContent.toLowerCase().includes(jobSearch.value.toLowerCase()));
      if(first){job.value=first.value;job.dispatchEvent(new Event('change',{bubbles:true}));}
    }
 });
 staffSearch.addEventListener('keydown',function(e){
    if(e.key==='Enter'){
      e.preventDefault();
      var first=Array.from(staff.options).find(o=>o.value&&o.textContent.toLowerCase().includes(staffSearch.value.toLowerCase()));
      if(first)staff.value=first.value;
    }
 });
 function isLub(){return cat.value==='STAFF_LUBRICASH';}
 function refresh(){
  var enabled=isLub();
  staffField.hidden=!enabled;
  staffField.style.display=enabled?'flex':'none';
  guide.hidden=!enabled;
  staff.disabled=!enabled;
  staff.required=enabled;
  jobSearch.hidden=!enabled;
  if(enabled){
    if(against.value!=='JOB'){
      against.value='JOB';
      against.dispatchEvent(new Event('change',{bubbles:true}));
    }
    desc.placeholder='Coupon company (e.g. oil company), coupon no, oil brand/grade, quantity, sale details';
    label.firstChild.nodeValue='Description / Narration (Coupon Company & Oil Details) *';
    if(payee)payee.placeholder='Actual payee / person or company receiving the expense';
  }else{
    desc.placeholder='What was this expense for?';
    label.firstChild.nodeValue='Description / Narration *';
    staff.required=false;
    if(payee)payee.placeholder='Person / Shop / Service Provider';
  }
 }
 cat.addEventListener('change',refresh);
 against.addEventListener('change',function(){
  if(isLub()&&against.value!=='JOB'){
   against.value='JOB';
   against.dispatchEvent(new Event('change',{bubbles:true}));
  }
 });
 form.addEventListener('submit',function(e){
   if(!isLub())return;
   if(against.value!=='JOB'||!job.value||!staff.value){
    e.preventDefault();e.stopImmediatePropagation();
    guide.textContent='Staff Lubricash requires a valid Staff Name and Job Card / Vehicle before Save.';
    if(!staff.value)staffSearch.focus();
    else jobSearch.focus();
    return;
   }
   if(!desc.value.trim()){
     e.preventDefault();e.stopImmediatePropagation();
     desc.setCustomValidity('Enter coupon company and oil-sale details in Narration.');
     desc.reportValidity();
   }
 },true);
 desc.addEventListener('input',function(){desc.setCustomValidity('');});
 refresh();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',setup,{once:true});
else setup();
})();
