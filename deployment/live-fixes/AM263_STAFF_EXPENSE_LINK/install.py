#!/usr/bin/env python3
"""AM263 linked non-Lubricash Staff Expenses in existing Expense/Payment Voucher.
check|apply|rollback STAGING_ROOT. No historical money changes.
Uses existing staff_id, payee_name and payment source; protects salary expense double posting.
"""
from pathlib import Path
import sys,os,re,tempfile,hashlib,json,subprocess
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM263_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM263_STOP_NOT_STAGING')
paths={
 'service':root/'app/Services/ExpensePaymentService.php',
 'view':root/'resources/views/expenses/create.blade.php',
}
backup=root/'storage/app/am-backups/AM263_STAFF_EXPENSE_LINK'
manifest=backup/'hashes.json'
marker='AM263_STAFF_EXPENSE_LINK'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 fd,tmp=tempfile.mkstemp(prefix='.am263_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(tmp,p.stat().st_mode&0o777);os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
if any(not p.is_file() or p.is_symlink() for p in paths.values()):raise SystemExit('AM263_STOP_SOURCE_MISSING')
old={n:read(p) for n,p in paths.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM263_STOP_BACKUP_MISSING')
 m=json.loads(read(manifest))
 if any(sha(old[n])!=m['after'][n] for n in paths):raise SystemExit('AM263_STOP_CONCURRENT_CHANGE')
 for n,p in paths.items():atomic(p,read(backup/(n+'.before')))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 print('AM263_SOURCE_ROLLBACK_PASS');sys.exit(0)
if all(marker in x for x in old.values()):print('AM263_ALREADY_INSTALLED');sys.exit(0)
if any(marker in x for x in old.values()):raise SystemExit('AM263_STOP_PARTIAL_INSTALL')
if not (root/'resources/views/staff-finance/index.blade.php').is_file():
 raise SystemExit('AM263_STOP_STAFF_LEDGER_NOT_INSTALLED')
new=dict(old)
def replace(name,a,b,key):
 count=new[name].count(a)
 if count!=1:raise SystemExit('AM263_STOP_ANCHOR_'+key+'_'+str(count))
 new[name]=new[name].replace(a,b,1)
# Extend existing data model, not a duplicated staff expense model.
serviceAnchor="""            if ($payee === '') throw ValidationException::withMessages(['payee_name'=>'Select Vendor or enter Payee Name.']);"""
serviceAdd="""            // AM263_STAFF_EXPENSE_LINK: all employee-linked reimbursement Expense Vouchers
            // reference canonical staff_id, not only Lubricash Coupon. Do not duplicate
            // salary accounting in Expense Voucher; use Staff Ledger Salary Provision.
            if (strtoupper(trim((string)($data['expense_category']??'')))==='SALARY_WAGES')
                throw ValidationException::withMessages(['expense_category'=>
                   'Staff salary must be accrued through Staff Financial Ledger → Salary Provision, to avoid duplicate salary expense.']);
            if (!$isCoupon && (int)($data['staff_id']??0)>0) {
                if ($vendorId>0) throw ValidationException::withMessages(['vendor_id'=>
                    'Select either Staff or Vendor as payee for this expense, never both.']);
                $staffId=(int)$data['staff_id'];
                $person=DB::table('staff')->where('id',$staffId)->first();
                if(!$person) throw ValidationException::withMessages(['staff_id'=>'Staff Master record not found.']);
                $payee=trim((string)$person->name);
            }
            if ($payee === '') throw ValidationException::withMessages(['payee_name'=>'Select Vendor or enter Payee Name.']);"""
replace('service',serviceAnchor,serviceAdd,'STAFF_EXPENSE_SERVICE')
replace('service',"'payee_type'=>$isCoupon?'STAFF':($vendorId>0?'VENDOR':'OTHER')",
 "'payee_type'=>$staffId>0?'STAFF':($vendorId>0?'VENDOR':'OTHER')",'STAFF_PAYMENT_KIND')
# For non-Coupon expenses the existing employee selector becomes optional but visible.
replace('view','#am233-lubricash[hidden]{display:none!important}',
 '#am233-lubricash[hidden]{display:none!important} #am263-company-label[hidden],#am263-coupon-no-label[hidden]{display:none!important}',
 'HIDE_NON_COUPON_FIELDS')
replace('view','<div class="am233-coupon-heading">Staff Lubricash Coupon — Oil Sale</div>',
 '<div class="am233-coupon-heading" id="am263-expense-staff-title">Staff Expense / Reimbursement (Optional Staff Link)</div>',
 'STAFF_EXPENSE_TITLE')
replace('view','<p class="am233-coupon-help">Select staff and Job Card to track which employee used which oil coupon on which vehicle. Coupon company and details are kept for audit.</p>',
 '<p class="am233-coupon-help" id="am263-expense-staff-help">Select Staff for employee reimbursements and provisions. Leave blank if this Expense is for a Vendor or other Payee. Oil Coupons require Staff + Job Card + Company.</p>',
 'STAFF_EXPENSE_HELP')
replace('view','<label>Staff Name * <input type="search" id="am233-staff-search"',
 '<label>Staff Name (required for Oil Coupon) <input type="search" id="am233-staff-search"',
 'STAFF_INPUT')
replace('view','<label>Oil / Coupon Company * <input',
 '<label id="am263-company-label">Oil / Coupon Company * <input',
 'COMPANY_LABEL')
replace('view','<label>Coupon Number / Reference <input',
 '<label id="am263-coupon-no-label">Coupon Number / Reference <input',
 'COUPON_LABEL')
replace('view','    pane.hidden=!isCoupon;',
 '    // AM263_STAFF_EXPENSE_LINK: optional staff selector for every expense category.\n    pane.hidden=false;\n    document.getElementById("am263-company-label").hidden=!isCoupon;\n    document.getElementById("am263-coupon-no-label").hidden=!isCoupon;',
 'VISIBLE_PANE')
replace('view','    staff.disabled=!isCoupon;','    staff.disabled=false;','STAFF_ENABLED')
replace('view','    staffSearch.disabled=!isCoupon;','    staffSearch.disabled=false;','STAFF_SEARCH_ENABLED')
replace('view',"""  function syncEmployee(){
    if(category.value==='LUBRICASH_COUPON')payee.value=staffName();
  }""",
 """  function syncEmployee(){
    const name=staffName();
    if(staff.value){
      payee.value=name;
      payee.readOnly=true;
      if(vendor.value)vendor.value='';
      vendor.disabled=true;
    }else if(category.value!=='LUBRICASH_COUPON'){
      payee.readOnly=false;
      vendor.disabled=false;
    }else payee.value='';
  }""",
 'SYNC_STAFF_GENERIC')
replace('view',"""      if(preCouponPayee && !payee.value)payee.value=preCouponPayee;
    }
  }
  category.addEventListener('change',sync);""",
 """      if(preCouponPayee && !payee.value)payee.value=preCouponPayee;
    }
    // Reapply selected employee lock after conditional vendor/payee controls.
    syncEmployee();
  }
  category.addEventListener('change',sync);""",
 'SYNC_AFTER_MODE')
# Explicit link from normal Expenses page to the balanced salary journal.
replace('view','<div class="page-head"><div><h1>New Expense Voucher</h1>',
 '<!-- AM263_STAFF_EXPENSE_LINK: this form links Staff Expense to canonical staff_id. -->\n'
 '<div class="page-head"><div><h1>New Expense Voucher</h1>',
 'EXPENSE_PAGE_MARKER')
for name in paths:
 if new[name].count(marker)!=1:raise SystemExit('AM263_STOP_MARKER_'+name+'_'+str(new[name].count(marker)))
# Existing Lubricash constraints must still remain.
for required in ["const isCoupon=category.value==='LUBRICASH_COUPON'",
 "company.required=isCoupon","job.required=true","vendor.disabled=isCoupon",
 "document.getElementById('am233-expense-against-hidden').disabled=false"]:
 if required not in new['view']:raise SystemExit('AM263_STOP_COUPON_GUARD_LOST_'+required)
for required in ["'staff_id'=>$staffId","'source_type'=>'EXPENSE_VOUCHER'","'coupon_company'=>$isCoupon"]:
 if required not in new['service']:raise SystemExit('AM263_STOP_FINANCIAL_SOURCE_LOST_'+required)
with tempfile.TemporaryDirectory(prefix='am263qa_') as td:
 d=Path(td)
 f=d/'ExpensePaymentService.php';f.write_text(new['service'],encoding='utf8',errors='surrogateescape')
 p=subprocess.run(['php','-l',str(f)],cwd=root,text=True,capture_output=True,timeout=40)
 if p.returncode:raise SystemExit('AM263_STOP_PHP_LINT '+(p.stdout+p.stderr)[-450:])
 view=d/'expense.blade.php';view.write_text(new['view'],encoding='utf8',errors='surrogateescape')
 output=d/'compiled.php';h=d/'compile.php'
 h.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 p=subprocess.run(['php',str(h),str(root),str(view),str(output)],cwd=root,text=True,capture_output=True,timeout=65)
 if p.returncode:raise SystemExit('AM263_STOP_BLADE_COMPILE '+p.stderr[-450:])
 p=subprocess.run(['php','-l',str(output)],cwd=root,text=True,capture_output=True,timeout=35)
 if p.returncode:raise SystemExit('AM263_STOP_BLADE_PHP '+(p.stdout+p.stderr)[-450:])
print('AM263_EXPENSE_SERVICE_AND_BLADE_PREFLIGHT_PASS')
if mode=='check':
 print('AM263_CHECK_ONLY_NO_FINANCIAL_DB_WRITES');sys.exit(0)
if backup.exists() or any(read(paths[n])!=old[n] for n in paths):raise SystemExit('AM263_STOP_BACKUP_OR_SOURCE_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for n,p in paths.items():
 f=backup/(n+'.before');f.write_text(old[n],encoding='utf8',errors='surrogateescape');os.chmod(f,0o600)
try:
 for n,p in paths.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'after':{n:sha(new[n]) for n in paths}}),encoding='ascii');os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  p=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
  if p.returncode:raise RuntimeError('AM263_CACHE '+p.stderr[-500:])
except BaseException as e:
 for n,p in paths.items():atomic(p,old[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM263_APPLY_FAILED_ROLLED_BACK '+str(e))
print('AM263_GENERAL_STAFF_EXPENSES_LINKED_TO_LEDGER_STAGING_SOURCE_INSTALLED')
print('AM263_NO_EXISTING_EXPENSE_OR_PAYMENT_ENTRIES_REPOSTED')
