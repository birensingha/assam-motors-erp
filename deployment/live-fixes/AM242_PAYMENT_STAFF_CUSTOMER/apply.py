#!/usr/bin/env python3
"""AM242 guarded STAGING installer: canonical Staff/Customer picker and Customer payout.
Only source files and additive migration. NO business transaction is executed.
"""
from pathlib import Path
import datetime,os,shutil,sys,tempfile
if len(sys.argv) not in (3,4) or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply ROOT [BACKUP] or rollback ROOT BACKUP')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM242_INVALID_STAGING_ROOT')
pkg=Path(__file__).resolve().parent
tracked={
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'app/Services/ExpensePaymentService.php':root/'app/Services/ExpensePaymentService.php',
 'resources/views/payments/create.blade.php':root/'resources/views/payments/create.blade.php',
 'resources/views/expenses/create.blade.php':root/'resources/views/expenses/create.blade.php'
}
added={
 'app/Services/CustomerPayoutVoucherService.php':(root/'app/Services/CustomerPayoutVoucherService.php',pkg/'CustomerPayoutVoucherService.php'),
 'database/migrations/2026_11_04_183000_am242_add_customer_to_payment_vouchers.php':
     (root/'database/migrations/2026_11_04_183000_am242_add_customer_to_payment_vouchers.php',
      pkg/'2026_11_04_183000_am242_add_customer_to_payment_vouchers.php')
}
backupdir=root/'storage/app/am-backups'
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM242_ROLLBACK_BACKUP_REQUIRED')
 b=Path(sys.argv[3]).expanduser().resolve()
 if b.parent!=backupdir or not b.name.startswith('AM242_PAYMENT_STAFF_CUSTOMER_'):
  raise SystemExit('AM242_ROLLBACK_INVALID_BACKUP')
 for k,p in tracked.items():
  if not (b/k).is_file():raise SystemExit('AM242_ROLLBACK_MISSING_FILE '+k)
 for k,p in tracked.items():shutil.copy2(b/k,p)
 for k,(p,_) in added.items():
  if (b/k).is_file():shutil.copy2(b/k,p)
  elif p.is_file() and 'AM242_CUSTOMER_PAYOUT_LEDGER' in p.read_text('utf8','replace'):p.unlink()
 print('AM242_ROLLBACK_SOURCE_DONE_SCHEMA_NULLABLE_COLUMN_RETAINED')
 sys.exit(0)
for k,p in tracked.items():
 if not p.is_file():raise SystemExit('AM242_MISSING '+k)
for k,(p,src) in added.items():
 if not src.is_file():raise SystemExit('AM242_MISSING_PACKAGE '+k)
 if p.is_file():raise SystemExit('AM242_CONFLICT_EXISTING '+k)
sources={k:p.read_text('utf8','surrogateescape') for k,p in tracked.items()}
marker='AM242_CUSTOMER_PAYOUT_LEDGER'
if marker in sources['app/Http/Controllers/Web/AdminExpensePaymentPageController.php']:
 print('AM242_ALREADY_APPLIED')
 sys.exit(0)
def once(text,old,new,key):
 n=text.count(old)
 if n!=1:raise SystemExit('AM242_SOURCE_ANCHOR_MISMATCH '+key+' matches '+str(n))
 return text.replace(old,new,1)

ctrl=sources['app/Http/Controllers/Web/AdminExpensePaymentPageController.php']
ctrl=once(ctrl,
    "DB::table('customer_master')",
    "DB::table('am_customer_master')",
    'canonical_customer_data_source')
ctrl=once(ctrl,"            'am238DebitSources'=>",
    """            // AM242_CUSTOMER_PAYOUT_LEDGER: same canonical IDs as Money Receipts.
            'am242CustomerBalances'=>\\Illuminate\\Support\\Facades\\DB::table('customer_ledger')
                ->select('customer_id')
                ->selectRaw('ROUND(SUM(COALESCE(credit,0)-COALESCE(debit,0)),2) as credit_balance')
                ->groupBy('customer_id')->get(),
            'am238DebitSources'=>""",'customer_credit_data')
ctrl=once(ctrl,"        // AM240_STAFF_PAYMENT_VOUCHER:",
    """        // AM242_CUSTOMER_PAYOUT_LEDGER: canonical Customer Master, atomic ledger debit.
        if(strtoupper((string)$request->input('payee_category','VENDOR'))==='CUSTOMER') {
            return app(\\App\\Services\\CustomerPayoutVoucherService::class)->post($request);
        }
        // AM240_STAFF_PAYMENT_VOUCHER:""",'customer_store_routing')
ctrl=once(ctrl,"        $voucher=\\App\\Models\\WorkshopPaymentVoucher::query()->find($payment);",
    """        $voucher=\\App\\Models\\WorkshopPaymentVoucher::query()->find($payment);
        if($voucher && $voucher->payee_type==='CUSTOMER' &&
           in_array($voucher->source_type,['CUSTOMER_REFUND','CUSTOMER_ADVANCE'],true)) {
            return app(\\App\\Services\\CustomerPayoutVoucherService::class)->void($request,$payment);
        }""",'customer_void_routing')
sources['app/Http/Controllers/Web/AdminExpensePaymentPageController.php']=ctrl

# AM242 fail-closed: generic Vendor/Expense void must never void a Customer
# Voucher without reversing its Customer Ledger Debit.
general=sources['app/Services/ExpensePaymentService.php']
general=once(general,
    "            if(in_array($p->source_type,['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'],true))",
    """            // AM242_CUSTOMER_PAYOUT_LEDGER: only dedicated customer reversal may Void this.
            if(in_array($p->source_type,['CUSTOMER_REFUND','CUSTOMER_ADVANCE'],true))
                throw ValidationException::withMessages(['payment'=>'Customer payout must use audited Customer Ledger reversal; generic void denied.']);
            if(in_array($p->source_type,['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'],true))""",
    'generic_void_customer_guard')
sources['app/Services/ExpensePaymentService.php']=general

pay=sources['resources/views/payments/create.blade.php']
customer_panel=(pkg/'customer-panel.blade.php').read_text('utf8')
customer_script=(pkg/'customer-script.blade.php').read_text('utf8')
pay=once(pay,'  <div id="am237-bills-slot"',
    customer_panel+'\n  <div id="am237-bills-slot"','customer_panel_insertion')
pay=once(pay,'  <div id="am240-staff-notice" role="status"></div>',
    """  <div id="am240-staff-notice" role="status"></div>
  {{-- AM242 Staff expense source quick links; expense booked only through Expense Voucher. --}}
  <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:9px;font-size:12px">
    <a href="{{ route('erp.expenses.create') }}" style="color:#155e99;font-weight:800">+ Staff Tour / Local Conveyance Expense</a>
    <a href="{{ route('erp.staff-finance.index') }}" style="color:#155e99;font-weight:800">Staff Finance Ledger</a>
  </div>""",'staff_expense_links')
pay=once(pay,"@endpush",customer_script+'\n@endpush','customer_script')
sources['resources/views/payments/create.blade.php']=pay

expense=sources['resources/views/expenses/create.blade.php']
expense=once(expense,
    "{{ str_replace('_',' ',$c) }}",
    "{{ $c==='TRAVEL' ? 'TRAVEL / TOUR / LOCAL CONVEYANCE' : str_replace('_',' ',$c) }}",
    'travel_category_readable')
expense=once(expense,
    '<form method="post" action="{{ route(\'erp.expenses.store\') }}"',
    """{{-- AM242 Staff travel/conveyance: this is an Expense Voucher, not Salary Advance. --}}
<div style="padding:11px 13px;margin-bottom:12px;border:1px solid #bdd7ea;border-radius:8px;background:#f2f8fe;color:#264e70;font-size:12px">
  <strong>Staff Travel / Tour / Local Conveyance:</strong> Category = TRAVEL; select Staff Name below, set GENERAL / REFERENCE
  (or Job Card when applicable), enter Amount and Description. Save unpaid and use Payment Voucher →
  Staff → Against Reference → Expense Reimbursement, or Record Payment Now for immediate settlement.
</div>
<form method="post" action="{{ route('erp.expenses.store') }}" """,
    'expense_instruction')
sources['resources/views/expenses/create.blade.php']=expense
for k,v in sources.items():
 if k.endswith('AdminExpensePaymentPageController.php'):
  if v.count(marker)!=2:raise SystemExit('AM242_INVALID_CONTROLLER_MARKERS')
 if k.endswith('payments/create.blade.php'):
  if v.count('am242-customer-panel')<2:raise SystemExit('AM242_MISSING_CUSTOMER_PANEL')
print('AM242_PREFLIGHT_PASS')
if mode=='check':
 print('AM242_CHECK_READONLY_NO_WRITES');sys.exit(0)
backup=backupdir/('AM242_PAYMENT_STAFF_CUSTOMER_'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S'))
backup.mkdir(mode=0o750,parents=True,exist_ok=False)
for k,p in tracked.items():
 (backup/k).parent.mkdir(parents=True,exist_ok=True)
 shutil.copy2(p,backup/k)
for k,(p,src) in added.items():
 if p.exists():
  (backup/k).parent.mkdir(parents=True,exist_ok=True)
  shutil.copy2(p,backup/k)
try:
 for k,p in tracked.items():
  fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.am242_',suffix='.tmp')
  try:
   with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(sources[k])
   os.chmod(tmp,p.stat().st_mode&0o777)
   os.replace(tmp,p)
  finally:
   if os.path.exists(tmp):os.unlink(tmp)
 for k,(p,src) in added.items():
  p.parent.mkdir(parents=True,exist_ok=True)
  shutil.copy2(src,p)
  os.chmod(p,0o644)
except Exception:
 for k,p in tracked.items():shutil.copy2(backup/k,p)
 for k,(p,src) in added.items():
  if (backup/k).exists():shutil.copy2(backup/k,p)
  elif p.exists():p.unlink()
 raise
print('AM242_APPLIED')
print('AM242_BACKUP='+str(backup))
print('AM242_SOURCE_ONLY_ADDITIVE_MIGRATION_NOT_EXECUTED_BY_INSTALLER')
