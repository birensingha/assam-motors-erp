// Authenticated read-only UI validation for FIX49 staging.
// No financial POSTs, no data writes, and no PII/logged HTML in public Actions logs.
import { chromium } from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!password)throw new Error('Required staging browser QA secrets missing');
const browser=await chromium.launch({headless:true});
let failures=[];
try{
 const page=await browser.newPage({viewport:{width:1366,height:768}});
 const login=await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 if(!login || login.status()>=400)throw new Error('ERP login page not available');
 const user=page.locator('input[type=email],input[name=email],input[name=username]').first();
 const pw=page.locator('input[type=password]').first();
 if(!await user.count()||!await pw.count())throw new Error('Login fields not found');
 await user.fill(email);await pw.fill(password);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 const pages=[
  ['/erp/workshop-tools/vendor-database','Vendor Database','Vendor Database'],
  ['/erp/workshop-tools/osl/register','OSL Register','OSL Purchase Register'],
  ['/erp/workshop-tools/payment-vouchers','Payment Voucher Register','Payment Voucher'],
  ['/erp/job-cards','Existing Job Card Index','Job Cards'],
  ['/erp/purchases','Existing Purchase Index','Purchase']
 ];
 for(const [path,label,title] of pages){
  const r=await page.goto(base+path,{waitUntil:'domcontentloaded',timeout:30000});
  const code=r?.status()||0;
  const body=await page.locator('body').innerText().catch(()=>'');
  const allowed=code===200 && body.includes(title) &&
   !/SQLSTATE|Fatal error|Whoops|Internal Server Error|Admin permission required|\b403\b/i.test(body);
  if(!allowed)failures.push(label+' HTTP '+code+' body-header-mismatch');
  console.log('FIX49_PAGE '+label+' HTTP '+code+' '+(allowed?'PASS':'FAIL'));
 }
}finally{await browser.close()}
if(failures.length)throw new Error(failures.join('; '));
console.log('FIX49_AUTHENTICATED_GET_PAGES_PASS — no financial writes');
