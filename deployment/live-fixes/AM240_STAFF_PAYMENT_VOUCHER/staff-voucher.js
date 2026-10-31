/* AM240: Staff salary/expense payout is entered from THE universal Payment Voucher.
 * Existing Vendor/OSL payment logic is untouched. Staff finance entries are posted
 * via the server's linked, auditable journal service (never a Vendor entry).
 */
(function(){
'use strict';
function init(){
 const el=id=>document.getElementById(id);
 const form=el('payment-form'),category=el('am230-payee-type'),staff=el('am231-staff-select');
 const search=el('am237-payee-search'),picked=el('am237-payee-selected'),mode=el('am237-mode');
 const panel=el('am240-staff-panel'),kind=el('am240-kind'),month=el('am240-month');
 const expense=el('am240-expense'),expenseBox=el('am240-expense-box');
 const monthBox=el('am240-month-box'),balance=el('am240-balance'),notice=el('am240-staff-notice');
 const selectedKind=el('am240-kind-value'),against=el('am240-against-value');
 const submit=el('p133-save'),amount=el('payment-value'),ref=el('am240-references-data');
 const account=form?.querySelector('input[name="account_name"]');
 const notes=form?.querySelector('textarea[name="notes"]');
 const preview=el('p133-preview-total');
 if([form,category,staff,search,picked,mode,panel,kind,month,expense,expenseBox,
     monthBox,balance,notice,selectedKind,against,submit,amount,ref,account,notes].some(x=>!x))return;
 let data={payables:[],expenses:[]};
 try{data=JSON.parse(ref.textContent||'{}')||data;}catch(e){}
 const payables=Array.isArray(data.payables)?data.payables:[];
 const expenses=Array.isArray(data.expenses)?data.expenses:[];
 const cash=v=>Number(v||0).toLocaleString('en-IN',{style:'currency',currency:'INR'});
 const isStaff=()=>category.value==='STAFF'&&!!staff.value&&
   picked.textContent.trim().toLowerCase().startsWith('staff');
 function due(){
  return payables.filter(x=>String(x.staff_id)===String(staff.value)&&x.salary_month===month.value)
    .reduce((s,x)=>s+Number(x.payable||0),0);
 }
 function payeeExpenses(){
  return expenses.filter(x=>String(x.staff_id)===String(staff.value)&&Number(x.outstanding)>0.009);
 }
 function renderExpenses(){
  const old=expense.value;
  expense.replaceChildren();
  const empty=document.createElement('option');empty.value='';empty.textContent='Select approved Staff Expense due';
  expense.append(empty);
  for(const item of payeeExpenses()){
    const option=document.createElement('option');option.value=String(item.id);
    option.textContent=item.expense_no+' · '+cash(item.outstanding)+' due'+(item.job_no?' · '+item.job_no:'');
    option.dataset.due=String(item.outstanding);
    expense.append(option);
  }
  if(Array.from(expense.options).some(o=>o.value===old))expense.value=old;
 }
 function valid(){
  if(!isStaff())return false;
  if(mode.value!=='ADVANCE'&&mode.value!=='AGAINST')return false;
  const type=mode.value==='ADVANCE'?'SALARY_ADVANCE':kind.value;
  if(!['SALARY_ADVANCE','SALARY_PAYMENT','EXPENSE_REIMBURSEMENT'].includes(type))return false;
  if(!Number.isFinite(Number(amount.value))||Number(amount.value)<=0)return false;
  if(!account.value.trim()||notes.value.trim().length<6)return false;
  if(type==='EXPENSE_REIMBURSEMENT'){
    const option=expense.options[expense.selectedIndex];
    return !!expense.value&&Number(amount.value)<=Number(option?.dataset.due||0)+0.009;
  }
  if(!month.value)return false;
  if(type==='SALARY_PAYMENT' && Number(amount.value)>due()+0.009)return false;
  return true;
 }
 let updating=false;
 function refresh(){
  if(updating)return;
  updating=true;
  try{
   const active=isStaff();
   panel.hidden=!active;
   form.classList.toggle('am240-staff-active',active);
   if(!active){return;}
   renderPreview();
   const a=mode.value==='ADVANCE',againstRef=mode.value==='AGAINST';
   selectedKind.value=a?'SALARY_ADVANCE':kind.value;
   against.value=mode.value;
   const effective=selectedKind.value;
   el('am240-kind-box').hidden=!againstRef;
   const expenseActive=againstRef&&effective==='EXPENSE_REIMBURSEMENT';
   expenseBox.hidden=!expenseActive;monthBox.hidden=expenseActive||(!a&&!againstRef);
   month.required=!expenseActive;expense.required=expenseActive;expense.disabled=!expenseActive;
   const outstanding=due();
   if(mode.value==='NEW_REFERENCE'||mode.value==='ON_ACCOUNT'){
     notice.textContent='Staff ke liye abhi Salary Advance aur verified Against Reference payables supported hain. New Reference / On Account Staff posting abhi enabled nahi hai.';
   } else if(expenseActive){
     const option=expense.options[expense.selectedIndex];
     notice.textContent=expense.value?'Selected expense payable '+cash(option?.dataset.due)+' (partial payment allowed).':'Choose an existing approved Staff Expense; no duplicate expense will be posted.';
   } else if(a){
     notice.textContent='Salary Advance → Staff Advance Asset Dr / Cash-Bank Cr. Recovery will be tracked in Staff Ledger.';
   } else{
     notice.textContent=outstanding>0?'Salary payable for '+month.value+' = '+cash(outstanding)+'. Salary payable cannot be overpaid.':'No posted Salary Payable for '+month.value+'. Use another month, or post a salary provision before paying.';
   }
   balance.textContent=expenseActive?'Existing Staff Expense payable: '+
      cash(Number(expense.options[expense.selectedIndex]?.dataset.due||0)):
      'Salary payable ('+month.value+'): '+cash(outstanding);
   const ready=valid();
   if(submit.disabled===ready)submit.disabled=!ready;
   const button=ready?'POST STAFF PAYMENT VOUCHER':'COMPLETE STAFF PAYMENT DETAILS';
   if(submit.textContent!==button)submit.textContent=button;
   if(preview)preview.textContent=cash(amount.value);
   const old=el('am231-posting-alert');if(old)old.hidden=true;
  }finally{updating=false;}
 }
 function renderPreview(){
   if(!isStaff())return;
   const context=el('am237-ledger-status');
   if(context)context.textContent='Staff payment will post once through the existing balanced Staff Ledger. This is one voucher, not a duplicate expense.';
 }
 // Staff is selected by AM237 after category/feature selectors have run.
 // Observe legacy disabled/text updates and re-evaluate without busy waiting.
 const observer=new MutationObserver(()=>{if(isStaff())refresh();});
 observer.observe(submit,{attributes:true,attributeFilter:['disabled'],childList:true,characterData:true,subtree:true});
 observer.observe(picked,{childList:true,characterData:true,subtree:true});
 // All old form submit guards are on the form. This document CAPTURE handler
 // validates Staff first, then prevents those VENDOR-ONLY listeners from
 // misclassifying genuine Staff payouts. Server still performs all checks.
 document.addEventListener('submit',event=>{
  if(event.target!==form||!isStaff())return;
  selectedKind.value=mode.value==='ADVANCE'?'SALARY_ADVANCE':kind.value;
  against.value=mode.value;
  if(!valid()){
    event.preventDefault();event.stopPropagation();
    refresh();panel.scrollIntoView({block:'nearest'});
    notice.textContent+=' Check Amount, Salary Month / Expense Reference, Bank Account and Payment Note.';
    return;
  }
  event.stopPropagation(); // Does not cancel browser's normal form POST.
 },true);
 form.addEventListener('input',event=>{
   if([amount,notes,account,month,expense].includes(event.target))refresh();
 });
 kind.addEventListener('change',refresh);
 month.addEventListener('change',refresh);
 expense.addEventListener('change',refresh);
 mode.addEventListener('change',()=>{renderExpenses();refresh();});
 staff.addEventListener('change',()=>{renderExpenses();queueMicrotask(refresh);});
 category.addEventListener('change',()=>queueMicrotask(refresh));
 search.addEventListener('input',()=>queueMicrotask(refresh));
 picked.addEventListener('DOMSubtreeModified',()=>queueMicrotask(refresh));
 const date=form.querySelector('input[name="payment_date"]');
 if(date)date.addEventListener('change',refresh);
 renderExpenses();refresh();
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
