// Additional FIX49 authenticated deep-link audit: read-only routes, no POST.
import {chromium} from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const user=process.env.STAGING_ADMIN_LOGIN||'',pass=process.env.STAGING_ADMIN_PASSWORD||'';
if(!user||!pass)throw new Error('Missing staging login test configuration');
const browser=await chromium.launch({headless:true});
let failures=[],attempted=0;
try{
 const p=await browser.newPage();
 await p.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:35000});
 await p.locator('input[type=email],input[name=email],input[name=username]').first().fill(user);
 await p.locator('input[type=password]').first().fill(pass);
 await p.locator('button[type=submit],input[type=submit]').first().click();
 await p.waitForLoadState('domcontentloaded',{timeout:14000}).catch(()=>{});
 async function check(path,title,label){
  const r=await p.goto(base+path,{waitUntil:'domcontentloaded',timeout:35000});
  const body=await p.locator('body').innerText().catch(()=>'');
  const ok=r?.status()===200 && body.includes(title) && !/SQLSTATE|Fatal error|Internal Server Error|Whoops|Admin permission required/i.test(body);
  console.log('FIX49_DETAIL '+label+' HTTP '+(r?.status()||0)+' '+(ok?'PASS':'FAIL'));
  attempted++;if(!ok)failures.push(label+': '+(r?.status()||0));
 }
 const jobs=await p.goto(base+'/erp/job-cards',{waitUntil:'domcontentloaded'});
 const anchors=await p.locator('a[href]').evaluateAll(as=>as.map(a=>a.getAttribute('href')||''));
 const match=anchors.map(u=>u.match(/\/erp\/job-cards\/(\d+)(?:\?|$)/)).find(Boolean);
 if(match)await check('/erp/workshop-tools/job-cards/'+match[1]+'/billing-edit','Edit Job Card Amount','Job Card Editor');
 else console.log('FIX49_DETAIL Job Card Editor SKIP: no discoverable Job Card links');
 await p.goto(base+'/erp/workshop-tools/osl/register',{waitUntil:'domcontentloaded'});
 const osl=await p.locator('a[href*="/workshop-tools/osl/"][href*="/edit"]').first().getAttribute('href').catch(()=>null);
 if(osl)await check(new URL(osl,base).pathname,'Edit Cost','OSL Editor');
 else console.log('FIX49_DETAIL OSL Editor SKIP: no purchase edit links found');
 await p.goto(base+'/erp/workshop-tools/payment-vouchers',{waitUntil:'domcontentloaded'});
 const pay=await p.locator('a[href*="/workshop-tools/payment-vouchers/"][href*="/edit"]').first().getAttribute('href').catch(()=>null);
 if(pay)await check(new URL(pay,base).pathname,'Payment Voucher','Voucher Editor');
 else console.log('FIX49_DETAIL Voucher Editor SKIP: no voucher edit links found');
}finally{await browser.close()}
if(failures.length)throw new Error('Read-only detail route failure: '+failures.join(', '));
console.log('FIX49_DETAIL_READONLY_TESTS_PASS checked='+attempted+' (missing-record routes not claimed)');
