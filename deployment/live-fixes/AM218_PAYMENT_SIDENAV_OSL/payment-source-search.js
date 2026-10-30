/* AM218: one search for existing PART + OSL outstanding sources.
 * UI-only; choosing a result changes the original source_type/source_key fields
 * and delegates ALL accounting validation to the existing Laravel payment service.
 */
(() => {
  'use strict';
  function init() {
    const input=document.getElementById('am218-source-search');
    const list=document.getElementById('am218-source-results');
    const note=document.getElementById('am218-source-choice');
    const type=document.getElementById('source-type');
    const key=document.getElementById('source-key');
    const vendorSelect=document.getElementById('payment-vendor');
    const multi=document.getElementById('p133-multi-toggle');
    if (![input,list,note,type,key].every(Boolean)) return;
    const all=(Array.isArray(window.AM_PAYMENT_SOURCES)?window.AM_PAYMENT_SOURCES:[])
      .filter(s=>['PURCHASE_INVOICE','OSL_JOB'].includes(s.type) &&
        typeof s.key==='string' && Number(s.outstanding)>0);
    const vendorNames={};
    if(vendorSelect) Array.from(vendorSelect.options).forEach(o=>{
      if(o.value)vendorNames[o.value]=o.textContent||'';
    });
    let matches=[],active=0;
    const kind=s=>s.type==='OSL_JOB'?'OSL Purchase':'Parts Purchase';
    const norm=v=>String(v||'').toLocaleLowerCase('en-IN').trim();
    function searchText(s){
      return norm([s.label,s.payee,s.key,s.job_no,s.vehicle,
        vendorNames[String(s.vendor_id||'')]].join(' '));
    }
    function close(){
      list.hidden=true;
      input.setAttribute('aria-expanded','false');
    }
    function choose(s){
      // Select an existing, server-recognised type/key pair; no invented source IDs.
      if(multi && multi.checked){
        multi.checked=false;
        multi.dispatchEvent(new Event('change',{bubbles:true}));
      }
      type.value=s.type;
      type.dispatchEvent(new Event('change',{bubbles:true}));
      if(!Array.from(key.options).some(o=>o.value===s.key)){
        note.textContent='This payable is no longer selectable. Refresh the page and retry.';
        note.setAttribute('role','alert');close();return;
      }
      key.value=s.key;
      key.dispatchEvent(new Event('change',{bubbles:true}));
      input.value=[s.payee||'',String(s.label||'').split(' · ')[0]].filter(Boolean).join(' · ');
      note.textContent='Selected '+kind(s)+' · '+(s.label||s.key)+
        ' — You may enter a smaller amount for a partial payment. Posting is validated by the server.';
      note.removeAttribute('role');
      close();
    }
    function highlight(){
      Array.from(list.querySelectorAll('button[data-index]')).forEach((b,i)=>{
        b.setAttribute('aria-selected',i===active?'true':'false');
      });
    }
    function render() {
      const q=norm(input.value),terms=q.split(/\s+/).filter(Boolean);
      matches=all.filter(s=>terms.every(w=>searchText(s).includes(w)))
        .sort((a,b)=>{
          // When search is empty, show OSL and PART bills together, OSL first.
          if(!terms.length)return (a.type==='OSL_JOB'?0:1)-(b.type==='OSL_JOB'?0:1);
          return 0;
        }).slice(0,60);
      list.replaceChildren();active=0;
      if(!matches.length){
        const empty=document.createElement('div');
        empty.className='am218-empty';
        empty.textContent=q?'No matching unpaid Parts / OSL Purchase found.':'No unpaid Parts or OSL purchases found.';
        list.appendChild(empty);
      }
      matches.forEach((s,i)=>{
        const b=document.createElement('button');
        b.type='button';b.dataset.index=String(i);
        b.setAttribute('role','option');
        b.setAttribute('aria-selected',i===0?'true':'false');
        b.className='am218-result';
        const heading=document.createElement('strong');
        heading.textContent=kind(s)+' · '+(s.payee||'Vendor');
        const detail=document.createElement('span');
        detail.textContent=s.label||s.key;
        const amt=document.createElement('small');
        amt.textContent='Outstanding ₹'+Number(s.outstanding).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2});
        b.append(heading,detail,amt);
        b.addEventListener('pointerdown',e=>{e.preventDefault();choose(s);});
        b.addEventListener('click',()=>choose(s));
        list.appendChild(b);
      });
      list.hidden=false;
      input.setAttribute('aria-expanded','true');
    }
    input.addEventListener('focus',render);
    input.addEventListener('input',render);
    input.addEventListener('keydown',e=>{
      if(e.key==='Escape'){e.preventDefault();close();return;}
      if(['ArrowDown','ArrowUp'].includes(e.key)){
        e.preventDefault();
        if(list.hidden){render();return;}
        if(matches.length){
          active=(active+(e.key==='ArrowDown'?1:-1)+matches.length)%matches.length;
          highlight();
          list.querySelectorAll('button[data-index]')[active]?.scrollIntoView({block:'nearest'});
        }
      }
      if(e.key==='Enter' && !list.hidden && matches[active]){
        e.preventDefault();choose(matches[active]);
      }
    });
    document.addEventListener('pointerdown',e=>{
      if(!e.target.closest('#am218-search-wrap'))close();
    });
    type.addEventListener('change',()=>{note.textContent='';input.value='';close();});
    // Selection by search triggers the type change above. Restore the description
    // after the original form has been synchronously rebuilt.
    close();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);
  else init();
})();
