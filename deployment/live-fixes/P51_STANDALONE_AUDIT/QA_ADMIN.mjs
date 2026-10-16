// P51 authenticated GET-only verification. Never writes business data.
import {chromium} from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const login=process.env.STAGING_ADMIN_LOGIN||'', password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!login||!password)throw Error('Staging Admin login missing in QA secrets');
const browser=await chromium.launch({headless:true});
const failed=[];
try{
 const anon=await browser.newPage();
 const unauth=await anon.goto(base+'/erp/admin',{waitUntil:'domcontentloaded',timeout:35000});
 const anonUrl=new URL(anon.url()).pathname;
 const unauthorized=unauth?.status()===403 || unauth?.status()===401 ||
   anonUrl.includes('/login');
 console.log('P51_ANON_NEW_ADMIN_BLOCKED '+(unauthorized?'PASS':'FAIL')+
  ' HTTP '+unauth?.status());
 if(!unauthorized)failed.push('anon permission');
 await anon.close();
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:35000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(login);
 await page.locator('input[type=password]').first().fill(password);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:850});
  const r=await page.goto(base+'/erp/admin',{waitUntil:'domcontentloaded',timeout:35000});
  const stats=await page.evaluate(()=>({
    newAdmin:!!document.querySelector('.am-admin-sidebar'),
    oldErp:!!document.querySelector('.erp-sidebar'),
    name:document.body.innerText.includes('NEW Admin'),
    links:document.querySelectorAll('.am-admin-menu a').length,
    horizontalOverflow:Math.max(0,document.documentElement.scrollWidth-innerWidth)
  }));
  const ok=r?.status()===200 && stats.newAdmin&&!stats.oldErp &&
   stats.name && stats.links>=6 && stats.horizontalOverflow<=15;
  console.log('P51_NEW_ADMIN '+width+' HTTP '+r?.status()+' '+JSON.stringify(stats)+' '+(ok?'PASS':'FAIL'));
  if(!ok)failed.push('new admin '+width);
 }
 await page.setViewportSize({width:1440,height:850});
 for(const path of ['/erp/dashboard','/erp/job-cards','/erp/workshop-tools/vendor-database']){
  const r=await page.goto(base+path,{waitUntil:'domcontentloaded',timeout:35000});
  const nav=await page.evaluate(()=>({
   oldErp:!!document.querySelector('.erp-sidebar'),
   newAdminLink:[...document.querySelectorAll('.sidebar-footer a')].some(x=>x.textContent.includes('NEW Admin Console')),
   legacyLinks:[...document.querySelectorAll('.sidebar-footer a')].filter(x=>/Classic Admin|Classic Workshop/.test(x.textContent)).length
  }));
  const ok=r?.status()===200 && nav.oldErp && nav.newAdminLink && nav.legacyLinks===0;
  console.log('P51_NEW_ERP '+path+' HTTP '+r?.status()+' '+(ok?'PASS':'FAIL'));
  if(!ok)failed.push('new erp '+path);
 }
 await page.goto(base+'/erp/dashboard',{waitUntil:'domcontentloaded'});
 await page.getByRole('link',{name:/NEW Admin Console/}).first().click();
 const clicked=new URL(page.url()).pathname==='/erp/admin';
 console.log('P51_ERP_TO_ADMIN_SWITCH '+(clicked?'PASS':'FAIL'));
 if(!clicked)failed.push('new admin navigation');
 await page.setViewportSize({width:390,height:844});
 await page.locator('.am-admin-toggle').click();
 const opened=await page.evaluate(()=>document.body.classList.contains('admin-open'));
 await page.locator('.am-admin-overlay').click({position:{x:320,y:500}});
 const closed=await page.evaluate(()=>!document.body.classList.contains('admin-open'));
 console.log('P51_ADMIN_MOBILE_MENU '+(opened&&closed?'PASS':'FAIL'));
 if(!(opened&&closed))failed.push('admin responsive menu');
}finally{await browser.close()}
if(failed.length)throw Error('P51_AUTH_QA_FAILED '+failed.join(', '));
console.log('P51_ADMIN_ERP_UI_SEPARATION_GET_ONLY_QA_PASS');
