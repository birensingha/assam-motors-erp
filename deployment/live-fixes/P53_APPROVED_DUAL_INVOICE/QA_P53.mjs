// Read-only authenticated acceptance of BOTH invoice formats.
// Never create invoices or modify tax and customer ledger.
import {chromium} from 'playwright';
const base=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN||'',password=process.env.STAGING_ADMIN_PASSWORD||'';
if(!email||!password)throw Error('P53 QA requires staging admin browser test credentials');
const browser=await chromium.launch({headless:true});
const failures=[];
try {
  const page=await browser.newPage({viewport:{width:1440,height:900}});
  await page.goto(base+'/erp/login',{waitUntil:'domcontentloaded',timeout:35000});
  await page.locator('input[type=email],input[name=email],input[name=username]').first().fill(email);
  await page.locator('input[type=password]').first().fill(password);
  await page.locator('button[type=submit],input[type=submit]').first().click();
  await page.waitForLoadState('domcontentloaded',{timeout:18000}).catch(()=>{});
  const knownInvoice=21;
  const view=await page.goto(base+'/erp/invoices/'+knownInvoice,{waitUntil:'domcontentloaded',timeout:35000});
  const taxButton=page.getByRole('link',{name:/Print Tax \/ Service Invoice/i}).first();
  const summaryButton=page.getByRole('link',{name:/Print Customer Cost Summary/i}).first();
  const linksOk=view?.status()===200 && (await taxButton.count())===1 && (await summaryButton.count())===1 &&
    (await taxButton.getAttribute('href'))?.includes('?format=tax') &&
    (await summaryButton.getAttribute('href'))?.includes('?format=summary');
  console.log('P53_TWO_PRINT_BUTTONS_IN_INVOICE_VIEW '+(linksOk?'PASS':'FAIL')+' HTTP '+view?.status());
  if(!linksOk)failures.push('invoice view dual print');
  const amounts=[];
  for(const width of [1440,390]){
   await page.setViewportSize({width,height:850});
   for(const format of ['tax','summary']){
     const response=await page.goto(base+'/erp/invoices/'+knownInvoice+'/print?format='+format,{waitUntil:'domcontentloaded',timeout:35000});
     const visible=await page.locator('body').innerText().catch(()=>'');
     const money=await page.locator('.amounts .total b').innerText().catch(()=>'');
     const doc=await page.locator('.doc-head h2').innerText().catch(()=>'');
     const hasTaxCol=await page.locator('.section th').filter({hasText:/^GST$/}).count();
     const bank=visible.includes('HDFC BANK')&&visible.includes('Bank Details');
     const notes=visible.includes('Terms and Conditions')&&visible.includes('Authorized Signatory');
     const parts=visible.includes('Parts / Materials')&&visible.includes('Labour / ROT Charges')&&visible.includes('OSL Jobs / Outside Services');
     const brand=await page.locator('.brand').evaluate(el=>getComputedStyle(el).borderTopColor).catch(()=>'');
     const matched=format==='tax'?
       doc.includes('Tax / Service Invoice')&&hasTaxCol>0:
       doc.includes('Customer Cost Summary')&&hasTaxCol===0&&visible.includes('NOT A TAX INVOICE')&&
        !visible.includes('Tax / GST (recorded)');
     const bounds=await page.evaluate(()=>({
       bodyScroll:document.documentElement.scrollWidth-innerWidth,
       brandWidth:Math.round(document.querySelector('.brand')?.getBoundingClientRect().width||0)
     }));
     const ok=response?.status()===200 && visible.includes('ASSAM MOTORS') &&
       money.startsWith('₹') && money.length>2 && matched && bank && notes && parts &&
       brand!=='rgb(0, 0, 0)' && bounds.bodyScroll<=15;
     console.log('P53_PRINT_'+format.toUpperCase()+'_'+width+' HTTP '+response?.status()+
       ' COL_GST '+hasTaxCol+' GRAND_TOTAL '+money+' OVERFLOW '+bounds.bodyScroll+' '+(ok?'PASS':'FAIL'));
     amounts.push(money);
     if(!ok)failures.push(format+'/'+width);
   }
  }
  if(new Set(amounts).size!==1){console.log('P53_SAME_GRAND_TOTAL_FAIL');failures.push('different amounts');}
  else console.log('P53_SAME_INVOICE_SAME_FINAL_AMOUNT_PASS');
  await page.setViewportSize({width:1440,height:900});
  await page.goto(base+'/erp/invoices/'+knownInvoice+'/print?format=summary',{waitUntil:'domcontentloaded'});
  const origin=await page.locator('.print-actions a').first().getAttribute('href');
  console.log('P53_DOCUMENT_SWITCH_NO_NEW_INVOICE '+(origin?.includes('/erp/invoices/21')?'PASS':'FAIL'));
  if(!origin?.includes('/erp/invoices/21'))failures.push('saved source invoice');
} finally {await browser.close()}
if(failures.length)throw Error('P53 browser print acceptance FAILED: '+failures.join(', '));
console.log('P53_APPROVED_TAX_AND_COST_SUMMARY_FORMATS_BROWSER_QA_PASS');
