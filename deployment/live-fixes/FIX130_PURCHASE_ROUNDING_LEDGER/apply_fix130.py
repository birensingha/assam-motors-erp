#!/usr/bin/env python3
"""FIX130: one vendor ledger row per Purchase Bill, show Auto Round Off on Purchase Entry.

Presentation-only change. Ledger purchase lines and ROUND_OFF corrections remain
in the database and calculations; only VendorService::ledger() aggregates their
display by batch. No DB, invoice, payment or tax calculation writes.
"""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,os,subprocess,sys,tempfile

ROOT = Path(sys.argv[2]).resolve() if len(sys.argv)==3 else None
MODE = sys.argv[1] if len(sys.argv)==3 else ""
SOURCE=Path("app/Services/VendorService.php")
VIEW=Path("resources/views/purchases/create.blade.php")
EXPECTED={
 SOURCE:"07fa038fd0e8669cbfe85df5e2369ab511866b7a9bd8ddf228e13a57374be38d",
 VIEW:"7dc30d97c9922fe788b9a8258084a8d5a39226b6acee9e3864e55f688f712d6f"
}

def sha(raw):return hashlib.sha256(raw).hexdigest()
def fail(message):
 print("FIX130_ABORT="+message,flush=True);sys.exit(2)
def run(args,root):
 p=subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=50)
 if p.returncode:
  print("FIX130_DIAGNOSTIC="+(p.stdout+"\n"+p.stderr)[-600:],flush=True)
  fail("COMMAND_FAILED")
 return p.stdout.strip()

NEW_PURCHASE_LOOP=r'''        // FIX130_LEDGER_ONE_ROW_PER_BILL: group by purchase batch for register DISPLAY.
        // Physical purchase lines and all audit/round-off corrections remain untouched.
        $purchaseGroups = $purchases->groupBy(static function ($r) {
            $batch=trim((string)($r->purchase_batch_no ?? ''));
            return $batch!=='' && strtoupper((string)$r->purchase_type)==='PART'
                ? 'B:'.$batch : 'P:'.$r->id;
        });
        $deltasByBill=[];
        foreach($batchDeltas as $adj) {
            $deltasByBill[(string)$adj['batch_no']]=$adj;
        }
        $absorbedRoundOffBills=[];
        foreach ($purchaseGroups as $key=>$group) {
            $r=$group->first();
            $batch=str_starts_with((string)$key,'B:') ? substr((string)$key,2) : '';
            $delta=0.0;
            if ($batch!=='' && isset($deltasByBill[$batch])) {
                $adj=$deltasByBill[$batch];
                // Respect original ledger date filters: don't pull out-of-window adjustments in.
                if (($from==='' || $adj['date'] >= $from) && ($to==='' || $adj['date'] <= $to)) {
                    $delta=(float)$adj['delta'];
                    $absorbedRoundOffBills[$batch]=true;
                }
            }
            $amt=round((float)$group->sum('total_amount')+$delta,2);
            $periodPurchases+=$amt;
            $jcs=$group->pluck('job_no')->filter()->unique()->values();
            $vehicles=$group->pluck('vehicle_reg_no')->filter()->unique()->values();
            $oneDescription=trim((string)($r->part_name ?: ($r->osl_name ?: $r->description)));
            $desc=$group->count()===1 ? $oneDescription
                : 'Purchase Bill · '.$group->count().' items';
            if (abs($delta)>=0.005) {
                $desc.=' · Round Off '.($delta>=0?'+':'-').'₹'.number_format(abs($delta),2);
            }
            $entries[]=[
                'sort_date'=>(string)$r->entry_date,'sort_type'=>1,'id'=>(int)$r->id,
                'date'=>(string)$r->entry_date,
                'entry_no'=>$batch!=='' ? $batch : $r->entry_no,
                'entry_type'=>'PURCHASE','payment_kind'=>'',
                'description'=>$desc,'invoice_no'=>$r->supplier_invoice,
                'job_no'=>$jcs->count()===1?(string)$jcs->first():($jcs->count()>1?'Multiple JC ('.$jcs->count().')':''),
                'vehicle_reg_no'=>$vehicles->count()===1?(string)$vehicles->first():($vehicles->count()>1?'Multiple Vehicles ('.$vehicles->count().')':''),
                'payment_mode'=>'','reference_no'=>'','debit'=>0.0,'credit'=>$amt
            ];
        }
'''
NEW_DELTA_LOOP=r'''        // Unmatched historical corrections stay visible; never omit money from a period.
        foreach($batchDeltas as $adj) {
            if($from!=='' && $adj['date']<$from)continue;
            if($to!=='' && $adj['date']>$to)continue;
            if(isset($absorbedRoundOffBills[(string)$adj['batch_no']]))continue;
            $delta=(float)$adj['delta'];$periodPurchases+=$delta;
            $entries[]=[
                'sort_date'=>$adj['date'],'sort_type'=>1,'id'=>1000000000+(int)$adj['id'],
                'date'=>$adj['date'],'entry_no'=>$adj['batch_no'],'entry_type'=>'ROUND_OFF','payment_kind'=>'',
                'description'=>'Purchase Bill Batch Round-Off Adjustment',
                'invoice_no'=>$adj['invoice'],'job_no'=>'','vehicle_reg_no'=>'','payment_mode'=>'','reference_no'=>'',
                'debit'=>max(0,-$delta),'credit'=>max(0,$delta)
            ];
        }
'''
def mutate_service(src):
 select="'p.id','p.purchase_no as entry_no','p.purchase_date as entry_date','p.purchase_type'"
 if src.count(select)!=1:fail("VENDOR_SELECT_ANCHOR")
 src=src.replace(select,"'p.id','p.purchase_no as entry_no','p.purchase_batch_no','p.purchase_date as entry_date','p.purchase_type'",1)
 a="        foreach ($purchases as $r) {"
 b="        foreach ($expenses as $r) {"
 if src.count(a)!=1 or src.count(b)!=1:fail("LEDGER_PURCHASE_LOOP_ANCHOR")
 st=src.index(a);en=src.index(b,st)
 old=src[st:en]
 if "periodPurchases" not in old or "entry_type'=>'PURCHASE'" not in old:fail("PURCHASE_LOOP_UNEXPECTED")
 src=src[:st]+NEW_PURCHASE_LOOP+src[en:]
 a="        foreach($batchDeltas as $adj) {"
 # first occurrence at method line 105 is inline 'if' different; closest matching full line for loop near end
 if src.count(a)!=2:fail("ROUND_OFF_LOOP_ANCHOR_COUNT_"+str(src.count(a)))
 anchor_after_payments="        foreach ($unifiedPayments as $r) {"
 if src.count(anchor_after_payments)!=1:fail("UNIFIED_PAYMENT_ANCHOR")
 st=src.index(a,src.index(anchor_after_payments))
 en=src.index("        usort($entries,",st)
 old=src[st:en]
 if "ROUND_OFF" not in old or "periodPurchases" not in old:fail("ROUND_OFF_LOOP_UNEXPECTED")
 src=src[:st]+NEW_DELTA_LOOP+src[en:]
 if src.count("FIX130_LEDGER_ONE_ROW_PER_BILL")!=1:fail("LEDGER_PATCH_MARKER")
 if src.count("private function purchaseBatchDeltas(")!=1:fail("ROUND_OFF_CALC_UNEXPECTED_CHANGE")
 return src

def mutate_view(src):
 old='''            <button type="button"
                    class="btn small roundoff-btn"
                    id="apply-roundoff">
                ↔ Round Off
            </button>'''
 new='''            {{-- FIX130_AUTO_ROUND_OFF_PRE_SAVE: always shown while editing, no manual apply click. --}}
            <span class="am130-roundoff-label" title="Automatically calculated before saving using nearest rupee">
                ✓ Auto Round Off (nearest ₹1)
            </span>'''
 if src.count(old)!=1:fail("CREATE_ROUND_OFF_BUTTON_ANCHOR")
 src=src.replace(old,new,1)
 if 'id="preview-roundoff"' not in src or 'id="preview-grand"' not in src:fail("MISSING_LIVE_TOTALS")
 # No change to submitted fields, only the preview label.
 style='''.am130-roundoff-label{
 display:inline-flex;align-items:center;min-height:31px;
 background:#e7f4ed;color:#116841;padding:5px 10px;border:1px solid #a8d3bb;
 border-radius:7px;font-size:11px;font-weight:800;white-space:normal;
}'''
 anchor='.roundoff-row{'
 if src.count(anchor)!=1:fail("ROUND_OFF_CSS_ANCHOR")
 src=src.replace(anchor,style+"\n"+anchor,1)
 if src.count("FIX130_AUTO_ROUND_OFF_PRE_SAVE")!=1:fail("VIEW_MARKER")
 return src

def php_lint_view(root,p):
 with tempfile.TemporaryDirectory(prefix="fix130_lint_") as d:
  compiled=Path(d)/"purchase_create_compiled.php"
  php="require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();file_put_contents($argv[2],\\Illuminate\\Support\\Facades\\Blade::compileString(file_get_contents($argv[1])));"
  run(["php","-r",php,str(p),str(compiled)],root)
  run(["php","-l",str(compiled)],root)
 print("FIX130_BLADE_COMPILE_PHP_LINT=PASS")

def main():
 if ROOT is None or MODE not in ("check","apply"):fail("USAGE_CHECK_OR_APPLY_AND_ROOT")
 if not (ROOT/"artisan").is_file() or not (ROOT/"vendor/autoload.php").is_file():fail("NOT_LARAVEL_ROOT")
 originals={}
 for path,expected in EXPECTED.items():
  file=ROOT/path
  if not file.is_file():fail("SOURCE_MISSING="+str(path))
  raw=file.read_bytes()
  print("FIX130_LIVE_SHA_"+path.name+"="+sha(raw))
  if sha(raw)!=expected:fail("SOURCE_CHANGED_REFUSE_"+path.name)
  originals[path]=raw
 # Preserve non-UTF8 bytes if any, as with other legacy Blade sources.
 service=mutate_service(originals[SOURCE].decode("utf-8","surrogateescape"))
 view=mutate_view(originals[VIEW].decode("utf-8","surrogateescape"))
 updated={
  SOURCE:service.encode("utf-8","surrogateescape"),
  VIEW:view.encode("utf-8","surrogateescape")
 }
 print("FIX130_LEDGER_PURCHASE_ROW_GROUPING=YES")
 print("FIX130_AUTO_ROUND_OFF_BEFORE_SAVE=YES")
 print("FIX130_DB_STOCK_PAYMENTS_UNCHANGED=YES")
 if MODE=="check":
  print("FIX130_READ_ONLY_PREFLIGHT_PASS")
  return
 with tempfile.TemporaryDirectory(prefix="fix130_apply_") as d:
  test=Path(d)
  vtmp=test/"create.blade.php";vtmp.write_bytes(updated[VIEW])
  stmp=test/"VendorService.php";stmp.write_bytes(updated[SOURCE])
  run(["php","-l",str(stmp)],ROOT)
  php_lint_view(ROOT,vtmp)
  backups=ROOT/"storage/app/am-backups"
  backups.mkdir(parents=True,exist_ok=True)
  date=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
  backupfiles=[]
  for source,raw in originals.items():
   back=backups/("FIX130_"+source.name+"_"+date+".bak")
   with os.fdopen(os.open(back,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as fd:
    fd.write(raw);fd.flush();os.fsync(fd.fileno())
   if sha(back.read_bytes())!=sha(raw):fail("BACKUP_VERIFY_FAIL")
   backupfiles.append(back)
  print("FIX130_BACKUPS_VERIFIED=YES")
  changed=[]
  try:
   for path in (SOURCE,VIEW):
    target=ROOT/path
    tmp=target.with_name("."+target.name+".fix130.tmp")
    if tmp.exists():raise RuntimeError("TEMP_ALREADY_EXISTS")
    tmp.write_bytes(updated[path])
    os.chmod(tmp,target.stat().st_mode&0o777)
    os.replace(tmp,target)
    changed.append(path)
   for path,expected in updated.items():
    if sha((ROOT/path).read_bytes())!=sha(expected):raise RuntimeError("POST_WRITE_HASH_MISMATCH")
   run(["php","artisan","view:clear"],ROOT)
   # End-to-end read-only ledger check against the exact reported purchase bill.
   verifyphp=r"""require 'vendor/autoload.php';
   $app=require 'bootstrap/app.php';
   $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
   $svc=$app->make(\App\Services\VendorService::class);
   $data=$svc->ledger(5,'2026-10-23','2026-10-26');
   $hits=array_values(array_filter($data['entries'],static fn($r)=>
       ($r['entry_no']??'')==='PB-261025124019-467'));
   if(count($hits)!==1) {fwrite(STDERR,'PURCHASE_ROW_COUNT='.count($hits));exit(21);}
   $x=$hits[0];
   if(($x['entry_type']??'')!=='PURCHASE' ||
      abs((float)($x['credit']??0)-560.00)>0.011 ||
      abs((float)($x['debit']??0))>0.011) {
      fwrite(STDERR,'PURCHASE_AMOUNT_OR_TYPE_MISMATCH');exit(22);
   }
   if((float)($x['balance']??0)<-1000000000)exit(23);
   echo 'FIX130_SAMPLE_VENDOR_LEDGER_ONE_ROW_560=PASS'.PHP_EOL;"""
   print(run(["php","-r",verifyphp],ROOT))
   print("FIX130_STAGING_APPLIED=YES")
   print("FIX130_NEW_SERVICE_SHA="+sha(updated[SOURCE]))
   print("FIX130_NEW_VIEW_SHA="+sha(updated[VIEW]))
   print("FIX130_NO_DATABASE_WRITES=YES")
   for back in backupfiles:print("FIX130_BACKUP="+str(back))
  except BaseException as err:
   print("FIX130_ROLLBACK_REASON="+str(err))
   for path in changed:(ROOT/path).write_bytes(originals[path])
   subprocess.run(["php","artisan","view:clear"],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
   fail("DEPLOY_FAILED_SOURCE_ROLLBACK_DONE")

if __name__=="__main__":
 main()
