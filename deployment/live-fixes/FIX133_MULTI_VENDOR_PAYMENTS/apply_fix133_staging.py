#!/usr/bin/env python3
"""FIX133: Guarded multi-purchase voucher + Money Receipt style STAGING deployment.

Only selected existing controller/service/show/view files + one NEW migration,
NEW service and NEW JS are installed. No finance posting during deployment.
check = read-only; apply = source backups, lint, add-only migration, route/view refresh.
"""
from pathlib import Path
import hashlib, os, subprocess, sys, tempfile, shutil
from datetime import datetime,timezone

BASE_URL="https://raw.githubusercontent.com/birensingha/assam-motors-erp/24d9dfd7ca75865991557de3b7713ee5128e4509/deployment/live-fixes/FIX133_MULTI_VENDOR_PAYMENTS/"
OLD={
 "app/Http/Controllers/Web/AdminExpensePaymentPageController.php":"052776b251cca2ab32c4d1fe5a70f68a11194acd9299855ea20a7234811c72af",
 "app/Services/ExpensePaymentService.php":"2f9215125893c4b4014455d26fe30403ca8ed4191c6a7aeb7a1fc87f56a95776",
 "resources/views/payments/create.blade.php":"dd1ac52ce7727d704a0a4d7cece5d9f1fb923c639f85501d7bc6d4cef113e381",
 "resources/views/payments/show.blade.php":"68c3480461868d232a9460161dff140f881a97fae76d27ab9a8d1585f6b5f7b0"
}
NEW={
 "app/Services/MultiPurchasePaymentService.php":"MultiPurchasePaymentService.php",
 "database/migrations/2026_10_25_093000_create_workshop_payment_allocations.php":"2026_10_25_093000_create_workshop_payment_allocations.php",
 "public/js/payment-voucher-multi.js":"payment-voucher-multi.js"
}
VIEW_SOURCE="payment-create.blade.php"
MARKER="FIX133"
ROOT=None
def sha(x):return hashlib.sha256(x).hexdigest()
def fail(s):print("FIX133_ABORT="+s,flush=True);raise SystemExit(2)
def run(args, cwd, timeout=55):
 p=subprocess.run(args,cwd=cwd,capture_output=True,text=True,timeout=timeout)
 if p.returncode:
  print("FIX133_ERROR_OUTPUT="+(p.stdout+"\n"+p.stderr)[-1100:],flush=True)
  fail("COMMAND_FAILED_"+str(args[:3]))
 return p.stdout.strip()
def one(data,needle,replacement,label):
 if data.count(needle)!=1:fail("ANCHOR_"+label+"_COUNT_"+str(data.count(needle)))
 return data.replace(needle,replacement,1)

def service_patch(text):
 single="$paidPurchase=DB::table('workshop_payment_vouchers')->where('record_status','ACTIVE')->where('source_type','PURCHASE_INVOICE')->whereNotNull('source_reference')->selectRaw('source_reference, SUM(amount) as paid')->groupBy('source_reference')->pluck('paid','source_reference');"
 extra="""$allocatedPurchase=DB::table('workshop_payment_allocations as a')
            ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
            ->where('v.record_status','ACTIVE')
            ->selectRaw('a.purchase_ref, SUM(a.amount) as paid')->groupBy('a.purchase_ref')
            ->pluck('paid','purchase_ref');"""
 text=one(text,single,single+"\n        "+extra,"PAID_SOURCES")
 old="$out=round($billTotal-(float)($paidPurchase[$p->src_ref] ?? 0),2);"
 new="$out=round($billTotal-(float)($paidPurchase[$p->src_ref] ?? 0)-(float)($allocatedPurchase[$p->src_ref] ?? 0),2);"
 text=one(text,old,new,"BATCH_DUE")
 key="'payee'=>$p->vendor_name,'job_no'=>"
 text=one(text,key,"'payee'=>$p->vendor_name,'vendor_id'=>(int)$p->vendor_id,'job_no'=>","SOURCE_VENDOR_ID")
 old="$total=$batchTotal!==null ? (float)$batchTotal : (float)$rows->sum('total_amount'); $first=$rows->first(); $paid=(float)WorkshopPaymentVoucher::query()->where('record_status','ACTIVE')->where('source_type',$type)->where('source_reference',$ref)->sum('amount');"
 new=old+"""
            // FIX133: active allocated shares of multi-bill vouchers must reduce single-bill outstanding too.
            $paid += (float)DB::table('workshop_payment_allocations as a')
                ->join('workshop_payment_vouchers as v','v.id','=','a.payment_voucher_id')
                ->where('a.purchase_ref',$ref)->where('v.record_status','ACTIVE')->sum('a.amount');"""
 text=one(text,old,new,"SINGLE_SOURCE_CAP")
 old="if(!$row) abort(404,'Payment Voucher not found.'); return ['payment'=>(array)$row];"
 new="""if(!$row) abort(404,'Payment Voucher not found.');
        $allocations=DB::table('workshop_payment_allocations')
            ->where('payment_voucher_id',$id)->orderBy('id')
            ->get(['purchase_ref','amount'])->map(fn($x)=>(array)$x)->all();
        return ['payment'=>(array)$row,'allocations'=>$allocations];"""
 text=one(text,old,new,"SHOW_ALLOCATIONS")
 return text
def controller_patch(text):
 a="public function storePayment(Request $request, ExpensePaymentService $service): RedirectResponse"
 b="public function storePayment(Request $request, ExpensePaymentService $service, \\App\\Services\\MultiPurchasePaymentService $multiService): RedirectResponse"
 text=one(text,a,b,"STORE_SIGNATURE")
 # Restrict splice to storePayment method, not other methods with $this->admin($request).
 start=text.index(b)
 end=text.index("    public function showPayment(",start)
 subsection=text[start:end]
 needle="        $user=$this->admin($request);\n        $data=$request->validate(["
 if subsection.count(needle)!=1:fail("PAYMENT_HANDLER_ANCHOR")
 insert="""        $user=$this->admin($request);
        // FIX133 one financial voucher with audited per-bill allocations.
        if($request->boolean('multi_bill')){
            $data=$request->validate([
                'source_type'=>['required','in:PURCHASE_INVOICE'],
                'request_key'=>['required','uuid'],
                'payment_date'=>['required','date_format:Y-m-d'],
                'allocations'=>['required','array','min:2','max:30'],
                'allocations.*.ref'=>['required','string','max:180'],
                'allocations.*.amount'=>['required','numeric','gt:0','max:99999999'],
                'amount'=>['required','numeric','gt:0'],
                'payment_mode'=>['required','in:Cash,UPI,Bank Transfer,Cheque,Card'],
                'account_name'=>['nullable','string','max:120'],
                'reference_no'=>['nullable','string','max:150'],
                'notes'=>['nullable','string','max:2000'],
            ]);
            $payment=$multiService->save($data,$this->actorName($user));
            return redirect()->route('erp.payments.show',$payment->id)
                ->with('success','One Payment Voucher posted against multiple Purchase Bills, with auditable allocations.');
        }
        $data=$request->validate(["""
 subsection=subsection.replace(needle,insert,1)
 return text[:start]+subsection+text[end:]
def show_patch(text):
 marker='<div class="info-grid"><div><span>Mode</span>'
 block="""@if(!empty($allocations))
<div class="card compact section-gap-small">
 <h3 style="color:#134b7b;margin:0 0 10px">Purchase Bills Adjusted ({{ count($allocations) }})</h3>
 <table class="data-table"><thead><tr><th>Purchase Bill</th><th class="num">Allocated Payment</th></tr></thead><tbody>
 @foreach($allocations as $a)
 <tr><td>{{ $a['purchase_ref'] }}</td><td class="num">₹{{ number_format((float)$a['amount'],2) }}</td></tr>
 @endforeach
 <tr><th>Total Allocated</th><th class="num">₹{{ number_format((float)collect($allocations)->sum('amount'),2) }}</th></tr>
 </tbody></table>
 <small>FIX133 · One Payment Voucher · Per-bill allocation history retained even when voided.</small>
</div>
@endif
"""
 return one(text,marker,block+marker,"PAYMENT_SHOW")
def lint_blade(root,view):
 with tempfile.TemporaryDirectory(prefix="fix133_blade_") as d:
  compiled=Path(d)/"compiled.php"
  php="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();file_put_contents($argv[2],\\Illuminate\\Support\\Facades\\Blade::compileString(file_get_contents($argv[1])));"
  run(["php","-r",php,str(view),str(compiled)],root)
  run(["php","-l",str(compiled)],root)
def main():
 if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):fail("USAGE")
 mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
 if not (root/"artisan").is_file() or not (root/"vendor/autoload.php").is_file():fail("NOT_LARAVEL")
 originals={}
 for p,expected in OLD.items():
  f=root/p
  if not f.is_file():fail("MISSING_"+p)
  raw=f.read_bytes()
  print("FIX133_SOURCE_SHA_"+Path(p).name+"="+sha(raw))
  if sha(raw)!=expected:fail("SOURCE_CHANGED_"+p)
  originals[p]=raw
 for p in NEW:
  if (root/p).exists():fail("NEW_TARGET_ALREADY_EXISTS_"+p)
 if mode=="check":
  print("FIX133_SOURCE_PREFLIGHT_NO_WRITES_PASS");return
 with tempfile.TemporaryDirectory(prefix="fix133_stage_") as d:
  stage=Path(d)
  downloads={}
  for target,name in {**NEW,"resources/views/payments/create.blade.php":VIEW_SOURCE}.items():
   dest=stage/name
   if not dest.exists():
    run(["curl","-fsSL","--retry","2",BASE_URL+name,"-o",str(dest)],root)
   downloads[target]=dest.read_bytes()
  new_existing={
   "app/Services/ExpensePaymentService.php":service_patch(originals["app/Services/ExpensePaymentService.php"].decode("utf-8","surrogateescape")).encode("utf-8","surrogateescape"),
   "app/Http/Controllers/Web/AdminExpensePaymentPageController.php":controller_patch(originals["app/Http/Controllers/Web/AdminExpensePaymentPageController.php"].decode("utf-8","surrogateescape")).encode("utf-8","surrogateescape"),
   "resources/views/payments/show.blade.php":show_patch(originals["resources/views/payments/show.blade.php"].decode("utf-8","surrogateescape")).encode("utf-8","surrogateescape"),
   "resources/views/payments/create.blade.php":downloads["resources/views/payments/create.blade.php"]
  }
  all_files={**new_existing,**{p:downloads[p] for p in NEW}}
  # Stage PHP code lint + Blade compilation before installing sources.
  for p,content in all_files.items():
   if not p.endswith(".php") and not p.endswith(".blade.php"):continue
   f=stage/(p.replace("/","_"))
   f.write_bytes(content)
   if p.endswith(".blade.php"):
    lint_blade(root,f)
   else:
    run(["php","-l",str(f)],root)
  print("FIX133_PHP_AND_BLADE_LINT=PASS")
  backupdir=root/"storage/app/am-backups";backupdir.mkdir(parents=True,exist_ok=True)
  tag=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
  backups=[]
  for p,raw in originals.items():
   name="FIX133_"+Path(p).name+"_"+tag+".bak"
   backup=backupdir/name
   with os.fdopen(os.open(backup,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as f:
    f.write(raw);f.flush();os.fsync(f.fileno())
   if sha(backup.read_bytes())!=sha(raw):fail("BACKUP_VERIFY")
   backups.append(backup)
  print("FIX133_BACKUPS_VERIFIED=YES")
  touched=[]
  try:
   # Install additive empty-table migration before service begins querying it.
   # Existing application code keeps running unchanged during migration.
   migration="database/migrations/2026_10_25_093000_create_workshop_payment_allocations.php"
   migration_target=root/migration
   migration_target.write_bytes(all_files[migration])
   touched.append(migration)
   run(["php","artisan","migrate","--path="+migration,"--force","--no-interaction"],root,timeout=80)
   for p,content in all_files.items():
    if p==migration: continue
    target=root/p;temp=target.with_name("."+target.name+".fix133.tmp")
    if temp.exists():raise RuntimeError("STALE_TEMP")
    temp.write_bytes(content)
    os.chmod(temp,target.stat().st_mode&0o777 if target.exists() else 0o644)
    os.replace(temp,target);touched.append(p)
   for p,content in all_files.items():
    if sha((root/p).read_bytes())!=sha(content):raise RuntimeError("POST_INSTALL_HASH_FAIL_"+p)
   # Exactly one named additive migration was executed before application switch.
   run(["php","artisan","view:clear"],root)
   code="require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();if(!\\Illuminate\\Support\\Facades\\Schema::hasTable('workshop_payment_allocations'))exit(3);echo 'FIX133_ALLOCATION_TABLE_READY=YES';"
   print(run(["php","-r",code],root))
   print("FIX133_STAGING_INSTALLED=YES")
   print("FIX133_NO_PAYMENT_POSTED_BY_INSTALL=YES")
   for x in backups:print("FIX133_BACKUP="+str(x))
  except BaseException as ex:
   print("FIX133_DEPLOY_ERROR="+str(ex))
   for p in touched:
    f=root/p
    if p in originals:f.write_bytes(originals[p])
    else:f.unlink(missing_ok=True)
   subprocess.run(["php","artisan","view:clear"],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
   print("FIX133_APPLICATION_SOURCES_ROLLED_BACK=YES")
   print("FIX133_NOTE_EMPTY_ALLOCATION_TABLE_MAY_REMAIN_IF_MIGRATION_SUCCEEDED")
   fail("DEPLOY_ABORTED")
if __name__=="__main__":main()
