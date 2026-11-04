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
 if(await staff.locator('option[value="3"]').count()!==1)throw new Error('Firajul Staff ID 3 not present');
 await staff.selectOption('3');
 if(await page.locator('#am245-expenses tr[data-id]').count()<1)
   throw new Error('Selected staff has no outstanding Expense references for browser QA');
 const exp=page.locator('#am245-expenses tr[data-id="16"]');
 if(await exp.count()!==1)throw new Error('EXP-20261104-000016 Staff expense not in outstanding list');
 const expText=await exp.innerText();
 if(!expText.includes('EXP-20261104-000016'))throw new Error('Correct Expense no missing');
 const due=Number(await exp.getAttribute('data-due'));
 if(due<1||due>22000)throw new Error('Target Expense outstanding invalid '+due);
 const pay=Math.min(due,10000);
 await amount.fill((pay/100).toFixed(2));
 await exp.locator('.am245-check').check();
 await page.locator('#am245-auto').click();
 if(await page.locator('#am245-post').isDisabled())throw new Error('Allocation UI did not allow valid partial payment');
 const allocated=await exp.locator('.am245-line-allocation').inputValue();
 if(Math.round(Number(allocated)*100)!==pay)throw new Error('Amount-first allocation incorrect: '+allocated);
 await page.screenshot({path:out+'/staff-multi-expense-amount-first.png',fullPage:true});
 // Manual selected bill share must be checked against the same outstanding limit.
 await exp.locator('.am245-line-allocation').fill(((due+100)/100).toFixed(2));
 if(!await page.locator('#am245-post').isDisabled())throw new Error('Over-allocation was not blocked in browser');
 await exp.locator('.am245-line-allocation').fill((pay/100).toFixed(2));
 if(await page.locator('#am245-post').isDisabled())throw new Error('Valid partial allocation unexpectedly blocked');
 // Verify posted header and reference inputs exist, but NEVER click submit.
 for(const name of ['request_key','payee_category','staff_multi_expense','staff_payment_kind','staff_payment_against','staff_id','amount','payment_mode','account_name','notes']){
   if(await form.locator('[name="'+name+'"]').count()!==1)throw new Error('Missing mandatory voucher field '+name);
 }
 if(errors.length)throw new Error('JS exceptions: '+errors.join(' | ').slice(0,750));
 console.log('AM245_STAFF_MULTI_EXPENSE_AMOUNT_FIRST_BROWSER_PASS_NO_POST');
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:true,staff:3,expense:16,allocated:pay/100,financial_posting_tested:false,no_payments_posted:true,checked:new Date().toISOString()},null,2));
}catch(e){
 await page.screenshot({path:out+'/failure.png',fullPage:true}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:false,error:String(e?.message||e)},null,2));
 throw e;
}finally{await browser.close()}
