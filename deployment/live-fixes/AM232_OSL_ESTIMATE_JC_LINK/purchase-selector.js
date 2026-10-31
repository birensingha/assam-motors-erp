/* AM232_OSL_JOB_LINK_SELECTOR: one JC/Vehicle search with JC-only approved OSL billing work.
 * Original job_card_id, labour_line_id, OSL Master and accounting fields remain authoritative.
 */
(function(){
'use strict';
const body=document.getElementById('osl-lines'),form=document.getElementById('osl-form');
const lines=Array.isArray(window.AM232_OSL_BILLING_LINES)?window.AM232_OSL_BILLING_LINES:[];
if(!body||!form)return;
const clean=x=>String(x||'').toLocaleLowerCase('en-IN').trim();
const normalize=x=>clean(x).replace(/[^a-z0-9]+/g,'');
const byJob=id=>lines.filter(x=>String(x.job_card_id)===String(id)&&Number(x.id)>0);
const selectedOption=(select)=>select?.options?.[select.selectedIndex]||null;
function attach(row){
 if(row.dataset.am232Ready)return;
 const filter=row.querySelector('.job-filter'),job=row.querySelector('.job-card');
 const master=row.querySelector('.osl-master'),description=row.querySelector('.description');
 const billing=row.querySelector('.am146-jc-link');
 if(!filter||!job||!master||!billing)return;
 row.dataset.am232Ready='1';
 const holder=filter.closest('.job-filter-wrap');
 // Keep original select for backend submission, but only ONE JC selection control visible.
 job.style.position='absolute';job.style.left='-10000px';job.style.width='1px';
 job.style.height='1px';job.style.opacity='0';job.style.pointerEvents='none';
 job.tabIndex=-1;job.required=false; // original form+server enforce job_card_id
 const opts=[...job.options].filter(o=>o.value).map(o=>({value:o.value,label:String(o.textContent||'').trim()}));
 holder.style.position='relative';
 filter.placeholder='Select Job Card / Vehicle (type to search)';
 filter.setAttribute('role','combobox');filter.setAttribute('aria-autocomplete','list');
 filter.setAttribute('aria-expanded','false');
 const menu=document.createElement('div');menu.className='am232-job-menu';menu.hidden=true;
 holder.insertBefore(menu,job);
 function close(){menu.hidden=true;filter.setAttribute('aria-expanded','false');}
 function selectJob(value){
  const o=opts.find(x=>x.value===String(value));if(!o)return;
  job.value=o.value;filter.value=o.label;
  job.dispatchEvent(new Event('change',{bubbles:true}));
  refreshOsl();
  close();
 }
 function show(){
  const q=normalize(filter.value);
  const matches=opts.filter(x=>!q||normalize(x.label).includes(q)).slice(0,30);
  menu.replaceChildren();
  if(!matches.length){
   const empty=document.createElement('div');empty.textContent='No matching Job Card / Vehicle';empty.className='am232-empty';menu.append(empty);
  }
  for(const entry of matches){
   const button=document.createElement('button');button.type='button';button.className='am232-job-choice';button.textContent=entry.label;
   button.addEventListener('pointerdown',e=>e.preventDefault());
   button.addEventListener('click',()=>selectJob(entry.value));menu.append(button);
  }
  menu.hidden=false;filter.setAttribute('aria-expanded','true');
 }
 const col=master.parentElement;
 const match=document.createElement('select');match.type='button';match.className='am232-estimate-osl';
 match.setAttribute('aria-label','OSL job work from this Job Card');col.insertBefore(match,master);
 const note=document.createElement('div');note.className='am232-osl-note';col.append(note);
 const legacy=billing.closest('.am146-purchase-line-link');
 if(legacy)legacy.style.display='none'; // linked value still updated by AM146
 function refreshOsl(){
  const id=job.value,items=byJob(id),previous=match.value;
  match.replaceChildren();
  if(!id){
   note.textContent='Select Job Card first to load its OSL work.';
   match.hidden=true;master.hidden=true;master.style.display='none';return;
  }
  if(!items.length){
   match.hidden=true;
   master.hidden=false;master.style.display='';
   note.textContent='No OSL line transferred to this JC. Release approved Estimate → Send to Job Card, or select Master to create a new customer OSL line.';
   return;
  }
  match.hidden=false;master.hidden=true;master.style.display='none';
  const p=new Option('Select OSL Work from this Job Card','');match.add(p);
  for(const x of items){
   const label=String(x.description||'OSL Work');
   const o=new Option(label+' · Customer ₹'+Number(x.labour_rate||0).toFixed(2),String(x.id));
   match.add(o);
  }
  match.value=items.some(x=>String(x.id)===previous)?previous:'';
  if(!match.value && items.length===1)match.value=String(items[0].id);
  note.textContent='Only this Job Card’s transferred Estimate/OSL billing lines are selectable.';
  if(match.value)applyLine();
 }
 function applyLine(){
  const x=byJob(job.value).find(x=>String(x.id)===match.value);
  if(!x)return;
  billing.value=String(x.id);
  billing.dispatchEvent(new Event('change',{bubbles:true})); // writes hidden labour_line_id
  // Never use a global OSL Master record from an unrelated vehicle.
  const mm=[...master.options].find(o=>o.value&&normalize(o.dataset.name)===normalize(x.description));
  master.value=mm?.value||'';
  description.value=String(x.description||'');
  const rate=row.querySelector('.customer-rate'),gst=row.querySelector('.customer-gst');
  if(rate)rate.value=String(x.labour_rate||0);
  if(gst)gst.value=String(x.gst_percent||0);
  row.dispatchEvent(new Event('input',{bubbles:true}));
 }
 filter.addEventListener('focus',show);
 filter.addEventListener('click',show);
 filter.addEventListener('input',()=>{
   // No stale JC/OSL references when the search text changes.
   if(job.value&&clean(filter.value)!==clean(opts.find(x=>x.value===job.value)?.label||'')){
      job.value='';job.dispatchEvent(new Event('change',{bubbles:true}));
      billing.value='';billing.dispatchEvent(new Event('change',{bubbles:true}));
      match.value='';refreshOsl();
   }
   show();
 });
 filter.addEventListener('keydown',e=>{
   if(e.key==='Escape')close();
   if(e.key==='Enter'){
    const first=menu.querySelector('button.am232-job-choice');
    if(first&&!menu.hidden){e.preventDefault();first.click();}
   }
 });
 match.addEventListener('change',applyLine);
 job.addEventListener('change',refreshOsl);
 document.addEventListener('pointerdown',e=>{if(!holder.contains(e.target))close();});
 if(job.value){
  const selected=opts.find(o=>o.value===job.value);
  if(selected)filter.value=selected.label;
 }
 refreshOsl();
}
const observer=new MutationObserver(()=>{body.querySelectorAll('tr.osl-line').forEach(attach);});
observer.observe(body,{childList:true,subtree:true});
body.querySelectorAll('tr.osl-line').forEach(attach);
form.addEventListener('submit',e=>{
 const invalid=[...body.querySelectorAll('tr.osl-line')].find(row=>!row.querySelector('.job-card')?.value);
 if(invalid){
  e.preventDefault();
  invalid.querySelector('.job-filter')?.focus();
  alert('Select a valid Job Card / Vehicle from the dropdown for each OSL line.');
 }
},true);
})();