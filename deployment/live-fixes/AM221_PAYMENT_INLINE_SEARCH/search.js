/* AM220_VENDOR_SOURCE_SINGLE_SEARCH: live Payment Voucher unified vendor/bill selector.
 * Uses real AM_PAYMENT_SOURCES from the Laravel view. Never posts a voucher. */
(function(){
'use strict';
function ready(){
 const search=document.getElementById('am218-source-search');
 const menu=document.getElementById('am218-source-results');
 const status=document.getElementById('am218-source-choice');
 const wrap=document.getElementById('am218-search-wrap');
 const type=document.getElementById('source-type');
 const key=document.getElementById('source-key');
 const keyField=document.getElementById('known-source-field');
 const vendor=document.getElementById('payment-vendor');
 const payee=document.getElementById('payment-payee');
 const vendorFilter=document.getElementById('am217-vendor-filter');
 const multi=document.getElementById('p133-multi-toggle');
 if(!search||!menu||!status||!wrap||!type||!key||!keyField)return;
 const billTypes=new Set(['PURCHASE_INVOICE','OSL_JOB']);
 const all=Array.isArray(window.AM_PAYMENT_SOURCES)?window.AM_PAYMENT_SOURCES:[];
 const payable=all.filter(x=>x&&billTypes.has(String(x.type))&&String(x.key||'')&&Number(x.outstanding)>0);
 const vendors=vendor?Array.from(vendor.options).filter(o=>o.value).map(o=>({id:String(o.value),label:o.textContent.trim()})):[];
 let current=[],focused=0,choosing=false;
 const text=x=>String(x??'').toLocaleLowerCase('en-IN').trim();
 const number=x=>Number(x||0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
 function hide(){menu.hidden=true;search.setAttribute('aria-expanded','false');}
 function mode(){
   // Show one searchable bill input, not two duplicate selectors.
   const purchase=billTypes.has(type.value);
   const multiOn=!!(multi&&multi.checked);
   wrap.hidden=!purchase||multiOn;
   keyField.style.display=purchase&&!multiOn?'none':'';
   if(vendorFilter)vendorFilter.hidden=true;
   if(!purchase||multiOn)hide();
 }
 function options(){
   // Include both Parts and OSL, not just the type that the form defaults to.
   const result=payable.map(s=>({kind:'bill',data:s,
     label:(s.type==='OSL_JOB'?'OSL Purchase':'Parts Purchase')+' · '+(s.payee||'Vendor'),
     detail:String(s.label||s.key),
     searchable:text([s.payee,s.label,s.key,s.job_no,s.vehicle,s.purchase_no].join(' ')),
     due:Number(s.outstanding)}));
   if(['ON_ACCOUNT','ADVANCE'].includes(type.value)){
     for(const v of vendors)result.push({kind:'vendor',data:v,label:'Vendor · '+v.label,detail:'Vendor Master',searchable:text(v.label),due:null});
   }
   return result;
 }
 function choose(item){
   choosing=true;
   try{
    if(item.kind==='vendor'){
      if(vendor){
        vendor.value=item.data.id;
        vendor.dispatchEvent(new Event('change',{bubbles:true}));
      }
      if(payee)payee.value=item.data.label.replace(/^[^—]*—\s*/,'');
      search.value=item.data.label;status.textContent='Vendor selected from Vendor Master.';
      hide();return;
    }
    const source=item.data;
    if(multi&&multi.checked){
      multi.checked=false;
      multi.dispatchEvent(new Event('change',{bubbles:true}));
    }
    type.value=String(source.type);
    type.dispatchEvent(new Event('change',{bubbles:true}));
    // payment-voucher.js synchronously recreates source-key options on change.
    const option=Array.from(key.options).find(o=>o.value===String(source.key));
    if(!option){
      status.textContent='Source list has changed. Refresh Payment Voucher and search again.';
      status.setAttribute('role','alert');hide();return;
    }
    key.value=option.value;
    key.dispatchEvent(new Event('change',{bubbles:true}));
    if(vendor&&source.vendor_id&&Array.from(vendor.options).some(o=>o.value===String(source.vendor_id)))
      vendor.value=String(source.vendor_id);
    search.value=(source.payee||'Vendor')+' · '+(source.label||source.key);
    status.textContent=(source.type==='OSL_JOB'?'OSL Purchase':'Parts Purchase')+
      ' selected. Outstanding ₹'+number(source.outstanding)+
      '. Amount can be reduced for a partial payment; server validates posting.';
    status.removeAttribute('role');
    mode();hide();
   }finally{choosing=false;}
 }
 function render(){
   const query=text(search.value),tokens=query.split(/\s+/).filter(Boolean);
   current=options().filter(row=>tokens.every(t=>row.searchable.includes(t)))
     .sort((a,b)=>a.kind!==b.kind?(a.kind==='bill'?-1:1):
       a.kind==='bill'&&b.kind==='bill'
         ?(a.data.type==='OSL_JOB'?0:1)-(b.data.type==='OSL_JOB'?0:1):0)
     .slice(0,90);
   menu.replaceChildren();focused=0;
   if(!current.length){
     const empty=document.createElement('div');empty.className='am218-empty';
     empty.textContent=payable.length
        ?'No matching unpaid Parts / OSL bill. Search another vendor or bill number.'
        :'No outstanding Parts / OSL Purchase sources were loaded. Refresh or check the purchase status.';
     menu.append(empty);
   }
   current.forEach((item,i)=>{
     const b=document.createElement('button');
     b.type='button';b.className='am218-result';b.dataset.index=String(i);
     b.setAttribute('role','option');b.setAttribute('aria-selected',i===0?'true':'false');
     const title=document.createElement('strong');title.textContent=item.label;
     const detail=document.createElement('span');detail.textContent=item.detail;
     const due=document.createElement('small');
     due.textContent=item.kind==='bill'?'Outstanding ₹'+number(item.due):'Select vendor';
     b.append(title,detail,due);
     b.addEventListener('pointerdown',e=>{e.preventDefault();choose(item);});
     b.addEventListener('click',()=>{if(!choosing)choose(item);});
     menu.append(b);
   });
   menu.hidden=false;search.setAttribute('aria-expanded','true');
 }
 // Remove stale duplicate vendor filter. The official vendor dropdown remains
 // in place for other payment modes and for server-side form submission.
 if(vendorFilter)vendorFilter.remove();
 search.placeholder='Search SAHERA, another vendor, OSL / Purchase No., JC or vehicle';
 search.setAttribute('aria-label','Search unpaid Parts and OSL purchases by vendor or bill');
 search.addEventListener('focus',render);
 search.addEventListener('click',render);
 search.addEventListener('input',render);
 search.addEventListener('keydown',e=>{
   if(e.key==='Escape'){hide();return;}
   if(e.key==='ArrowDown'||e.key==='ArrowUp'){
      e.preventDefault();if(menu.hidden)render();
      if(current.length){focused=(focused+(e.key==='ArrowDown'?1:-1)+current.length)%current.length;
        Array.from(menu.querySelectorAll('button')).forEach((b,i)=>b.setAttribute('aria-selected',i===focused?'true':'false'));}
   }
   if(e.key==='Enter'&&!menu.hidden&&current[focused]){e.preventDefault();choose(current[focused]);}
 });
 type.addEventListener('change',()=>{if(!choosing){search.value='';status.textContent='';}mode();});
 if(multi)multi.addEventListener('change',mode);
 document.addEventListener('pointerdown',e=>{if(!wrap.contains(e.target))hide();});
 mode();
 const selected=all.find(s=>String(s.type)===type.value&&String(s.key)===key.value);
 if(selected&&billTypes.has(type.value)){
   search.value=(selected.payee||'Vendor')+' · '+(selected.label||selected.key);
 }
 status.textContent='Search or click to choose an unpaid Parts / OSL vendor bill. '+payable.length+
   ' outstanding bill sources loaded.';
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready,{once:true});
else ready();
})();