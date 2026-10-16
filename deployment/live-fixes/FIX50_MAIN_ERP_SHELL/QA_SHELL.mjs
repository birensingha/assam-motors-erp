import { chromium } from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',pass=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!pass)throw Error('Missing staging QA credentials');
const browser=await chromium.launch({headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
 await page.locator('input[type=password]').first().fill(pass);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 for(const width of [1440,1024,390]){
   await page.setViewportSize({width,height:850});
   await page.goto(base+'/erp/job-cards',{waitUntil:'domcontentloaded'});
   const m=await page.evaluate(()=>{
     const rect=(sel)=>{let e=document.querySelector(sel);if(!e)return null;let r=e.getBoundingClientRect(),s=getComputedStyle(e);return{x:Math.round(r.x),y:Math.round(r.y),width:Math.round(r.width),height:Math.round(r.height),position:s.position,display:s.display,overflow:s.overflow,transform:s.transform}};
     return {body:rect('body'),sidebar:rect('.erp-sidebar'),topbar:rect('.erp-topbar'),main:rect('.page-shell'),nav:rect('.sidebar-nav'),links:document.querySelectorAll('.sidebar-nav a').length,financeLinks:[...document.querySelectorAll('.sidebar-nav a')].filter(x=>/Voucher Edit|Vendor Database|OSL Purchases|JC Amount/.test(x.textContent)).length,overflowX:document.documentElement.scrollWidth-innerWidth,styles:[...document.querySelectorAll('link[rel=stylesheet]')].map(x=>x.getAttribute('href')).map(h=>h&&h.split('?')[0])};
   });
   console.log('FIX50_BASELINE '+width+' '+JSON.stringify(m));
 }
}finally{await browser.close()}
