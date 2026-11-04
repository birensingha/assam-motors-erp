import { chromium } from 'playwright';
import fs from 'node:fs';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const loginPath=process.env.STAGING_LOGIN_PATH||'/erp/login';
const email=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
const out=process.env.SMOKE_OUTPUT_DIR||'am240-browser-proof';
fs.mkdirSync(out,{recursive:true});
if(!email||!password) throw new Error('Missing staging Admin test credentials');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:900}});
const errors=[];
page.on('pageerror',e=>errors.push(e.message));
try {
 await page.goto(base+loginPath,{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 const res=await page.goto(base+'/erp/purchases',{waitUntil:'networkidle',timeout:45000});
 if(!res||res.status()!==200)throw new Error('Purchase Register HTTP '+res?.status());
 const rows=page.locator('.p18-register-table tbody tr[data-purchase-url]');
 const count=await rows.count();
 if(count<1)throw new Error('Purchase Register returned no rows');
 const osl=rows.filter({has:page.locator('.badge.osl')});
 const oslCount=await osl.count();
 if(oslCount<1)throw new Error('No OSL bills in current Purchase Register; cannot confirm actual Edit link');
 const target=osl.first();
 const edit=target.locator('a[aria-label^="Edit OSL Purchase"]');
 if(await edit.count()!==1)throw new Error('OSL Purchase row does not show exactly one Edit action');
 const href=await edit.getAttribute('href');
 if(!/\/workshop-tools\/osl\/\d+\/edit(?:\?|$)/.test(href||''))throw new Error('OSL Edit URL wrong: '+href);
 const editText=(await edit.innerText()).trim();
 if(!editText.includes('Edit'))throw new Error('OSL edit button title missing');
 const part=rows.filter({has:page.locator('.badge.part')});
 if(await part.count()>0){
   const partEdit=part.first().locator('a[href*="/purchases/"][href$="/edit"]');
   if(await partEdit.count()!==1)throw new Error('Parts Edit regressed');
 }
 await page.screenshot({path:out+'/purchase-register-osl-edit-button.png',fullPage:false});
 await edit.click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 if(!/\/workshop-tools\/osl\/\d+\/edit/.test(page.url()))throw new Error('OSL Edit click redirected away from editor: '+page.url());
 const body=await page.locator('body').innerText();
 if(/Internal Server Error|SQLSTATE|Whoops|Forbidden|Not Found/i.test(body))throw new Error('OSL editor shows server error');
 if(!/OSL|Outside Labour|Supplier Cost|Purchase/i.test(body))throw new Error('OSL editor page not recognizable');
 if(!(await page.locator('form').count()))throw new Error('OSL editor has no form');
 // Verify editing is genuine and audited. Do not submit any form.
 if(!(await page.locator('input[name="reason"],textarea[name="reason"]').count()))throw new Error('OSL edit form missing audit reason');
 if(!(await page.locator('input[name="vendor_rate"]').count()))throw new Error('OSL edit form missing supplier rate');
 await page.screenshot({path:out+'/osl-edit-form-opened.png',fullPage:false});
 if(errors.length)throw new Error('JS error: '+errors.slice(0,3).join(' | '));
 console.log('AM240_REGISTER_OSL_EDIT_AND_PAGE_PASS_NO_PURCHASE_WRITES');
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:true,osl_register_rows:oslCount,edit_route:href,no_data_writes:true,checked:new Date().toISOString()},null,2));
} catch(e) {
 await page.screenshot({path:out+'/failure.png',fullPage:false}).catch(()=>{});
 fs.writeFileSync(out+'/result.json',JSON.stringify({ok:false,error:String(e?.message||e)},null,2));
 throw e;
} finally{await browser.close()}
