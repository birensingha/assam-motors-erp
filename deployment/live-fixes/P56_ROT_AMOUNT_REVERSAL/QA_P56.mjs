import {chromium} from 'playwright';
// P56 authenticated GET-only browser acceptance. NO POSTs, invoices, ledger,
// reversals, payments or staff activities are modified by these checks.
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!password)throw Error('P56 admin browser credential unavailable');
const browser=await chromium.launch({headless:true});
const failed=[];
try{
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
 await page.locator('input[type=password]').first().fill(password);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:900});
  let r=await page.goto(base+'/erp/workshop-tools/job-cards/21/billing-edit',{waitUntil:'domcontentloaded',timeout:30000});
  let panel=page.locator('#p55-rot-edit');
  let rows=panel.locator('[data-p55-item]');
  const found=r?.status()===200&&(await panel.count())===1&&(await rows.count())===7;
  console.log('P56_WIP_AMOUNT_EDITOR_ROT_SECTION_'+width+' '+(found?'PASS':'FAIL')+' HTTP '+r?.status());
  if(!found){failed.push('WIP amount editor '+width);continue;}
  const row=rows.first();
  const disabled=await row.locator('[data-p55-field]:disabled').count();
  const controls=await row.locator('[data-p55-field]').count();
  let locked=controls===7&&disabled===7&&(await row.locator('[data-p55-save]').isDisabled());
  await row.locator('[data-p55-lock]').click();
  await row.locator('[data-p55-reason]').selectOption({label:'Start / End time correction'});
  await row.locator('[name=admin_note]').fill('P56 browser review only — no submission or business data changes.');
  await row.locator('[data-p55-unlock]').click();
  const unlock=await row.locator('[data-p55-field]:enabled').count()===7&&await row.locator('[data-p55-save]').isEnabled();
  console.log('P56_WIP_LOCK_REASON_NOTE_UNLOCK_'+width+' '+(locked&&unlock?'PASS':'FAIL'));
  if(!locked||!unlock)failed.push('WIP lock/unlock '+width);
  r=await page.goto(base+'/erp/job-cards/21/edit',{waitUntil:'domcontentloaded',timeout:30000});
  const removed=r?.status()===200&&(await page.locator('#p55-rot-edit').count())===0;
  console.log('P56_NO_DUPLICATE_ROT_ON_JC_EDIT_'+width+' '+(removed?'PASS':'FAIL'));
  if(!removed)failed.push('duplicate panel on Edit '+width);
  r=await page.goto(base+'/erp/workshop-tools/job-cards/13/billing-edit',{waitUntil:'domcontentloaded',timeout:30000});
  const lockedPanel=page.locator('#p55-rot-edit');
  const lockBtns=await lockedPanel.locator('[data-p55-lock]').count();
  const saved=await lockedPanel.locator('[data-p55-save]:enabled').count();
  const link=await page.locator('#p56-invoice-finance-gate a').count();
  const readOnly=r?.status()===200&&(await lockedPanel.count())===1&&lockBtns===0&&saved===0&&link>0;
  console.log('P56_INVOICED_AMOUNT_EDITOR_READONLY_'+width+' '+(readOnly?'PASS':'FAIL')+' HTTP '+r?.status());
  if(!readOnly)failed.push('invoice read-only '+width);
  r=await page.goto(base+'/erp/job-cards/13/reversal',{waitUntil:'domcontentloaded',timeout:30000});
  const rev=page.locator('#p56-finance-control');
  const invoice=await rev.innerText().catch(()=>'');
  const hasFinanceForm=(await rev.locator('form[action*="/reversal/request"] input[name=scope][value=FINANCE]').count())===1;
  const hasAmountLink=(await rev.locator('a[href*="billing-edit"]').count())===1;
  const hasInvoiceLink=(await rev.locator('a[href*="/erp/invoices/"]').count())>=1;
  const noFakeReversed=invoice.includes('review')&&invoice.includes('not a posted reversal')&&
     !invoice.includes('Reopen CLOSED Job Card to WIP');
  const revOkay=r?.status()===200&&(await rev.count())===1&&hasFinanceForm&&hasAmountLink&&hasInvoiceLink&&noFakeReversed;
  console.log('P56_INVOICED_REVERSAL_CENTRE_CONTROLS_'+width+' '+(revOkay?'PASS':'FAIL')+' HTTP '+r?.status());
  if(!revOkay)failed.push('reversal centre '+width);
  const bodyScroll=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
  console.log('P56_REVERSAL_LAYOUT_'+width+' BODY_SCROLL='+bodyScroll);
  if(bodyScroll>18)failed.push('overflow '+width);
 }
}finally{await browser.close()}
if(failed.length)throw Error('P56_UI_QA_FAILED: '+failed.join('; '));
console.log('P56_ROT_IN_AMOUNT_INVOICE_READONLY_REVERSAL_NAVIGATION_QA_PASS');
