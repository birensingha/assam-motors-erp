import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am244-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!email||!password)throw new Error('Missing staging test Admin credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:900}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
try{
 await page.goto(base+(process.env.STAGING_LOGIN_PATH||'/erp/login'),{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 let response=await page.goto(base+'/erp/expenses/16',{waitUntil:'networkidle',timeout:45000});
 if(!response||response.status()!==200)throw new Error('Target Expense Voucher HTTP '+response?.status());
 const text=await page.locator('body').innerText();
 if(!text.includes('EXP-20261104-000016'))throw new Error('Wrong Expense Voucher loaded');
 const link=page.locator('a',{hasText:'Pay This Expense'}).first();
 await link.waitFor({state:'visible',timeout:10000});
 const href=await link.getAttribute('href');
 if(!href?.includes('staff_expense=16'))throw new Error('Expense -> Payment Voucher still uses old link '+href);
 const against=page.locator('a',{hasText:'Pay Against Reference · Staff Expense'});
 if(await against.count()!==1)throw new Error('Payment History missing direct Against Reference link');
 await page.screenshot({path:out+'/expense-staff-payment-link.png',fullPage:false});
 await link.click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 await page.locator('#am244-staff-expense-prefill').waitFor({state:'attached'});
 await page.waitForFunction(()=>{
   const staff=document.getElementById('am231-staff-select');
   const expense=document.getElementById('am240-expense');
   return staff?.value==='3' && expense?.value==='16';
 },{timeout:11000});
 const picked=(await page.locator('#am237-payee-selected').innerText()).trim();
 if(!picked.toLowerCase().startsWith('staff')||!picked.toLowerCase().includes('firajul'))
   throw new Error('Wrong Staff payee or no Staff selection: '+picked);
 if(await page.locator('#am237-mode').inputValue()!=='AGAINST')throw new Error('Payment Against not selected');
 if(await page.locator('#am240-kind').inputValue()!=='EXPENSE_REIMBURSEMENT')throw new Error('Staff Expense Reimbursement not selected');
 if(await page.locator('#payment-value').inputValue()!=='220.00')throw new Error('₹220 outstanding not populated');
 const src=await page.locator('#am240-expense option:checked').innerText();
 if(!src.includes('EXP-20261104-000016'))throw new Error('Expense reference number not in selected option');
 if(!await page.locator('#am240-expense-box').isVisible())throw new Error('Against Reference selector not visible');
 const summary=(await page.locator('#am244-selected-reference').innerText()).trim();
 if(!summary.includes('EXP-20261104-000016'))throw new Error('Payee outstanding notice missing expected reference');
 await page.screenshot({path:out+'/staff-expense-220-selected-payment-voucher.png',fullPage:true});
 // From a blank payment form the same expense must be discoverable manually.
 response=await page.goto(base+'/erp/payments/create',{waitUntil:'networkidle',timeout:45000});
 if(!response||response.status()!==200)throw new Error('New Payment Voucher HTTP '+response?.status());
 const search=page.locator('#am237-payee-search');
 await search.fill('Firajul');
 const staffOption=page.locator('#am237-payee-results button.am237-result').filter({hasText:'FIRAJUL'}).first();
 await staffOption.waitFor({state:'visible',timeout:7000});
 await staffOption.click();
 await page.locator('#am237-mode').selectOption('AGAINST');
 await page.locator('#am240-kind').selectOption('EXPENSE_REIMBURSEMENT');
 const expenseOption=page.locator('#am240-expense option[value="16"]');
 await expenseOption.waitFor({state:'attached',timeout:7000});
 if(!await page.locator('#am240-expense-box').isVisible())
    throw new Error('Generic New Payment Voucher not showing unpaid Staff Expense references');
 await page.screenshot({path:out+'/manual-against-reference-staff-expense-list.png',fullPage:false});
 if(errors.length)throw new Error('JS errors: '+errors.slice(0,3).join(' | '));
 console.log('AM244_EXPENSE_TO_STAFF_AGAINST_REFERENCE_BROWSER_PASS_NO_PAYMENT_POST');
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:true,expense:'EXP-20261104-000016',payee:'FIRAJUL HOQUE',outstanding:220,no_financial_post:true,checked:new Date().toISOString()},null,2));
}catch(e){
 await page.screenshot({path:out+'/failure.png',fullPage:false}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:false,error:String(e?.message||e)},null,2));
 throw e;
}finally{await browser.close()}
