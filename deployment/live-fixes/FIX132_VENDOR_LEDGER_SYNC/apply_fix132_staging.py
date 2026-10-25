#!/usr/bin/env python3
"""FIX132: Make Vendor Database cards display the SAME totals as Vendor Ledger.

Only 2 presentation/controller source files change. No financial record updates.
Guard exact staging SHA, backup, lint, and test sample Vendor #5 ₹1830 / ₹1830.
"""
from pathlib import Path
import hashlib, os, subprocess, sys, tempfile
from datetime import datetime,timezone
CONT=Path("app/Http/Controllers/Web/AdminWorkshopControlPageController.php")
VIEW=Path("resources/views/workshop-controls/vendor-database.blade.php")
EXPECTED={CONT:"0d11c4fb04eb55a9bcd28ec7122753952387ebb4b631701a49e80467eadecc36",
VIEW:"eac2981bb56a9f815188f2dfb35d30705bc39b0e688011f5bac9ff5dcc3b248f"}
START="        $purchaseTotals=[];$paymentTotals=[];"
END="        return view('workshop-controls.vendor-database',compact('vendors','purchaseTotals','paymentTotals','q'));"
REPLACE=r'''        // FIX132_VENDOR_CARD_LEDGER_AUTHORITY: identical sources, statuses,
        // batch round-off and unified/legacy payment accounting as Vendor Ledger.
        // Page-scoped, no writes or duplicate financial posting.
        $vendorSummaries=[];
        foreach($vendors as $vendor){
            $detail=$vendorService->ledger((int)$vendor->id);
            $vendorSummaries[(int)$vendor->id]=$detail['summary'];
        }
        return view('workshop-controls.vendor-database',compact('vendors','vendorSummaries','q'));'''
MARKER="FIX132_VENDOR_CARD_LEDGER_AUTHORITY"
def sha(raw):return hashlib.sha256(raw).hexdigest()
def stop(x):print("FIX132_ABORT="+x,flush=True);raise SystemExit(2)
def run(cmd,root):
 p=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=70)
 if p.returncode:
  print("FIX132_DIAG="+(p.stderr+"\n"+p.stdout)[-700:])
  stop("COMMAND_FAILED")
 return p.stdout.strip()
def compile_blade(root,view):
 with tempfile.TemporaryDirectory(prefix="fix132_blade_") as d:
  output=Path(d)/"compiled.php"
  code="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();file_put_contents($argv[2],\\Illuminate\\Support\\Facades\\Blade::compileString(file_get_contents($argv[1])));"
  run(["php","-r",code,str(view),str(output)],root)
  run(["php","-l",str(output)],root)
 print("FIX132_BLADE_COMPILE_PHP_LINT=PASS")
def main():
 if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):stop("USAGE")
 mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
 if not (root/"artisan").is_file():stop("INVALID_LARAVEL_ROOT")
 original={}
 for p,expected in EXPECTED.items():
  f=root/p
  if not f.is_file():stop("MISSING_"+str(p))
  raw=f.read_bytes()
  print("FIX132_SOURCE_SHA_"+p.name+"="+sha(raw))
  if sha(raw)!=expected:stop("SOURCE_SHA_CHANGED_"+p.name)
  original[p]=raw
 c=original[CONT].decode("utf-8","surrogateescape")
 v=original[VIEW].decode("utf-8","surrogateescape")
 a=c.find(START);b=c.find(END,a)
 if a<0 or b<0 or c.count(START)!=1 or c.count(END)!=1:stop("CONTROLLER_ANCHOR_MISSING")
 c=c[:a]+REPLACE+c[b+len(END):]
 sig="public function vendorDatabase(Request $request): View"
 if c.count(sig)!=1:stop("METHOD_SIGNATURE_NOT_FOUND")
 c=c.replace(sig,"public function vendorDatabase(Request $request, \\App\\Services\\VendorService $vendorService): View",1)
 old1='<div><span>Purchase line totals*</span><b>₹{{ number_format((float)($purchaseTotals[$v->id] ?? 0),2) }}</b></div><div><span>Vendor payments*</span><b>₹{{ number_format((float)($paymentTotals[$v->id] ?? 0),2) }}</b></div>'
 new1='<div><span>Purchases (Ledger)</span><b>₹{{ number_format((float)($vendorSummaries[$v->id][\\'purchases\\'] ?? 0),2) }}</b></div><div><span>Payments (Ledger)</span><b>₹{{ number_format((float)($vendorSummaries[$v->id][\\'payments\\'] ?? 0),2) }}</b></div><div><span>Outstanding Payable</span><b>₹{{ number_format((float)($vendorSummaries[$v->id][\\'outstanding\\'] ?? 0),2) }}</b></div>'
 if v.count(old1)!=1:stop("VENDOR_CARD_AMOUNT_ANCHOR")
 v=v.replace(old1,new1,1)
 old2='*Values are purchase/payment table aggregates, not verified final outstanding. OSL purchase corrections must reconcile supplier invoice and customer Job Card billing.'
 new2='FIX132: Amounts come directly from the same authoritative Vendor Ledger (including active status, posted purchase round-off and unified Payment Vouchers).'
 if v.count(old2)!=1:stop("VENDOR_FOOTNOTE_ANCHOR")
 v=v.replace(old2,new2,1)
 result={CONT:c.encode("utf-8","surrogateescape"),VIEW:v.encode("utf-8","surrogateescape")}
 if c.count(MARKER)!=1 or v.count("FIX132: Amounts")!=1:stop("PATCH_VALIDATION")
 print("FIX132_LEDGER_AUTHORITY_PER_VENDOR=YES")
 print("FIX132_DB_CHANGES=NONE")
 if mode=="check":
  print("FIX132_READ_ONLY_CHECK_PASS");return
 with tempfile.TemporaryDirectory(prefix="fix132_prepare_") as d:
  temp=Path(d)
  tc=temp/"controller.php";tc.write_bytes(result[CONT])
  tv=temp/"vendor-database.blade.php";tv.write_bytes(result[VIEW])
  run(["php","-l",str(tc)],root)
  compile_blade(root,tv)
  backupdir=root/"storage/app/am-backups";backupdir.mkdir(parents=True,exist_ok=True)
  stamp=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
  for p in original:
   dest=backupdir/("FIX132_"+p.name+"_"+stamp+".bak")
   with os.fdopen(os.open(dest,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as f:
    f.write(original[p]);f.flush();os.fsync(f.fileno())
   if sha(dest.read_bytes())!=sha(original[p]):stop("BACKUP_VERIFY_FAIL")
   print("FIX132_BACKUP_VERIFIED="+str(dest))
  changed=[]
  try:
   for p in (CONT,VIEW):
    target=root/p
    tmp=target.with_name("."+target.name+".fix132.tmp")
    if tmp.exists():raise RuntimeError("STALE_TMP")
    tmp.write_bytes(result[p])
    os.chmod(tmp,target.stat().st_mode&0o777)
    os.replace(tmp,target);changed.append(p)
   for p in result:
    if sha((root/p).read_bytes())!=sha(result[p]):raise RuntimeError("WRITE_HASH_MISMATCH")
   run(["php","artisan","view:clear"],root)
   # Read-only comparison: authoritative Vendor #5 ledger summary.
   code="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$s=$app->make(\\App\\Services\\VendorService::class)->ledger(5)['summary'];if(abs($s['purchases']-1830.0)>0.01||abs($s['payments']-1830.0)>0.01||abs($s['outstanding'])>0.01)exit(4);echo 'FIX132_VENDOR_5_MATCH=PASS';"
   print(run(["php","-r",code],root))
   print("FIX132_STAGING_APPLIED=YES")
   print("FIX132_CONTROLLER_SHA="+sha(result[CONT]))
   print("FIX132_VIEW_SHA="+sha(result[VIEW]))
   print("FIX132_NO_FINANCIAL_WRITES=YES")
  except BaseException as e:
   print("FIX132_ROLLBACK_REASON="+str(e))
   for p in changed:(root/p).write_bytes(original[p])
   subprocess.run(["php","artisan","view:clear"],cwd=root,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
   stop("ROLLED_BACK_UNSAFE_RESULT")
if __name__=="__main__":main()
