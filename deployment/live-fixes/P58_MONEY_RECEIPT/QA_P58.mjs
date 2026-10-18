// P58 authenticated, GET-only browser acceptance. Never post a customer payment.
// Test historical receipt rendering and all three new-reference UI branches.
import {chromium} from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const login=process.env.STAGING_ADMIN_LOGIN||'',pass=process.env.STAGING_ADMIN_PASSWORD||'';
if(!login||!pass)throw Error('P58 authenticated test login not available');
const browser=await chromium.launch({headless:true});
const failed=[];
try{
 const anon=await browser.newPage();
 const r=await anon.goto(base+'/erp/money-receipts/create',{waitUntil:'domcontentloaded',timeout:30000});
 const blocked=anon.url().includes('/login')||r?.status()===401||r?.status()===403;
 console.log('P58_GUEST_RECEIPT_REQUIRES_ERP_LOGIN '+(blocked?'PASS':'FAIL'));
 if(!blocked)failed.push('guest receipt form');
 await anon.close();
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(login);
 await page.locator('input[type=password]').first().fill(pass);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:850});
  let response=await page.goto(base+'/erp/money-receipts',{waitUntil:'domcontentloaded',timeout:30000});
  const register=response?.status()===200&&
    (await page.getByRole('heading',{name:/Money Receipt Register/i}).count())===1&&
    (await page.locator('table tbody tr').count())>0&&
    (await page.locator('a[href$="/erp/money-receipts/create"]').count())>0;
  console.log('P58_MONEY_RECEIPT_REGISTER_'+width+' '+(register?'PASS':'FAIL')+' HTTP '+response?.status());
  if(!register)failed.push('register '+width);
  response=await page.goto(base+'/erp/money-receipts/create',{waitUntil:'domcontentloaded',timeout:30000});
  const form=page.locator('#p58-receipt-form');
  const hasForm=response?.status()===200&&(await form.count())===1&&
    (await page.locator('input[name=request_key]').count())===1;
  if(!hasForm){failed.push('create form '+width);continue;}
  const options=await page.locator('input[name=reference_type]').count();
  const typeOK=options===3;
  const invoiceSelected=await page.locator('#p58-invoice').isEnabled();
  await page.locator('input[value=new_reference]').check();
  const newOK=(await page.locator('#p58-ref-no').isEnabled())&&
    (await page.locator('#p58-ref-no').getAttribute('required'))!==null&&
    !(await page.locator('#p58-invoice').isEnabled());
  await page.locator('input[value=on_account]').check();
  const onAccount=!(await page.locator('#p58-invoice').isEnabled())&&
    !(await page.locator('#p58-ref-no').isEnabled())&&
    (await page.locator('#p58-on-account-group').isVisible());
  await page.locator('input[value=against_reference]').check();
  const against=(await page.locator('#p58-invoice').isEnabled())&&
    !(await page.locator('#p58-ref-no').isEnabled());
  await page.locator('#p58-mode').selectOption('Cash + Online');
  await page.locator('#p58-cash').fill('500');
  await page.locator('#p58-online').fill('250.25');
  const total=await page.locator('#p58-sum-total').innerText();
  const preview=total.includes('750.25')&&
     (await page.locator('#p58-cash').isEditable())&&
     (await page.locator('#p58-online').isEditable());
  const valid=typeOK&&invoiceSelected&&newOK&&onAccount&&against&&preview;
  console.log('P58_ALL_THREE_RECEIPT_REFERENCES_'+width+' '+(valid?'PASS':'FAIL')+' totalPreview '+total);
  if(!valid)failed.push('three reference form '+width);
  response=await page.goto(base+'/erp/money-receipts/9',{waitUntil:'domcontentloaded',timeout:30000});
  const details=response?.status()===200&&
    (await page.getByText('Money Receipt —',{exact:false}).count())>0&&
    (await page.locator('a[href*="/erp/money-receipts/9/print"]').count())>0;
  console.log('P58_EXISTING_RECEIPT_DETAIL_'+width+' '+(details?'PASS':'FAIL'));
  if(!details)failed.push('existing receipt detail '+width);
  response=await page.goto(base+'/erp/money-receipts/9/print',{waitUntil:'domcontentloaded',timeout:30000});
  const text=await page.locator('body').innerText();
  const title=await page.locator('.doc-head h2').innerText().catch(()=>'');
  const received=await page.locator('.receipt-total strong').innerText().catch(()=>'');
  const bank= text.includes('HDFC BANK')&&text.includes('Bank Details');
  const branded=text.includes('ASSAM MOTORS')&&title.toLowerCase().includes('money receipt');
  const noGstRelevy=text.includes('not a Tax Invoice')&&text.includes('does not add or recalculate GST');
  const words=text.includes('Amount in Words')&&text.includes('Indian Rupees');
  const bounds=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
  const print=response?.status()===200&&branded&&bank&&noGstRelevy&&words&&
   received.startsWith('₹')&&received.length>2&&bounds<=18;
  console.log('P58_P53_STYLE_MONEY_RECEIPT_PRINT_'+width+' '+(print?'PASS':'FAIL')+
    ' HTTP '+response?.status()+' overflow '+bounds);
  if(!print)failed.push('P53 receipt print '+width);
  response=await page.goto(base+'/erp/dashboard',{waitUntil:'domcontentloaded',timeout:30000});
  const side=await page.locator('a[href$="/erp/money-receipts"]').count();
  const nav=response?.status()===200&&side>=1;
  console.log('P58_LEFT_ERP_NAV_MONEY_RECEIPTS_'+width+' '+(nav?'PASS':'FAIL'));
  if(!nav)failed.push('sidebar nav '+width);
 }
}finally{await browser.close()}
if(failed.length)throw Error('P58_QA_FAILURE: '+failed.join('; '));
console.log('P58_NATIVE_CUSTOMER_MONEY_RECEIPT_GET_ONLY_BROWSER_QA_PASS');
