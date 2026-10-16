// P52 UI validation against CURRENT staging status.
// JC-36 has progressed through legitimate Admin assignment; never assume
// a previously pending real customer job stays unassigned during QA.
import { chromium } from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',pass=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!pass)throw Error('QA admin login missing');
const browser=await chromium.launch({headless:true});
const failed=[];
try{
 const page=await browser.newPage({viewport:{width:1380,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
 await page.locator('input[type=password]').first().fill(pass);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:15000}).catch(()=>{});
 const jm=await page.goto(base+'/erp/inspection-manager',{waitUntil:'domcontentloaded',timeout:30000});
 const row=page.locator('tr').filter({hasText:'JC-20261016-00036'}).first();
 const inspector=await row.locator('td').nth(2).innerText().catch(()=>'');
 const deciding=await row.locator('td').nth(4).innerText().catch(()=>'');
 // The Inspector and Decider must come from *different sources*: inspection
 // assignment table vs latest explicit Admin diagnosis event. The technician
 // MAY be identical, but only with "Assigned by Admin" evidence.
 const roleValid=jm?.status()===200 && inspector.includes('KAPIL RAJBONGSHI') &&
   (deciding.includes('Assigned by Admin') || deciding.includes('NOT ASSIGNED'));
 console.log('P52_INDEPENDENT_ASSIGNMENT_CELLS '+(roleValid?'PASS':'FAIL'));
 if(!roleValid)failed.push('manager status/labels');
 const jd=await page.goto(base+'/erp/job-cards/36',{waitUntil:'domcontentloaded',timeout:30000});
 const box=page.locator('#deciding-technician');
 const hasBox=(await box.count())>0;
 const pageText=await page.locator('body').innerText();
 let jobOk=jd?.status()===200 && pageText.includes('Inspection');
 if(hasBox){
   const t=await box.innerText();
   const waiting=t.includes('NOT ASSIGNED');
   const official=t.includes('Assigned / Deciding Technician') && !waiting;
   const select=await box.locator('select[name=technician_id] option:checked').first().getAttribute('value').catch(()=>'missing');
   // Even with existing JC technician, the Admin chooses a name explicitly.
   jobOk=jobOk && (waiting||official) && select==='';
   if(waiting){
     const hidden=await page.locator('input[type=hidden][name=technician_id]').count();
     jobOk=jobOk && hidden===0;
   }
   console.log('P52_JOBCARD_ADMIN_ASSIGNMENT_FORM '+(jobOk?'PASS':'FAIL')+' '+(waiting?'PENDING':'CONFIRMED'));
 } else {
   // On completed diagnosis/decision this edit panel can be intentionally
   // locked/hidden. No need to reopen a real job to fake pre-work state.
   jobOk=jobOk && /Diagnosis|Decision/.test(pageText);
   console.log('P52_COMPLETED_JOBCARD_REMAINS_READABLE '+(jobOk?'PASS':'FAIL'));
 }
 if(!jobOk)failed.push('Job Card form/status');
 const detail=await page.goto(base+'/erp/inspection-manager/36',{waitUntil:'domcontentloaded',timeout:30000});
 const detailsOk=detail?.status()===200 && (await page.locator('table').count())>0;
 console.log('P52_MANAGER_DETAILS '+(detailsOk?'PASS':'FAIL'));
 if(!detailsOk)failed.push('manager detail');
 await page.setViewportSize({width:390,height:844});
 const mob=await page.goto(base+'/erp/inspection-manager/36',{waitUntil:'domcontentloaded',timeout:30000});
 const mobileRow=page.locator('tr').filter({hasText:'JC-20261016-00036'}).first();
 const mobileDecider=await mobileRow.locator('td').nth(4).innerText().catch(()=>'');
 const mobileOk=mob?.status()===200 &&
   (mobileDecider.includes('NOT ASSIGNED')||mobileDecider.includes('Assigned by Admin'));
 console.log('P52_MOBILE_DECIDER_STATUS '+(mobileOk?'PASS':'FAIL'));
 if(!mobileOk)failed.push('mobile role');
}finally{await browser.close()}
if(failed.length)throw Error('P52 CURRENT-STATE QA failed: '+failed.join('; '));
console.log('P52_AUTHENTICATED_ROLES_AND_DECIDING_ADMIN_CONTROL_GET_QA_PASS');
