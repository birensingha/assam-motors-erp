// P52 authenticated GET-only user-facing workflow acceptance.
// No deciding technician assignments, diagnosis, or business records are written.
import { chromium } from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',pass=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!pass)throw Error('QA admin credentials unavailable');
const browser=await chromium.launch({headless:true});
const errors=[];
try{
 const page=await browser.newPage({viewport:{width:1380,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
 await page.locator('input[type=password]').first().fill(pass);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 const exampleJob='JC-20261016-00036';
 const m=await page.goto(base+'/erp/inspection-manager',{waitUntil:'domcontentloaded',timeout:30000});
 const rows=page.locator('tr').filter({hasText:exampleJob});
 const row=rows.first();
 const rowText=await row.innerText().catch(()=>'');
 const inspection=await row.locator('td').nth(2).innerText().catch(()=>'');
 const deciding=await row.locator('td').nth(4).innerText().catch(()=>'');
 const normal=m?.status()===200 && inspection.includes('KAPIL RAJBONGSHI') &&
   deciding.includes('NOT ASSIGNED') && !deciding.includes('KAPIL RAJBONGSHI');
 console.log('P52_INSPECTOR_AND_DECIDER_INDEPENDENT '+(normal?'PASS':'FAIL'));
 if(!normal)errors.push('inspection role independently labeled');
 const r=await page.goto(base+'/erp/job-cards/36',{waitUntil:'domcontentloaded',timeout:30000});
 const ui=page.locator('#deciding-technician');
 const section=await ui.innerText().catch(()=>'');
 const selected=await ui.locator('select[name=technician_id] option:checked').first().getAttribute('value').catch(()=>'missing');
 const locked=(await page.locator('input[type=hidden][name=technician_id]').count())===0;
 const ok=r?.status()===200 && section.includes('NOT ASSIGNED') && 
   section.includes('Admin selection pending') && selected==='' && locked;
 console.log('P52_DECIDER_FORM_NOT_PRESELECTED '+(ok?'PASS':'FAIL')+' HTTP '+r?.status());
 if(!ok)errors.push('Job Card decision form not clearly unassigned');
 const canLink=await page.locator('form[action*="/diagnosis/assign-technician"]').count()===1;
 if(!canLink)errors.push('Admin assignment action missing');
 await page.goto(base+'/erp/inspection-manager/36',{waitUntil:'domcontentloaded',timeout:30000});
 const link=page.getByRole('link',{name:/Admin: Select Deciding Technician/}).first();
 const href=await link.getAttribute('href').catch(()=>'');
 const anchorCorrect=href?.includes('/erp/job-cards/36') && href.endsWith('#deciding-technician');
 console.log('P52_DECIDING_ASSIGNMENT_LINK '+(anchorCorrect?'PASS':'FAIL'));
 if(!anchorCorrect)errors.push('Assign deciding officer shortcut');
 await page.setViewportSize({width:390,height:844});
 const rm=await page.goto(base+'/erp/inspection-manager/36',{waitUntil:'domcontentloaded',timeout:30000});
 const mobile=rm?.status()===200 && (await page.getByText('NOT ASSIGNED').count())>0;
 console.log('P52_MOBILE_PENDING_STATUS '+(mobile?'PASS':'FAIL'));
 if(!mobile)errors.push('mobile view not unassigned');
}finally{await browser.close()}
if(errors.length)throw Error('P52 QA failed: '+errors.join('; '));
console.log('P52_INSPECTION_DECIDER_SEPARATION_AUTHENTICATED_GET_QA_PASS');
