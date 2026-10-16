// FIX50 authenticated layout/route QA — GET and UI clicks only; no finance writes.
import {chromium} from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!password)throw Error('Staging admin browser QA secrets missing');
const browser=await chromium.launch({headless:true});
const failures=[];
try{
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
 await page.locator('input[type=password]').first().fill(password);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:14000}).catch(()=>{});
 const targets=[
 ['/erp/dashboard','Dashboard'],
 ['/erp/job-cards','Job Cards'],
 ['/erp/purchases','Purchase'],
 ['/erp/workshop-tools/vendor-database','Vendor Database'],
 ['/erp/workshop-tools/osl/register','OSL Register'],
 ['/erp/workshop-tools/payment-vouchers','Voucher Register']
 ];
 async function test(path,label,width){
  await page.setViewportSize({width,height:850});
  const response=await page.goto(base+path,{waitUntil:'domcontentloaded',timeout:35000});
  const metrics=await page.evaluate(()=>{
   const rect=(sel)=>{const e=document.querySelector(sel);if(!e)return null;const r=e.getBoundingClientRect();return{x:Math.round(r.x),y:Math.round(r.y),width:Math.round(r.width),height:Math.round(r.height)}};
   return{side:rect('.erp-sidebar'),head:rect('.erp-topbar'),main:rect('.page-shell'),topLinks:document.querySelectorAll('.erp-topbar .am50-top-shortcuts a').length,sideLinks:document.querySelectorAll('.erp-sidebar .am50-finance').length,scrollX:Math.max(0,document.documentElement.scrollWidth-innerWidth),theme:!!document.querySelector('style') && !!document.querySelector('.am50-top-shortcuts')};
  });
  const present=response?.status()===200 && metrics.side&&metrics.head&&metrics.main && metrics.topLinks===4 && metrics.sideLinks===4;
  const sideOk=width>1020?
    Math.abs(metrics.side.x)<=2 && Math.abs(metrics.head.x-(metrics.side.x+metrics.side.width))<=3 && Math.abs(metrics.main.x-metrics.head.x)<=3:
    Math.abs(metrics.head.x)<=2 && Math.abs(metrics.main.x)<=2;
  // Sticky topbar must be in normal flow above the content (not overlapping it).
  const verticalOk=metrics.main.y>=metrics.head.y+metrics.head.height-3;
  const allowed=present && sideOk && verticalOk && metrics.scrollX<=15;
  console.log('FIX50_LAYOUT '+label+' '+width+' HTTP '+(response?.status()??0)+' NAV '+metrics.sideLinks+'/'+metrics.topLinks+
    ' X '+metrics.side?.x+','+metrics.head?.x+','+metrics.main?.x+
    ' Y '+metrics.head?.y+','+metrics.head?.height+','+metrics.main?.y+
    ' OVERFLOW '+metrics.scrollX+' '+(allowed?'PASS':'FAIL'));
  if(!allowed)failures.push(label+'_'+width);
  return allowed;
 }
 for(const w of [1440,1024,390]){
   for(const [path,label] of targets)await test(path,label,w);
 }
 await page.setViewportSize({width:390,height:850});
 await page.goto(base+'/erp/dashboard',{waitUntil:'domcontentloaded'});
 const toggle=page.locator('.sidebar-toggle');
 await toggle.click();
 const opened=await page.evaluate(()=>document.body.classList.contains('sidebar-open'));
 if(!opened)failures.push('mobile_sidebar_open');
 await page.locator('.am50-overlay').click({position:{x:330,y:350}});
 const closed=await page.evaluate(()=>!document.body.classList.contains('sidebar-open'));
 if(!closed)failures.push('mobile_sidebar_close');
 console.log('FIX50_MOBILE_SIDEBAR '+(opened&&closed?'PASS':'FAIL'));
 // Primary finance navigation must actually take the user to the intended page.
 await page.setViewportSize({width:1440,height:900});
 await page.goto(base+'/erp/dashboard',{waitUntil:'domcontentloaded'});
 await page.locator('.am50-top-shortcuts a').nth(2).click();
 const vendorRoute=new URL(page.url()).pathname;
 if(vendorRoute!=='/erp/workshop-tools/vendor-database')failures.push('finance_nav_vendor_route');
 console.log('FIX50_VENDOR_NAV_CLICK '+(vendorRoute==='/erp/workshop-tools/vendor-database'?'PASS':'FAIL'));
}finally{await browser.close()}
if(failures.length)throw Error('FIX50_LAYOUT_BROWSER_QA_FAILED '+failures.join(','));
console.log('FIX50_AUTHENTICATED_SIDEBAR_TOPBAR_ALIGNMENT_AND_SHORTCUTS_PASS');
