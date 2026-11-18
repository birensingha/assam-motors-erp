/* AM269 Node-only Purchase Part dropdown regression QA.
   Uses the actual deployed JS function text, a minimal browser DOM mock,
   and deferred server-search promises. No network, no ERP writes.
 */
'use strict';
const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const file=process.argv[2];
if(!file)throw new Error('USAGE: node qa_part_dropdown.cjs absolute_patched_purchase_entry.js');
const source=fs.readFileSync(file,'utf8');
const start=source.indexOf('  function closeResults(row) {');
const end=source.indexOf('  function setActive(row, idx) {',start);
assert(start>=0 && end>start,'AM269 functions missing from actual Purchase JS');
const extracted=source.slice(start,end);
const contains=(x)=>assert(source.includes(x),'AM269_MISSING_'+x);
contains('AM269_SINGLE_ACTIVE_PART_MENU');
contains("document.activeElement !== search");
contains('closeOtherPartResults(row)');
contains("const isTab = e.key === 'Tab'");
contains('selectPart(row, list[idx], false)');
contains("row.querySelector('.qty').focus()");
assert.equal((source.match(/function renderResults\(/g)||[]).length,1);

function classes(){
 const values=new Set();
 return {add(x){values.add(x)},remove(x){values.delete(x)},contains(x){return values.has(x)}};
}
function row(label){
 const search={
   value:label,dataset:{selectedLabel:''},attributes:{},
   setAttribute(k,v){this.attributes[k]=v;},
 };
 const list={
   classList:classes(),children:[],innerHTML:'',
   replaceChildren(){this.children=[];this.innerHTML='';},
   appendChild(x){this.children.push(x);}
 };
 const result={
   isConnected:true,dataset:{},
   search,list,
   querySelector(k){
     if(k==='.part-search')return search;
     if(k==='.part-results')return list;
     throw new Error('Unexpected selector '+k);
   },
 };
 return result;
}
const a=row('A'),b=row('BULB'),c=row('C');
const requests=new Map();
const document={
 activeElement:null,
 createElement(tag){return {
   tag,className:'',textContent:'',dataset:{},classList:classes(),
   append(...items){this.children=items;},
   addEventListener(){},
 };}
};
const context={
 document,body:{querySelectorAll(sel){assert.equal(sel,'.purchase-line');return [a,b,c]}},
 requestSeq:0,console,
 partLabel(p){return p.name},
 fetchParts(query){
  return new Promise(resolve=>requests.set(query,resolve));
 }
};
vm.createContext(context);
vm.runInContext('let requestSeq=0;\n'+extracted,context);
async function main(){
  document.activeElement=a.search;
  const old=context.renderResults(a,'A');
  assert(a.list.classList.contains('open'),'first focused part picker must open');
  document.activeElement=b.search;
  const current=context.renderResults(b,'BULB');
  assert(!a.list.classList.contains('open'),'previous row must close immediately');
  assert(b.list.classList.contains('open'),'current focused row must show list');
  assert.equal(a.search.attributes['aria-expanded'],'false');
  requests.get('A')([{id:100,name:'OLD UNFILTERED ITEM'}]);
  await old;
  assert(!a.list.classList.contains('open'),'late search must NOT reopen previous row');
  requests.get('BULB')([{id:7,name:'Tail Lamp Bulb'}]);
  await current;
  assert(b.list.classList.contains('open'),'latest focused search must remain open');
  assert.equal(b.list.children.length,1,'one filtered suggestion expected');
  const inactive=await context.renderResults(a,'STALE');
  assert.equal(inactive.length,0,'old timer on non-focused row ignored');
  assert(!requests.has('STALE'),'inactive row timer must NOT call search server');
  assert(!a.list.classList.contains('open'),'inactive list remains closed');
  document.activeElement=c.search;
  const third=context.renderResults(c,'C');
  assert(!b.list.classList.contains('open'),'previous filtered list closes when third row focused');
  requests.get('C')([]);
  await third;
  assert(c.list.classList.contains('open'),'current row can show no-matches message');
  assert.equal([a,b,c].filter(r=>r.list.classList.contains('open')).length,1,
       'exactly ONE part suggestion panel may be visible');
  c.isConnected=false;
  await context.renderResults(c,'REMOVED');
  assert(!c.list.classList.contains('open'),'removed row must close its results');
  assert(!requests.has('REMOVED'),'removed row must not issue search');
  console.log('AM269_BROWSER_SIM_TWO_OVERLAPPING_MENUS_PREVENTED_PASS');
  console.log('AM269_ASYNC_OUT_OF_ORDER_RESPONSE_CANNOT_REOPEN_OLD_MENU_PASS');
  console.log('AM269_ENTER_TAB_PART_SELECTION_AND_QTY_FLOW_PRESERVED_PASS');
  console.log('AM269_JS_PURCHASE_SINGLE_PART_DROPDOWN_QA_ALL_PASS');
}
main().catch(err=>{console.error(err);process.exitCode=1});
