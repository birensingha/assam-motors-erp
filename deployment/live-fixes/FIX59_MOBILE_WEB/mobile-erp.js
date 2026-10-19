/* ASSAM MOTORS FIX59: mobile-only ERP navigation, no business logic mutations */
(()=>{'use strict';
const mq=window.matchMedia('(max-width:767px)');
const source=document.querySelector('#erpSidebar .sidebar-nav');
const main=document.querySelector('main.page-shell');
if(!source||!main||document.getElementById('am59-top'))return;
const origin=location.origin;
const valid=a=>{try{const u=new URL(a,location.href);return u.origin===origin&&u.pathname.startsWith('/erp/')&&!/\/(logout|delete)/i.test(u.pathname);}catch(_){return false}};
const links=[],seen=new Set();
for(const a of source.querySelectorAll('a[href]')){
 if(!valid(a.href))continue;
 const url=new URL(a.href,location.href).href;
 if(seen.has(url))continue;
 seen.add(url);
 const label=(a.textContent||'').replace(/\s+/g,' ').replace(/^[^\w]+/u,'').trim();
 if(label)links.push({label,url});
}
const choose=(matcher,fallback)=>links.find(x=>matcher.test(new URL(x.url).pathname)||matcher.test(x.label))||fallback||null;
const site=p=>new URL(p,origin).href;
const items=[
 {label:'Home',icon:'⌂',target:choose(/dashboard/i,{label:'Dashboard',url:site('/erp/dashboard')})},
 {label:'Jobs',icon:'▤',target:choose(/job.cards/i,{label:'Job Cards',url:site('/erp/job-cards')})},
 {label:'Parts',icon:'▣',target:choose(/parts.master|inventory|stock|configuration/i)},
 {label:'Staff',icon:'♙',target:choose(/live.duty.location|attendance|staff/i)}
];
const elem=(tag,cls,text)=>{const n=document.createElement(tag);if(cls)n.className=cls;if(text!==undefined)n.textContent=text;return n};
const top=elem('header','am59-top');top.id='am59-top';
const menu=elem('button','am59-menu','☰');menu.type='button';menu.setAttribute('aria-label','Open mobile ERP navigation');
const brand=elem('div','am59-brand');brand.append(elem('strong','', 'ASSAM MOTORS'));
const title=elem('small','',document.querySelector('main.page-shell h1')?.textContent?.trim().slice(0,36)||'Workshop ERP');
brand.append(title);
const add=elem('a','am59-new','＋ New JC');add.href=site('/erp/job-cards/create');
top.append(menu,brand,add);
const bottom=elem('nav','am59-bottom');bottom.id='am59-bottom';bottom.setAttribute('aria-label','Mobile ERP tabs');
function active(target){if(!target)return false;const p=new URL(target.url).pathname.replace(/\/$/,'');return p===location.pathname.replace(/\/$/,'')||(p!== '/erp/dashboard'&&location.pathname.startsWith(p+'/'));}
for(const item of items){
 const nav=item.target?elem('a','am59-tab'):elem('button','am59-tab');
 if(item.target){nav.href=item.target.url;if(active(item.target))nav.setAttribute('aria-current','page')}
 else{nav.type='button';nav.disabled=true}
 nav.append(elem('span','am59-tabicon',item.icon),elem('span','',item.label));bottom.append(nav);
}
const more=elem('button','am59-tab');more.type='button';more.append(elem('span','am59-tabicon','⋯'),elem('span','','More'));bottom.append(more);
const overlay=elem('div','am59-shade');overlay.id='am59-shade';
const panel=elem('aside','am59-panel');panel.id='am59-panel';
panel.setAttribute('role','dialog');panel.setAttribute('aria-modal','true');panel.setAttribute('aria-label','ERP module menu');panel.tabIndex=-1;
const heading=elem('div','am59-panel-head');heading.append(elem('strong','','ERP Modules'));
const close=elem('button','am59-dismiss','×');close.type='button';close.setAttribute('aria-label','Close mobile menu');
heading.append(close);panel.append(heading);
const search=elem('input','am59-search');search.type='search';search.placeholder='Search ERP module';search.setAttribute('aria-label','Search ERP module');panel.append(search);
const entries=elem('div','am59-entries');panel.append(entries);
let beforeOpen=null;
function draw(){
 entries.replaceChildren();const q=search.value.trim().toLowerCase();
 const filtered=links.filter(x=>!q||x.label.toLowerCase().includes(q));
 for(const l of filtered){const n=elem('a','am59-entry',l.label);n.href=l.url;if(active(l))n.setAttribute('aria-current','page');entries.append(n);}
 if(!filtered.length)entries.append(elem('p','am59-empty','No matching ERP modules'));
}
draw();
function open(){if(!mq.matches)return;beforeOpen=document.activeElement;document.documentElement.classList.add('am59-drawer-open');menu.setAttribute('aria-expanded','true');search.focus({preventScroll:true})}
function dismiss(){document.documentElement.classList.remove('am59-drawer-open');menu.setAttribute('aria-expanded','false');(beforeOpen||menu).focus({preventScroll:true})}
menu.addEventListener('click',open);more.addEventListener('click',open);
close.addEventListener('click',dismiss);overlay.addEventListener('click',dismiss);
search.addEventListener('input',draw);
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.documentElement.classList.contains('am59-drawer-open'))dismiss()});
document.body.append(top,bottom,overlay,panel);
main.classList.add('am59-main');
const tables=()=>{for(const t of main.querySelectorAll('table')){if(t.closest('.am59-table-scroll,.table-responsive,[class*="table-scroll"],[contenteditable="true"]'))continue;
 const wrap=elem('div','am59-table-scroll');wrap.setAttribute('role','region');wrap.setAttribute('aria-label','Horizontally scrollable ERP table');wrap.tabIndex=0;t.before(wrap);wrap.appendChild(t);}};
tables();
let queued=false;
new MutationObserver(()=>{if(queued)return;queued=true;requestAnimationFrame(()=>{queued=false;tables()})}).observe(main,{childList:true,subtree:true});
function mode(){document.documentElement.classList.toggle('am59-mobile',mq.matches);
 if(!mq.matches)document.documentElement.classList.remove('am59-drawer-open')}
mode();if(mq.addEventListener)mq.addEventListener('change',mode);else mq.addListener(mode);
})();