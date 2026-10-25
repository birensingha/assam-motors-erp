/* FIX133: multiple Purchase Bills with one Payment Voucher, frontend preview only.
   All amounts/vendors/outstanding revalidated by server during the transaction. */
(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const form = $('payment-form'), type = $('source-type'), key = $('source-key');
  const toggle = $('p133-multi-toggle'), toggleWrap = $('p133-multi-toggle-wrap');
  const area = $('p133-multi-area'), vendor = $('p133-multi-vendor');
  const rows = $('p133-multi-bills'), errors = $('p133-multi-error');
  const amount = $('payment-value'), mode = $('p133-payment-mode');
  const save = $('p133-save'), preview = $('source-preview'), known = $('known-source-field');
  const totalView = $('p133-preview-total'), countView = $('p133-preview-count');
  const modeView = $('p133-preview-mode');
  if (![form,type,key,toggle,area,vendor,rows,errors,amount,mode,save,preview,known].every(Boolean)) return;
  const sources = (Array.isArray(window.AM_PAYMENT_SOURCES) ? window.AM_PAYMENT_SOURCES : [])
    .filter((s) => s.type === 'PURCHASE_INVOICE' && Number(s.vendor_id) > 0 &&
      Number(s.outstanding) >= 0.01 && typeof s.key === 'string');
  const money = (n) => '₹' + Number(n || 0).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
  const isMulti = () => type.value === 'PURCHASE_INVOICE' && toggle.checked;

  const vendors = new Map();
  for (const s of sources) if (!vendors.has(String(s.vendor_id))) {
    vendors.set(String(s.vendor_id),String(s.payee || 'Vendor #' + s.vendor_id));
  }
  Array.from(vendors.entries()).sort((a,b) => a[1].localeCompare(b[1]))
    .forEach(([id,name]) => { const option=document.createElement('option');
      option.value=id; option.textContent=name; vendor.appendChild(option);
    });
  function fillVendor() {
    rows.replaceChildren(); errors.textContent='';
    const selected=sources.filter((s)=>String(s.vendor_id)===vendor.value);
    if (selected.length === 0) {rows.textContent=vendor.value?'No outstanding Purchase Bills.':'Select Vendor to view outstanding bills.'; return;}
    selected.forEach((s) => {
      const row=document.createElement('div');row.className='p133-bill';
      const checkbox=document.createElement('input');checkbox.type='checkbox';
      checkbox.setAttribute('aria-label','Select '+s.key);checkbox.dataset.ref=s.key.slice('PURCHASE_INVOICE::'.length);
      const label=document.createElement('label');label.textContent=String(s.label || checkbox.dataset.ref);
      const input=document.createElement('input');input.type='number';input.step='0.01';input.min='0.01';
      input.max=Number(s.outstanding).toFixed(2);input.value=Number(s.outstanding).toFixed(2);
      input.disabled=true;input.setAttribute('aria-label','Allocated amount for '+checkbox.dataset.ref);
      row.append(checkbox,label,input);rows.appendChild(row);
      checkbox.addEventListener('change',()=>{
        input.disabled=!checkbox.checked;
        if (checkbox.checked && Number(input.value)<=0) input.value=Number(s.outstanding).toFixed(2);
        update();
      });
      input.addEventListener('input',update);
    });
  }
  function update() {
    modeView.textContent=mode.value;
    if (!isMulti()) {
      countView.textContent=(key.value && ['PURCHASE_INVOICE','EXPENSE_VOUCHER','OSL_JOB'].includes(type.value))?'1':'—';
      totalView.textContent=money(amount.value);
      save.textContent='✓ POST PAYMENT VOUCHER';
      amount.readOnly=false;
      return;
    }
    let count=0,cents=0;
    rows.querySelectorAll('.p133-bill').forEach((row)=>{
      const checkbox=row.querySelector('input[type="checkbox"]');
      const input=row.querySelector('input[type="number"]');
      input.removeAttribute('name');
      const existing=row.querySelector('input[type="hidden"]');
      if (existing) existing.remove();
      if (!checkbox.checked) return;
      const dueCents=Math.round(Number(input.max)*100);
      const allocatedCents=Math.round(Number(input.value)*100);
      if(!Number.isFinite(allocatedCents)||allocatedCents<1||allocatedCents>dueCents){
        input.setCustomValidity('Amount must be greater than zero and cannot exceed outstanding.');
      } else {
        input.setCustomValidity('');
      }
      const hidden=document.createElement('input');hidden.type='hidden';
      hidden.name='allocations['+count+'][ref]';hidden.value=checkbox.dataset.ref;
      row.appendChild(hidden);
      input.name='allocations['+count+'][amount]';
      cents+=allocatedCents; count++;
    });
    amount.readOnly=true;
    amount.value=(cents/100).toFixed(2);
    countView.textContent=String(count)+' Purchase Bill(s)';
    totalView.textContent=money(cents/100);
    save.textContent='✓ POST '+count+' BILL PAYMENT';
    preview.style.display='grid';
    preview.textContent='';
    const label=document.createElement('div');const name=document.createElement('span');
    const value=document.createElement('strong');name.textContent='Vendor';
    value.textContent=vendors.get(vendor.value)||'Select Vendor';label.append(name,value);
    const allocated=document.createElement('div');const a=document.createElement('span'),b=document.createElement('strong');
    a.textContent='Allocated Total';b.textContent=money(cents/100);allocated.append(a,b);
    preview.append(label,allocated);
    errors.textContent=count<2?'Select at least 2 Purchase Bills.':'';
  }
  function setMode() {
    const purchase=type.value==='PURCHASE_INVOICE';
    toggleWrap.hidden=!purchase;
    if(!purchase)toggle.checked=false;
    const multi=isMulti();
    area.hidden=!multi;known.style.display=multi?'none':(['PURCHASE_INVOICE','EXPENSE_VOUCHER','OSL_JOB'].includes(type.value)?'flex':'none');
    key.required=!multi && ['PURCHASE_INVOICE','EXPENSE_VOUCHER','OSL_JOB'].includes(type.value);
    key.disabled=multi;
    if(!multi){ rows.querySelectorAll('.p133-bill input[type="number"]').forEach(x=>x.setCustomValidity('')); }
    update();
  }
  toggle.addEventListener('change',()=>{if(toggle.checked){key.value='';vendor.value='';fillVendor();}setMode();});
  vendor.addEventListener('change',()=>{fillVendor();update();});
  type.addEventListener('change',setMode);
  key.addEventListener('change',update);
  amount.addEventListener('input',update);
  mode.addEventListener('change',update);
  form.addEventListener('submit',e=>{
    if (!isMulti()) return;
    let count=0;
    for(const row of rows.querySelectorAll('.p133-bill')){
      const checked=row.querySelector('input[type="checkbox"]');
      if(!checked.checked)continue;
      count++;
      const input=row.querySelector('input[type="number"]');
      if(!input.checkValidity()){input.reportValidity();e.preventDefault();return;}
    }
    if(count<2 || !(Number(amount.value)>0) || !vendor.value) {
      e.preventDefault();errors.textContent='Choose one vendor, at least two bills, and positive allocations.';
      area.scrollIntoView({behavior:'smooth',block:'center'});return;
    }
    if(!confirm('Post one Payment Voucher for '+count+' bills, total '+money(amount.value)+'? Each bill will be adjusted individually.')) {
      e.preventDefault();
    }
  });
  fillVendor();setMode();
})();
