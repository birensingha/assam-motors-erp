import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am246-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!email||!password)throw Error('AM246 missing authenticated staging QA credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1540,height:900}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
try{
 await page.goto(base+(process.env.STAGING_LOGIN_PATH||'/erp/login'),{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 const posted=page.waitForResponse(r=>r.request().method()==='POST'&&r.url().includes('/erp/login'),{timeout:45000});
 await page.locator('button[type="submit"],input[type="submit"]').first().click({noWaitAfter:true,timeout:15000});
 const login=await posted;
 if(login.status()>=400)throw Error('Login POST HTTP '+login.status());
 for(const [type,url,master] of [
  ['PART','/erp/masters/parts','PART'],
  ['LABOUR','/erp/masters/labour','LABOUR'],
  ['OSL','/erp/osl-masters','OSL'],
 ]){
  const res=await page.goto(base+url,{waitUntil:'domcontentloaded',timeout:45000});
  if(!res || res.status()!==200)throw Error(type+' Master HTTP '+res?.status());
  if(type==='PART'){
    const row=page.locator('a[href*="masters/item-ledger?"][href*="PART"]').first();
    if(!await row.count())throw Error('Parts Master row missing direct Transaction drilldown');
  }
  if(type==='LABOUR'){
    const row=page.locator('a[href*="masters/item-ledger?"][href*="LABOUR"]').first();
    if(!await row.count())throw Error('Labour Master row missing direct Transaction drilldown');
  }
  if(type==='OSL'){
    const link=page.getByRole('link',{name:/Search OSL Item Ledger/i});
    if(!await link.count())throw Error('OSL Master ledger shortcut missing');
  }
  console.log('AM246_'+type+'_MASTER_LINK_PASS');
 }
 let res=await page.goto(base+'/erp/masters/item-ledger?type=PART',{waitUntil:'domcontentloaded',timeout:45000});
 if(!res||res.status()!==200)throw Error('Master Ledger HTTP '+res?.status());
 await page.locator('h1',{hasText:'Item Transaction Ledger'}).waitFor({state:'visible'});
 if(await page.locator('#am246-search').count()!==1)throw Error('Master code/name search input missing');
 await page.locator('#am246-search').fill('oil');
 const menu=page.locator('#am246-menu');
 await menu.waitFor({state:'visible',timeout:12000});
 let list=menu.locator('button.am246-option');
 if(!await list.count()) {
   await page.locator('#am246-search').fill('AM-');
   await list.first().waitFor({state:'visible',timeout:12000});
 }
 await list.first().click();
 await page.waitForURL(/masters\/item-ledger\?/, {timeout:10000}).catch(()=>{});
 await page.locator('h2',{hasText:'Parts Ledger'}).waitFor({state:'visible',timeout:15000});
 if(await page.getByText('Opening Stock (Period)').count()!==1)throw Error('Opening stock KPI missing');
 if(await page.getByText('Closing Stock (Period)').count()!==1)throw Error('Closing stock KPI missing');
 if(await page.locator('.am246-table thead th').count()<10)throw Error('Tally-style ledger columns missing');
 await page.screenshot({path:out+'/parts-transaction-ledger.png',fullPage:true});
 await page.locator('#am246-type').selectOption('LABOUR');
 await page.locator('#am246-search').fill('ROT');
 await page.locator('#am246-menu button.am246-option').first().waitFor({state:'visible',timeout:12000});
 await page.locator('#am246-menu button.am246-option').first().click();
 await page.locator('h2',{hasText:'ROT / Labour Ledger'}).waitFor({state:'visible',timeout:15000});
 await page.screenshot({path:out+'/labour-transaction-ledger.png',fullPage:true});
 await page.locator('#am246-type').selectOption('OSL');
 await page.locator('#am246-search').fill('');
 await page.locator('#am246-menu button.am246-option').first().waitFor({state:'visible',timeout:12000});
 await page.locator('#am246-menu button.am246-option').first().click();
 await page.locator('h2',{hasText:'OSL Ledger'}).waitFor({state:'visible',timeout:15000});
 await page.screenshot({path:out+'/osl-transaction-ledger.png',fullPage:true});
 if(errors.length)throw Error('Browser JS exceptions: '+errors.join(' | ').slice(0,900));
 console.log('AM246_MASTER_ITEM_LEDGER_BROWSER_PASS_NO_DB_WRITES');
 fs.writeFileSync(out+'/result.json',JSON.stringify({pass:true,types:['PART','LABOUR','OSL'],search:true,stockKpis:true,transactionGrid:true,noWrites:true,checked:new Date().toISOString()},null,2));
}catch(e){
 await page.screenshot({path:out+'/failure.png',fullPage:true}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({pass:false,error:String(e?.message||e)},null,2));
 throw e;
}finally{await browser.close();}
