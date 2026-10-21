#!/usr/bin/env python3
"""FIX77 Money Receipt: make zero-value validation actionable and invoice due opt-in.
Only edits the Money Receipt create Blade view; no POST, routes, DB or ledger changes.
"""
from pathlib import Path
import sys,os,tempfile,hashlib
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: patch.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
p=root/'resources/views/money-receipts/create.blade.php'
raw=p.read_bytes();d=hashlib.sha256(raw).hexdigest()
expected='efbdf376611bebb1faaa20e8ce793905520af129c300744b92e2f126644fe17b'
print('FIX77_VIEW_SHA',d)
s=raw.decode('utf8','surrogateescape')
marker='FIX77_RECEIPT_AMOUNT_GUIDANCE'
if marker in s:print('FIX77_ALREADY_APPLIED');sys.exit(0)
if d!=expected:raise SystemExit('FIX77_SOURCE_CHANGED_BLOCK_UNEXPECTED_VIEW')
def one(a,b,desc):
 global s
 count=s.count(a);print('FIX77_ANCHOR',desc,count)
 if count!=1:raise RuntimeError('FIX77_ANCHOR_MISMATCH_'+desc)
 s=s.replace(a,b,1)
css="""<style id="FIX77_RECEIPT_AMOUNT_GUIDANCE">
.p77-amount-guide{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;
 margin:6px 0 13px;padding:10px 12px;background:#ebf5ff;color:#194e83;
 border:1px solid #a8c9e8;border-radius:9px;font-size:11.5px;line-height:1.5}
.p77-amount-guide strong{font-weight:900}
.p77-fill-due{border:1px solid #2b6eac;background:white;color:#185487;font:800 11px Arial,sans-serif;
 border-radius:8px;padding:8px 12px;min-height:33px;cursor:pointer}
.p77-fill-due:hover{background:#d5eafc}
.p77-amount-error{padding:10px 12px;margin:9px 0;color:#a52a2b;background:#fff0f0;
 border:1px solid #d96666;border-left:4px solid #bf3030;border-radius:8px;font-size:12px;font-weight:800}
.p77-amount-error[hidden]{display:none!important}
.p58-label .p77-amount-invalid{border:2px solid #c53535!important;
 outline:2px solid #f5b1b1!important;background:#fff7f7!important}
.p58-label input[readonly]{background:#eef3f9!important;color:#6b8096!important}
</style>
"""
one('</style>\n<div class="p58-wrap">','</style>\n'+css+'<div class="p58-wrap">','css')
one('<h2>2. Payment Details</h2>','''<h2>2. Payment Details</h2>
     <div class="p77-amount-guide">
      <span><strong>Enter actual amount received.</strong> Invoice Outstanding is display-only; it is not a payment amount.
      For Cash choose Cash Received; for UPI / Bank choose Online / Bank Received.</span>
      <button type="button" id="p77-fill-due" class="p77-fill-due">Use Invoice Outstanding</button>
     </div>
     <div id="p77-amount-error" class="p77-amount-error" role="alert" aria-live="assertive" hidden></div>''','amount_guide')
one('id="p58-cash" name="cash_amount"','id="p58-cash" name="cash_amount" inputmode="decimal"','cash_entry')
one('id="p58-online" name="online_amount"','id="p58-online" name="online_amount" inputmode="decimal"','online_entry')
one("const cash=$('p58-cash'),online=$('p58-online'),reference=$('p58-ref-no');",
 """const cash=$('p58-cash'),online=$('p58-online'),reference=$('p58-ref-no');
const amountError=$('p77-amount-error');
const fillDue=$('p77-fill-due');
function clearAmountError(){
 amountError.hidden=true;amountError.textContent='';
 cash.classList.remove('p77-amount-invalid');
 online.classList.remove('p77-amount-invalid');
}
function showAmountError(message,target){
 amountError.textContent=message;amountError.hidden=false;
 target.classList.add('p77-amount-invalid');
 amountError.scrollIntoView({behavior:'smooth',block:'center'});
 target.focus({preventScroll:true});
 if(!target.readOnly && target.select) target.select();
}""",'js_guide')
one(" $('p58-sum-total').textContent=fmt(Math.round((cc+oo)*100)/100);",
 """ $('p58-sum-total').textContent=fmt(Math.round((cc+oo)*100)/100);
 fillDue.hidden=!(t==='against_reference' && Number(o?.dataset.balance||0)>0);
 if(cc+oo>0)clearAmountError();""",'preview_fill_btn')
one("mode.addEventListener('change',modeChange);",
 """mode.addEventListener('change',modeChange);
fillDue.addEventListener('click',function(){
 if(type()!=='against_reference' || !invoice.value){
  showAmountError('Select the customer invoice first, then choose Use Invoice Outstanding.',cash.readOnly?online:cash);
  return;
 }
 const due=Math.round(Number(invoice.selectedOptions[0]?.dataset.balance||0)*100)/100;
 if(!Number.isFinite(due)||due<=0){
  showAmountError('Selected invoice has no outstanding balance. Select another invoice.',cash.readOnly?online:cash);
  return;
 }
 if(mode.value==='Cash + Online'){
  showAmountError('For Cash + Online, enter the two received portions separately. The system will not guess the split.',cash);
  return;
 }
 if(mode.value==='Cash'){cash.value=due.toFixed(2);online.value='0.00';}
 else{online.value=due.toFixed(2);cash.value='0.00';}
 refresh();
 const target=mode.value==='Cash'?cash:online;
 target.focus();
 target.select();
});""",'fill_amount_button')
one(""" if(cc+oo<=0){ev.preventDefault();alert('Positive receipt amount required.');return;}""",
 """ if(cc+oo<=0){
  ev.preventDefault();
  showAmountError('Enter a positive amount in '+(mode.value==='Cash'?'Cash Received':mode.value==='Cash + Online'?'both Cash and Online/Bank Received':'Online / Bank Received')+' before posting the Money Receipt.',cash.readOnly?online:cash);
  return;
 }
 if(mode.value==='Cash + Online'&&(cc<=0||oo<=0)){
  ev.preventDefault();
  showAmountError('Cash + Online mode requires positive amounts in both fields.',cc<=0?cash:online);
  return;
 }
 if(type()==='against_reference'&&invoice.value){
  const due=Math.round(Number(invoice.selectedOptions[0]?.dataset.balance||0)*100);
  if(due>0&&cc+oo>due){
   ev.preventDefault();
   showAmountError('Received amount '+fmt((cc+oo)/100)+' exceeds the selected invoice due '+fmt(due/100)+'. Use On Account for any additional payment.',cash.readOnly?online:cash);
   return;
  }
 }""",'positive_client_validation')
one(""" if(type()==='against_reference' && !invoice.value){ev.preventDefault();alert('Select Customer Invoice.');return;}""",
 """ if(type()==='against_reference' && !invoice.value){
  ev.preventDefault();
  showAmountError('Select a customer invoice before posting.',cash.readOnly?online:cash);
  return;
 }""",'invoice_missing_message')
for t in ['FIX77_RECEIPT_AMOUNT_GUIDANCE','Use Invoice Outstanding','p77-amount-error','p77-fill-due',"form.addEventListener('submit'"]:
 if t not in s:raise RuntimeError('FIX77_CONTRACT_MISSING_'+t)
# Original amount fields, server endpoint and posting policy are unchanged.
for t in ['name="cash_amount"','name="online_amount"',"route('erp.money-receipts.store')",
          'name="request_key"', '<button id="p58-submit" type="submit"']:
 if t not in s:raise RuntimeError('FIX77_FORM_CONTRACT_CHANGED_'+t)
if mode=='check':
 print('FIX77_PREFLIGHT_PASS_NO_DB_OR_CONTROLLER_CHANGES');sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix77_',dir=p.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX77_UI_AMOUNT_ERROR_AND_EXPLICIT_FILL_DEPLOYED')
