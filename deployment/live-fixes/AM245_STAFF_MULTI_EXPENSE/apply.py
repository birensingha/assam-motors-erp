#!/usr/bin/env python3
"""AM245 guarded install: multiple Staff expense references, ONE Payment Voucher.
check = no writes, apply = backup+atomic source edits, rollback = restore.
Never directly posts expenses/payments, and never writes to database.
"""
from pathlib import Path
import sys,re,os,shutil,tempfile,datetime
if len(sys.argv) not in (3,4) or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply ROOT | rollback ROOT BACKUP')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM245_STOP_INVALID_STAGING')
base=Path(__file__).resolve().parent
original_files={
 'routes/web.php':root/'routes/web.php',
 'resources/views/payments/create.blade.php':root/'resources/views/payments/create.blade.php',
 'app/Services/ExpensePaymentService.php':root/'app/Services/ExpensePaymentService.php',
 'app/Http/Controllers/Web/AdminExpensePaymentPageController.php':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'app/Http/Controllers/Web/AdminPaymentVoucherEditController.php':root/'app/Http/Controllers/Web/AdminPaymentVoucherEditController.php',
}
new_files={
 'app/Services/StaffMultiExpensePaymentService.php':(root/'app/Services/StaffMultiExpensePaymentService.php',base/'StaffMultiExpensePaymentService.php'),
 'app/Http/Controllers/Web/AdminStaffMultiExpensePaymentController.php':(root/'app/Http/Controllers/Web/AdminStaffMultiExpensePaymentController.php',base/'AdminStaffMultiExpensePaymentController.php'),
 'resources/views/payments/staff-expenses-multi.blade.php':(root/'resources/views/payments/staff-expenses-multi.blade.php',base/'staff-expenses-multi.blade.php'),
}
backup_root=root/'storage/app/am-backups'
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM245_ROLLBACK_BACKUP_REQUIRED')
 backup=Path(sys.argv[3]).resolve()
 if backup.parent!=backup_root or not backup.name.startswith('AM245_STAFF_MULTI_EXPENSE_'):
  raise SystemExit('AM245_STOP_BAD_BACKUP')
 for rel in original_files:
  if not (backup/rel).is_file():raise SystemExit('AM245_STOP_BACKUP_MISSING '+rel)
 for rel,p in original_files.items():shutil.copy2(backup/rel,p)
 for rel,(p,src) in new_files.items():
  if (backup/rel).exists():shutil.copy2(backup/rel,p)
  elif p.exists() and 'AM245' in p.read_text('utf8','replace'):p.unlink()
 print('AM245_ROLLBACK_COMPLETE')
 sys.exit(0)
for rel,p in original_files.items():
 if not p.is_file():raise SystemExit('AM245_STOP_REQUIRED_FILE_MISSING '+rel)
raw={rel:p.read_text('utf8','surrogateescape') for rel,p in original_files.items()}
marker='AM245_STAFF_MULTI_EXPENSE'
if any(marker in text for text in raw.values()):
 print('AM245_ALREADY_INSTALLED')
 sys.exit(0)
for rel,(p,src) in new_files.items():
 if p.exists():raise SystemExit('AM245_STOP_NEW_FILE_ALREADY_EXISTS '+rel)
 if not src.is_file() or 'AM245' not in src.read_text('utf8'):
  raise SystemExit('AM245_STOP_PACKAGE_SOURCE_MISSING '+rel)
def once(text,old,new,label):
 count=text.count(old)
 if count!=1:raise SystemExit('AM245_STOP_SOURCE_ANCHOR '+label+' COUNT='+str(count))
 return text.replace(old,new,1)
def after_method(source,method,code,signature_regex):
 m=list(re.finditer(signature_regex,source,re.MULTILINE))
 if len(m)!=1:raise SystemExit('AM245_METHOD_NOT_UNIQUE '+method+' '+str(len(m)))
 p=m[0].end()
 return source[:p]+'\n'+code+source[p:]
# Routes must be inside existing authenticated ERP admin middleware group.
routes=raw['routes/web.php']
anchor="    Route::get('/payments/create', [AdminExpensePaymentPageController::class,'createPayment'])->name('erp.payments.create');"
routes=once(routes,anchor,
 """    // AM245_STAFF_MULTI_EXPENSE: one Payment Voucher, multiple linked Staff Expense references.
    Route::get('/payments/staff-expenses/multi', [\\App\\Http\\Controllers\\Web\\AdminStaffMultiExpensePaymentController::class,'create'])->name('erp.payments.staff-multi.create');
    Route::post('/payments/staff-expenses/multi', [\\App\\Http\\Controllers\\Web\\AdminStaffMultiExpensePaymentController::class,'store'])->name('erp.payments.staff-multi.store');
"""+anchor,'routes')
raw['routes/web.php']=routes
# Link from current Universal Payment Voucher Staff panel, not a separate menu.
view=raw['resources/views/payments/create.blade.php']
if 'AM244_STAFF_EXPENSE_PAYMENT_AGAINST_REFERENCE' not in view:
 raise SystemExit('AM245_NEEDS_VERIFIED_AM244_STAFF_EXPENSE_BASE')
anchor='<div id="am240-staff-notice" role="status"></div>'
link="""<div id="am240-staff-notice" role="status"></div>
{{-- AM245_STAFF_MULTI_EXPENSE: vendor-style multi-reference Staff payout, no second expense booking. --}}
<div style="margin-top:12px">
 <a id="am245-staff-multi-link" class="p133-back" style="display:inline-flex;background:#185ba7;color:#fff;border-color:#185ba7;text-decoration:none;padding:10px 15px;border-radius:9px;font-weight:800"
    href="{{ route('erp.payments.staff-multi.create') }}">＋ Pay Multiple Staff Expense Vouchers — One Payment</a>
 <small style="display:block;margin-top:6px;color:#537086">Select multiple unpaid Expense references; amount-first allocation and partial payment supported.</small>
</div>
<script id="am245-staff-multi-link-script">
document.addEventListener('DOMContentLoaded',function(){
 const link=document.getElementById('am245-staff-multi-link');
 if(!link)return;
 const initial=link.getAttribute('href');
 link.addEventListener('click',function(){
   const staff=document.getElementById('am231-staff-select');
   const id=staff?.value||'';
   link.href=initial+(id?'?staff_id='+encodeURIComponent(id):'');
 });
});
</script>"""
view=once(view,anchor,link,'payment_voucher_staff_link')
raw['resources/views/payments/create.blade.php']=view
# Prevent generic void/edit code from modifying the multi allocation header without reconciling shares.
controller=raw['app/Http/Controllers/Web/AdminExpensePaymentPageController.php']
voidIntercept="""        // AM245_STAFF_MULTI_EXPENSE: reverse all shares atomically via dedicated audited service.
        if (\\Illuminate\\Support\\Facades\\DB::table('workshop_payment_vouchers')
            ->where('id',$payment)->where('source_type','EXPENSE_VOUCHER')
            ->where('source_reference','STAFF_EXPENSE_MULTI')->exists()) {
            return app(\\App\\Services\\StaffMultiExpensePaymentService::class)->void($request,$payment);
        }
"""
controller=after_method(controller,'voidPayment',voidIntercept,
 r'public function voidPayment\\(Request \\$request,\\s*int \\$payment,\\s*ExpensePaymentService \\$service\\)\\s*:\\s*RedirectResponse\\s*\\{'.replace('\\\\','\\'))
raw['app/Http/Controllers/Web/AdminExpensePaymentPageController.php']=controller
edit=raw['app/Http/Controllers/Web/AdminPaymentVoucherEditController.php']
guard="""        // AM245_STAFF_MULTI_EXPENSE: allocation header cannot be edited independently.
        if (\\Illuminate\\Support\\Facades\\DB::table('workshop_payment_vouchers')
            ->where('id',$payment)->where('source_type','EXPENSE_VOUCHER')
            ->where('source_reference','STAFF_EXPENSE_MULTI')->exists())
            abort(403,'Multi-expense voucher allocation requires audited VOID followed by a new payment; direct edit is blocked.');
"""
for meth in ('edit','update'):
 sig=r'public function '+meth+r'\\([^\\n]*\\$payment[^\\n]*\\)\\s*:\\s*\\w+\\s*\\{'
 sig=sig.replace('\\\\','\\')
 edit=after_method(edit,meth,guard,sig)
raw['app/Http/Controllers/Web/AdminPaymentVoucherEditController.php']=edit
service=raw['app/Services/ExpensePaymentService.php']
# One screen/ledger reports each employee expense as its individual allocated share.
anchor="$payments=DB::table('workshop_payment_vouchers')->where('source_type','EXPENSE_VOUCHER')->where('source_id',$id)->orderByDesc('id')->get()->map(fn($r)=>(array)$r)->all();"
extra="""$multi=DB::table('workshop_payment_allocations as a')
            ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
            ->where('a.purchase_ref','EXPENSE_VOUCHER::'.$id)
            ->select('v.*','a.amount as expense_share')->get();
        foreach($multi as $item){$row=(array)$item;$row['amount']=(float)$item->expense_share;unset($row['expense_share']);$payments[]=$row;}
        usort($payments,fn($a,$b)=>(int)$b['id']<=>(int)$a['id']);"""
service=once(service,anchor,anchor+'\n        // AM245_STAFF_MULTI_EXPENSE: show THIS expense share, not full multi-voucher total.\n        '+extra,'expense_payment_history')
# Never void an Expense while an active multi voucher still has an allocated payment share.
anchor="if (WorkshopPaymentVoucher::query()->where('source_type','EXPENSE_VOUCHER')->where('source_id',$id)->where('record_status','ACTIVE')->exists())"
blocking="""if (DB::table('workshop_payment_allocations as a')
                ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                ->where('a.purchase_ref','EXPENSE_VOUCHER::'.$id)
                ->where('v.record_status','ACTIVE')->exists())
                throw ValidationException::withMessages(['expense'=>'Active Staff Multi-Expense payment exists. Void the linked Payment Voucher first.']);
            """
service=once(service,anchor,blocking+anchor,'prevent_void_paid_multi_expense')
# Outstanding source lists must include payments allocated via multi voucher.
anchor="$paidExpense=DB::table('workshop_payment_vouchers')->where('record_status','ACTIVE')->where('source_type','EXPENSE_VOUCHER')->whereNotNull('source_id')->selectRaw('source_id, SUM(amount) as paid')->groupBy('source_id')->pluck('paid','source_id');"
extra="""// AM245_STAFF_MULTI_EXPENSE: all active allocated shares reduce per-expense outstanding.
        $expenseShares=DB::table('workshop_payment_allocations as a')
            ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
            ->where('v.record_status','ACTIVE')
            ->where('a.purchase_ref','like','EXPENSE_VOUCHER::%')
            ->selectRaw('a.purchase_ref,SUM(a.amount) AS paid')
            ->groupBy('a.purchase_ref')->get();
        foreach($expenseShares as $share){
            if(preg_match('/^EXPENSE_VOUCHER::([1-9]\\d*)$/',(string)$share->purchase_ref,$m)){
                $eid=(int)$m[1];$paidExpense[$eid]=(float)($paidExpense[$eid]??0)+(float)$share->paid;
            }
        }"""
service=once(service,anchor,anchor+'\n        '+extra,'outstanding_staff_expenses')
# Single-expense payment must also see earlier multi allocations; prevents overpayment/double booking.
start=service.index("if($type==='EXPENSE_VOUCHER'){")
stop=service.index("if($type==='OSL_JOB'){",start)
part=service[start:stop]
anchor="$paid=(float)WorkshopPaymentVoucher::query()->where('record_status','ACTIVE')->where('source_type',$type)->where('source_id',$id)->sum('amount');"
addition="""$paid += (float)DB::table('workshop_payment_allocations as a')
                ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                ->where('a.purchase_ref','EXPENSE_VOUCHER::'.$id)->where('v.record_status','ACTIVE')
                ->sum('a.amount');"""
part=once(part,anchor,anchor+'\n            // AM245_STAFF_MULTI_EXPENSE ledger-aware single-reference cap.\n            '+addition,'single_expense_cap')
service=service[:start]+part+service[stop:]
# Ensure any external single expense post or payment void updates paid/partial/unpaid correctly.
anchor="round((float)WorkshopPaymentVoucher::query()->where('record_status','ACTIVE')->where('source_type','EXPENSE_VOUCHER')->where('source_id',$expenseId)->sum('amount'),2)"
new="""round((float)WorkshopPaymentVoucher::query()->where('record_status','ACTIVE')->where('source_type','EXPENSE_VOUCHER')->where('source_id',$expenseId)->sum('amount')
            + (float)DB::table('workshop_payment_allocations as a')
                ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                ->where('v.record_status','ACTIVE')
                ->where('a.purchase_ref','EXPENSE_VOUCHER::'.$expenseId)->sum('a.amount'),2)"""
service=once(service,anchor,new,'expense_paid_status')
# Generic service must not bypass multi-specific void transaction.
signature=r'public function voidPayment\\(int \\$id,\\s*string \\$reason,\\s*string \\$actor\\)\\s*:\\s*void\\s*\\{'.replace('\\\\','\\')
service=after_method(service,'generic_void_guard',
 """        // AM245_STAFF_MULTI_EXPENSE: reverse through dedicated audited multi-allocation service.
        if(DB::table('workshop_payment_vouchers')->where('id',$id)
            ->where('source_type','EXPENSE_VOUCHER')->where('source_reference','STAFF_EXPENSE_MULTI')->exists())
            throw ValidationException::withMessages(['payment'=>'Use dedicated Staff multi-expense Void; generic voucher Void is blocked.']);
 """,signature)
# Present expense numbers on Payment Voucher; do not change underlying immutable allocation keys.
anchor="$debitUses=\\Illuminate\\Support\\Facades\\Schema::hasTable('workshop_vendor_debit_uses')"
if anchor not in service:raise SystemExit('AM245_STOP_PAYMENT_VIEW_ANCHOR_MISSING')
extra="""// AM245_STAFF_MULTI_EXPENSE: resolve display references only; storage keeps EXPENSE_VOUCHER::<id>.
        if((string)($row->source_reference??'')==='STAFF_EXPENSE_MULTI' && (string)($row->source_type??'')==='EXPENSE_VOUCHER'){
            foreach($allocations as &$allocation){
                if(preg_match('/^EXPENSE_VOUCHER::([1-9]\\d*)$/',(string)$allocation['purchase_ref'],$m)){
                    $number=DB::table('workshop_expenses')->where('id',(int)$m[1])->value('expense_no');
                    $allocation['purchase_ref']=$number ?: $allocation['purchase_ref'];
                }
            }
            unset($allocation);
        }
        """
service=once(service,anchor,extra+anchor,'payment_allocation_display')
raw['app/Services/ExpensePaymentService.php']=service
for rel in original_files:
 if not raw[rel]:raise SystemExit('AM245_INVALID_EMPTY_OUTPUT '+rel)
print('AM245_PREFLIGHT_PASS')
if mode=='check':
 print('AM245_READONLY_CHECK_NO_CHANGES')
 sys.exit(0)
now=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S')
backup=backup_root/('AM245_STAFF_MULTI_EXPENSE_'+now)
backup.mkdir(parents=True,mode=0o750,exist_ok=False)
for rel,p in original_files.items():
 b=backup/rel;b.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(p,b)
written=[]
try:
 for rel,p in original_files.items():
  with tempfile.NamedTemporaryFile(mode='w',encoding='utf8',errors='surrogateescape',dir=p.parent,prefix='.am245_',delete=False) as f:
   f.write(raw[rel]);tmp=Path(f.name)
  os.chmod(tmp,p.stat().st_mode&0o777);os.replace(tmp,p);written.append(rel)
 for rel,(p,src) in new_files.items():
  p.parent.mkdir(parents=True,exist_ok=True)
  shutil.copy2(src,p);os.chmod(p,0o644);written.append(rel)
except Exception:
 for rel,p in original_files.items():shutil.copy2(backup/rel,p)
 for rel,(p,src) in new_files.items():
  if p.exists():p.unlink()
 raise
print('AM245_INSTALLED')
print('AM245_BACKUP='+str(backup))
print('AM245_SOURCE_CHANGE_ONLY_NO_FINANCIAL_DATA_POSTED')
