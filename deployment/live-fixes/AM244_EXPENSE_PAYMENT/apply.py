#!/usr/bin/env python3
"""AM244: link unpaid Staff Expenses to Universal Payment Voucher AGAINST REFERENCE.
Guarded staging-only Blade patch with backup and rollback. No DB posting.
"""
from pathlib import Path
import os,sys,shutil,tempfile,datetime
if len(sys.argv) not in (3,4) or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply STAGING_ROOT | rollback STAGING_ROOT BACKUP')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM244_STOP_NOT_STAGING')
paths={
 'resources/views/payments/create.blade.php':root/'resources/views/payments/create.blade.php',
 'resources/views/expenses/show.blade.php':root/'resources/views/expenses/show.blade.php'
}
backup_root=root/'storage/app/am-backups'
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM244_BACKUP_REQUIRED')
 backup=Path(sys.argv[3]).expanduser().resolve()
 if backup.parent!=backup_root or not backup.name.startswith('AM244_STAFF_EXPENSE_PAYMENT_'):
  raise SystemExit('AM244_INVALID_BACKUP')
 for name,dest in paths.items():
  if not (backup/name).is_file():raise SystemExit('AM244_INCOMPLETE_BACKUP '+name)
 for name,dest in paths.items():shutil.copy2(backup/name,dest)
 print('AM244_ROLLBACK_COMPLETE');sys.exit(0)
raw={}
for name,dest in paths.items():
 if not dest.is_file():raise SystemExit('AM244_MISSING '+name)
 raw[name]=dest.read_text('utf8','surrogateescape')
p=raw['resources/views/payments/create.blade.php']
e=raw['resources/views/expenses/show.blade.php']
marker='AM244_STAFF_EXPENSE_PAYMENT_AGAINST_REFERENCE'
if marker in p:
 print('AM244_ALREADY_APPLIED');sys.exit(0)
originalLink="route('erp.payments.create',['source'=>'EXPENSE_VOUCHER','id'=>$expense['id']])"
if e.count(originalLink)!=2:
 raise SystemExit('AM244_STOP_EXPENSE_PAYMENT_LINK_MISMATCH '+str(e.count(originalLink)))
newLink="route('erp.payments.create', (int)($expense['staff_id']??0)>0 && ($expense['record_status']??'')==='ACTIVE' ? ['staff_expense'=>$expense['id']] : ['source'=>'EXPENSE_VOUCHER','id'=>$expense['id']])"
e=e.replace(originalLink,newLink)
before='''<span style="color:#14507d;font-weight:700">Staff-linked payment below</span>'''
after='''<a class="btn small primary" href="{{ route('erp.payments.create',['staff_expense'=>$expense['id']]) }}">＋ Pay Against Reference · Staff Expense</a>'''
if e.count(before)!=1:raise SystemExit('AM244_STOP_EXPENSE_HISTORY_LINK_NOT_FOUND')
e=e.replace(before,after,1)
# Use the exact already-verified AM240 staff data list; never trust query ID for actual financial source.
script='''{{-- AM244_STAFF_EXPENSE_PAYMENT_AGAINST_REFERENCE: verified Staff expense is preselected from canonical outstanding sources. --}}
<script id="am244-staff-expense-prefill">
(function(){
 'use strict';
 function init(){
   const expenseId=Number(@json((int)request('staff_expense',0)));
   if(!Number.isInteger(expenseId)||expenseId<=0)return;
   const $=id=>document.getElementById(id);
   const ref=$('am240-references-data'),staff=$('am231-staff-select');
   const search=$('am237-payee-search'),results=$('am237-payee-results');
   const kind=$('am240-kind'),source=$('am240-expense'),mode=$('am237-mode'),amount=$('payment-value');
   const notice=$('am240-staff-notice'),notes=document.querySelector('#payment-form textarea[name="notes"]');
   if([ref,staff,search,results,kind,source,mode,amount,notice].some(x=>!x))return;
   let parsed={};
   try{parsed=JSON.parse(ref.textContent||'{}')||{};}catch(e){}
   const due=(Array.isArray(parsed.expenses)?parsed.expenses:[])
      .find(x=>Number(x.id)===expenseId && Number(x.outstanding)>0.009);
   if(!due){
     const banner=document.createElement('div');
     banner.className='p133-errors';
     banner.setAttribute('role','alert');
     banner.textContent='This Staff Expense is not outstanding or is already paid/voided. No payment reference selected.';
     const form=$('payment-form');if(form)form.prepend(banner);
     return;
   }
   const staffOption=[...staff.options].find(o=>String(o.value)===String(due.staff_id));
   if(!staffOption){notice.textContent='Expense is not linked to an available Staff Master. Posting blocked.';return;}
   const lookup=(staffOption.dataset.name||staffOption.textContent||'').trim();
   if(!lookup)return;
   // Use AM237's existing searchable picker, not a second payee identity.
   search.value=lookup;
   search.dispatchEvent(new Event('input',{bubbles:true}));
   const buttons=[...results.querySelectorAll('button.am237-result')];
   const match=buttons.find(b=>b.querySelector('.am237-tag')?.textContent.trim()==='Staff' &&
      b.textContent.toLocaleLowerCase('en-IN').includes(lookup.toLocaleLowerCase('en-IN')));
   if(!match){notice.textContent='Staff search did not resolve this expense payee. Choose Staff manually.';return;}
   mode.value='AGAINST';mode.dispatchEvent(new Event('change',{bubbles:true}));
   match.click();
   kind.value='EXPENSE_REIMBURSEMENT';kind.dispatchEvent(new Event('change',{bubbles:true}));
   if(![...source.options].some(o=>o.value===String(expenseId))){
     notice.textContent='The selected Expense is not available as an outstanding Staff Reference. No payment posted.';
     return;
   }
   source.value=String(expenseId);source.dispatchEvent(new Event('change',{bubbles:true}));
   amount.value=Number(due.outstanding).toFixed(2);amount.dispatchEvent(new Event('input',{bubbles:true}));
   if(notes && !notes.value.trim())notes.value='Staff expense payment against '+due.expense_no;
   if(notes)notes.dispatchEvent(new Event('input',{bubbles:true}));
   notice.textContent='Expense '+due.expense_no+' selected: '+Number(due.outstanding).toLocaleString('en-IN',{style:'currency',currency:'INR'})+
     ' due. Choose Cash/Bank Account and payment mode, then review and post. No extra expense will be created.';
   const box=$('am240-staff-panel');if(box)box.scrollIntoView({block:'nearest'});
 }
 // All existing AM237/AM240 form controllers initialize on DOMContentLoaded.
 if(document.readyState==='complete')init();
 else window.addEventListener('load',init,{once:true});
})();
</script>
'''
anchor='<script id="am240-staff-script">'
if p.count(anchor)!=1:raise SystemExit('AM244_STOP_STAFF_SCRIPT_AFTER_ANCHOR_NOT_FOUND')
if p.count('id="am240-staff-script"')!=1 or p.count('id="am237-unified-script"')!=1:
 raise SystemExit('AM244_STOP_EXISTING_STAFF_PICKER_NOT_FOUND')
p=p.replace(anchor,script+anchor,1)
p=p.replace('Approved Staff Expense Reference *','Outstanding Staff Expense Voucher / Against Reference *',1)
if p.count(marker)!=1 or p.count('id="am244-staff-expense-prefill"')!=1:
 raise SystemExit('AM244_STOP_BAD_OUTPUT')
print('AM244_PREFLIGHT_PASS')
if mode=='check':
 print('AM244_CHECK_NO_WRITES');sys.exit(0)
stamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S')
backup=backup_root/('AM244_STAFF_EXPENSE_PAYMENT_'+stamp)
backup.mkdir(mode=0o750,parents=True,exist_ok=False)
for name,dest in paths.items():
 (backup/name).parent.mkdir(parents=True,exist_ok=True)
 shutil.copy2(dest,backup/name)
try:
 for name,dest in paths.items():
  content=p if 'payments/create' in name else e
  with tempfile.NamedTemporaryFile(mode='w',encoding='utf8',errors='surrogateescape',dir=dest.parent,prefix='.am244_',delete=False) as fh:
   fh.write(content);tmp=Path(fh.name)
  os.chmod(tmp,dest.stat().st_mode&0o777)
  os.replace(tmp,dest)
except Exception:
 for name,dest in paths.items():shutil.copy2(backup/name,dest)
 raise
print('AM244_APPLIED')
print('AM244_BACKUP='+str(backup))
print('AM244_ONLY_BLADE_UI_CHANGED_NO_DATABASE_POSTING')
