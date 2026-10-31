/* AM224 Tally-style single + multi vendor bill adjustment: Parts and OSL.
   Real Laravel payableSources / allocations; never posts without server validation. */
(function(){
'use strict';
function start(){
 const id=x=>document.getElementById(x);
 const form=id('payment-form'),type=id('source-type'),key=id('source-key');
 const search=id('am218-source-search'),menu=id('am218-source-results'),wrap=id('am218-search-wrap'),help=id('am218-source-choice');
 const multi=id('p133-multi-toggle'),multiWrap=id('p133-multi-toggle-wrap'),area=id('p133-multi-area');
 const vendor=id('p133-multi-vendor'),rows=id('p133-multi-bills'),error=id('p133-multi-error');
 const known=id('known-source-field'),amount=id('payment-value'),payee=id('payment-payee');
 const mode=id('p133-payment-mode'),preview=id('source-preview');
 const previewTotal=id('p133-preview-total'),previewCount=id('p133-preview-count'),previewMode=id('p133-preview-mode'),save=id('p133-save');
 if([form,type,key,search,menu,wrap,help,multi,multiWrap,area,vendor,rows,error,known,amount,mode,save].some(x=>!x))return;
 const bills=(Array.isArray(window.AM_PAYMENT_SOURCES)?window.AM_PAYMENT_SOURCES:[]).filter(s=>
  s&&['PURCHASE_INVOICE','OSL_JOB'].includes(s.type)&&Number(s.vendor_id)>0&&
  String(s.key||'').length>0&&Number(s.outstanding)>0.009);
 const vendorNames=new Map(),byKey=new Map();
 bills.forEach(s=>{const v=String(s.vendor_id);if(!vendorNames.has(v))vendorNames.set(v,String(s.payee||'Vendor #'+v));byKey.set(s.key,s);});
 let selectedVendor='',hits=[],focus=0,programmatic=false;
 const money=n=>'₹'+Number(n||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
 const cents=n=>Math.round(Number(n||0)*100);
 const normalize=s=>String(s??'').toLocaleLowerCase('en-IN').trim();
 const isMulti=()=>multi.checked&&['PURCHASE_INVOICE','OSL_JOB'].includes(type.value);
 const billRef=s=>s.type==='OSL_JOB'?s.key:s.key.slice('PURCHASE_INVOICE::'.length);
 const billType=s=>s.type==='OSL_JOB'?'OSL Purchase':'Parts Purchase';
 const findVendor=s=>String(s.vendor_id);
 function dismiss(){menu.hidden=true;search.setAttribute('aria-expanded','false');}
 function vendorOptions(){
   vendor.replaceChildren(new Option('— Select Vendor —',''));
   [...vendorNames.entries()].sort((a,b)=>a[1].localeCompare(b[1])).forEach(([v,n])=>vendor.add(new Option(n,v)));
   if(selectedVendor&&vendorNames.has(selectedVendor))vendor.value=selectedVendor;
 }
 function rebuildRows(preselect){
   rows.replaceChildren();error.textContent='';
   selectedVendor=String(vendor.value||'');
   const list=bills.filter(s=>findVendor(s)===selectedVendor);
   if(!list.length){
     const empty=document.createElement('div');empty.className='p133-help';
     empty.textContent=selectedVendor?'No unpaid Parts or OSL bills for this vendor.':'Select or search a vendor to see all unpaid bills.';
     rows.append(empty);update();return;
   }
   // Tally-like per-invoice adjustment table. Selected bills belong to the same vendor.
   const title=document.createElement('div');title.className='am224-head';
   ['Use','Bill / Type','Outstanding','Adjust ₹','Balance'].forEach(t=>{
     const span=document.createElement('span');span.textContent=t;title.append(span);
   });rows.append(title);
   list.forEach(s=>{
     const row=document.createElement('div');row.className='am224-row';row.dataset.sourceKey=s.key;
     const check=document.createElement('input');check.type='checkbox';check.className='am224-check';
     check.setAttribute('aria-label','Select '+s.label);
     const bill=document.createElement('div');bill.className='am224-bill';
     const name=document.createElement('strong');name.textContent=billType(s);
     const ref=document.createElement('span');ref.textContent=String(s.label||s.key);bill.append(name,ref);
     const due=document.createElement('span');due.className='am224-amt';due.textContent=money(s.outstanding);
     const alloc=document.createElement('input');alloc.type='number';alloc.step='0.01';alloc.min='0.01';
     alloc.max=Number(s.outstanding).toFixed(2);alloc.value=Number(s.outstanding).toFixed(2);
     alloc.className='am224-adjust';alloc.disabled=true;
     alloc.setAttribute('aria-label','Adjust amount '+(s.label||s.key));
     const balance=document.createElement('span');balance.className='am224-balance';balance.textContent=money(s.outstanding);
     row.append(check,bill,due,alloc,balance);rows.append(row);
     const valid=()=>{
       if(!check.checked){alloc.setCustomValidity('');return;}
       const v=cents(alloc.value),max=cents(s.outstanding);
       alloc.setCustomValidity(!Number.isFinite(v)||v<=0||v>max?'Adjust amount must be above zero and not exceed outstanding.':'');
     };
     check.addEventListener('change',()=>{
       alloc.disabled=!check.checked;
       if(check.checked&&cents(alloc.value)<=0)alloc.value=Number(s.outstanding).toFixed(2);
       valid();update();
     });
     alloc.addEventListener('input',()=>{valid();update();});
     if(preselect&&preselect===s.key){
       check.checked=true;alloc.disabled=false;
     }
   });
   update();
 }
 function update(){
   if(previewMode)previewMode.textContent=mode.value;
   if(!isMulti()){
     if(previewTotal)previewTotal.textContent=money(amount.value);
     if(previewCount)previewCount.textContent=key.value?'1':'—';
     amount.readOnly=false;
     save.textContent='✓ POST PAYMENT VOUCHER';
     return;
   }
   let n=0,total=0;
   rows.querySelectorAll('.am224-row').forEach(row=>{
     const c=row.querySelector('.am224-check'),a=row.querySelector('.am224-adjust');
     const old=row.querySelectorAll('input[type="hidden"]');old.forEach(e=>e.remove());
     a.removeAttribute('name');
     const s=byKey.get(row.dataset.sourceKey);
     if(!s)return;
     const due=cents(s.outstanding),allocated=c.checked?cents(a.value):0;
     row.querySelector('.am224-balance').textContent=money((due-(Number.isFinite(allocated)?allocated:0))/100);
     if(!c.checked)return;
     if(!Number.isFinite(allocated)||allocated<1||allocated>due){
       a.setCustomValidity('Adjust amount exceeds outstanding or is invalid.');
     } else {a.setCustomValidity('');}
     const hidden=document.createElement('input');hidden.type='hidden';
     hidden.name='allocations['+n+'][ref]';hidden.value=billRef(s);row.append(hidden);
     a.name='allocations['+n+'][amount]';total+=allocated;n++;
   });
   amount.readOnly=true;amount.value=(total/100).toFixed(2);
   if(previewTotal)previewTotal.textContent=money(total/100);
   if(previewCount)previewCount.textContent=n+' Bills';
   save.textContent='✓ POST '+n+' BILL PAYMENT';
   if(preview){
     preview.replaceChildren();
     const part=document.createElement('div'),l=document.createElement('span'),v=document.createElement('strong');
     l.textContent='Vendor';v.textContent=vendorNames.get(selectedVendor)||'Select vendor';
     part.append(l,v);preview.append(part);
     const p=document.createElement('div'),tl=document.createElement('span'),tv=document.createElement('strong');
     tl.textContent='Allocated total';tv.textContent=money(total/100);p.append(tl,tv);preview.append(p);
     preview.style.display='grid';
   }
   error.textContent=n>0&&n<2?'Choose at least two bills for one voucher.':'';
 }
 function setMode(){
   const purchase=['PURCHASE_INVOICE','OSL_JOB'].includes(type.value);
   multiWrap.hidden=!purchase;
   if(!purchase)multi.checked=false;
   if(isMulti()&&type.value==='OSL_JOB'){
     programmatic=true;type.value='PURCHASE_INVOICE';type.dispatchEvent(new Event('change',{bubbles:true}));programmatic=false;
   }
   const multiple=isMulti();
   wrap.hidden=!purchase;
   area.hidden=!multiple;
   known.style.display=multiple?'none':(purchase?'none':'');
   key.required=!multiple&&['PURCHASE_INVOICE','OSL_JOB','EXPENSE_VOUCHER'].includes(type.value);
   key.disabled=multiple;
   // Do not remove vendor search when checkbox is selected.
   if(multiple){
     if(!vendor.options.length||vendor.options.length===1)vendorOptions();
     if(selectedVendor&&vendor.value!==selectedVendor)vendor.value=selectedVendor;
     if(!rows.querySelector('.am224-row')&&selectedVendor)rebuildRows();
     update();
   } else {
     rows.querySelectorAll('.am224-adjust').forEach(x=>x.setCustomValidity(''));
     update();
   }
 }
 function choice(item){
   if(item.kind==='vendor'){
     if(!isMulti()){
       multi.checked=true;
       type.value='PURCHASE_INVOICE';
       type.dispatchEvent(new Event('change',{bubbles:true}));
     }
     vendor.value=item.id;selectedVendor=item.id;rebuildRows();
     search.value=vendorNames.get(item.id)||'';help.textContent='Choose bill rows below and enter each Adjust Amount.';
   } else {
     const s=item.s;
     if(isMulti()){
       if(selectedVendor!==findVendor(s)){
         vendor.value=findVendor(s);selectedVendor=vendor.value;
         rebuildRows(s.key);
       }else{
         const row=Array.from(rows.querySelectorAll('.am224-row')).find(x=>x.dataset.sourceKey===s.key);
         if(row){const c=row.querySelector('.am224-check');c.checked=true;
           row.querySelector('.am224-adjust').disabled=false;update();}
       }
       search.value=vendorNames.get(findVendor(s))||String(s.payee||'');
       help.textContent='Bill selected for allocation. Add other bills from same vendor.';
     }else{
       programmatic=true;type.value=s.type;type.dispatchEvent(new Event('change',{bubbles:true}));programmatic=false;
       const option=Array.from(key.options).find(o=>o.value===s.key);
       if(!option){help.textContent='Reference changed. Refresh and retry.';dismiss();return;}
       key.value=s.key;key.dispatchEvent(new Event('change',{bubbles:true}));
       search.value=s.payee+' · '+s.label;
       help.textContent='Selected '+billType(s)+', outstanding '+money(s.outstanding)+'.';
       update();
     }
   }
   dismiss();
 }
 function render(){
   const terms=normalize(search.value).split(/\s+/).filter(Boolean);
   const entries=[];
   [...vendorNames.entries()].forEach(([id,name])=>entries.push({kind:'vendor',id,name,search:normalize(name),order:0}));
   bills.forEach(s=>entries.push({kind:'bill',s,search:normalize([s.payee,s.key,s.label,s.job_no,s.vehicle].join(' ')),order:1}));
   hits=entries.filter(x=>terms.every(t=>x.search.includes(t))).slice(0,90);
   menu.replaceChildren();focus=0;
   if(!hits.length){const d=document.createElement('div');d.className='am218-empty';
     d.textContent='No matching unpaid vendor or bill.';menu.append(d);}
   hits.forEach((x,i)=>{
     const b=document.createElement('button');b.type='button';b.className='am218-result';
     b.dataset.index=String(i);b.setAttribute('role','option');b.setAttribute('aria-selected',i===0?'true':'false');
     const h=document.createElement('strong');h.textContent=x.kind==='vendor'?'Vendor · '+x.name:billType(x.s)+' · '+x.s.payee;
     const d=document.createElement('span');d.textContent=x.kind==='vendor'?'Select vendor and show outstanding bills':x.s.label;
     b.append(h,d);
     if(x.kind==='bill'){const p=document.createElement('small');p.textContent=money(x.s.outstanding)+' outstanding';b.append(p);}
     b.addEventListener('pointerdown',e=>{e.preventDefault();choice(x);});
     b.addEventListener('click',()=>choice(x));menu.append(b);
   });
   menu.hidden=false;search.setAttribute('aria-expanded','true');
 }
 // Older AM217 vendor text input is redundant; one search supports both now.
 id('am217-vendor-filter')?.remove();
 vendorOptions();
 search.placeholder='Type vendor, purchase/OSL bill number, JC or vehicle';
 search.addEventListener('focus',render);
 search.addEventListener('input',render);
 search.addEventListener('keydown',e=>{
   if(e.key==='Escape')dismiss();
   if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();
     if(menu.hidden)render();else if(hits.length){focus=(focus+(e.key==='ArrowDown'?1:-1)+hits.length)%hits.length;
       Array.from(menu.querySelectorAll('.am218-result')).forEach((b,i)=>b.setAttribute('aria-selected',i===focus?'true':'false'));}}
   if(e.key==='Enter'&&!menu.hidden&&hits[focus]){e.preventDefault();choice(hits[focus]);}
 });
 document.addEventListener('pointerdown',e=>{if(!wrap.contains(e.target))dismiss();});
 type.addEventListener('change',()=>{if(!programmatic){search.value='';help.textContent='';}setMode();});
 multi.addEventListener('change',()=>{if(multi.checked){key.value='';key.dispatchEvent(new Event('change',{bubbles:true}));}setMode();});
 vendor.addEventListener('change',()=>{selectedVendor=vendor.value;rebuildRows();});
 mode.addEventListener('change',update);amount.addEventListener('input',update);
 key.addEventListener('change',update);
 form.addEventListener('submit',e=>{
   if(!isMulti())return;
   const picked=Array.from(rows.querySelectorAll('.am224-row')).filter(x=>x.querySelector('.am224-check').checked);
   if(!selectedVendor||picked.length<2||picked.length>30||!(Number(amount.value)>0)){
     e.preventDefault();error.textContent='Choose one vendor and between 2 and 30 bills, with positive allocations.';
     return;
   }
   for(const row of picked){const n=row.querySelector('.am224-adjust');
      if(!n.checkValidity()){e.preventDefault();n.reportValidity();return;}}
   if(!confirm('Post ONE vendor Payment Voucher with '+picked.length+' bill allocations totalling '+money(amount.value)+'?')){
     e.preventDefault();return;}
   save.disabled=true;
 });
 setMode();help.textContent='Search a vendor or bill, then select amounts. Parts + OSL supported.';
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});
else start();
})();