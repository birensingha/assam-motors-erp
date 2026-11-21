/* AM260 synthetic keyboard test; no business submissions. */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const {JSDOM}=require('jsdom');
const userEvent=require('@testing-library/user-event').default;
const code=fs.readFileSync(__dirname+'/files/public/js/am260-unified-keyboard-entry.js','utf8');
async function test(){
  const html=[
   '<!doctype html><html><body>',
   '<script id="am254-global-draft-script" data-user="3" data-guard="erp"></script>',
   '<form id="voucher" method="POST" action="/erp/expenses">',
   '<select id="type" name="type"><option>PART</option><option>LABOUR</option></select>',
   '<input id="reference" name="reference" type="text" aria-autocomplete="list">',
   '<input id="description" name="description" type="text">',
   '<input id="quantity" name="quantity" type="number" value="1">',
   '<input id="rate" name="rate" type="number">',
   '<textarea id="note" name="note"></textarea>',
   '<button id="save" type="submit">Save</button></form>',
   '<form method="POST" action="/erp/customers" id="customer">',
   '<input id="customer-name" name="name" type="text">',
   '<input id="customer-phone" name="phone" type="tel">',
   '<input id="customer-email" name="email" type="email">',
   '<button type="submit">Save Customer</button></form>',
   '<form method="GET" action="/erp/sales-register" id="searchform">',
   '<input id="keyword" name="keyword"><input name="hiddensearch" type="hidden" value="x">',
   '<button type="submit">Search</button></form>',
   '<form method="POST" action="/erp/expenses/26/void" id="void-form">',
   '<input id="reason-void" type="text"><input id="admin-confirm" type="text">',
   '<button type="submit">Void</button></form>',
   '<form method="POST" action="/erp/expenses/create" id="one">',
   '<input id="single-field" type="text"><button type="submit">Save</button></form>',
   '<form method="POST" action="/erp/expenses/create" id="picker">',
   '<input class="am60-input" id="pick-input" aria-autocomplete="list">',
   '<input id="picker-quantity" type="number"><button type="submit">Save</button></form>',
   '<form method="POST" action="/erp/staff/create" id="password-form">',
   '<input id="staff-name" type="text">',
   '<input id="staff-password" type="password"><button type="submit">Save</button></form>',
   '</body></html>'
  ].join('');
  const dom=new JSDOM(html,{runScripts:'dangerously',pretendToBeVisual:true,url:'https://staging.assammotors.com/erp/expenses/create'});
  const w=dom.window,d=w.document,user=userEvent.setup({document:d});
  w.eval(code);
  assert.equal(w.__am260UnifiedKeyboardEntry,true,'Authenticated module boots');
  let submitCount=0;
  d.querySelectorAll('form').forEach(form=>form.addEventListener('submit',e=>{submitCount++;e.preventDefault();}));
  function focus(id){d.getElementById(id).focus();assert.equal(d.activeElement.id,id);}
  focus('type');
  await user.tab();
  assert.equal(d.activeElement.id,'reference','TYPE Tab -> REFERENCE');
  await user.tab();
  assert.equal(d.activeElement.id,'description','REFERENCE Tab -> DESCRIPTION');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'quantity','DESCRIPTION Enter -> QTY');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'rate','QTY Enter -> RATE');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'note','RATE Enter -> NOTES');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'note','Enter in textarea stays multiline');
  assert.equal(submitCount,0,'No accidental Save');
  await user.tab({shift:true});
  assert.equal(d.activeElement.id,'rate','Shift+Tab retained');

  focus('customer-name');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'customer-phone');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'customer-email');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'customer-email','Last input does not automatically Save');
  assert.equal(submitCount,0);

  focus('reference');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'reference','Searchable part Enter remains picker-owned');
  focus('pick-input');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'pick-input','Global searchable selector Enter untouched');

  focus('keyword');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'keyword','GET search form not intercepted');
  focus('reason-void');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'reason-void','Void confirmation untouched');
  focus('single-field');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'single-field','One field submit untouched');
  focus('staff-name');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'staff-name','Password-bearing form excluded');

  const added=d.createElement('input');
  added.disabled=true;added.id='disabled-field';
  d.getElementById('customer-email').before(added);
  focus('customer-phone');
  await user.keyboard('{Enter}');
  assert.equal(d.activeElement.id,'customer-email','Disabled input skipped');
  dom.window.close();
  console.log('AM260_ENTER_NEXT_TEXT_NUMBER_EMAIL_PASS');
  console.log('AM260_NATIVE_TAB_SHIFT_TAB_PICKER_ENTER_PRESERVED_PASS');
  console.log('AM260_NO_ACCIDENTAL_PAYMENT_VOID_LOGIN_OR_SAVE_PASS');
}
test().catch(e=>{console.error(e);process.exitCode=1;});
