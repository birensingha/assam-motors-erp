import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const loginPath=process.env.STAGING_LOGIN_PATH||'/erp/login';
const email=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am238-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!email||!password)throw new Error('Missing staging Admin test credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:900}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
const checkPart=async row=>{
 const part=row.locator('.part-search');
 await part.fill('AM-0835');
 const item=row.locator('.part-option').filter({hasText:'AM-0835'}).first();
 await item.waitFor({state:'visible',timeout:9000});
 await item.click();
 const id=await row.locator('.part-id').inputValue();
 if(!id||id==='0')throw new Error('Part code could not be selected');
 return id;
};
try {
 await page.goto(base+loginPath,{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 const response=await page.goto(base+'/erp/purchases/create',{waitUntil:'networkidle',timeout:45000});
 if(!response||response.status()!==200)throw new Error('Purchase form HTTP '+response?.status());
 const first=page.locator('#purchase-lines tr').first();
 await first.waitFor({state:'visible'});
 if(!await page.locator('script[src*="purchase-entry.js?v=238"]').count())throw new Error('AM238 cache-busted JS not loaded');
 const publicScript=await page.locator('script[src*="purchase-entry.js"]').last().getAttribute('src');
 const served=await page.evaluate(async src=>await fetch(src,{cache:'no-store'}).then(x=>x.text()),publicScript);
 if(!served.includes('AM238_JC_ENTER_SAFE_NEXT_LINE'))throw new Error('Actual served JS not patched');
 await checkPart(first);
 await first.locator('.allocation').selectOption('JOB');
 const jc=first.locator('.job-filter');
 await jc.fill('sum');
 const option=page.locator('.am234-jc-option',{hasText:'JC-2026-0011'}).first();
 await option.waitFor({state:'visible',timeout:9000});
 if(!(await option.innerText()).includes('SUMEN ROY'))throw new Error('Expected SUMEN ROY suggestion missing');
 await option.click();
 if(await first.locator('.job-select').inputValue()!=='11')throw new Error('JC ID 11 not selected initially');
 await jc.focus();
 await jc.press('Enter');
 if(await first.locator('.job-select').inputValue()!=='11')throw new Error('Enter replaced selected SUMEN ROY JC');
 if(await page.locator('#purchase-lines tr').count()!==2)throw new Error('Enter after selected JC failed to create next line');
 const second=page.locator('#purchase-lines tr').nth(1);
 const activeClass=await page.evaluate(()=>document.activeElement?.className||'');
 if(!String(activeClass).includes('part-search'))throw new Error('New row Part Search not focused: '+activeClass);
 if(await second.locator('.part-id').inputValue()!=='')throw new Error('Next row was not blank');
 await page.screenshot({path:out+'/jc-0011-enter-next-row.png',fullPage:false});
 // Independent row and keyboard selection. A second entry should never mutate row 1.
 await checkPart(second);
 await second.locator('.allocation').selectOption('JOB');
 const jc2=second.locator('.job-filter');
 await jc2.fill('JC-20261031-00046');
 const option2=page.locator('.am234-jc-option',{hasText:'JC-20261031-00046'}).first();
 await option2.waitFor({state:'visible',timeout:9000});
 await jc2.press('Enter'); // choose the matching second JC by keyboard
 const secondId=await second.locator('.job-select').inputValue();
 if(!secondId||secondId==='11')throw new Error('Second line independent JC selection failed');
 await jc2.press('Enter'); // advance after selected JC
 if(await page.locator('#purchase-lines tr').count()!==3)throw new Error('Second line Enter did not add third line');
 if(await second.locator('.job-select').inputValue()!==secondId)throw new Error('Second line changed its JC on Enter');
 if(await first.locator('.job-select').inputValue()!=='11')throw new Error('First line JC changed during second line entry');
 await page.screenshot({path:out+'/multi-jc-enter-3-rows.png',fullPage:false});
 if(errors.length)throw new Error('Browser JavaScript errors: '+errors.slice(0,3).join(' | '));
 console.log('AM238_ENTER_JC_LOCK_AND_NEXT_ROW_PASS_NO_PURCHASE_POST');
 fs.writeFileSync(out+'/result.json',JSON.stringify({success:true,first_id:11,second_id:secondId,rows:3,no_purchase_saved:true,checked:new Date().toISOString()},null,2));
} catch(e) {
 await page.screenshot({path:out+'/failure.png',fullPage:false}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({success:false,error:String(e?.message||e)},null,2));
 throw e;
} finally {await browser.close();}
