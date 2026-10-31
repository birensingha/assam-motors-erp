/* AM238 Vendor Debit selector: previous On Account cash can settle new bills.
 * Debit use is settlement allocation, not second cash/bank payment.
 */
(function(){
 'use strict';
 function init(){
  const $=id=>document.getElementById(id);
  const form=$('payment-form'),vendor=$('payment-vendor'),panel=$('am238-debit-panel');
  const amount=$('payment-value'),mode=$('am237-mode'),billSlot=$('am237-bills-slot');
  const src=$('am238-debit-json'),summary=$('am238-debit-summary'),options=$('am238-debit-options');
  if(!form||!vendor||!panel||!amount||!mode||!billSlot||!src||!summary||!options)return;
  let sources=[];
  try{sources=JSON.parse(src.textContent||'[]');if(!Array.isArray(sources))sources=[];}catch(e){}
  let selected=new Map();
  const cents=value=>Math.round((Number(value)||0)*100);
  const money=n=>'₹'+(n/100).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
  const active=()=>mode.value==='AGAINST'&&vendor.value;
  function totalCents(){return Array.from(selected.values()).reduce((s,v)=>s+v,0);}
  window.AM238Debit={totalCents, creditTotal:totalCents};
  function update(){
    options.querySelectorAll('.am238-debit-row').forEach(row=>{
      const id=Number(row.dataset.id),box=row.querySelector('.am238-debit-check'),qty=row.querySelector('.am238-debit-amount');
      const value=box.checked?cents(qty.value):0;
      const max=cents(row.dataset.max);
      selected.delete(id);
      if(box.checked && value>0 && value<=max)selected.set(id,value);
      qty.setCustomValidity(box.checked&&(value<=0||value>max)?'Adjustment must be positive and at most the available debit.':'');
      qty.disabled=!box.checked;
      // Form names are applied only for a selected valid source.
      for(const hidden of row.querySelectorAll('input[type=hidden]'))hidden.remove();
    });
    [...selected.entries()].forEach(([id,value],index)=>{
      const row=options.querySelector('.am238-debit-row[data-id="'+id+'"]');
      if(!row)return;
      const input=document.createElement('input');input.type='hidden';
      input.name='vendor_debits['+index+'][voucher_id]';input.value=String(id);row.append(input);
      row.querySelector('.am238-debit-amount').name='vendor_debits['+index+'][amount]';
    });
    options.querySelectorAll('.am238-debit-amount').forEach(input=>{
      if(!input.closest('.am238-debit-row')?.querySelector('.am238-debit-check')?.checked)input.removeAttribute('name');
    });
    summary.textContent='Prior vendor debit to adjust: '+money(totalCents())+'. New cash/payment: '+money(cents(amount.value))+
      '. Bill allocation limit: '+money(cents(amount.value)+totalCents())+'. Any remaining new cash stays On Account.';
  }
  function render(){
    selected=new Map();
    options.replaceChildren();
    panel.hidden=!active();
    if(!active()){summary.textContent='';return;}
    const list=sources.filter(s=>String(s.vendor_id)===String(vendor.value)&&Number(s.available)>0.009);
    if(!list.length){
      const p=document.createElement('p');p.className='am238-none';p.textContent='No previous unadjusted Debit / On Account balance for this vendor.';
      options.append(p);
    }
    for(const s of list){
      const row=document.createElement('div');row.className='am238-debit-row';row.dataset.id=String(s.voucher_id);
      row.dataset.max=String(s.available);
      const left=document.createElement('label');left.className='am238-debit-desc';
      const check=document.createElement('input');check.type='checkbox';check.className='am238-debit-check';
      const line=document.createElement('span');
      const bold=document.createElement('strong');bold.textContent=s.reference||'OA-'+s.voucher_id;
      const caption=document.createElement('small');caption.textContent=(s.date||'')+' · '+(s.payment_type||'')+' · Debit available '+money(cents(s.available));
      line.append(bold,caption);left.append(check,line);
      const right=document.createElement('input');right.type='number';right.className='am238-debit-amount';
      right.step='.01';right.min='.01';right.max=String(s.available);right.value=Number(s.available).toFixed(2);
      right.disabled=true;right.setAttribute('aria-label','Use debit amount from '+s.reference);
      row.append(left,right);options.append(row);
      check.addEventListener('change',()=>{
        if(check.checked)right.disabled=false;
        else right.disabled=true;
        update();amount.dispatchEvent(new Event('input',{bubbles:true}));
      });
      right.addEventListener('input',()=>{
        update();amount.dispatchEvent(new Event('input',{bubbles:true}));
      });
    }
    update();amount.dispatchEvent(new Event('input',{bubbles:true}));
  }
  vendor.addEventListener('change',render);
  mode.addEventListener('change',render);
  amount.addEventListener('input',update);
  form.addEventListener('submit',e=>{
    if(!active())return;
    update();
    const invalid=options.querySelector('.am238-debit-check:checked + .am238-debit-amount:invalid');
    if(invalid){e.preventDefault();e.stopImmediatePropagation();invalid.reportValidity();return;}
    const applied=Array.from(options.querySelectorAll('.am238-debit-amount'))
      .filter(el=>el.closest('.am238-debit-row')?.querySelector('.am238-debit-check')?.checked)
      .reduce((s,el)=>s+cents(el.value),0);
    if(applied!==totalCents()){
      e.preventDefault();e.stopImmediatePropagation();summary.textContent='Invalid prior Debit amount. Recheck and retry.';
    }
  },true);
  // Existing payment bill table is owned by AM225. Keep it in same scroll area.
  if(panel.parentElement!==billSlot)billSlot.append(panel);
  render();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
