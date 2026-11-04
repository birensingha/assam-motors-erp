import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const loginPath=process.env.STAGING_LOGIN_PATH||'/erp/login';
const email=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am239-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!email||!password)throw new Error('Missing staging Admin test credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:840}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
try{
 await page.goto(base+loginPath,{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 const response=await page.goto(base+'/erp/purchases',{waitUntil:'networkidle',timeout:45000});
 if(!response||response.status()!==200)throw new Error('Purchase Register HTTP '+response?.status());
 await page.locator('.p18-purchase-register .p18-hero').waitFor({state:'visible',timeout:10000});
 const nav=page.locator('.p18-purchase-register .p18-actions');
 const links=nav.locator('a.p18-create');
 if(await links.count()!==3)throw new Error('Expected exactly 3 Purchase Register action buttons');
 const names=(await links.allTextContents()).map(x=>x.trim());
 if(!names[0].includes('New OSL Purchase')||!names[1].includes('New Parts Purchase')||!names[2].includes('Parts Reconciliation'))throw new Error('CTA order/text incorrect: '+names.join(' | '));
 const hrefs=await Promise.all([0,1,2].map(i=>links.nth(i).getAttribute('href')));
 if(!hrefs[0].includes('/purchases/osl')||!hrefs[1].includes('/purchases/create')||!hrefs[2].includes('/purchases/reconciliation'))throw new Error('Purchase actions destinations incorrect: '+hrefs.join(','));
 const old=await page.locator('body').innerText();
 if(old.includes('AM235_PURCHASE_RECONCILIATION'))throw new Error('Old technical marker still shown in visible button');
 const measure=async(size)=>{
   await page.setViewportSize({width:size,height:880});
   await page.waitForTimeout(200);
   const data=await nav.evaluate(nav=>{
      const links=[...nav.querySelectorAll('a.p18-create')];
      const rects=links.map(el=>{const r=el.getBoundingClientRect(),css=getComputedStyle(el);return {top:r.top,left:r.left,height:r.height,width:r.width,bg:css.backgroundImage,color:css.color,display:css.display};});
      const grid=getComputedStyle(nav);
      return {rects,display:grid.display,columns:grid.gridTemplateColumns,navWidth:nav.getBoundingClientRect().width};
   });
   console.log('AM239_GEOMETRY_'+size,JSON.stringify(data));
   if(data.rects.length!==3||data.display!=='grid')throw new Error('Bad layout '+size);
   if(Math.max(...data.rects.map(x=>x.top))-Math.min(...data.rects.map(x=>x.top))>2)
      throw new Error('Buttons are not in one row at '+size);
   if(Math.max(...data.rects.map(x=>x.height))-Math.min(...data.rects.map(x=>x.height))>2)
      throw new Error('Buttons height differs at '+size);
   if(data.rects.some(x=>x.width<80))throw new Error('Buttons too narrow at '+size);
   if(!data.rects[2].bg.includes('gradient'))throw new Error('Reconciliation button not coloured');
 };
 await measure(1440);
 await page.screenshot({path:out+'/purchase-register-3-buttons-desktop.png',fullPage:false});
 await measure(1180);
 await page.screenshot({path:out+'/purchase-register-3-buttons-1180.png',fullPage:false});
 await measure(760);
 await page.screenshot({path:out+'/purchase-register-3-buttons-tablet.png',fullPage:false});
 await page.setViewportSize({width:1440,height:900});
 const link=nav.locator('a.p18-recon');
 await link.click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 if(!page.url().includes('/purchases/reconciliation'))throw new Error('Reconciliation action did not navigate');
 await page.locator('h1',{hasText:'Parts Purchase Reconciliation'}).first().waitFor({state:'visible'});
 if(errors.length)throw new Error('JavaScript errors: '+errors.slice(0,4).join(' | '));
 console.log('AM239_THREE_COLOURED_BUTTONS_SAME_ROW_PASS_NO_PURCHASE_WRITES');
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:true,buttons:names,no_purchase_saved:true,checked:new Date().toISOString()},null,2));
}catch(e){
 await page.screenshot({path:out+'/failure.png',fullPage:false}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:false,error:String(e?.message||e)},null,2));
 throw e;
}finally{await browser.close()}
