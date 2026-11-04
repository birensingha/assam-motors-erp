import { chromium } from 'playwright';
import fs from 'node:fs';
// READ-ONLY browser smoke: never clicks Purchase Save.
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const loginPath=process.env.STAGING_LOGIN_PATH||'/erp/login';
const username=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am234-browser-proof';
fs.mkdirSync(out,{recursive:true});
if (!username||!password) throw new Error('Missing staging test Admin credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:900}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
try {
 await page.goto(base+loginPath,{waitUntil:'domcontentloaded',timeout:45000});
 const user=page.locator('input[type="email"],input[name="email"],input[name="username"]').first();
 const pass=page.locator('input[type="password"]').first();
 await user.fill(username);await pass.fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 const response=await page.goto(base+'/erp/purchases/create',{waitUntil:'networkidle',timeout:45000});
 if (!response||response.status()>=400) throw new Error('Purchase page HTTP status '+response?.status());
 const row=page.locator('#purchase-lines tr').first();
 await row.waitFor({state:'attached',timeout:15000});
 if (!await row.locator('.part-search').count()) throw new Error('Original Part Search missing');
 const input=row.locator('.job-filter'),select=row.locator('.job-select'),against=row.locator('.allocation');
 const probe = await page.evaluate(async () => {
   const row = document.querySelector('#purchase-lines tr');
   const host = row?.querySelector('.job-filter-wrap');
   const input = row?.querySelector('.job-filter');
   const allocation = row?.querySelector('.allocation');
   const select = row?.querySelector('.job-select');
   const scripts = Array.from(document.scripts).filter(s => s.src.includes('purchase-entry.js')).map(s => s.src);
   const served = scripts.length ? await fetch(scripts[scripts.length-1], {cache:'no-store'}).then(r=>r.text()).catch(()=> '') : '';
   return {
     against:allocation?.value, initialDisabled:input?.disabled,
     pickerInitialized:host?.dataset?.am234Ready || '',
     jobOptions:select?.options?.length ?? -1,
     purchaseScriptUrls:scripts, servedScriptContainsAM234:served.includes('AM234_PURCHASE_JC_PICKER_V2')
   };
 });
 console.log('AM234_RUNTIME_PROBE',JSON.stringify(probe));
 if (!probe.servedScriptContainsAM234 || probe.pickerInitialized!=='1')
   throw new Error('AM234 JavaScript not served or initialization missing: '+JSON.stringify(probe));
 if (!await input.isDisabled()) throw new Error('STOCK mode should disable Job Card input');
 await against.selectOption('JOB');
 if (await input.isDisabled()) throw new Error('JOB mode did not enable JC lookup');
 await input.fill('sum');
 const match=page.locator('.am234-jc-option',{hasText:'JC-2026-0011'}).first();
 await match.waitFor({state:'visible',timeout:8000});
 if (!(await match.innerText()).includes('SUMEN ROY')) throw new Error('Correct customer not in dropdown');
 await page.screenshot({path:out+'/jc-search-results.png'});
 await match.click();
 if (await select.inputValue()!=='11') throw new Error('Expected authoritative JC ID 11');
 await input.fill('unknown');
 if (await select.inputValue()!=='') throw new Error('Editing search did not clear stale linked JC');
 await against.selectOption('STOCK');
 if (!await input.isDisabled()||await select.inputValue()!=='') throw new Error('STOCK reset failed');
 if (errors.length) throw new Error('JS page errors: '+errors.slice(0,3).join(' | '));
 await page.screenshot({path:out+'/stock-reset.png'});
 fs.writeFileSync(out+'/result.json',JSON.stringify({success:true,checked:new Date().toISOString(),no_business_data_written:true},null,2));
 console.log('AM234_BROWSER_SMOKE_PASS');
} catch(error) {
 fs.writeFileSync(out+'/result.json',JSON.stringify({success:false,reason:String(error?.message||error)},null,2));
 await page.screenshot({path:out+'/failure.png'}).catch(()=>{});
 throw error;
} finally {await browser.close();}
