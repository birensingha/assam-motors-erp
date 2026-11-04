import { chromium } from 'playwright';
import { parse } from 'acorn';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'';
const password=process.env.STAGING_ADMIN_PASSWORD||'';
const path=process.env.STAGING_LOGIN_PATH||'/erp/login';
if(!email||!password)throw new Error('Missing staging QA auth');
const browser=await chromium.launch({headless:true});
const page=await browser.newPage();
let errors=[];
page.on('pageerror',e=>errors.push(e.message));
try{
 await page.goto(base+path,{waitUntil:'domcontentloaded',timeout:45000});
 await page.locator('input[type="email"],input[name="email"],input[name="username"]').first().fill(email);
 await page.locator('input[type="password"]').first().fill(password);
 await page.locator('button[type="submit"],input[type="submit"]').first().click();
 await page.waitForLoadState('networkidle').catch(()=>{});
 errors=[];
 const response=await page.goto(base+'/erp/payments/create',{waitUntil:'networkidle',timeout:45000});
 console.log('AM242_BASELINE_HTTP',response?.status());
 console.log('AM242_BASELINE_PAGE_ERRORS',JSON.stringify(errors));
 const scripts=await page.locator('script').evaluateAll(els=>els.map((e,i)=>({
  index:i,id:e.id||'',type:e.type||'',src:e.src||'',
  code:(!e.src && (e.type===''||e.type==='text/javascript'||e.type==='module'))?e.textContent:''
 })));
 let failed=0;
 for(const s of scripts){
  if(!s.code)continue;
  try{parse(s.code,{ecmaVersion:'latest',sourceType:s.type==='module'?'module':'script',allowReturnOutsideFunction:true});}
  catch(err){
   failed++;
   const line=err.loc?.line||0;
   const text=s.code.split('\n');
   const snip=text.slice(Math.max(0,line-2),Math.min(text.length,line+2))
     .map(x=>x.replace(/(['"=])[A-Za-z0-9@.-]{15,}(?=['"])/g,'$1[REDACTED]').slice(0,200));
   console.log('AM242_INVALID_INLINE_SCRIPT',JSON.stringify({scriptIndex:s.index,id:s.id,line,error:err.message,snippet:snip}));
  }
 }
 console.log('AM242_BASELINE_PARSE_RESULT',JSON.stringify({inlineScripts:scripts.filter(s=>s.code).length,failures:failed,pageErrors:errors.length}));
 if(response?.status()!==200)throw new Error('Cannot inspect baseline Payment Voucher');
}finally{await browser.close()}
