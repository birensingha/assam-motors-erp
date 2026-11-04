import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
const loginPath=process.env.STAGING_LOGIN_PATH||'/erp/login';
const out=process.env.SMOKE_OUTPUT_DIR||'am241-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!email||!password)throw new Error('Missing admin test credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:900}});
const exceptions=[];
page.on('pageerror',e=>exceptions.push(e.message));
try{
 await page.goto(base+loginPath,{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 let response=await page.goto(base+'/erp/purchases',{waitUntil:'networkidle',timeout:45000});
 if(!response||response.status()!==200)throw new Error('Purchase Register HTTP '+response?.status());
 const purchaseRows=page.locator('.p18-register-table tbody tr[data-purchase-url]');
 const oslRows=purchaseRows.filter({has:page.locator('.badge.osl')});
 if(await oslRows.count()<1)throw new Error('OSL purchase register has no OSL rows for QA');
 const first=oslRows.first(),edit=first.locator('a[aria-label^="Edit OSL Purchase"]');
 if(await edit.count()!==1)throw new Error('OSL Edit link missing in register');
 const href=await edit.getAttribute('href');
 if(!/\/purchases\/osl-batches\/\d+\/edit(?:\?|$)/.test(href||''))throw new Error('OSL Edit not linked to new full bill editor '+href);
 await edit.click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 if(!page.url().includes('/purchases/osl-batches/'))throw new Error('Did not navigate to full OSL editor');
 await page.locator('h1',{hasText:'Edit OSL Purchase'}).waitFor({state:'visible'});
 const form=page.locator('#am241-edit-form'),voidForm=page.locator('#am241-void-form');
 await form.waitFor({state:'visible'});
 await voidForm.waitFor({state:'visible'});
 for(const selector of ['select[name=vendor_id]','input[name=purchase_date]','input[name=supplier_invoice]','textarea[name=notes]','textarea[name=reason]','input[name=expected_total]']){
  if(await form.locator(selector).count()!==1)throw new Error('Missing edit header field '+selector);
 }
 const rows=page.locator('#am241-lines tr.am241-line'),initialCount=await rows.count();
 if(initialCount<1)throw new Error('Existing OSL purchase has no lines');
 for(const selector of ['[name$="[purchase_id]"]','[name$="[job_card_id]"]','[name$="[labour_line_id]"]','[name$="[description]"]','[name$="[qty]"]','[name$="[unit_cost]"]','[name$="[discount_percent]"]','[name$="[gst_mode]"]','[name$="[gst_percent]"]','.am241-line-total','.am241-remove']){
  if(await rows.first().locator(selector).count()!==1)throw new Error('Existing OSL row missing field '+selector);
 }
 if(await voidForm.locator('textarea[name=void_reason]').count()!==1)throw new Error('Delete/Void reason missing');
 if(await voidForm.locator('button[type=submit]').count()!==1)throw new Error('Delete/Void button not available');
 if(await page.locator('#am241-grand').count()!==1)throw new Error('OSL summary Grand Total missing');
 await page.screenshot({path:out+'/osl-full-edit-with-delete-button.png',fullPage:true});
 const blocked=await page.locator('.am241-error:has-text("Financial editing locked")').count()>0;
 if(!blocked){
   await page.locator('#am241-add-line').click();
   if(await rows.count()!==initialCount+1)throw new Error('Add OSL line does not work');
   const newRow=rows.last();
   if(await newRow.locator('.am241-billing-choice').count()!==1)throw new Error('New line JC OSL selection missing');
   page.once('dialog',d=>d.accept());
   await newRow.locator('.am241-remove').click();
   if(await rows.count()!==initialCount)throw new Error('Remove line did not revert unsaved line');
 }
 // Clicking Delete is intentionally prohibited: browser QA is non-posting only.
 const deleteAction=await voidForm.getAttribute('action');
 if(!deleteAction.includes('/purchases/osl-batches/'))throw new Error('Delete/Void action points to wrong URI');
 if(await form.locator('[name="_method"]').inputValue()!=='PUT')throw new Error('Edit method wrong');
 if(await voidForm.locator('[name="_method"]').inputValue()!=='DELETE')throw new Error('Void method wrong');
 const bad=await page.locator('body').innerText();
 if(/SQLSTATE|Internal Server Error|Fatal error|Whoops/.test(bad))throw new Error('OSL Edit page server error');
 if(exceptions.length)throw new Error('Browser JS errors '+exceptions.join(' | ').slice(0,600));
 console.log('AM241_OSL_FULL_EDIT_VOID_UI_BROWSER_PASS_NO_FINANCIAL_WRITES');
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:true,existingLines:initialCount,voidVisible:true,blocked,noPurchaseOrVoidPosted:true,checked:new Date().toISOString()},null,2));
}catch(e){
 await page.screenshot({path:out+'/failure.png',fullPage:true}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:false,error:String(e?.message||e)},null,2));
 throw e;
}finally{await browser.close()}
