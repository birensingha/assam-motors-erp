import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const loginPath=process.env.STAGING_LOGIN_PATH||'/erp/login';
const email=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am235-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!email||!password)throw new Error('Missing STAGING Admin test credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:950}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
try{
 await page.goto(base+loginPath,{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 const check=async path=>{
  const res=await page.goto(base+path,{waitUntil:'networkidle',timeout:45000});
  if (!res||res.status()!==200)throw new Error(path+' HTTP '+res?.status());
  const body=await page.locator('body').innerText();
  if (/SQLSTATE|Internal Server Error|Whoops|Fatal error|Forbidden/i.test(body))
    throw new Error(path+' server error visible');
 };
 await check('/erp/purchases/reconciliation');
 await page.locator('.am235-page h1',{hasText:'Parts Purchase Reconciliation'}).waitFor({state:'visible'});
 await page.locator('table.am235-table').waitFor({state:'visible'});
 const layoutStyle=await page.locator('.am235-filters').evaluate(el=>getComputedStyle(el).display);
 if(layoutStyle!=='flex')throw new Error('AM235 report stylesheet missing; filters display='+layoutStyle);

 await page.screenshot({path:out+'/reconciliation-overview.png',fullPage:true});
 // Read-only filtering; never submit a purchase or modify financial records.
 await page.locator('select[name=against]').selectOption('JOB');
 await page.locator('.am235-filters button[type=submit]').click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 if(!page.url().includes('against=JOB'))throw new Error('JOB filter did not submit');
 await page.locator('.am235-page').waitFor({state:'visible'});
 await page.locator('input[name=q]').fill('JC-2026-0011');
 await page.locator('.am235-filters button[type=submit]').click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 if(!page.url().includes('JC-2026-0011'))throw new Error('JC search filter not applied');
 await page.locator('.am235-page').waitFor({state:'visible'});
 await page.screenshot({path:out+'/jc-filter.png',fullPage:true});
 await check('/erp/purchases');
 const registerLink=page.locator('a[href*="purchases/reconciliation"]').first();
 await registerLink.waitFor({state:'visible'});
 await check('/erp/purchases/create');
 await page.locator('a[href*="purchases/reconciliation"]').first().waitFor({state:'visible'});
 const bodyText=await page.locator('body').innerText();
 if(bodyText.includes('Keyboard Entry:'))throw new Error('Removed keyboard entry text still shown');
 const line=page.locator('#purchase-lines tr').first();
 const part=line.locator('.part-search'),against=line.locator('.allocation');
 await part.waitFor({state:'visible'});
 await against.selectOption('JOB');
 const lookup=line.locator('.job-filter'),jcId=line.locator('.job-select');
 await lookup.fill('sum');
 const match=page.locator('.am234-jc-option',{hasText:'JC-2026-0011'}).first();
 await match.waitFor({state:'visible',timeout:8000});
 await match.click();
 if(await jcId.inputValue()!=='11')throw new Error('JC linkage regression in purchase form');
 await page.screenshot({path:out+'/purchase-jc-linked.png',fullPage:true});
 if(errors.length)throw new Error('Browser JS error '+errors.join(' | ').slice(0,900));
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:true,readonly:true,no_purchase_posted:true,checked:new Date().toISOString()},null,2));
 console.log('AM235_BROWSER_REPORT_PASS_NO_DB_WRITES');
}catch(e){
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:false,error:String(e?.message||e)},null,2));
 await page.screenshot({path:out+'/failure.png',fullPage:true}).catch(()=>{});
 throw e;
}finally{await browser.close()}
