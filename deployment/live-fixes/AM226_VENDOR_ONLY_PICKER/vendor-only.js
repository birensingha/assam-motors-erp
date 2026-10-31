/* AM226_VENDOR_ONLY_PICKER: Vendor-only top selector built on AM225 payment-first, oldest-bill-first, editable Parts + OSL voucher.
 * No payment is posted by this script. Laravel validates every allocation at posting.
 */
(function(root){
'use strict';
const paisa=value=>{
  if(value===null||value===undefined||String(value).trim()==='')return 0;
  const num=Number(value);
  return Number.isFinite(num)&&num>=0?Math.round(num*100):NaN;
};
const money=n=>'₹'+(n/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
function allocateOldestFirst(total,items){
  let rest=paisa(total),sum=0;
  if(!Number.isFinite(rest))return {items:[],unallocated:NaN,allocated:0,count:0};
  const normalized=items.map((s,i)=>({
    key:s.key,
    due:paisa(s.outstanding),
    billDate:String(s.bill_date||s.purchase_date||s.date||''),
    index:i
  }));
  normalized.sort((a,b)=>a.billDate.localeCompare(b.billDate)||a.index-b.index);
  const out=normalized.map(s=>{
    const due=Number.isFinite(s.due)?Math.max(0,s.due):0;
    const take=Math.min(rest,due);
    rest-=take;sum+=take;
    return {key:s.key,allocated:take,balance:due-take,due};
  });
  return {items:out,allocated:sum,unallocated:rest,count:out.filter(s=>s.allocated>0).length};
}
function filterVendorNames(entries,query){
  const words=String(query||'').toLocaleLowerCase('en-IN').trim().split(/\s+/).filter(Boolean);
  return entries.filter(([,name])=>words.every(w=>String(name).toLocaleLowerCase('en-IN').includes(w)))
    .sort((a,b)=>String(a[1]).localeCompare(String(b[1]))).slice(0,85);
}
if(typeof module!=='undefined'&&module.exports){
  module.exports={allocateOldestFirst,paisa,filterVendorNames};
}
if(!root||!root.document)return;
const doc=root.document;
const $=id=>doc.getElementById(id);
const clean=x=>String(x||'').toLocaleLowerCase('en-IN').trim();
const esc=x=>String(x??'');
function init(){
 const form=$('payment-form'),type=$('source-type'),key=$('source-key');
 const multi=$('p133-multi-toggle'),toggleWrap=$('p133-multi-toggle-wrap'),area=$('p133-multi-area');
 const vendor=$('p133-multi-vendor'),rows=$('p133-multi-bills'),error=$('p133-multi-error');
 const search=$('am218-source-search'),menu=$('am218-source-results'),searchWrap=$('am218-search-wrap'),help=$('am218-source-choice');
 const amount=$('payment-value'),known=$('known-source-field'),save=$('p133-save'),mode=$('p133-payment-mode');
 const preview=$('source-preview'),previewTotal=$('p133-preview-total'),previewCount=$('p133-preview-count'),previewMode=$('p133-preview-mode');
 const amountLabel=amount?.closest('label'),originalParent=amountLabel?.parentNode,originalNext=amountLabel?.nextSibling;
 const amountSlot=doc.createElement('div');amountSlot.id='am225-amount-first-slot';
 if(searchWrap.parentNode)searchWrap.parentNode.insertBefore(amountSlot,searchWrap);amountSlot.hidden=true;
 if([form,type,key,multi,area,vendor,rows,error,search,menu,searchWrap,amount,known,save,mode].some(v=>!v))return;
 const types=new Set(['PURCHASE_INVOICE','OSL_JOB']);
 const all=Array.isArray(root.AM_PAYMENT_SOURCES)?root.AM_PAYMENT_SOURCES:[];
 const bills=all.filter(s=>s&&types.has(s.type)&&paisa(s.outstanding)>0&&Number(s.vendor_id)>0&&String(s.key||''));
 const sourceByKey=new Map(bills.map(s=>[s.key,s])),vendorById=new Map();
 bills.forEach(s=>{
   const v=String(s.vendor_id);
   if(!vendorById.has(v))vendorById.set(v,String(s.payee||'Vendor '+v));
 });
 let currentVendor='',autoMode=true,searchResults=[],active=0,internalType=false,posting=false;
 const isMulti=()=>multi.checked&&types.has(type.value);
 const getRows=()=>Array.from(rows.querySelectorAll('.am225-row'));
 const selected=()=>bills.filter(s=>String(s.vendor_id)===currentVendor);
 const reference=s=>s.type==='OSL_JOB'?s.key:s.key.slice('PURCHASE_INVOICE::'.length);
 const name=s=>(s.type==='OSL_JOB'?'OSL Purchase':'Parts Purchase')+' · '+(s.label||s.key);
 function clearMenu(){menu.hidden=true;search.setAttribute('aria-expanded','false');}
 function fillVendorOptions(){
  vendor.replaceChildren(new Option('— Select Vendor —',''));
  [...vendorById.entries()].sort((a,b)=>a[1].localeCompare(b[1])).forEach(([id,n])=>vendor.add(new Option(n,id)));
  if(currentVendor)vendor.value=currentVendor;
 }
 function renderHelp(text,bad){
   help.textContent=text;
   help.setAttribute('role',bad?'alert':'status');
 }
 // In multi mode Payment Amount is NEVER computed or overwritten by selected bills.
 function refresh(){
  if(previewMode)previewMode.textContent=mode.value;
  if(!isMulti()){
    amount.readOnly=false;
    if(previewTotal)previewTotal.textContent=money(Math.max(0,paisa(amount.value)||0));
    if(previewCount)previewCount.textContent=key.value?'1':'—';
    save.disabled=posting;save.textContent='✓ POST PAYMENT VOUCHER';
    return;
  }
  const payment=paisa(amount.value),validPayment=Number.isFinite(payment)&&payment>0;
  let allocated=0,count=0,invalid=false;
  getRows().forEach(row=>{
    const box=row.querySelector('.am225-check'),input=row.querySelector('.am225-adjust');
    const source=sourceByKey.get(row.dataset.key);
    row.querySelectorAll('input[type="hidden"]').forEach(e=>e.remove());
    input.removeAttribute('name');
    if(!source)return;
    const due=paisa(source.outstanding);
    const applied=box.checked?paisa(input.value):0;
    row.querySelector('.am225-balance').textContent=money(Math.max(0,due-(Number.isFinite(applied)?applied:0)));
    if(!box.checked){input.setCustomValidity('');return;}
    const valid=Number.isFinite(applied)&&applied>0&&applied<=due;
    input.setCustomValidity(valid?'':'Enter amount above zero and within this bill outstanding.');
    if(!valid)invalid=true;
    const hidden=doc.createElement('input');hidden.type='hidden';
    hidden.name='allocations['+count+'][ref]';hidden.value=reference(source);
    row.append(hidden);input.name='allocations['+count+'][amount]';
    allocated+=Number.isFinite(applied)?applied:0;count++;
  });
  const remaining=Number.isFinite(payment)?payment-allocated:NaN;
  const warning=!currentVendor?'Select a vendor.'
    :!validPayment?'Enter the Payment Amount first.'
    :invalid?'Correct the invalid bill adjustments.'
    :allocated>payment?'Allocated bills exceed the entered Payment Amount by '+money(allocated-payment)+'.'
    :remaining>0?'Unadjusted payment: '+money(remaining)+'. Select more bills or use Auto Adjust.'
    :count<1?'Choose at least one bill to adjust.'
    :'Payment fully allocated across '+count+' bill(s). Ready for review.';
  error.textContent=warning;
  if(previewTotal)previewTotal.textContent=money(Number.isFinite(payment)?payment:0);
  if(previewCount)previewCount.textContent=String(count)+' Bill(s)';
  let allocatedEl=$('am225-summary-allocated'),leftEl=$('am225-summary-unallocated');
  if(allocatedEl)allocatedEl.textContent=money(allocated);
  if(leftEl)leftEl.textContent=money(Math.max(0,Number.isFinite(remaining)?remaining:0));
  if(preview){
    preview.replaceChildren();
    const a=doc.createElement('div'),n=doc.createElement('span'),v=doc.createElement('strong');
    n.textContent='Vendor';v.textContent=vendorById.get(currentVendor)||'Select vendor';a.append(n,v);
    const b=doc.createElement('div'),n2=doc.createElement('span'),v2=doc.createElement('strong');
    n2.textContent='Payment amount';v2.textContent=money(Number.isFinite(payment)?payment:0);b.append(n2,v2);
    const c=doc.createElement('div'),n3=doc.createElement('span'),v3=doc.createElement('strong');
    n3.textContent='Allocated / Left';v3.textContent=money(allocated)+' / '+money(Math.max(0,Number.isFinite(remaining)?remaining:0));c.append(n3,v3);
    preview.append(a,b,c);preview.style.display='grid';
  }
  save.disabled=posting||!validPayment||!currentVendor||count<1||invalid||remaining!==0;
  save.textContent='✓ POST '+count+' BILL PAYMENT';
 }
 function applyOldest(){
  if(!isMulti())return;
  const payment=paisa(amount.value);
  if(!Number.isFinite(payment)||payment<=0){refresh();return;}
  autoMode=true;
  const plan=allocateOldestFirst(amount.value,selected());
  const values=new Map(plan.items.map(x=>[x.key,x.allocated]));
  getRows().forEach(row=>{
    const v=values.get(row.dataset.key)||0,box=row.querySelector('.am225-check'),input=row.querySelector('.am225-adjust');
    box.checked=v>0;input.disabled=v<=0;input.value=(v/100).toFixed(2);
  });
  refresh();
  renderHelp('Auto Adjust: oldest available bills first. You can change the selection or amounts manually.',false);
 }
 function buildRows(){
  rows.replaceChildren();currentVendor=String(vendor.value||'');
  const sources=selected().slice().sort((a,b)=>
     String(a.bill_date||'').localeCompare(String(b.bill_date||''))||String(a.key).localeCompare(String(b.key)));
  if(!sources.length){
    const hint=doc.createElement('div');hint.className='p133-help';
    hint.textContent=currentVendor?'No unpaid bills found for this vendor.':'Select a vendor to display unpaid bills.';
    rows.append(hint);refresh();return;
  }
  const head=doc.createElement('div');head.className='am224-head';
  ['Adjust','Bill / Type','Outstanding','Adjust ₹','Balance'].forEach(t=>{const x=doc.createElement('span');x.textContent=t;head.append(x);});rows.append(head);
  sources.forEach(s=>{
    const row=doc.createElement('div');row.className='am224-row am225-row';row.dataset.key=s.key;
    const box=doc.createElement('input');box.type='checkbox';box.className='am224-check am225-check';box.setAttribute('aria-label','Adjust '+name(s));
    const info=doc.createElement('div');info.className='am224-bill';
    const title=doc.createElement('strong');title.textContent=s.type==='OSL_JOB'?'OSL Purchase':'Parts Purchase';
    const desc=doc.createElement('span');desc.textContent=s.label||s.key;info.append(title,desc);
    const due=doc.createElement('span');due.className='am224-amt';due.textContent=money(paisa(s.outstanding));
    const input=doc.createElement('input');input.type='number';input.step='0.01';input.min='0.01';input.max=Number(s.outstanding).toFixed(2);
    input.value='0.00';input.disabled=true;input.className='am224-adjust am225-adjust';
    input.setAttribute('aria-label','Adjust amount '+name(s));
    const balance=doc.createElement('span');balance.className='am224-balance am225-balance';balance.textContent=money(paisa(s.outstanding));
    row.append(box,info,due,input,balance);rows.append(row);
    box.addEventListener('change',()=>{
      autoMode=false;
      input.disabled=!box.checked;
      if(box.checked){
        const other=getRows().filter(r=>r!==row&&r.querySelector('.am225-check').checked)
           .reduce((sum,r)=>sum+paisa(r.querySelector('.am225-adjust').value),0);
        const available=Math.max(0,paisa(amount.value)-other);
        if(available>0)input.value=(Math.min(available,paisa(s.outstanding))/100).toFixed(2);
        else input.value='0.00';
      }else input.value='0.00';
      refresh();
    });
    input.addEventListener('input',()=>{autoMode=false;refresh();});
  });
  if(autoMode)applyOldest();else refresh();
 }
 function setMode(){
  const inPurchase=types.has(type.value);
  if(toggleWrap)toggleWrap.hidden=!inPurchase;
  if(!inPurchase)multi.checked=false;
  const m=isMulti();
  searchWrap.hidden=!inPurchase;
  area.hidden=!m;
  known.style.display=m?'none':(inPurchase?'none':'');
  key.required=!m&&['PURCHASE_INVOICE','OSL_JOB','EXPENSE_VOUCHER'].includes(type.value);
  key.disabled=m;
  amount.readOnly=false;
  // In multi mode, move the ORIGINAL payment input to the top, preserving its
  // value/name/validation (not a second amount input).
  if(amountLabel&&originalParent){
    if(m){amountSlot.hidden=false;amountSlot.append(amountLabel);}
    else {
      if(originalNext&&originalNext.parentNode===originalParent)originalParent.insertBefore(amountLabel,originalNext);
      else originalParent.append(amountLabel);
      amountSlot.hidden=true;
    }
  }
  if(m&&type.value==='OSL_JOB'){
    internalType=true;type.value='PURCHASE_INVOICE';type.dispatchEvent(new Event('change',{bubbles:true}));internalType=false;
  }
  if(m&&currentVendor){
    if(vendor.value!==currentVendor)vendor.value=currentVendor;
    if(!getRows().length)buildRows();
  }
  refresh();
 }
 function selectVendor(id){
  const existed=currentVendor===id;
  currentVendor=id;
  if(!isMulti()){
    multi.checked=true;internalType=true;type.value='PURCHASE_INVOICE';
    type.dispatchEvent(new Event('change',{bubbles:true}));internalType=false;
  }
  vendor.value=id;
  autoMode=true;
  buildRows();
  search.value=vendorById.get(id)||'';
  renderHelp('Selected vendor. Enter Payment Amount, then Auto Adjust shows how many bills can be settled.',false);
 }
 function chooseVendor(entry){
  // AM226: Search results contain only vendor names, never purchase lines.
  selectVendor(entry.id);
  clearMenu();
 }
 function render(){
  // All bill names / references stay in the lower adjustment panel.
  searchResults=filterVendorNames([...vendorById.entries()],search.value)
    .map(([id,name])=>({id,label:name}));
  menu.replaceChildren();active=0;
  if(!searchResults.length){
    const p=doc.createElement('p');p.className='am218-empty';
    p.textContent='No matching vendor with outstanding Parts / OSL bills.';
    menu.append(p);
  }
  searchResults.forEach((entry,i)=>{
    const button=doc.createElement('button');
    button.type='button';button.className='am218-result';
    button.setAttribute('role','option');
    button.setAttribute('aria-selected',i===0?'true':'false');
    const label=doc.createElement('strong');label.textContent=entry.label;
    button.append(label);
    // Pointerdown prevents blur from cancelling the selection.
    button.addEventListener('pointerdown',event=>event.preventDefault());
    button.addEventListener('click',()=>chooseVendor(entry));
    menu.append(button);
  });
  menu.hidden=false;search.setAttribute('aria-expanded','true');
 }
 const obsolete=$('am217-vendor-filter');if(obsolete)obsolete.remove();
 fillVendorOptions();
 // The hidden vendor select remains in the DOM for the original bill lookup
 // and its change handlers; only its duplicate visible label is removed.
 const extraVendorLabel=vendor.closest('label');
 if(extraVendorLabel){
   extraVendorLabel.style.display='none';
   extraVendorLabel.setAttribute('aria-hidden','true');
 }
 vendor.tabIndex=-1;
 search.placeholder='Type and select a vendor name';
 search.setAttribute('aria-label','SELECT VENDOR NAME');
 search.addEventListener('input',()=>{
   // Never allow a stale vendor's bill allocation after changing the search.
   if(currentVendor&&clean(search.value)!==clean(vendorById.get(currentVendor))){
     currentVendor='';vendor.value='';autoMode=true;buildRows();
     renderHelp('Select a vendor from the matching names before posting.',false);
   }
   render();
 });
 search.addEventListener('focus',render);
 search.addEventListener('keydown',e=>{
   if(e.key==='Escape'){clearMenu();return;}
   if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();if(menu.hidden)render();
     else if(searchResults.length){active=(active+(e.key==='ArrowDown'?1:-1)+searchResults.length)%searchResults.length;
       Array.from(menu.querySelectorAll('.am218-result')).forEach((b,i)=>b.setAttribute('aria-selected',i===active?'true':'false'));}}
   if(e.key==='Enter'&&!menu.hidden&&searchResults[active]){e.preventDefault();chooseVendor(searchResults[active]);}
 });
 doc.addEventListener('pointerdown',e=>{if(!searchWrap.contains(e.target))clearMenu();});
 vendor.addEventListener('change',()=>{currentVendor=vendor.value;autoMode=true;buildRows();});
 const box=doc.createElement('div');box.id='am225-allocate-controls';box.className='am225-controls';
 const auto=doc.createElement('button');auto.type='button';auto.textContent='Auto Adjust · Oldest Bills First';auto.className='am225-auto';
 auto.addEventListener('click',()=>{autoMode=true;applyOldest();});
 const totals=doc.createElement('div');totals.className='am225-summary';
 const a=doc.createElement('span');a.textContent='Allocated: ';
 const av=doc.createElement('strong');av.id='am225-summary-allocated';av.textContent='₹0.00';a.append(av);
 const b=doc.createElement('span');b.textContent='Unadjusted: ';
 const bv=doc.createElement('strong');bv.id='am225-summary-unallocated';bv.textContent='₹0.00';b.append(bv);
 totals.append(a,b);box.append(auto,totals);area.append(box);
 amount.addEventListener('input',()=>{if(isMulti()&&autoMode)applyOldest();else refresh();});
 multi.addEventListener('change',()=>{
   if(multi.checked){key.value='';key.dispatchEvent(new Event('change',{bubbles:true}));}
   autoMode=true;setMode();if(isMulti()&&currentVendor)applyOldest();
 });
 type.addEventListener('change',()=>{if(!internalType){search.value='';renderHelp('',false);}setMode();});
 mode.addEventListener('change',refresh);key.addEventListener('change',refresh);
 form.addEventListener('submit',e=>{
   if(!isMulti())return;
   refresh();
   const total=paisa(amount.value),picked=getRows().filter(r=>r.querySelector('.am225-check').checked);
   const sum=picked.reduce((n,r)=>n+paisa(r.querySelector('.am225-adjust').value),0);
   const invalid=picked.some(r=>!r.querySelector('.am225-adjust').checkValidity());
   if(!currentVendor||picked.length<1||picked.length>30||!Number.isFinite(total)||total<=0||invalid||total!==sum){
     e.preventDefault();error.textContent='Enter Payment Amount and allocate exactly that amount to 1–30 bills for the same vendor. For remaining advance, create a separate On Account voucher.';
     return;
   }
   if(!root.confirm('Post one Payment Voucher of '+money(total)+' allocated across '+picked.length+' bill(s) for '+vendorById.get(currentVendor)+'?')){
     e.preventDefault();return;
   }
   posting=true;save.disabled=true;
 });
 setMode();
 renderHelp('Select a vendor name; that vendor’s outstanding Parts and OSL bills appear in the adjustment table below.',false);
}
if(doc.readyState==='loading')doc.addEventListener('DOMContentLoaded',init,{once:true});else init();
})(typeof window!=='undefined'?window:null);
