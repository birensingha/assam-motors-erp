/* AM259: test actual deployed Estimate editor focus with a simulated browser.
   No customer, purchase, staff or database records are used. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const userEvent = require('@testing-library/user-event').default;

async function run() {
  const jsPath = process.argv[2];
  if (!jsPath) throw Error('Usage: node keyboard-smoke.cjs PATH_TO_DEPLOYED_ESTIMATE_JS');
  const js = fs.readFileSync(jsPath,'utf8');
  assert.ok(js.includes('AM259_TYPE_TAB_REFERENCE_FOCUS'),'AM259 marker missing');
  const dom = new JSDOM(
    '<!doctype html><html><body>' +
    '<button type="button" id="add-estimate-line">Add Line</button>' +
    '<table><tbody id="estimate-lines"></tbody></table>' +
    '<output id="estimate-preview-total"></output>' +
    '</body></html>',
    {runScripts:'dangerously', pretendToBeVisual:true, url:'https://staging.assammotors.com/erp/job-cards/62/estimate'}
  );
  const w=dom.window;
  w.AM_ESTIMATE_DATA={
    initial:[],
    parts:[{id:3,code:'AM-0164',description:'Clutch Release Bearing',hsn:'8708',unit:'PCS',rate:479,tax:18}],
    labour:[],osl:[]
  };
  w.eval(js);
  const doc=w.document;
  const body=doc.querySelector('#estimate-lines');
  assert.equal(body.querySelectorAll('tr').length,1,'initial blank row exists');
  doc.querySelector('#add-estimate-line').click();
  assert.equal(body.querySelectorAll('tr').length,2,'second blank row exists');

  const tr=body.querySelectorAll('tr')[1];
  const type=tr.querySelector('.e-type');
  const reference=tr.querySelector('.e-ref-search');
  const description=tr.querySelector('.e-desc');
  const qty=tr.querySelector('.e-qty');
  const unit=tr.querySelector('.e-unit');
  const rate=tr.querySelector('.e-rate');

  const user=userEvent.setup({document:doc});
  type.focus();
  assert.equal(doc.activeElement,type,'TYPE initially focused');
  type.value='ROT';
  type.dispatchEvent(new w.Event('change',{bubbles:true}));
  assert.equal(doc.activeElement,type,
    'TYPE change must not force Reference focus and skip it on next Tab');

  await user.tab();
  assert.equal(doc.activeElement,reference,'TYPE Tab must focus REFERENCE, not DESCRIPTION');
  await user.tab();
  assert.equal(doc.activeElement,description,'REFERENCE Tab must focus DESCRIPTION');
  await user.tab();
  assert.equal(doc.activeElement,qty,'DESCRIPTION Tab must focus QTY');
  await user.tab();
  assert.equal(doc.activeElement,unit,'QTY Tab must focus UNIT');
  await user.tab();
  assert.equal(doc.activeElement,rate,'UNIT Tab must focus RATE');

  // Reverse Tab from REFERENCE must return to TYPE.
  type.focus();
  await user.tab();
  await user.tab({shift:true});
  assert.equal(doc.activeElement,type,'Shift+Tab returns Reference -> TYPE');

  // Switching TYPE should not change data already saved in other rows.
  const first=body.querySelectorAll('tr')[0];
  first.querySelector('.e-desc').value='Clutch Release Bearing';
  type.value='PART';
  type.dispatchEvent(new w.Event('change',{bubbles:true}));
  assert.equal(first.querySelector('.e-desc').value,'Clutch Release Bearing');
  assert.equal(doc.activeElement,type);
  dom.window.close();
  console.log('AM259_TYPE_CHANGE_DOES_NOT_STEAL_FOCUS_PASS');
  console.log('AM259_TYPE_TAB_REFERENCE_DESCRIPTION_QTY_UNIT_RATE_PASS');
  console.log('AM259_SHIFT_TAB_AND_OTHER_ROWS_PRESERVED_PASS');
}
run().catch(err=>{console.error(err);process.exitCode=1;});
