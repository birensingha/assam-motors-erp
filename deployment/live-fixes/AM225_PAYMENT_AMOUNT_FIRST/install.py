#!/usr/bin/env python3
"""AM225: STAGING payment amount first / oldest-bill-first multi allocation.
Modes check|apply|rollback STAGING_ROOT. Changes only source files, never data.
Requires AM224 and exact expected anchors; backs up all files and rolls back
on failed PHP/Blade validation. Does not post payments.
"""
from pathlib import Path
import os,sys,subprocess,tempfile,hashlib,json,shutil
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('AM225_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM225_STOP_NOT_STAGING')
paths={
 'multi':'app/Services/MultiPurchasePaymentService.php',
 'service':'app/Services/ExpensePaymentService.php',
 'controller':'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'view':'resources/views/payments/create.blade.php',
}
inputs={name:root/path for name,path in paths.items()}
patch=Path(__file__).resolve().parent
jsfile=patch/'payment-first.js'
testfile=patch/'test-allocation.js'
backup=root/'storage/app/am-backups/AM225_PAYMENT_AMOUNT_FIRST'
manifest=backup/'hashes.json'
marker='AM225_PAYMENT_FIRST_ALLOCATIONS'
def read(p):return p.read_text(encoding='utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 fd,t=tempfile.mkstemp(prefix='.am225_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(t,p.stat().st_mode&0o777 if p.exists() else 0o644)
  os.replace(t,p)
 finally:
  if os.path.exists(t):os.unlink(t)
if any(not p.is_file() or p.is_symlink() for p in inputs.values()) or not jsfile.is_file() or not testfile.is_file():
 raise SystemExit('AM225_STOP_MISSING_PATCH_SOURCE')
original={n:read(p) for n,p in inputs.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM225_STOP_NO_BACKUP')
 hashes=json.loads(read(manifest))
 if any(sha(original[n])!=hashes['after'][n] for n in paths):
  raise SystemExit('AM225_STOP_CONCURRENT_SOURCE_CHANGED')
 if any(not (backup/(n+'.before')).is_file() for n in paths):raise SystemExit('AM225_STOP_INCOMPLETE_BACKUP')
 for n,p in inputs.items():atomic(p,read(backup/(n+'.before')))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=45)
 print('AM225_SOURCE_ROLLBACK_PASS');sys.exit(0)
if marker in original['view']:
 if all(marker in original[n] for n in paths):
  print('AM225_ALREADY_INSTALLED');sys.exit(0)
 raise SystemExit('AM225_STOP_PARTIAL_INSTALL')
if any(marker in original[n] for n in paths):raise SystemExit('AM225_STOP_PARTIAL_INSTALL')
if 'AM224_TALLY_PAYMENT_MULTIBILL' not in original['multi'] or 'am224-tally-payment-multibill-inline' not in original['view']:
 raise SystemExit('AM225_STOP_AM224_PREREQUISITE_ABSENT')
candidate=dict(original)
def edit(n,old,new,label):
 count=candidate[n].count(old)
 if count!=1:raise SystemExit('AM225_STOP_ANCHOR_'+label+'_COUNT_'+str(count))
 candidate[n]=candidate[n].replace(old,new,1)
edit('controller',"'allocations'=>['required','array','min:2','max:30'],",
 "'allocations'=>['required','array','min:1','max:30'], // AM225_PAYMENT_FIRST_ALLOCATIONS allows one or more bill shares",
 'CONTROLLER_MIN_1')
edit('controller',"'One Payment Voucher posted against multiple Purchase Bills, with auditable allocations.'",
 "'Payment Voucher posted with auditable Parts / OSL bill allocations. AM225_PAYMENT_FIRST_ALLOCATIONS'",
 'CONTROLLER_SUCCESS_MESSAGE')
edit('multi', "count($items)<2 || count($items)>30", "count($items)<1 || count($items)>30 /* AM225_PAYMENT_FIRST_ALLOCATIONS */",
 'SERVICE_MIN_1')
edit('multi', "Choose between 2 and 30 Purchase Bills.", "Choose between 1 and 30 outstanding Parts / OSL bills.",
 'SERVICE_ONE_TO_THIRTY')
edit('service', "'outstanding'=>$out,'payee'=>$p->vendor_name",
 "'outstanding'=>$out,'bill_date'=>$p->d,'payee'=>$p->vendor_name", 'PART_BILL_DATE')
edit('service', "'outstanding'=>$out,'payee'=>$o->vendor_name",
 "'outstanding'=>$out,'bill_date'=>$o->purchase_date,'payee'=>$o->vendor_name",
 'OSL_BILL_DATE')
# Ensure repeated non-OSL edits never reassign accounting amounts.
edit('service',
 "        // AM224_TALLY_PAYMENT_MULTIBILL: OSL shares posted via multi-voucher",
 "        // AM225_PAYMENT_FIRST_ALLOCATIONS: all allocated totals still validated under DB locks.\n        // AM224_TALLY_PAYMENT_MULTIBILL: OSL shares posted via multi-voucher",
 'SERVICE_MARKER')
js=read(jsfile)
if marker not in js or '</script' in js.lower():raise SystemExit('AM225_STOP_INLINE_JS_SOURCE')
head='<script id="am224-tally-payment-multibill-inline">'
count=candidate['view'].count(head)
if count!=1:raise SystemExit('AM225_STOP_OLD_INLINE_COUNT_'+str(count))
start=candidate['view'].index(head);end=candidate['view'].find('</script>',start)
if end<0:raise SystemExit('AM225_STOP_INLINE_END_MISSING')
candidate['view']=candidate['view'][:start]+'<script id="am225-payment-amount-first-inline">\n'+js+'\n</script>'+candidate['view'][end+len('</script>'):]
edit('view','Multiple Bills — one Payment Voucher (Parts + OSL)',
 'Adjust Multiple Bills (enter Payment Amount first) — Parts + OSL',
 'MULTI_LABEL')
edit('view','</style>',
 """/* AM225_PAYMENT_FIRST_ALLOCATIONS: manual adjustments and payment amount first */
.am225-controls{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px}
.am225-auto{border:1px solid #94bddd;background:#e7f3fe;color:#134b79;font-weight:800;font-size:12px;
border-radius:8px;padding:9px 13px;cursor:pointer}
.am225-auto:hover{background:#d4eaff}
.am225-summary{display:flex;gap:14px;flex-wrap:wrap;font-size:12px;color:#28506c}
.am225-summary strong{color:#164a78}
#am225-amount-first-slot:not([hidden]){display:block;margin:0 0 12px;padding:10px 12px;border:1px solid #a8cde7;background:#f1f8ff;border-radius:9px}
#am225-amount-first-slot .p133-label>input{min-height:44px;width:100%;box-sizing:border-box}
#am225-amount-first-slot .p133-label{font-size:11px;font-weight:850;color:#174d78}
</style>""",
 'PAYMENT_FIRST_CSS')
for n in paths:
 if marker not in candidate[n]:raise SystemExit('AM225_STOP_MISSING_SOURCE_MARKER_'+n)
# PHP lint before a single write.
with tempfile.TemporaryDirectory(prefix='am225_qa_') as t:
 tmp=Path(t)
 for name in ('multi','service','controller'):
  f=tmp/(name+'.php');f.write_text(candidate[name],encoding='utf8',errors='surrogateescape')
  res=subprocess.run(['php','-l',str(f)],cwd=root,capture_output=True,text=True,timeout=30)
  if res.returncode:raise SystemExit('AM225_STOP_PHP_LINT_'+name+' '+(res.stdout+res.stderr)[-500:])
 helper=tmp/'compile.php';source=tmp/'create.blade.php';dest=tmp/'compiled.php'
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 source.write_text(candidate['view'],encoding='utf8',errors='surrogateescape')
 res=subprocess.run(['php',str(helper),str(root),str(source),str(dest)],cwd=root,capture_output=True,text=True,timeout=50)
 if res.returncode:raise SystemExit('AM225_STOP_BLADE_COMPILE '+res.stderr[-450:])
 res=subprocess.run(['php','-l',str(dest)],cwd=root,capture_output=True,text=True,timeout=40)
 if res.returncode:raise SystemExit('AM225_STOP_COMPILED_BLADE_PHP '+(res.stdout+res.stderr)[-450:])
 if marker not in read(dest):raise SystemExit('AM225_STOP_COMPILED_VIEW_MISSING_SCRIPT')
 node=shutil.which('node')
 if node:
  for args in ([node,'--check',str(jsfile)],[node,str(testfile)]):
   res=subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=35)
   if res.returncode:raise SystemExit('AM225_STOP_JS_TEST '+(res.stdout+res.stderr)[-600:])
  print('AM225_PAYMENT_FIRST_ALLOCATION_TESTS_PASS')
print('AM225_PHP_BLADE_JS_PREFLIGHT_PASS')
if mode=='check':
 print('AM225_CHECK_ONLY_NO_SOURCE_OR_DATA_WRITES');sys.exit(0)
if backup.exists():raise SystemExit('AM225_STOP_BACKUP_EXISTS')
if any(read(inputs[n])!=original[n] for n in paths):raise SystemExit('AM225_STOP_CONCURRENT_SOURCE_UPDATE')
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for n in paths:
 original_file=backup/(n+'.before');original_file.write_text(original[n],encoding='utf8',errors='surrogateescape')
 os.chmod(original_file,0o600)
try:
 for n,p in inputs.items():atomic(p,candidate[n])
 manifest.write_text(json.dumps({'before':{n:sha(original[n]) for n in paths},'after':{n:sha(candidate[n]) for n in paths}}))
 os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  res=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
  if res.returncode:raise RuntimeError('AM225_STOP_VIEW_CACHE '+res.stderr[-500:])
except BaseException as e:
 for n,p in inputs.items():atomic(p,original[n])
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 raise SystemExit('AM225_ROLLED_BACK_AFTER_FAILURE '+str(e))
print('AM225_STAGING_SOURCES_APPLIED_NO_FINANCIAL_DATA_WRITES')
print('AM225_AUTHENTICATED_BROWSER_AND_FINANCIAL_POSTING_QA_PENDING')
