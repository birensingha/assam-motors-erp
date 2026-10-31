#!/usr/bin/env python3
"""AM224: gated Tally-style Parts+OSL multi-vendor voucher source patch.
check|apply|rollback STAGING_ROOT
Changes only specific PHP service/controller and Payment Voucher Blade source.
No database writes by the patch; posting remains the existing server transaction.
"""
from pathlib import Path
import os,hashlib,subprocess,sys,tempfile,shutil,json
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM224_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM224_STOP_NOT_STAGING')
paths={
 'multi':'app/Services/MultiPurchasePaymentService.php',
 'service':'app/Services/ExpensePaymentService.php',
 'view':'resources/views/payments/create.blade.php'}
backup=root/'storage/app/am-backups/AM224_TALLY_PAYMENT_MULTIBILL'
payload=Path(__file__).resolve().parent/'unified.js'
manifest=backup/'hashes.json'
marker='AM224_TALLY_PAYMENT_MULTIBILL'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
 fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.am224_')
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(tmp,(p.stat().st_mode&0o777) if p.exists() else 0o644)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
files={n:root/path for n,path in paths.items()}
if not payload.is_file() or any(not p.is_file() for p in files.values()):raise SystemExit('AM224_STOP_SOURCE_MISSING')
src={n:read(p) for n,p in files.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM224_STOP_NO_BACKUP')
 m=json.loads(read(manifest))
 if any(sha(src[n])!=m['after'][n] for n in paths):raise SystemExit('AM224_STOP_CONCURRENT_SOURCE_CHANGE')
 for n in paths:
  if not (backup/(n+'.before')).is_file():raise SystemExit('AM224_STOP_BACKUP_MISSING')
 for n,p in files.items():atomic(p,read(backup/(n+'.before')))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=50)
 print('AM224_ROLLBACK_RESTORED_PREVIOUS_SOURCE');sys.exit(0)
if all(marker in src[n] for n in paths):
 print('AM224_ALREADY_INSTALLED');sys.exit(0)
if any(marker in src[n] for n in paths):raise SystemExit('AM224_STOP_PARTIAL_DEPLOY')
new=dict(src)
def replace(n,old,updated,label):
 if new[n].count(old)!=1:raise SystemExit('AM224_STOP_ANCHOR_'+label+'_'+str(new[n].count(old)))
 new[n]=new[n].replace(old,updated,1)
# Existing per-vendor multi service validates parts invoices and locks them. Extend,
# but keep the already-tested Parts financial branch intact.
replace('multi',
 "        if (strtoupper((string)($data['source_type']??''))!=='PURCHASE_INVOICE') {",
 "        // AM224_TALLY_PAYMENT_MULTIBILL: PURCHASE_INVOICE is the parent voucher category;\n"
 "        // allocations may refer to Parts batches or OSL_JOB::purchase_id.\n"
 "        if (strtoupper((string)($data['source_type']??''))!=='PURCHASE_INVOICE') {",
 'MULTI_SOURCE_MARKER')
replace('multi',
 "            foreach($alloc as $ref=>$amount) {\n                $rows=DB::table('workshop_purchases')",
 """            foreach($alloc as $ref=>$amount) {
                // AM224 OSL entries remain in the existing purchase_ref field
                // as namespaced keys; legacy Parts bill references stay unchanged.
                if(str_starts_with($ref,'OSL_JOB::')){
                    $idText=substr($ref,strlen('OSL_JOB::'));
                    if(!preg_match('/^[1-9][0-9]*$/D',$idText))$this->reject('Invalid OSL purchase reference.');
                    $id=(int)$idText;
                    $osl=DB::table('workshop_purchases')->where('id',$id)
                        ->where('purchase_type','OSL')
                        ->whereRaw("COALESCE(record_status,'ACTIVE') <> 'DELETED'")
                        ->lockForUpdate()->first();
                    if(!$osl)$this->reject('OSL purchase is missing or deleted. Refresh the bill list.');
                    $billVendor=(int)$osl->vendor_id;
                    if($billVendor<=0)$this->reject('OSL purchase Vendor Master link missing.');
                    if($vendorId===null)$vendorId=$billVendor;
                    if($vendorId!==$billVendor)$this->reject('All selected bills must belong to ONE vendor.');
                    $direct=(float)DB::table('workshop_payment_vouchers')
                        ->where('source_type','OSL_JOB')->where('source_id',$id)
                        ->where('record_status','ACTIVE')->sum('amount');
                    $allocated=(float)DB::table('workshop_payment_allocations as a')
                        ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                        ->where('a.purchase_ref',$ref)->where('v.record_status','ACTIVE')->sum('a.amount');
                    $due=round((float)$osl->total_amount-$direct-$allocated,2);
                    if($due<0.009 || $amount>$due+0.009){
                        $this->reject('OSL bill '.$ref.' outstanding ₹'.number_format(max(0,$due),2).
                          ', requested ₹'.number_format($amount,2).'. No payment saved.');
                    }
                    $job=(int)($osl->job_card_id??0);
                    if($job<=0)$allAssigned=false;else $allJobIds[$job]=true;
                    continue;
                }
                $rows=DB::table('workshop_purchases')""",
 'MULTI_OSL_TRANSACTION_BRANCH')
# OSL source list + single-payment resolver must subtract active multi allocations
# to prevent double-paying invoices that are partially covered by multi vouchers.
replace('service',
 "        $osls=DB::table('workshop_purchases as p')",
 """        // AM224_TALLY_PAYMENT_MULTIBILL: OSL shares posted via multi-voucher
        // use unique OSL_JOB::<id> purchase_ref and reduce the available balance.
        $allocatedOsl=DB::table('workshop_payment_allocations as a')
            ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
            ->where('v.record_status','ACTIVE')
            ->where('a.purchase_ref','like','OSL_JOB::%')
            ->selectRaw('a.purchase_ref, SUM(a.amount) as paid')
            ->groupBy('a.purchase_ref')->pluck('paid','purchase_ref');
        $osls=DB::table('workshop_purchases as p')""",
 'OSL_MULTI_PAID_SOURCE')
replace('service',
 "foreach($osls as $o){ $out=round((float)$o->total_amount-(float)($paidOsl[$o->id] ?? 0),2);",
 "foreach($osls as $o){ $out=round((float)$o->total_amount-(float)($paidOsl[$o->id] ?? 0)-(float)($allocatedOsl['OSL_JOB::'.$o->id] ?? 0),2);",
 'OSL_OUTSTANDING_SUBTRACT_MULTI')
replace('service',
 """$paid=(float)WorkshopPaymentVoucher::query()->where('record_status','ACTIVE')->where('source_type',$type)->where('source_id',$id)->sum('amount');
            return ['source_id'=>$id,'source_reference'=>($p->purchase_batch_no ?: $p->purchase_no)""",
 """$paid=(float)WorkshopPaymentVoucher::query()->where('record_status','ACTIVE')->where('source_type',$type)->where('source_id',$id)->sum('amount');
            // AM224: single OSL payments must see the earlier multi-bill allocations.
            $paid+=(float)DB::table('workshop_payment_allocations as a')
                ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                ->where('a.purchase_ref','OSL_JOB::'.$id)->where('v.record_status','ACTIVE')->sum('a.amount');
            return ['source_id'=>$id,'source_reference'=>($p->purchase_batch_no ?: $p->purchase_no)""",
 'OSL_RESOLVE_MULTI_PAID')
# Preserve one unified search and the existing normal Payment Voucher source code.
start="<script src=\"{{ asset('js/payment-voucher-multi.js') }}?v=133\" defer></script>"
head='<script id="am221-payment-source-search-inline">'
if new['view'].count(start)!=1 or new['view'].count(head)!=1:
 raise SystemExit('AM224_STOP_VIEW_SCRIPT_ANCHORS')
new['view']=new['view'].replace(start,
 "<!-- AM224_TALLY_PAYMENT_MULTIBILL: legacy parts-only JS intentionally disabled. -->",1)
left=new['view'].index(head);right=new['view'].find('</script>',left)
if right<0:raise SystemExit('AM224_STOP_OLD_INLINE_END')
js=read(payload)
if '</script' in js.lower() or marker not in js:raise SystemExit('AM224_STOP_BAD_JS')
new['view']=new['view'][:left]+'<script id="am224-tally-payment-multibill-inline">\n'+js+'\n</script>'+new['view'][right+len('</script>'):]
replace('view',
 'Multiple Purchase Bills — one Payment Voucher',
 'Multiple Bills — one Payment Voucher (Parts + OSL)',
 'MULTI_CHECKBOX_LABEL')
# Provide compact readable Tally grid with pending balance calculated before posting.
css="""/* AM224 Tally-style vendor allocation grid */
.am224-head,.am224-row{display:grid;grid-template-columns:32px minmax(130px,2fr) minmax(85px,1fr) minmax(98px,1fr) minmax(85px,1fr);align-items:center;gap:8px;}
.am224-head{background:#e3effa;border-radius:7px;padding:8px 6px;font-size:10px;text-transform:uppercase;color:#335f82;font-weight:800}
.am224-row{padding:9px 6px;border-bottom:1px solid #d8e7f1;background:#fff}
.am224-row .am224-bill{min-width:0;display:flex;flex-direction:column;gap:3px;overflow-wrap:anywhere}
.am224-bill strong{font-size:11px;color:#153e65}.am224-bill span{font-size:10px;color:#506d85}
.am224-amt,.am224-balance{font-size:11px;font-weight:700}
.am224-adjust{max-width:100%;width:100%;height:35px;min-width:0;border:1px solid #accbe1;border-radius:6px;padding:5px 8px}
.am224-check{width:18px;height:18px;accent-color:#0e629f}
#am218-search-wrap[hidden]{display:none!important}
#p133-multi-area:not([hidden]){display:block}
#p133-multi-vendor{max-width:100%}
#p133-multi-bills{max-height:430px}
@media(max-width:650px){.am224-head,.am224-row{grid-template-columns:25px minmax(105px,1.6fr) minmax(67px,1fr) minmax(74px,1fr) minmax(65px,1fr);gap:4px}.am224-amt,.am224-balance{font-size:10px}}
"""
replace('view','</style>',css+'</style>','TALLY_CSS')
# Guard against latent AM217 input duplication: no need to remove from Blade,
# the JS removes it at runtime for multi and single, retaining Vendor Master select.
for n,v in new.items():
 if marker not in v:raise SystemExit('AM224_STOP_PATCH_MARKER_MISSING_'+n)
for n in ('multi','service'):
 with tempfile.NamedTemporaryFile(suffix='.php',mode='w',delete=False,encoding='utf8') as f:f.write(new[n]);name=f.name
 try:
  r=subprocess.run(['php','-l',name],cwd=root,capture_output=True,text=True,timeout=35)
  if r.returncode:raise SystemExit('AM224_PHP_LINT_FAILED_'+n+' '+(r.stdout+r.stderr)[-350:])
 finally:os.unlink(name)
with tempfile.TemporaryDirectory(prefix='am224qa_') as d:
 d=Path(d);a=d/'view.blade.php';b=d/'compiled.php';h=d/'blade.php'
 a.write_text(new['view'],encoding='utf8',errors='surrogateescape')
 h.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 p=subprocess.run(['php',str(h),str(root),str(a),str(b)],cwd=root,capture_output=True,text=True,timeout=45)
 if p.returncode:raise SystemExit('AM224_BLADE_COMPILE_FAILED '+p.stderr[-400:])
 p=subprocess.run(['php','-l',str(b)],cwd=root,capture_output=True,text=True,timeout=30)
 if p.returncode:raise SystemExit('AM224_BLADE_COMPILED_PHP_FAILED '+(p.stdout+p.stderr)[-400:])
 node=shutil.which('node')
 if node:
  p=subprocess.run([node,'--check',str(payload)],cwd=root,capture_output=True,text=True,timeout=30)
  if p.returncode:raise SystemExit('AM224_JS_LINT_FAILED '+p.stderr[-400:])
print('AM224_ALL_PHP_BLADE_JS_PREFLIGHT_PASS')
print('AM224_MULTI_PARTS_OSL_TRANSACTION_GUARDS_PRESENT')
if mode=='check':
 print('AM224_CHECK_PASS_NO_FINANCIAL_WRITES');sys.exit(0)
if backup.exists():raise SystemExit('AM224_STOP_EXISTING_BACKUP')
if any(read(files[n])!=src[n] for n in files):raise SystemExit('AM224_STOP_CONCURRENT_SOURCE_CHANGE')
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for n in paths:
 p=backup/(n+'.before');p.write_text(src[n],encoding='utf8',errors='surrogateescape');os.chmod(p,0o600)
try:
 for n,p in files.items():atomic(p,new[n])
 manifest.write_text(json.dumps({'before':{n:sha(src[n]) for n in paths},'after':{n:sha(new[n]) for n in paths}}))
 os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  x=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
  if x.returncode:raise RuntimeError('AM224_VIEW_CACHE_FAILED '+x.stderr[-400:])
except BaseException as ex:
 for n,p in files.items():atomic(p,src[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 raise SystemExit('AM224_APPLY_FAILED_ROLLED_BACK '+str(ex))
print('AM224_STAGING_SOURCE_APPLIED_NO_PAYMENT_DB_WRITES')
print('AM224_BROWSER_AND_POSTING_QA_STILL_REQUIRED')
