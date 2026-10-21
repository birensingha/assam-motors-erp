/* ASSAM MOTORS FIX60 - accessible, non-destructive searchable native select */
(()=>{'use strict';
if(window.__AM_SELECT_V1)return;window.__AM_SELECT_V1=true;
const bySelect=new WeakMap();let active=null;let pending=false;
const norm=s=>String(s||'').normalize('NFKD').toLocaleLowerCase().replace(/\s+/g,' ').trim();
const list=document.createElement('div');list.className='am60-list';list.setAttribute('role','listbox');list.hidden=true;document.body.appendChild(list);
function close(){list.hidden=true;list.replaceChildren();if(active){active.input.setAttribute('aria-expanded','false');active=null;}}
function visible(e){return !!(e.getClientRects().length)&&getComputedStyle(e).visibility!=='hidden';}
function eligible(s){return s instanceof HTMLSelectElement&&!s.multiple&&!s.hasAttribute('data-am-no-search')&&!s.closest('[data-am-no-search],template,noscript')&&!s.classList.contains('select2-hidden-accessible')&&!s.classList.contains('am60-native')&&s.options.length>0&&visible(s);}
function displayOption(o){return (o.textContent||'').replace(/\s+/g,' ').trim();}
function itemsFor(state,q){
 const a=[];const terms=norm(q).split(' ').filter(Boolean);
 for(let i=0;i<state.select.options.length;i++){
  const o=state.select.options[i];if(o.disabled||o.closest('optgroup[disabled]')||(!o.value&&!displayOption(o)))continue;
  const corpus=norm(displayOption(o)+' '+o.value+' '+(o.dataset.search||''));
  if(terms.every(t=>corpus.includes(t)))a.push({option:o,index:i,label:displayOption(o)});
 }return a.slice(0,80);
}
function locate(){
 if(!active||list.hidden)return;
 const r=active.input.getBoundingClientRect();
 if(!r.width||r.bottom<0||r.top>innerHeight){close();return;}
 const maxH=Math.min(290,Math.max(145,innerHeight-20));
 const below=innerHeight-r.bottom,above=r.top;
 const upwards=below<200&&above>below;
 list.style.left=Math.max(6,Math.min(r.left,innerWidth-185))+'px';
 list.style.width=Math.min(Math.max(r.width,190),innerWidth-12)+'px';
 list.style.maxHeight=maxH+'px';
 list.style.top=upwards?'auto':Math.min(r.bottom+3,innerHeight-35)+'px';
 list.style.bottom=upwards?(innerHeight-r.top+3)+'px':'auto';
}
function chosen(state,o,fromKeyboard){
 const old=state.select.value;state.select.selectedIndex=o.index;
 state.input.value=o.label;state.input.setCustomValidity('');
 close();
 state.select.dispatchEvent(new Event('input',{bubbles:true}));
 state.select.dispatchEvent(new Event('change',{bubbles:true}));
 if(fromKeyboard)focusNext(state.input);
}
function focusNext(input){
 const form=input.closest('form');if(!form)return;
 const ctrls=[...form.querySelectorAll('input:not([type=hidden]),textarea,select:not(.am60-native)')]
  .filter(el=>!el.disabled&&el.tabIndex!==-1&&visible(el)&&!el.readOnly);
 const i=ctrls.indexOf(input);if(i<0)return;
 const next=ctrls.slice(i+1).find(x=>x!==input);
 if(next){next.focus();if(next.classList.contains('am60-input'))next.select();}
}
function draw(state,query,showAll=false){
 close();active=state;
 const choices=itemsFor(state,showAll?'':query);
 state.matches=choices;state.cursor=0;
 list.replaceChildren();list.hidden=false;state.input.setAttribute('aria-expanded','true');
 if(!choices.length){
  const n=document.createElement('div');n.className='am60-empty';n.textContent='No matching record. Select from Master.';list.appendChild(n);
 }else{
  choices.forEach((x,i)=>{
   const item=document.createElement('div');item.className='am60-option';item.setAttribute('role','option');item.id='am60-opt-'+state.uid+'-'+i;
   item.textContent=x.label;item.title=x.option.value;item.dataset.idx=i;
   item.setAttribute('aria-selected',i===0?'true':'false');
   item.addEventListener('mousedown',e=>e.preventDefault());
   item.addEventListener('click',e=>{e.preventDefault();chosen(state,x,false);state.input.focus();});
   list.appendChild(item);
  });
 }
 locate();paint(state);
}
function paint(s){
 const all=list.querySelectorAll('[role=option]');
 all.forEach((el,i)=>{el.classList.toggle('am60-active',i===s.cursor);el.setAttribute('aria-selected',i===s.cursor?'true':'false');});
 const target=all[s.cursor];
 if(target){s.input.setAttribute('aria-activedescendant',target.id);target.scrollIntoView({block:'nearest'});}
 else s.input.removeAttribute('aria-activedescendant');
}
function sync(s,force=false){
 const o=s.select.selectedOptions[0];const label=o&&o.value?displayOption(o):'';
 if(force||document.activeElement!==s.input){s.input.value=label;}
 const disabled=s.select.disabled||!!s.select.closest('fieldset[disabled]');
 s.input.disabled=disabled;s.input.required=s.select.required;
 s.input.placeholder=s.select.getAttribute('data-placeholder')||s.select.querySelector('option[value=""]')?.textContent?.trim()||s.select.getAttribute('aria-label')||'Type to search and select...';
 if(active===s&&!list.hidden)draw(s,s.input.value);
}
function init(sel){
 if(bySelect.has(sel)||!eligible(sel))return;
 const box=document.createElement('div');box.className='am60-box';
 const input=document.createElement('input');input.type='text';input.className='am60-input';
 input.autocomplete='off';input.spellcheck=false;input.setAttribute('role','combobox');
 input.setAttribute('aria-autocomplete','list');input.setAttribute('aria-haspopup','listbox');input.setAttribute('aria-expanded','false');
 input.setAttribute('aria-label',sel.getAttribute('aria-label')||sel.getAttribute('name')||'Search and select');
 input.addEventListener('focus',()=>{input.select();draw(state,'',true);});
 input.addEventListener('click',()=>{if(active!==state)draw(state,'',true);});
 input.addEventListener('input',()=>{
  // The previous canonical selection must not silently survive unmatched text.
  if(sel.selectedIndex>=0&&sel.options[sel.selectedIndex]?.value){
   sel.selectedIndex=sel.querySelector('option[value=""]')?.index??-1;
   sel.dispatchEvent(new Event('change',{bubbles:true}));
  }
  input.setCustomValidity('');
  draw(state,input.value);
 });
 input.addEventListener('keydown',e=>{
  if(e.key==='ArrowDown'||e.key==='ArrowUp'){
   e.preventDefault();e.stopPropagation();
   if(active!==state)draw(state,'',true);
   if(state.matches.length){state.cursor=(state.cursor+(e.key==='ArrowDown'?1:-1)+state.matches.length)%state.matches.length;paint(state);}
  }else if(e.key==='Enter'){
   if(active===state&&!list.hidden){
    e.preventDefault();e.stopPropagation();if(state.matches[state.cursor])chosen(state,state.matches[state.cursor],true);
   }else if(!sel.value){e.preventDefault();draw(state,'',true);}
  }else if(e.key==='Escape'){
   if(active===state){e.preventDefault();close();sync(state,true);}
  }
 });
 input.addEventListener('blur',()=>{setTimeout(()=>{if(active===state&&document.activeElement!==input)close();sync(state,true);},120);});
 const arrow=document.createElement('span');arrow.className='am60-arrow';arrow.textContent='⌄';arrow.setAttribute('aria-hidden','true');
 box.append(input,arrow);sel.before(box);sel.classList.add('am60-native');sel.tabIndex=-1;
 const uid=Math.random().toString(36).slice(2,9);
 const state={uid,select:sel,input,box,matches:[],cursor:0};bySelect.set(sel,state);
 sel.addEventListener('change',()=>sync(state,true));
 new MutationObserver(()=>sync(state)).observe(sel,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['disabled','required','selected','value','class']});
 if(sel.form){sel.form.addEventListener('submit',e=>{
  if(sel.required&&!sel.value&&!e.defaultPrevented){e.preventDefault();input.setCustomValidity('Select a matching record from the list.');input.reportValidity();input.focus();draw(state,'',true);}
 },true);}
 sync(state,true);
}
function scan(){pending=false;document.querySelectorAll('select').forEach(init);}
function schedule(){if(pending)return;pending=true;requestAnimationFrame(scan);}
function boot(){scan();new MutationObserver(schedule).observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:['class','style','hidden','disabled']});}
window.addEventListener('scroll',locate,true);window.addEventListener('resize',locate);
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&active)close();},true);
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();