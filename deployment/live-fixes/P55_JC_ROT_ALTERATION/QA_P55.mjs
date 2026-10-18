// P55 browser QA is GET-only. Never POST or modify a real completed ROT.
import {chromium} from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const login=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!login||!password)throw Error('Admin test login unavailable');
const browser=await chromium.launch({headless:true});
const errors=[];
try{
 const guest=await browser.newPage();
 await guest.goto(base+'/erp/job-cards/21/edit',{waitUntil:'domcontentloaded',timeout:30000});
 // JC-2026-0021: live read-only audit verified in-progress with seven ROT rows.
 const guestProtected=new URL(guest.url()).pathname.endsWith('/login');
 console.log('P55_ADMIN_AUTH_REQUIRED '+(guestProtected?'PASS':'FAIL'));
 if(!guestProtected)errors.push('anonymous JC edit not protected');
 await guest.close();
 const page=await browser.newPage({viewport:{width:1440,height:900}});
 await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:30000});
 await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(login);
 await page.locator('input[type=password]').first().fill(password);
 await page.locator('button[type=submit],input[type=submit]').first().click();
 await page.waitForLoadState('domcontentloaded',{timeout:16000}).catch(()=>{});
 for(const width of [1440,390]){
  await page.setViewportSize({width,height:850});
  const response=await page.goto(base+'/erp/job-cards/21/edit',{waitUntil:'domcontentloaded',timeout:30000});
  const panel=page.locator('#p55-rot-edit');
  const found=response?.status()===200&&(await panel.count())===1;
  const row=panel.locator('[data-p55-item]').first();
  const form=panel.locator('[data-p55-form]').first();
  const count=await panel.locator('[data-p55-item]').count();
  const original=page.locator('form[action*="/job-cards/21"]:not([data-p55-form])');
  const baseFields=await page.locator('input[name=customer_name]').count();
  const exists=found&&count>0&&(await original.count())>0&&baseFields>0;
  console.log('P55_EDITOR_'+width+' '+(exists?'PASS':'FAIL')+' rows='+count+' http='+response?.status());
  if(!exists){errors.push('no ROT rows/form '+width);continue;}
  const disabled=await form.locator('[data-p55-field]:disabled').count();
  const fcount=await form.locator('[data-p55-field]').count();
  const saveDisabled=await form.locator('[data-p55-save]').isDisabled();
  const lockReady=(disabled===fcount)&&saveDisabled&&fcount===7;
  if(!lockReady)errors.push('pre-unlock locked '+width);
  await row.locator('[data-p55-lock]').click();
  const visible=await row.locator('[data-p55-approval]').isVisible();
  if(!visible)errors.push('reason panel not visible '+width);
  await row.locator('[data-p55-reason]').selectOption({label:'Standard Hours / Rate correction'});
  await row.locator('[name=admin_note]').fill('Admin correction screen test only. No data submitted.');
  await row.locator('[data-p55-unlock]').click();
  const after=await form.locator('[data-p55-field]:enabled').count();
  const saveEnabled=await form.locator('[data-p55-save]').isEnabled();
  const status=await row.locator('[data-p55-success]').isVisible();
  const unlocked=after===fcount&&saveEnabled&&status;
  console.log('P55_LOCK_REASON_NOTE_UNLOCK_'+width+' '+(unlocked?'PASS':'FAIL')+' beforeDisabled='+disabled+' afterEnabled='+after);
  if(!unlocked)errors.push('unlock process '+width);
  const nested=await page.locator('form form').count();
  if(nested){errors.push('nested HTML forms '+width)}
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
  console.log('P55_RESPONSIVE_NO_OVERFLOW_'+width+' '+(overflow<=15?'PASS':'FAIL')+' '+overflow);
  if(overflow>15)errors.push('overflow '+width);
 }
}finally{await browser.close()}
if(errors.length)throw Error('P55_ROT_EDITOR_QA_FAIL: '+errors.join('; '));
console.log('P55_OLD_ERP_ROT_LOCK_REASON_NOTE_UNLOCK_QA_PASS_NO_POST');
