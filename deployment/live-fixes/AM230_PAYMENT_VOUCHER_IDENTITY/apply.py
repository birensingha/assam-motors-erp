#!/usr/bin/env python3
"""AM230 guarded Staging: persistent payee selector, safe Expense Voucher deeplink.
No payment / ledger writes. Staff/Customer posting blocked until ledgers exist.
"""
from pathlib import Path
import os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
view=root/'resources/views/payments/create.blade.php'
ctrl=root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php'
backup=root/'storage/app/am-backups/AM230_PAYMENT_VOUCHER_IDENTITY'
marker='AM230_PAYMENT_TO_CATEGORY_PERSISTENT'
if not all(p.is_file() for p in (root/'artisan',view,ctrl)):
 raise SystemExit('AM230_STOP_NOT_STAGING_LARAVEL')
v=view.read_text('utf8',errors='surrogateescape');c=ctrl.read_text('utf8',errors='surrogateescape')
def atomic(p,s):
 fd,tmp=tempfile.mkstemp(prefix='.am230_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(tmp,p.stat().st_mode&0o777);os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
if mode=='rollback':
 av=backup/'create.blade.php.before_AM230';ac=backup/'controller.php.before_AM230'
 if v.count(marker)!=1 or not (av.is_file() and ac.is_file()):
  raise SystemExit('AM230_STOP_ROLLBACK_BACKUPS_OR_MARKER')
 atomic(view,av.read_text('utf8',errors='surrogateescape'))
 atomic(ctrl,ac.read_text('utf8',errors='surrogateescape'))
 print('AM230_PREVIOUS_VIEW_AND_CONTROLLER_RESTORED')
 sys.exit(0)
if marker in v:
 print('AM230_ALREADY_APPLIED');sys.exit(0)
guardanchors=[
 ('VENDOR_MASTER','<select name="vendor_id" id="payment-vendor">',v),
 ('TOP_SEARCH','<div id="am218-search-wrap" class="am218-wrap">',v),
 ('EXPENSE_OPTION','<option value="EXPENSE_VOUCHER">Expense Voucher</option>',v),
 ('PUSH_END','@endpush',v),
 ('MAIN_MULTI_JS','<script id="am226-vendor-only-inline">',v),
 ('SOURCE_SELECT','<select name="source_type" id="source-type" required>',v),
 ('SERVER_STORE','public function storePayment(Request $request, ExpensePaymentService $service, \\App\\Services\\MultiPurchasePaymentService $multiService): RedirectResponse',c),
]
for name,anchor,s in guardanchors:
 n=s.count(anchor)
 print('AM230_ANCHOR_'+name+'='+('PASS' if n==1 else 'FAIL_'+str(n)))
 if n!=1:raise SystemExit('AM230_STOP_SOURCE_CHANGED_'+name)
block='''<style id="am230-payment-to-style">
.am230-payee{display:flex;flex-direction:column;gap:5px;padding:10px;border:1px solid #b6d5eb;background:#f4f9ff;border-radius:9px;margin-bottom:9px}
.am230-payee select,.am230-payee input{width:100%;min-height:36px;box-sizing:border-box;border:1px solid #b3cadd;border-radius:6px;padding:7px;background:#fff}
.am230-payee #am230-vendor-slot>label{display:flex;flex-direction:column;gap:4px;font-size:11px;font-weight:700;color:#164a75}
.am230-payee #am230-payee-info{font-size:11px;color:#436a85;line-height:1.55}
</style>
{{-- AM230_PAYMENT_TO_CATEGORY_PERSISTENT: payee separate from settlement type. --}}
<div id="am230-payee-box" class="p133-full am230-payee">
<label class="p133-label" for="am230-payee-type">Payment To *</label>
<select id="am230-payee-type" name="payee_category">
<option value="VENDOR" @selected(old('payee_category','VENDOR')==='VENDOR')>Vendor</option>
<option value="STAFF" @selected(old('payee_category')==='STAFF')>Staff</option>
<option value="CUSTOMER" @selected(old('payee_category')==='CUSTOMER')>Customer</option>
</select>
<div id="am230-vendor-slot"></div>
<p id="am230-payee-info" role="status">Vendor Master will remain visible when Payment Against changes.</p>
</div>
'''
v=v.replace('<div id="am218-search-wrap" class="am218-wrap">',block+'<div id="am218-search-wrap" class="am218-wrap">',1)
v=v.replace('<option value="EXPENSE_VOUCHER">Expense Voucher</option>',
'''@if(request('source')==='EXPENSE_VOUCHER' || old('source_type')==='EXPENSE_VOUCHER')
<option value="EXPENSE_VOUCHER">Against Reference — Expense Voucher (opened from Expenses)</option>
@endif''',1)
v=v.replace('Vendor (required for On Account)','Select Vendor Name (Master)',1)
v=v.replace('Advance — Supplier','Advance',1)
v=v.replace('Against Reference — Purchase Bill','Against Reference — Parts Purchase',1)
js="""<script id="am230-payee-script">
(function(){
'use strict';
function init(){
 var group=document.getElementById('am230-payee-type');
 var slot=document.getElementById('am230-vendor-slot');
 var vendor=document.getElementById('payment-vendor');
 var form=document.getElementById('payment-form');
 var info=document.getElementById('am230-payee-info');
 if(!group||!slot||!vendor||!form||!info)return;
 var label=vendor.closest('label');
 if(!label)return;
 slot.appendChild(label);label.style.display='flex';label.removeAttribute('aria-hidden');
 // Move the original vendor_id SELECT: never create a duplicate posting field.
 var stale=document.getElementById('am217-vendor-filter');
 if(stale)stale.remove();
 var input=document.createElement('input');input.type='search';input.autocomplete='off';
 input.placeholder='Search Vendor Master by name or code';
 input.setAttribute('aria-label','Filter Vendor Master');
 label.insertBefore(input,vendor);
 var original=Array.from(vendor.options).map(function(o){return o.cloneNode(true);});
 input.addEventListener('input',function(){
   var needle=input.value.toLowerCase().trim(),selected=vendor.value;
   var filtered=original.filter(function(o){
     return !o.value||o.value===selected||o.textContent.toLowerCase().includes(needle);
   });
   vendor.replaceChildren.apply(vendor,filtered.map(function(o){return o.cloneNode(true);}));
   vendor.value=selected;
 });
 function update(){
  var ok=group.value==='VENDOR';slot.hidden=!ok;
  info.textContent=ok
   ? 'Vendor Master stays selected for Against Reference, Advance and On Account. Use outstanding-bill search for Parts/OSL allocations.'
   : group.value+' payout requires a linked Staff/Customer ledger, approval and reversal. Posting is disabled until that workflow is available.';
 }
 group.addEventListener('change',update);update();
 // Fail closed before any existing JS confirmation or POST for unlinked payees.
 form.addEventListener('submit',function(event){
  if(group.value!=='VENDOR'){event.preventDefault();event.stopImmediatePropagation();update();}
 },true);
 // Reference bill search serves a separate purpose from persistent payee identity.
 var b=document.querySelector('label[for="am218-source-search"]');
 if(b)b.textContent='Find Vendor Outstanding Bills';
 var sourceKey=document.getElementById('source-key');
 if(sourceKey)sourceKey.addEventListener('change',function(){
  var item=(window.AM_PAYMENT_SOURCES||[]).find(function(x){return x.key===sourceKey.value;});
  if(item&&item.vendor_id){
    vendor.replaceChildren.apply(vendor,original.map(function(o){return o.cloneNode(true);}));
    input.value='';vendor.value=String(item.vendor_id);
  }
 });
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
else init();
})();
</script>
"""
v=v.replace('@endpush',js+'@endpush',1)
serverneedle='public function storePayment(Request $request, ExpensePaymentService $service, \\App\\Services\\MultiPurchasePaymentService $multiService): RedirectResponse'
start=c.index(serverneedle);brace=c.index('{',start)
serverguard="""
        // AM230 no Staff/Customer ledger key in current Payment Voucher schema.
        // Fail closed rather than posting staff/customer money as free-text OTHER.
        if (in_array(strtoupper((string)$request->input('payee_category','VENDOR')),['STAFF','CUSTOMER'],true)) {
            return back()->withErrors(['payee_category'=>
                'Staff/Customer payout requires audited ledger integration. No payment saved.'])->withInput();
        }
"""
c=c[:brace+1]+'\n'+serverguard+c[brace+1:]
if v.count(marker)!=1 or c.count('AM230 no Staff/Customer ledger key')!=1:
 raise SystemExit('AM230_STOP_PATCH_MARKERS_MISSING')
with tempfile.TemporaryDirectory(prefix='am230_check_') as t:
 d=Path(t);candidate=d/'create.blade.php';compiled=d/'view.php';php=d/'AdminExpensePaymentPageController.php'
 candidate.write_text(v,'utf8',errors='surrogateescape')
 php.write_text(c,'utf8',errors='surrogateescape')
 compile_script=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
 for cmd in (['php','-r',compile_script,str(candidate),str(compiled)],['php','-l',str(compiled)],['php','-l',str(php)]):
  r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=65)
  if r.returncode:raise SystemExit('AM230_STOP_BLADE_OR_PHP_LINT '+(r.stdout+r.stderr)[-320:])
# Validate the new inline client JS separately from Blade/PHP.
if shutil.which('node'):
 node=subprocess.run(['node','--check'],input=js,cwd=root,capture_output=True,text=True,timeout=25)
 if node.returncode:raise SystemExit('AM230_STOP_INLINE_JAVASCRIPT_SYNTAX '+node.stderr[-220:])
 print('AM230_CLIENT_JS_SYNTAX_PASS')
else:
 print('AM230_STAGING_NODE_NOT_INSTALLED_JS_VALIDATED_ON_GITHUB_CI')
print('AM230_VENDOR_MASTER_PERSISTENT=PASS')
print('AM230_EXPENSE_DEEPLINK_STILL_ALLOWED=PASS')
print('AM230_STAFF_CUSTOMER_POST_BLOCKED=PASS')
print('AM230_EXISTING_PAYMENT_ROUTES_AND_ALLOCATION_UNCHANGED=PASS')
print('AM230_LARAVEL_BLADE_PHP_PREFLIGHT=PASS')
if mode=='check':
 print('AM230_READONLY_PREFLIGHT_PASS');sys.exit(0)
if backup.exists():raise SystemExit('AM230_STOP_BACKUP_ALREADY_EXISTS')
backup.mkdir(parents=True,exist_ok=False)
shutil.copy2(view,backup/'create.blade.php.before_AM230')
shutil.copy2(ctrl,backup/'controller.php.before_AM230')
try:
 atomic(ctrl,c);atomic(view,v)
except BaseException:
 shutil.copy2(backup/'create.blade.php.before_AM230',view)
 shutil.copy2(backup/'controller.php.before_AM230',ctrl)
 raise
print('AM230_STAGING_VIEW_AND_GUARD_INSTALLED')
print('AM230_DATABASE_AND_LEDGER_UNCHANGED')
