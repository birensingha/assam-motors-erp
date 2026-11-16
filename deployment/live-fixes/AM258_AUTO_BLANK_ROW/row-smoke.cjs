/* Synthetic browser DOM smoke checks; no live staff, stock or transaction POST. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const script = fs.readFileSync(__dirname + '/files/public/js/am258-auto-blank-entry.js', 'utf8');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

const templates = {
 estimate:{
   form:'estimate-form',body:'estimate-lines',add:'add-estimate-line',picker:'.e-ref-search',
   selected:'.e-ref-id',
   html:'<tr><td><select class="e-type"><option>PART</option></select></td>' +
        '<td><input class="e-ref-search"><input class="e-ref-id" type="hidden"></td>' +
        '<td><input class="e-desc" required><input class="e-qty" value="1"></td>' +
        '<td><input class="e-rate" value="0"><input class="e-disc" value="0"></td>' +
        '<td><input class="e-tax" value="18"></td>' +
        '<td><button type="button" class="e-remove">X</button></td></tr>'
 },
 purchase:{
   form:'purchase-form',body:'purchase-lines',add:'add-line',picker:'.part-search',
   selected:'.part-id',
   html:'<tr class="purchase-line"><td><input class="part-search">' +
        '<input class="part-id" type="hidden"></td><td><input class="qty" value="1"></td>' +
        '<td><input class="rate" value="0"><input class="disc" value="0"></td>' +
        '<td><input class="gst" value="0"><input class="job-filter"></td>' +
        '<td><button type="button" class="remove-line">X</button></td></tr>'
 },
 counter:{
   form:'p31-counter-invoice-form',body:'p31-lines',add:'p31-add-line',
   picker:'select[data-part]',selected:'select[data-part]',
   html:'<tr><td><select data-part required><option value="">Choose</option>' +
        '<option value="101">Part</option></select><input data-note></td>' +
        '<td><input data-qty value="1"><input data-rate required></td>' +
        '<td><input data-disc value="0"><input data-gst readonly value="0%"></td>' +
        '<td><button type="button" data-remove>X</button></td></tr>'
 }
};

async function check(kind) {
 const c=templates[kind];
 const html='<!doctype html><body><form method="post" id="'+c.form+'">' +
    '<button type="button" id="'+c.add+'">Add</button><table><tbody id="'+c.body+'"></tbody></table>' +
    '<button type="submit">Save</button></form></body>';
 const dom=new JSDOM(html,{runScripts:'dangerously',url:'https://staging.assammotors.com/erp/test'});
 const w=dom.window,d=w.document,body=d.getElementById(c.body);
 w.alert=function(){throw Error('Unexpected alert');};
 const add=d.getElementById(c.add);
 add.addEventListener('click',function(){
   body.insertAdjacentHTML('beforeend',c.html);
   if(!w.__am258AppendingBlank)body.lastElementChild.querySelector(c.picker).focus();
 });
 add.click();
 w.eval(script);
 d.dispatchEvent(new w.Event('DOMContentLoaded',{bubbles:true}));
 await delay(15);
 assert.equal(body.children.length,1,kind+' initial one blank');
 const first=body.firstElementChild;
 const input=first.querySelector(c.picker);
 if(kind==='estimate'){
   assert.equal(first.querySelector('.e-desc').required,false,'estimate blank desc optional');
   input.value='Brake Shoe';
   input.dispatchEvent(new w.Event('input',{bubbles:true}));
   first.querySelector('.e-desc').value='Brake Shoe';
 }else if(kind==='purchase'){
   input.value='AM-101 — Part';
   first.querySelector(c.selected).value='101';
   first.querySelector(c.selected).dispatchEvent(new w.Event('change',{bubbles:true}));
 }else{
   assert.equal(input.required,false,'counter blank part optional');
   assert.equal(first.querySelector('[data-rate]').required,false,'counter blank rate optional');
   input.value='101';
   input.dispatchEvent(new w.Event('change',{bubbles:true}));
   first.querySelector('[data-rate]').value='250';
 }
 await delay(15);
 assert.equal(body.children.length,2,kind+' new blank appended');
 assert.equal(body.lastElementChild.querySelector(c.picker).value,'',kind+' trailing entry empty');
 assert.equal(first.querySelector('.e-remove,.remove-line,[data-remove]').tabIndex,-1,'remove not in Tab sequence');
 let submittedRowCount=-1;
 d.getElementById(c.form).addEventListener('submit',function(){submittedRowCount=body.children.length;});
 d.getElementById(c.form).dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
 assert.equal(submittedRowCount,1,kind+' never sends empty row to legacy validation');
 await delay(170);
 assert.equal(body.children.length,2,kind+' blank restored when page remains');
 if(kind==='estimate') assert.equal(body.lastElementChild.querySelector('.e-desc').required,false);
 if(kind==='counter') assert.equal(body.lastElementChild.querySelector('[data-rate]').required,false);
 dom.window.close();
 console.log('AM258_SYNTHETIC_'+kind.toUpperCase()+'_READY_ROW_BLANK_SAVE_PASS');
}
(async function(){
 for(const k of ['estimate','purchase','counter'])await check(k);
 console.log('AM258_ALL_SYNTHETIC_ENTRY_TESTS_PASS');
})().catch(function(e){console.error(e);process.exitCode=1;});
