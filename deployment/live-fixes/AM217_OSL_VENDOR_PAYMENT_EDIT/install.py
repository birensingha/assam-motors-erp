#!/usr/bin/env python3
"""AM217 OSL payment + purchase edit STAGING source patch.
Usage: install.py check|apply|rollback STAGING_ROOT
Does not write DB rows, create vouchers or edit existing purchases.
"""
from pathlib import Path
import hashlib,os,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM217_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM217_STOP_NOT_STAGING')
paths={
 'service':'app/Services/ExpensePaymentService.php',
 'controller':'app/Http/Controllers/Web/AdminWorkshopControlPageController.php',
 'purchase':'resources/views/purchases/show.blade.php',
 'editor':'resources/views/workshop-controls/osl-edit.blade.php',
 'payment':'resources/views/payments/create.blade.php',
}
backup=root/'storage/app/am-backups/AM217_OSL_VENDOR_PAYMENT_EDIT'
manifest=backup/'installed-hashes.txt'
marker='AM217_OSL_VENDOR_PAYMENT_EDIT'
src={}
for name,relative in paths.items():
    p=root/relative
    if not p.is_file() or p.is_symlink():
        raise SystemExit('AM217_STOP_SOURCE_MISSING_'+name)
    src[name]=p.read_text('utf8',errors='surrogateescape')
def sha(s):
    return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(path,s):
    fd,tmp=tempfile.mkstemp(prefix='.am217_',dir=path.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,path.stat().st_mode&0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def replace(name,old,new,label):
    if name=='controller':
        start=changed[name].find('    public function updateOsl(')
        end=changed[name].find('    public function vendorDatabase(',start)
        if start<0 or end<start:raise SystemExit('AM217_STOP_OSL_METHOD_BOUNDARIES')
        body=changed[name][start:end]
        if body.count(old)!=1:
            raise SystemExit('AM217_STOP_ANCHOR_'+label+'_'+str(body.count(old)))
        changed[name]=changed[name][:start]+body.replace(old,new,1)+changed[name][end:]
        return
    if changed[name].count(old)!=1:
        raise SystemExit('AM217_STOP_ANCHOR_'+label+'_'+str(changed[name].count(old)))
    changed[name]=changed[name].replace(old,new,1)
def compiled(view,source,temp):
    inp=temp/(view+'.blade.php');out=temp/(view+'.php')
    inp.write_text(source,encoding='utf8',errors='surrogateescape')
    compiler=temp/'compile.php'
    if not compiler.exists():
        compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    proc=subprocess.run(['php',str(compiler),str(root),str(inp),str(out)],cwd=root,text=True,capture_output=True,timeout=55)
    if proc.returncode:
        raise SystemExit('AM217_STOP_BLADE_COMPILE_'+view+' '+proc.stderr[-300:])
    lint=subprocess.run(['php','-l',str(out)],cwd=root,text=True,capture_output=True,timeout=30)
    if lint.returncode: raise SystemExit('AM217_STOP_BLADE_LINT_'+view+' '+lint.stderr[-300:])
if mode=='rollback':
    if not manifest.is_file():raise SystemExit('AM217_STOP_BACKUP_MISSING')
    expected=manifest.read_text().splitlines()
    if expected!=[sha(src[n]) for n in paths]:
        raise SystemExit('AM217_STOP_SOURCE_CHANGED_SINCE_INSTALL')
    for n in paths:
        if not (backup/(n+'.before')).is_file():raise SystemExit('AM217_STOP_BACKUP_INCOMPLETE')
    for n,relative in paths.items():atomic(root/relative,(backup/(n+'.before')).read_text('utf8',errors='surrogateescape'))
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=30)
    print('AM217_ROLLBACK_RESTORED_SOURCE_ONLY')
    sys.exit(0)
if any(marker in s for s in src.values()):
    if all(marker in s for s in src.values()):
        print('AM217_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM217_STOP_PARTIAL_INSTALL')
changed=dict(src)
replace('service',
    "'payee'=>$o->vendor_name,'job_no'=>$o->job_no",
    "'payee'=>$o->vendor_name,'vendor_id'=>(int)$o->vendor_id,'job_no'=>$o->job_no",
    'OSL_PAYABLE_SOURCE_VENDOR_ID')
replace('service',
    "->get(['p.id','p.purchase_no','p.purchase_date','p.description'",
    "->get(['p.id','p.purchase_no','p.purchase_batch_no','p.purchase_date','p.description'",
    'OSL_BATCH_REFERENCE_SELECT')
replace('service',
    "'label'=>$o->purchase_no.' · '.$o->job_no",
    "'label'=>($o->purchase_batch_no ?: $o->purchase_no).' · '.$o->job_no",
    'OSL_BATCH_REFERENCE_LABEL')
replace('service',
    "'source_reference'=>$p->purchase_no,'job_card_id'=>$p->job_card_id",
    "'source_reference'=>($p->purchase_batch_no ?: $p->purchase_no),'job_card_id'=>$p->job_card_id",
    'OSL_BATCH_PAYMENT_REFERENCE')
changed['service']=changed['service'].replace(
    '    public function payableSources(): array',
    '    // AM217_OSL_VENDOR_PAYMENT_EDIT: OSL source carries same Vendor Master identity as its payable.\n    public function payableSources(): array',1)

replace('purchase',
    "    @endif\n    <a class=\"btn\" target=\"_blank\" href=\"{{ route('erp.purchases.print',$purchase['id']) }}\">",
    """    @elseif(strtoupper((string)$purchase['purchase_type']) === 'OSL')
        <a class="btn primary" href="{{ route('erp.fix48.osl-edit',$purchase['id']) }}">✏ Edit OSL Purchase</a>
        <a class="btn" href="{{ route('erp.payments.create',['source'=>'OSL_JOB','id'=>$purchase['id']]) }}">₹ Pay Against Reference</a>
    @endif
    <a class="btn" target="_blank" href="{{ route('erp.purchases.print',$purchase['id']) }}">""",
    'PURCHASE_ACTIONS')
changed['purchase']=changed['purchase'].replace(
    '<div class="page-head">','{{-- AM217_OSL_VENDOR_PAYMENT_EDIT: safe OSL edit + exact source payment links. --}}\n<div class="page-head">',1)

replace('editor',
    '<label>Vendor Discount % *<input',
    '''<label>Supplier Invoice / Bill No.<input type="text" name="supplier_invoice" maxlength="100" value="{{ old('supplier_invoice',$row->supplier_invoice??'') }}" placeholder="Supplier invoice reference" {{ $blocked?'disabled':'' }}></label>
<label>Vendor Discount % *<input''',
    'INVOICE_FIELD')
changed['editor']=changed['editor'].replace(
    '<form method="post" action="{{ route(\'erp.fix48.osl-save\',$row->id) }}"',
    '{{-- AM217_OSL_VENDOR_PAYMENT_EDIT: supplier invoice correction follows existing audited OSL save. --}}\n<form method="post" action="{{ route(\'erp.fix48.osl-save\',$row->id) }}"',1)

replace('controller',
    "'expected_old_total'=>['required','numeric'],\n        ]);\n        DB::transaction(function()use($purchase,$data,$user){",
    "'expected_old_total'=>['required','numeric'],\n            'supplier_invoice'=>['nullable','string','max:100'],\n        ]);\n        DB::transaction(function()use($purchase,$data,$user){",
    'INVOICE_VALIDATION')
replace('controller',
    "'discount_percent'=>(float)$data['discount_percent'],\n                'discount_amount'=>$q['discountAmount'],",
    "'discount_percent'=>(float)$data['discount_percent'],\n                'supplier_invoice'=>trim((string)($data['supplier_invoice']??'')) ?: null,\n                'discount_amount'=>$q['discountAmount'],",
    'INVOICE_LINE_UPDATE')
replace('controller',
    "if($refs && DB::table('workshop_payment_vouchers')->where('record_status','ACTIVE')\n                    ->where('source_type','OSL_JOB')->whereIn('source_reference',$refs)->exists()){",
    """if(DB::table('workshop_payment_vouchers')->where('record_status','ACTIVE')
                    ->where('source_type','OSL_JOB')
                    ->where(function($query)use($purchase,$refs){
                        $query->where('source_id',$purchase);
                        if($refs)$query->orWhereIn('source_reference',$refs);
                    })->exists()){""",
    'PAID_SOURCE_ID_GUARD')
replace('controller',
    "if(!$batch) throw ValidationException::withMessages(['reason'=>'OSL batch header missing. Edit not saved.']);",
    """if(!$batch) throw ValidationException::withMessages(['reason'=>'OSL batch header missing. Edit not saved.']);
                // AM217_OSL_VENDOR_PAYMENT_EDIT: prevent half-updating supplier invoice
                // on a multi-line batch until its full-document revision is available.
                if(trim((string)($row->supplier_invoice??''))!==trim((string)($data['supplier_invoice']??''))
                    && DB::table('workshop_purchases')->where('purchase_batch_no',$batchNo)->count()>1){
                    throw ValidationException::withMessages(['supplier_invoice'=>'Multi-line supplier invoices require full-batch audited revision.']);
                }""",
    'MULTILINE_INVOICE_GUARD')
replace('controller',
    "'grand_total'=>round($sumTotal+(float)($batch->round_off??0),2),",
    "'grand_total'=>round($sumTotal+(float)($batch->round_off??0),2),\n                    'supplier_invoice'=>trim((string)($data['supplier_invoice']??'')) ?: null,",
    'INVOICE_BATCH_UPDATE')

replace('payment',
    '<option value="OSL_JOB">OSL Job</option>',
    '<option value="OSL_JOB">Against Reference — OSL Purchase</option>',
    'OSL_PAYMENT_TYPE_LABEL')
replace('payment',
    '<label class="p133-label">Vendor (required for On Account)<select name="vendor_id"',
    '''<label class="p133-label">Vendor (required for On Account)
<input id="am217-vendor-filter" type="search" placeholder="Search Vendor Master by name or code" autocomplete="off" aria-label="Search vendor list">
<select name="vendor_id"''',
    'VENDOR_FILTER_INPUT')
replace('payment',
    '<script src="{{ asset(\'js/payment-voucher-multi.js\') }}?v=133" defer></script>',
    '''<script src="{{ asset('js/payment-voucher-multi.js') }}?v=133" defer></script>
{{-- AM217_OSL_VENDOR_PAYMENT_EDIT: filter existing authorized Vendor Master options. --}}
<script>
document.addEventListener('DOMContentLoaded',function(){
  const filter=document.getElementById('am217-vendor-filter');
  const select=document.getElementById('payment-vendor');
  if(!filter||!select)return;
  const original=Array.from(select.options).map(x=>x.cloneNode(true));
  filter.addEventListener('input',function(){
    const needle=filter.value.toLowerCase().trim();
    const tokens=needle.split(/\\s+/).filter(Boolean);
    const prior=select.value;
    const matches=original.filter(function(o){
      if(!o.value)return true;
      const name=o.textContent.toLowerCase();
      if(!needle||name.includes(needle))return true;
      return tokens.length>=2&&tokens.filter(t=>name.includes(t)).length>=2;
    });
    select.replaceChildren(...matches.map(o=>o.cloneNode(true)));
    if([...select.options].some(o=>o.value===prior))select.value=prior;
    else select.value='';
  });
});
</script>''',
    'VENDOR_FILTER_BEHAVIOR')
for n in paths:
    if marker not in changed[n]:raise SystemExit('AM217_STOP_MARKER_MISSING_'+n)
for n in ['controller','service']:
    with tempfile.NamedTemporaryFile('w',suffix='.php',encoding='utf8',delete=False) as f:
        f.write(changed[n]);tempfile_path=f.name
    try:
        lint=subprocess.run(['php','-l',tempfile_path],cwd=root,text=True,capture_output=True,timeout=30)
        if lint.returncode:raise SystemExit('AM217_STOP_PHP_LINT_'+n+' '+(lint.stdout+lint.stderr)[-300:])
    finally:os.unlink(tempfile_path)
with tempfile.TemporaryDirectory(prefix='am217_compile_') as t:
    for view in ['purchase','editor','payment']:
        compiled(view,changed[view],Path(t))
print('AM217_PHP_BLADE_PREFLIGHT_PASS')
print('AM217_OSL_SOURCE_VENDOR_PAYMENT_AND_EDIT_UI_PREPARED')
if mode=='check':
    print('AM217_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if backup.exists():
    if all((backup/(n+'.before')).is_file() and
      (backup/(n+'.before')).read_text('utf8',errors='surrogateescape')==src[n] for n in paths):
        # A prior guarded run rolled back to byte-identical originals.
        # Preserve that backup under a unique archive name before retrying.
        backup.rename(backup.with_name(backup.name+'_rolled_back_'+str(os.getpid())))
    else:raise SystemExit('AM217_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=False)
os.chmod(backup,0o700)
for n in paths:
    orig=backup/(n+'.before')
    orig.write_text(src[n],encoding='utf8',errors='surrogateescape')
    os.chmod(orig,0o600)
if any((root/relative).read_text('utf8',errors='surrogateescape')!=src[n] for n,relative in paths.items()):
    raise SystemExit('AM217_STOP_CONCURRENT_SOURCE_CHANGE')
try:
    for n,relative in paths.items():atomic(root/relative,changed[n])
    manifest.write_text('\n'.join(sha(changed[n]) for n in paths)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        p=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
        if p.returncode:raise RuntimeError('VIEW_CACHE_FAILED_'+p.stderr[-250:])
except BaseException as e:
    for n,relative in paths.items():atomic(root/relative,src[n])
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    raise SystemExit('AM217_APPLY_FAILED_AND_ROLLED_BACK '+str(e))
print('AM217_STAGING_SOURCE_APPLIED_NO_DATABASE_WRITES')
print('AM217_BROWSER_PAYMENT_AND_EDIT_TEST_REQUIRED')
