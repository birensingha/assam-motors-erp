import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const username=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am237-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!username||!password)throw new Error('Admin credentials missing in automation');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:860}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
try {
 await page.goto(base+(process.env.STAGING_LOGIN_PATH||'/erp/login'),{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(username);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 let response=await page.goto(base+'/erp/purchases/create',{waitUntil:'networkidle',timeout:45000});
 if(!response||response.status()!==200)throw new Error('Purchase Entry HTTP '+response?.status());
 await page.locator('#am237-purchase-compact-row').waitFor({state:'attached'});
 const row=page.locator('#purchase-lines tr').first();
 await row.waitFor({state:'visible'});
 const inputs=['.part-search','.qty','.rate','.disc','.gst','.allocation','.job-filter'];
 const audit=async label=>{
  const measurements=await row.evaluate((r,selectors)=>{
   const items=selectors.map(sel=>{
    const el=r.querySelector(sel);const box=el.getBoundingClientRect();
    return {sel,height:box.height,top:box.top,bottom:box.bottom,center:(box.top+box.bottom)/2,width:box.width,display:getComputedStyle(el).display};
   });
   const tbl=r.closest('table').getBoundingClientRect(),sc=r.closest('.line-scroll').getBoundingClientRect();
   return {items,tableWidth:tbl.width,linePanelHeight:sc.height};
  },inputs);
  console.log('AM237_DIMENSIONS_'+label,JSON.stringify(measurements));
  const h=measurements.items.map(x=>x.height),c=measurements.items.map(x=>x.center);
  if(h.some(x=>x<27||x>35))throw new Error(label+' field heights not compact '+h.join(','));
  if(Math.max(...h)-Math.min(...h)>1.1)throw new Error(label+' field heights differ '+h.join(','));
  if(Math.max(...c)-Math.min(...c)>2)throw new Error(label+' fields not in parallel row '+c.join(','));
  if(measurements.items.some(x=>x.width<25))throw new Error(label+' a field is collapsed');
  if(measurements.linePanelHeight>315)throw new Error(label+' the empty line panel is still oversized');
 };
 await audit('100');
 await page.locator('[data-am-scale="115"]').click();await audit('115');
 await page.locator('[data-am-scale="125"]').click();await audit('125');
 await page.locator('[data-am-scale="100"]').click();await audit('100_restored');
 await page.screenshot({path:out+'/compact-one-line.png',fullPage:false});
 // JC picker must retain the working business ID link.
 await row.locator('.allocation').selectOption('JOB');
 const input=row.locator('.job-filter');
 await input.fill('sum');
 const item=page.locator('.am234-jc-option',{hasText:'JC-2026-0011'}).first();
 await item.waitFor({state:'visible',timeout:8000});
 await item.click();
 if(await row.locator('.job-select').inputValue()!=='11')
  throw new Error('AM234 linked job ID regression');
 await page.screenshot({path:out+'/compact-jc-selected.png',fullPage:false});
 // Add another line and confirm no stacked/oversized fields.
 await page.locator('#add-line').click();
 if(await page.locator('#purchase-lines tr').count()<2)throw new Error('Add Line not working');
 await row.locator('.part-search').fill('AM-0835');
 await page.locator('.part-results.open').first().waitFor({state:'visible',timeout:8000});
 await page.screenshot({path:out+'/part-dropdown-intact.png',fullPage:false});
 if(errors.length)throw new Error('JS errors '+errors.slice(0,3).join(' | '));
 console.log('AM237_BROWSER_COMPACT_ALIGNMENT_PASS_NO_PURCHASE_POST');
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:true,no_business_data_writes:true,checked:new Date().toISOString()},null,2));
} catch(e) {
 await page.screenshot({path:out+'/failure.png',fullPage:false}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:false,reason:String(e?.message||e)},null,2));
 throw e;
} finally {await browser.close()}
