import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am245-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!email||!password)throw new Error('Missing staging Admin QA credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:940}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
try{
 await page.goto(base+(process.env.STAGING_LOGIN_PATH||'/erp/login'),{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 let r=await page.goto(base+'/erp/payments/create',{waitUntil:'networkidle',timeout:45000});
 if(!r||r.status()!==200)throw new Error('Universal Payment Voucher HTTP '+r?.status());
 const link=page.locator('#am245-staff-multi-link');
 await link.waitFor({state:'attached',timeout:7000});
 if(!/\/erp\/payments\/staff-expenses\/multi/.test(await link.getAttribute('href')||''))throw new Error('Staff Multi link missing/wrong route');
 await page.screenshot({path:out+'/universal-payment-staff-multi-link.png',fullPage:false});
 r=await page.goto(base+'/erp/payments/staff-expenses/multi?staff_id=3',{waitUntil:'networkidle',timeout:45000});
 if(!r||r.status()!==200)throw new Error('Staff multi expense page HTTP '+r?.status());
 await page.locator('h1',{hasText:'Multiple Staff Expenses'}).waitFor({state:'visible'});
 const form=page.locator('#am245-form'),staff=page.locator('#am245-staff'),amount=page.locator('#am245-amount');
 await form.waitFor({state:'visible'});
 if(await staff.locator('option[value="3"]').count()!==1)throw new Error('Staff Master ID 3 not available');
 await staff.selectOption('3');
 const liveDueCount=await page.locator('#am245-expenses tr[data-id]').count();
 console.log('AM245_LIVE_STAFF_OUTSTANDING_ROWS',liveDueCount);
 // Real outstanding data can legitimately become zero after a staff reimbursement.
 // Test the page and empty state with real DB data, then isolate UI geometry/allocations
 // with browser-only fixture data. Never create test expenses on the server.
 await page.screenshot({path:out+'/staff-multi-live-outstanding.png',fullPage:false});
 const fixture=[
  {id:910001,staff_id:3,expense_no:'QA-EXP-001',date:'2026-11-01',category:'TRAVEL',job_no:'JC-QA-001',vehicle:'QA',description:'Test fixture 1',total:160,paid:0,outstanding:160},
  {id:910002,staff_id:3,expense_no:'QA-EXP-002',date:'2026-11-02',category:'CONVEYANCE',job_no:'JC-QA-002',vehicle:'QA',description:'Test fixture 2',total:220,paid:0,outstanding:220},
  {id:910003,staff_id:3,expense_no:'QA-EXP-003',date:'2026-11-03',category:'OTHER',job_no:'JC-QA-003',vehicle:'QA',description:'Test fixture 3',total:100,paid:0,outstanding:100}
 ];
 await page.route('**/erp/payments/staff-expenses/multi?am245_browser_fixture=1',async route=>{
   const upstream=await route.fetch();
   let html=await upstream.text();
   const begin=html.indexOf('const dataset='),end=html.indexOf(',initial=',begin);
   if(begin<0||end<0)throw new Error('Unable to inject non-posting browser-only fixture');
   html=html.slice(0,begin)+'const dataset='+JSON.stringify(fixture)+html.slice(end);
   await route.fulfill({response:upstream,body:html});
 });
 const mock=await page.goto(base+'/erp/payments/staff-expenses/multi?am245_browser_fixture=1',{waitUntil:'networkidle',timeout:45000});
 if(!mock||mock.status()!==200)throw new Error('Isolated multi-reference UI fixture failed');
 await page.locator('#am245-staff').selectOption('3');
 const mockRows=page.locator('#am245-expenses tr[data-id]');
 if(await mockRows.count()!==3)throw new Error('Three expense references were not rendered');
 await page.locator('#am245-amount').fill('420.00');
 await page.locator('#am245-select-all').click();
 await page.locator('#am245-auto').click();
 if(await page.locator('#am245-post').isDisabled())throw new Error('Vendor-style multi expense allocation invalid despite 420 allocated');
 const amounts=await page.locator('.am245-line-allocation').evaluateAll(els=>els.map(e=>e.value));
 if(JSON.stringify(amounts)!==JSON.stringify(['160.00','220.00','40.00']))throw new Error('Multi-expense FIFO incorrect '+amounts);
 if(await page.locator('#am245-count').innerText()!=='3')throw new Error('Not all 3 expense references were selected');
 await page.screenshot({path:out+'/three-expenses-one-payment-420-fixture.png',fullPage:true});
 await page.locator('#am245-amount').fill('300.00');
 if(await page.locator('#am245-post').isDisabled())throw new Error('Partial 300 allocation blocked');
 if(await page.locator('#am245-count').innerText()!=='2')throw new Error('Zero-allocated third expense should have been deselected');
 await page.screenshot({path:out+'/partial-300-across-expenses-fixture.png',fullPage:true});
 const second=page.locator('#am245-expenses tr[data-id="910002"]');
 await second.locator('.am245-line-allocation').fill('300.00');
 if(!await page.locator('#am245-post').isDisabled())throw new Error('Over-allocation was not blocked');
 await second.locator('.am245-line-allocation').fill('140.00');
 if(await page.locator('#am245-post').isDisabled())throw new Error('Valid restored allocation disabled');
 // Verify posted header/reference inputs exist. NEVER click submit in browser QA.
 for(const name of ['request_key','payee_category','staff_multi_expense','staff_payment_kind','staff_payment_against','staff_id','amount','payment_mode','account_name','notes']){
   if(await page.locator('#am245-form [name="'+name+'"]').count()!==1)throw new Error('Missing voucher field '+name);
 }
 if(errors.length)throw new Error('JS exceptions: '+errors.join(' | ').slice(0,750));
 console.log('AM245_STAFF_MULTI_EXPENSE_AMOUNT_FIRST_BROWSER_PASS_NO_POST_LIVE='+liveDueCount);
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:true,staff:3,liveDueCount,syntheticBrowserFixture:true,threeExpenseAllocation:420,partialAllocation:300,financial_posting_tested:false,no_payments_posted:true,checked:new Date().toISOString()},null,2));
}catch(e){
 await page.screenshot({path:out+'/failure.png',fullPage:true}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:false,error:String(e?.message||e)},null,2));
 throw e;
}finally{await browser.close()}
