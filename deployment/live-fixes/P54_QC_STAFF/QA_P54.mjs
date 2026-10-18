import {chromium} from 'playwright';
// P54 GET-only browser QA: no mutation of inspection, JC, QC or ledger.
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!password)throw Error('P54 admin browser QA secrets unavailable');
const browser=await chromium.launch({headless:true});
const errors=[];
try{
 const unauth=await browser.newPage();
 const r=await unauth.goto(base+'/staff/qc/36',{waitUntil:'domcontentloaded',timeout:30000});
 const blocked=r?.status()===403||r?.status()===401||new URL(unauth.url()).pathname.includes('/login');
 console.log('P54_STAFF_QC_ANONYMOUS_ACCESS_BLOCKED '+(blocked?'PASS':'FAIL')+' HTTP '+r?.status());
 if(!blocked)errors.push('anonymous QC');
 await unauth.close();
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
 await page.locator('input[type=password]').first().fill(password);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:16000}).catch(()=>{});
 for(const width of [1440,390]){
   await page.setViewportSize({width,height:850});
   const resp=await page.goto(base+'/erp/job-cards',{waitUntil:'domcontentloaded',timeout:30000});
   const actionCells=await page.locator('.jc-row-actions').count();
   const actions=await page.locator('.jc-row-actions').first().locator('a').allTextContents();
   const onlyTwo=actionCells>0&&actions.length===2&&actions.some(s=>s.includes('Open'))&&actions.some(s=>s.includes('JC Summary'));
   const noExtra=!(await page.locator('.jc-row-actions').first().innerText()).match(/Edit|Estimate|Purchase|Delete|Inspection/i);
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
   const valid=resp?.status()===200&&onlyTwo&&noExtra&&overflow<=15;
   console.log('P54_JC_ACTIONS_'+width+' '+(valid?'PASS':'FAIL')+' HTTP '+resp?.status()+' links '+actions.length+' overflow '+overflow);
   if(!valid)errors.push('job cards actions '+width);
 }
 for(const width of [1440,390]){
   await page.setViewportSize({width,height:900});
   const resp=await page.goto(base+'/erp/job-cards/36',{waitUntil:'domcontentloaded',timeout:30000});
   const noBanner=(await page.locator('.p30-next-action').count())===0;
   const panel=(await page.getByText('Workshop Lifecycle & Billing').count())>=1;
   const stillActions=(await page.getByText('Current Stage:',{exact:false}).count())>=1;
   const ok=resp?.status()===200&&noBanner&&panel&&stillActions;
   console.log('P54_LIFECYCLE_COMPACT_'+width+' '+(ok?'PASS':'FAIL')+' HTTP '+resp?.status());
   if(!ok)errors.push('lifecycle compact '+width);
 }
}finally{await browser.close()}
if(errors.length)throw Error('P54_BROWSER_QA_FAIL '+errors.join('; '));
console.log('P54_JC_ACTIONS_LIFECYCLE_STAFF_GATE_BROWSER_QA_PASS');
