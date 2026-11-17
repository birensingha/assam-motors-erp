/* AM263 GRN dynamic row / restored Part Name unit QA.
 * Node built-ins only; no browser, network, user drafts or server data.
 */
'use strict';
const fs=require('node:fs');
const vm=require('node:vm');
const assert=require('node:assert/strict');
const helper=fs.readFileSync(__dirname+'/grn_draft_js_helpers.js','utf8');
const rows=[{index:0,hidden:{value:'236'},display:{value:'',closest(){return rowFor(this)}}}];
function rowFor(hidden){
  const row=rows.find(r=>r.hidden===hidden||r.display===hidden);
  return {querySelector(q){if(q==='input.part-search')return row.display;return null;}};
}
for(const row of rows){row.hidden.closest=function(){return rowFor(this)}}
const body={
  querySelector(query){
    if(query==='input.part-search')return rows[0].display;
    const m=/input\[name="lines\[(\d+)\]\[part_master_id\]"\]/.exec(query);
    return m?rows.find(r=>r.index===Number(m[1]))?.hidden:null;
  },
  querySelectorAll(query){
    if(query==='tr.line')return rows;
    return [];
  }
};
const options=[{value:'AM-0236 | Brake Shoe',getAttribute(name){return name==='data-id'?'236':null;}}];
const list={querySelectorAll(query){assert.equal(query,'option');return options;}};
const form={
 id:'am260-grn-form',
 querySelector(q){if(q==='#am260-grn-rows')return body;return null;},
 querySelectorAll(q){return q==='#am260-grn-rows .part-id'?rows.map(r=>r.hidden):[];}
};
const ctx={
  window:{am260AddGrnRow(){
    const i=rows.length;
    const item={index:i,hidden:{value:''},display:{value:''}};
    item.hidden.closest=function(){return rowFor(this)};
    rows.push(item);
  }},
  document:{getElementById(q){return q==='am260-part-options'?list:null;}},
  Array,Number,Object,String,
};
vm.createContext(ctx);
vm.runInContext(helper,ctx);
const draft={fields:[
 {n:'lines[0][part_master_id]',v:'236'},
 {n:'lines[0][qty]',v:'1'},
 {n:'lines[1][part_master_id]',v:'236'},
 {n:'lines[2][part_master_id]',v:'236'},
 {n:'lines[2][qty]',v:'3'},
]};
ctx.prepareGrnDraftRows(form,draft);
assert.equal(rows.length,3,'missing dynamic rows must be recreated before restore');
assert.equal(draft.fields.length,5,'original user draft must remain untouched');
rows[1].hidden.value='236';
rows[2].hidden.value='236';
assert.equal(ctx.hydrateGrnPartSearches(form),0);
assert.equal(rows[0].display.value,'AM-0236 | Brake Shoe');
assert.equal(rows[2].display.value,'AM-0236 | Brake Shoe');
rows[1].hidden.value='999';
assert.equal(ctx.hydrateGrnPartSearches(form),1,'unknown Part Master must preserve draft and flag missing');
const other={id:'not-grn-form',querySelector(){throw Error('must not touch other forms')}};
ctx.prepareGrnDraftRows(other,draft);
assert.equal(ctx.hydrateGrnPartSearches(other),0);
console.log('AM263_GRN_DYNAMIC_DRAFT_RESTORE_JS_UNIT_QA_PASS');
