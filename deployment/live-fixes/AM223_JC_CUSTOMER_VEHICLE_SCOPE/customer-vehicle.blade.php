
{{-- AM223_JC_CUSTOMER_VEHICLE_SCOPE: customer-ID-owned vehicles only. --}}
<style>
  #jcCustomerSuggestions{position:fixed;z-index:1800;display:none;
    max-height:300px;overflow-y:auto;overscroll-behavior:contain;
    background:#fff;color:#1c3e5b;border:1px solid #9ab5cf;border-radius:9px;
    box-shadow:0 14px 35px rgba(16,47,81,.27);padding:5px}
  #jcCustomerSuggestions button{width:100%;display:block;text-align:left;
    background:#fff;color:#234761;padding:8px 10px;border:0;border-radius:6px;
    cursor:pointer;font-size:12px;line-height:1.45}
  #jcCustomerSuggestions button:hover,#jcCustomerSuggestions button:focus-visible{
    background:#e3f0fb;color:#124574}
  #jcCustomerSuggestions .am223-minor{display:block;font-size:10px;color:#5d7590}
  .am223-vehicle-status{display:block;font-size:11px;color:#5f7790;font-weight:500;
    padding:3px 1px 0}
</style>
<script>
(function(){
  'use strict';
  function ready(){
    const cs=document.getElementById('jcCustomerMaster');
    const vs=document.getElementById('jcVehicleMaster');
    const name=document.getElementById('jcCustomerName');
    if(!cs||!vs||!name||vs.dataset.am223Ready)return;
    vs.dataset.am223Ready='1';
    const customerOptions=Array.from(cs.options).filter(o=>o.value).map(o=>({
      id:String(o.value),name:String(o.dataset.name||''),
      phone:String(o.dataset.phone||''),code:String(o.textContent||'').split('·')[0].trim()
    }));
    const allVehicles=Array.from(vs.options).filter(o=>o.value&&o.dataset.customer)
      .map(o=>o.cloneNode(true));
    const placeholder=vs.options[0].cloneNode(true);
    placeholder.textContent='— Select customer first / Manual Vehicle —';
    const status=document.createElement('span');
    status.className='am223-vehicle-status';status.setAttribute('aria-live','polite');
    vs.closest('label')?.appendChild(status);
    const popup=document.createElement('div');
    popup.id='jcCustomerSuggestions';popup.setAttribute('role','listbox');
    document.body.appendChild(popup);
    const inputFields=['jcReg','jcMake','jcModel','jcYear','jcVariant','jcFuel',
      'jcOdo','jcChassis','jcEngine'];
    function clearVehicle(){
      inputFields.forEach(id=>{const field=document.getElementById(id);if(field)field.value=''});
    }
    function refreshList(preserve){
      const customer=String(cs.value||'');
      const prior=String(vs.value||'');
      const allowed=allVehicles.filter(o=>String(o.dataset.customer||'')===customer);
      const existing=allowed.some(o=>o.value===prior);
      vs.replaceChildren(placeholder.cloneNode(true));
      allowed.forEach(o=>{o.hidden=false;vs.appendChild(o.cloneNode(true))});
      if(preserve&&existing)vs.value=prior;
      else {
        vs.value='';
        if(prior&&!existing)clearVehicle();
      }
      if(!customer){
        status.textContent='Customer Master select karein, tab unki registered vehicles dikhenge.';
      }else if(allowed.length){
        status.textContent=allowed.length+' linked vehicle(s) — only selected customer.';
      }else{
        status.textContent='Is customer ki registered vehicle nahi mili. Neeche manual vehicle details bhar sakte hain.';
      }
    }
    const originalCustomer=window.amFillCustomer;
    const originalVehicle=window.amFillVehicle;
    if(typeof originalCustomer!=='function'||typeof originalVehicle!=='function')return;
    let currentOwner=String(cs.value||'');
    window.amFillCustomer=function(){
      const next=String(cs.value||'');
      if(next&&cs.selectedOptions.length)originalCustomer.apply(this,arguments);
      else if(currentOwner){
        ['jcCustomerName','jcCustomerPhone','jcCustomerEmail','jcCustomerAddress']
          .forEach(id=>{const el=document.getElementById(id);if(el)el.value=''});
      }
      refreshList(next===currentOwner);
      currentOwner=next;
    };
    window.amFillVehicle=function(){
      const o=vs.selectedOptions[0];
      if(!o||!o.value)return;
      if(!cs.value||String(o.dataset.customer||'')!==String(cs.value)){
        vs.value='';clearVehicle();
        status.textContent='Vehicle selection rejected: customer mismatch.';
        return;
      }
      originalVehicle.apply(this,arguments);
      refreshList(true);
    };
    const pos=()=>{
      const rect=name.getBoundingClientRect();
      const width=Math.max(260,Math.min(520,rect.width));
      popup.style.width=width+'px';
      popup.style.left=Math.max(5,Math.min(rect.left,window.innerWidth-width-5))+'px';
      popup.style.top=(window.innerHeight-rect.bottom<300&&rect.top>300?
        Math.max(5,rect.top-305):rect.bottom+4)+'px';
    };
    const hide=()=>popup.style.display='none';
    function choose(row){
      cs.value=row.id;
      window.amFillCustomer();
      hide();name.focus();
    }
    function suggestions(){
      const term=String(name.value||'').trim().toLocaleLowerCase();
      popup.replaceChildren();
      if(!term){hide();return}
      const matches=customerOptions.filter(c=>
        [c.name,c.phone,c.code].some(v=>v.toLocaleLowerCase().includes(term))).slice(0,25);
      if(!matches.length){hide();return}
      matches.forEach(c=>{
        const item=document.createElement('button');item.type='button';
        item.setAttribute('role','option');item.textContent=c.name||('Customer #'+c.id);
        const sub=document.createElement('span');sub.className='am223-minor';
        sub.textContent=[c.phone,c.code].filter(Boolean).join(' · ');
        item.appendChild(sub);
        item.addEventListener('pointerdown',e=>{e.preventDefault();choose(c)});
        popup.appendChild(item);
      });
      pos();popup.style.display='block';
    }
    let lastCustomerName=String(cs.selectedOptions[0]?.dataset.name||'');
    name.addEventListener('input',()=>{
      const newName=String(name.value||'').trim();
      if(cs.value&&newName!==lastCustomerName){
        cs.value='';
        refreshList(false);
        currentOwner='';
      }
      suggestions();
    });
    cs.addEventListener('change',()=>{
      lastCustomerName=String(cs.selectedOptions[0]?.dataset.name||'');
      // Existing inline onchange calls amFillCustomer() automatically.
      // This handler only closes free-text suggestions.
      hide();
    });
    name.addEventListener('focus',suggestions);
    name.addEventListener('keydown',e=>{
      if(e.key==='Escape'){hide();return}
      if(e.key==='ArrowDown'&&popup.style.display==='block'){
        e.preventDefault();popup.querySelector('button')?.focus();
      }
    });
    document.addEventListener('pointerdown',e=>{
      if(!popup.contains(e.target)&&e.target!==name)hide();
    });
    document.addEventListener('keydown',e=>{if(e.key==='Escape')hide()});
    window.addEventListener('resize',()=>{if(popup.style.display==='block')pos()});
    window.addEventListener('scroll',()=>{if(popup.style.display==='block')pos()},true);
    // Initial edit/old-input state: preserve only a vehicle that is actually
    // owned by the currently selected master customer.
    refreshList(true);
    console.info('AM223_JC_CUSTOMER_VEHICLE_SCOPE loaded');
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',ready);
  else ready();
})();
</script>
