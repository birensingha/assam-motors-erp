// Read-only authenticated FIX49 Enterprise UI desktop/mobile acceptance.
import { chromium } from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!password)throw Error('Staging QA account not configured');
const browser=await chromium.launch({headless:true});
const failures=[];
try {
 const context=await browser.newContext({viewport:{width:1440,height:900}});
 const page=await context.newPage();
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:35000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
 await page.locator('input[type=password]').first().fill(password);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 async function open(path,name,mobile=false){
   await page.setViewportSize(mobile?{width:390,height:844}:{width:1440,height:900});
   const response=await page.goto(base+path,{waitUntil:'domcontentloaded',timeout:35000});
   const present=await page.locator('.am-modern .am-suite .am-brand').count();
   const nav=await page.locator('.am-modern .am-suite-nav a').count();
   const css=await page.locator('.am-modern').first().evaluate(el=>{
     const st=getComputedStyle(el);
     return {background:st.backgroundColor,font:st.fontFamily};
   }).catch(()=>({background:'',font:''}));
   const ok=response?.status()===200 && present===1 && nav===4 && css.background==='rgb(243, 246, 251)';
   console.log('FIX49_UI '+name+' '+(mobile?'MOBILE':'DESKTOP')+' HTTP '+(response?.status()??0)+' '+(ok?'PASS':'FAIL'));
   if(!ok)failures.push(name+' '+(mobile?'mobile':'desktop'));
   return ok;
 }
 const pages=[
   ['/erp/workshop-tools/vendor-database','Vendor Database'],
   ['/erp/workshop-tools/osl/register','OSL Register'],
   ['/erp/workshop-tools/payment-vouchers','Payment Vouchers']
 ];
 for(const [path,name] of pages){await open(path,name);await open(path,name,true)}
 await page.goto(base+'/erp/job-cards',{waitUntil:'domcontentloaded'}); // existing ERP index unchanged
 // Existing JC index is not part of the enterprise workshop-controls theme.
 // Discover a real Job Card ID via its href instead:
 const jobLinks=await page.locator('a[href]').evaluateAll(anchors=>anchors.map(a=>a.getAttribute('href')||''));
 const jc=jobLinks.map(h=>h.match(/\/erp\/job-cards\/(\d+)(?:\?|$)/)).find(Boolean);
 if(jc){
   const path='/erp/workshop-tools/job-cards/'+jc[1]+'/billing-edit';
   await open(path,'JC Amount Editor');await open(path,'JC Amount Editor',true);
 }
 await page.setViewportSize({width:1440,height:900});
 await page.goto(base+'/erp/workshop-tools/osl/register',{waitUntil:'domcontentloaded'});
 const oslLink=await page.locator('a[href*="/workshop-tools/osl/"][href*="/edit"]').first().getAttribute('href').catch(()=>null);
 if(oslLink){
   const path=new URL(oslLink,base).pathname;
   await open(path,'OSL Cost Editor');await open(path,'OSL Cost Editor',true);
   const actual=await page.locator('input[name=vendor_rate]').count();
   if(actual!==1){console.log('FIX49_UI OSL input MISSING');failures.push('OSL cost field')}
 }
 await page.setViewportSize({width:1440,height:900});
 await page.goto(base+'/erp/workshop-tools/payment-vouchers',{waitUntil:'domcontentloaded'});
 const voucherLink=await page.locator('a[href*="/workshop-tools/payment-vouchers/"][href*="/edit"]').first().getAttribute('href').catch(()=>null);
 if(voucherLink){
   const path=new URL(voucherLink,base).pathname;
   await open(path,'Payment Voucher Editor');await open(path,'Payment Voucher Editor',true);
   const forms=await page.locator('form input[name=amount]').count();
   if(forms!==1){console.log('FIX49_UI Voucher Amount field MISSING');failures.push('Voucher amount field')}
 }
} finally {await browser.close()}
if(failures.length)throw Error('FIX49 Enterprise UI QA failed: '+failures.join(', '));
console.log('FIX49_ENTERPRISE_UI_DESKTOP_MOBILE_GET_QA_PASS (no financial POSTs)');
