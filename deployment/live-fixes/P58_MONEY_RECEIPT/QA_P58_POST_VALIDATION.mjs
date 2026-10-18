// P58 server POST negative validation only. Never posts valid money or creates a receipt.
import {chromium} from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!password)throw Error('No test admin login');
const browser=await chromium.launch({headless:true});
try{
 const page=await browser.newPage();
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
 await page.locator('input[type=password]').first().fill(password);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 const get=await page.goto(base+'/erp/money-receipts/create',{waitUntil:'domcontentloaded',timeout:30000});
 if(get?.status()!==200)throw Error('Money Receipt create not available for test');
 const csrf=await page.locator('#p58-receipt-form input[name=_token]').inputValue();
 const startRegister=await page.goto(base+'/erp/money-receipts',{waitUntil:'domcontentloaded',timeout:30000});
 if(startRegister?.status()!==200)throw Error('Receipt register not found before negative test');
 // Invalid method/category and 0 amount ensure validation fails before DB transaction.
 const requestKey='00000000-0000-4000-8000-000000000000';
 const response=await page.request.post(base+'/erp/money-receipts',{
   headers:{Accept:'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
   form:{
    _token:csrf,request_key:requestKey,receipt_date:'1900-01-01',
    customer_id:'0',reference_type:'not-a-real-mode',invoice_id:'',
    payment_mode:'Cash',cash_amount:'0',online_amount:'0',
    online_reference:'',notes:'P58 INVALID validation check only; do not save.'
   },
   timeout:30000
 });
 const body=await response.text();
 const validation=response.status()===422&&body.includes('errors')&&
    body.includes('customer_id')&&body.includes('reference_type');
 console.log('P58_SERVER_POST_INVALID_PAYMENT_REJECTED '+(validation?'PASS':'FAIL')+' HTTP '+response.status());
 if(!validation)throw Error('Negative POST validation failed. Response '+response.status());
 const after=await page.goto(base+'/erp/money-receipts',{waitUntil:'domcontentloaded',timeout:30000});
 const register=after?.status()===200;
 console.log('P58_MONEY_RECEIPT_REGISTER_AFTER_INVALID_POST '+(register?'PASS':'FAIL'));
 if(!register)throw Error('Receipt register not accessible after invalid POST.');
 console.log('P58_NEGATIVE_POST_QA_PASS_NO_MONEY_RECEIVED');
}finally{await browser.close()}
