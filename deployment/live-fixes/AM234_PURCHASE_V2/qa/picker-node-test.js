const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
class Fake {
 constructor(tag='div'){this.tagName=tag;this.children=[];this.events={};this.dataset={};this.attrs={};this.value='';this.disabled=false;this.hidden=false;this.className='';this.isConnected=true;this.style={setProperty(){}};this.classList={add(){},remove(){},toggle(){}};}
 addEventListener(name,fn){(this.events[name]??=[]).push(fn);}
 dispatchEvent(ev){ev.target??=this;for(const fn of this.events[ev.type]||[])fn(ev);return true;}
 setAttribute(k,v){this.attrs[k]=String(v)}
 removeAttribute(k){delete this.attrs[k]}
 appendChild(n){this.children.push(n);return n}
 replaceChildren(...xs){this.children=xs}
 querySelectorAll(sel){return this.children.filter(e=>sel.slice(1)===e.className||sel.includes(e.className))}
 contains(el){return this===el||this.children.includes(el)}
 getBoundingClientRect(){return {left:10,top:120,bottom:148,width:200}}
 scrollIntoView(){}
 setCustomValidity(m){this.validation=m}
 focus(){this.dispatchEvent({type:'focus'})}
 remove(){this.isConnected=false}
}
const input=new Fake('input'),select=new Fake('select'),allocation=new Fake('select'),host=new Fake('div');
input.disabled=true;select.disabled=true;allocation.value='STOCK';
select.options=[
 {value:'',textContent:'Select Job Card'},
 {value:'11',textContent:'JC-2026-0011 — AS01HC0595 — SUMEN ROY'},
 {value:'12',textContent:'JC-2026-0012 — AS02K0860 — OTHER CUSTOMER'}
];
const row={querySelector(sel){return ({'.job-filter':input,'.job-select':select,'.allocation':allocation,'.job-filter-wrap':host})[sel]}};
const document={body:new Fake(),createElement:n=>new Fake(n),addEventListener(){}};
const window={innerHeight:900,addEventListener(){},setTimeout(fn){fn()}};
const ctx={document,window,Event:class{constructor(type,opts){this.type=type;Object.assign(this,opts||{})}},console};
vm.createContext(ctx);
vm.runInContext(fs.readFileSync(require('path').join(__dirname,'..','jc-picker-function.js'),'utf8'),ctx);
const init=vm.runInContext('wireJobFilter',ctx);
init(row);
assert.equal(input.disabled,true,'STOCK must disable JC lookup');
allocation.value='JOB';allocation.dispatchEvent({type:'change'});
assert.equal(input.disabled,false,'JOB must enable JC lookup');
for (const term of ['sum','JC-2026-0011','AS01HC0595']) {
 input.value=term;input.dispatchEvent({type:'input'});
 const menu=document.body.children[0];
 assert.equal(menu.children.length,1,'Search '+term+' must return one result');
 assert.match(menu.children[0].textContent,/SUMEN ROY/);
}
const menu=document.body.children[0];
menu.children[0].dispatchEvent({type:'pointerdown',preventDefault(){}});
assert.equal(select.value,'11','Selection must link actual server-rendered ID=11');
assert.match(input.value,/JC-2026-0011/);
input.value='unknown';input.dispatchEvent({type:'input'});
assert.equal(select.value,'','Changing search clears stale JC ID');
assert.match(menu.children[0].textContent,/No matching/);
allocation.value='STOCK';allocation.dispatchEvent({type:'change'});
assert.equal(input.disabled,true);
assert.equal(select.value,'');
console.log('PASS JC No, vehicle, customer lookup; pointer selection => ID 11; stale ID clearing; STOCK reset');
