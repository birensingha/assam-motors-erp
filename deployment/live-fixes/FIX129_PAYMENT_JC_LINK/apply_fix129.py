#!/usr/bin/env python3
"""FIX129 — Payment Voucher Purchase Bill JC/Vehicle metadata display only.

Reads verified live STAGING service source; adds group-safe JC metadata to
payableSources() while preserving payable bill total and payment accounting.
Does NOT update Purchase, Payment Voucher, Job Card or any DB records.
Usage: python3 apply_fix129.py check|apply /absolute/staging/root
"""
from pathlib import Path
from datetime import datetime,timezone
import hashlib,os,subprocess,sys,tempfile

SOURCE=Path("app/Services/ExpensePaymentService.php")
EXPECTED="2f9e37707b9f48a1097a1a7a68fec8c621eb0694c1c176f00822d88fcf0eb7d7"
MARKER="FIX129_PURCHASE_BILL_JC_VEHICLE"
BILL="PB-261025124019-467"

OLD_BASE="$purchaseBase=DB::table('workshop_purchases as p')->leftJoin('workshop_vendors as v','v.id','=','p.vendor_id')"
NEW_BASE=OLD_BASE+"\n            // "+MARKER+": read JC metadata, not another financial source.\n            ->leftJoin('job_cards as jc','jc.id','=','p.job_card_id')"

OLD_SELECT=('->selectRaw("COALESCE(NULLIF(p.purchase_batch_no,\'\'),p.purchase_no) as src_ref, '
            'p.purchase_date, p.supplier_invoice, p.vendor_id, v.vendor_name, p.total_amount");')
NEW_SELECT=('->selectRaw("COALESCE(NULLIF(p.purchase_batch_no,\'\'),p.purchase_no) as src_ref, '
            'p.purchase_date, p.supplier_invoice, p.vendor_id, v.vendor_name, p.total_amount, '
            'p.job_card_id, jc.job_no, jc.vehicle_reg_no");')

OLD_GROUP=('->selectRaw("src_ref, MIN(purchase_date) as d, MAX(supplier_invoice) as invoice_no, '
           'MAX(vendor_id) as vendor_id, MAX(vendor_name) as vendor_name, SUM(total_amount) as total")')
NEW_GROUP=('->selectRaw("src_ref, MIN(purchase_date) as d, MAX(supplier_invoice) as invoice_no, '
           'MAX(vendor_id) as vendor_id, MAX(vendor_name) as vendor_name, SUM(total_amount) as total, '
           'MIN(job_card_id) as min_job_id, MAX(job_card_id) as max_job_id, '
           'COUNT(job_card_id) as assigned_lines, COUNT(*) as line_count, '
           'MAX(job_no) as job_no, MAX(vehicle_reg_no) as vehicle_reg_no")')

OLD_OUTPUT="'outstanding'=>$out,'payee'=>$p->vendor_name,'job_no'=>'','vehicle'=>''"
NEW_OUTPUT=("'outstanding'=>$out,'payee'=>$p->vendor_name,"
  "'job_no'=>((int)$p->assigned_lines===(int)$p->line_count && "
  "(int)$p->min_job_id>0 && (int)$p->min_job_id===(int)$p->max_job_id "
  "? (string)$p->job_no : ''),"
  "'vehicle'=>((int)$p->assigned_lines===(int)$p->line_count && "
  "(int)$p->min_job_id>0 && (int)$p->min_job_id===(int)$p->max_job_id "
  "? (string)$p->vehicle_reg_no : '')")

def sha(b):return hashlib.sha256(b).hexdigest()
def abort(x):
 print("FIX129_ABORT="+x,flush=True)
 sys.exit(2)
def exact_replace(s,old,new,label):
 n=s.count(old)
 if n!=1:abort("EXPECTED_"+label+"_COUNT="+str(n))
 return s.replace(old,new,1)
def patched(raw):
 s=raw.decode("utf-8","surrogateescape")
 s=exact_replace(s,OLD_BASE,NEW_BASE,"PURCHASE_BASE")
 s=exact_replace(s,OLD_SELECT,NEW_SELECT,"PURCHASE_SELECT")
 s=exact_replace(s,OLD_GROUP,NEW_GROUP,"PURCHASE_GROUP")
 s=exact_replace(s,OLD_OUTPUT,NEW_OUTPUT,"PURCHASE_DISPLAY_FIELDS")
 if s.count(MARKER)!=1:abort("POST_PATCH_MARKER_MISMATCH")
 return s.encode("utf-8","surrogateescape")
def cmd(args,root):
 p=subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=65)
 if p.returncode:
  print("FIX129_OUTPUT="+(p.stdout+"\n"+p.stderr)[-900:],flush=True)
  abort("COMMAND_FAILED="+str(args[:3]))
 return p.stdout.strip()
def source_state(root):
 php=(
  "require 'vendor/autoload.php';"
  "$app=require 'bootstrap/app.php';"
  "$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();"
  "$key='PURCHASE_INVOICE::"+BILL+"';"
  "$a=collect(app(\\App\\Services\\ExpensePaymentService::class)->payableSources())->firstWhere('key',$key);"
  "if(!$a){fwrite(STDERR,'BILL_NOT_IN_OUTSTANDING_LIST');exit(11);}"
  "$jid=\\Illuminate\\Support\\Facades\\DB::table('workshop_purchases')->where('purchase_batch_no','"+BILL+"')->value('job_card_id');"
  "$jc=$jid?\\Illuminate\\Support\\Facades\\DB::table('job_cards')->where('id',$jid)->first():null;"
  "echo json_encode(['key'=>$a['key'],'outstanding'=>$a['outstanding'],'payee'=>$a['payee'],"
  "'job_no'=>$a['job_no'],'vehicle'=>$a['vehicle'],'expected_job'=>$jc->job_no??null,"
  "'expected_vehicle'=>$jc->vehicle_reg_no??null],JSON_UNESCAPED_UNICODE);"
 )
 import json
 try:return json.loads(cmd(["php","-r",php],root))
 except ValueError:abort("READ_ONLY_SOURCE_JSON_INVALID")
def main():
 if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):abort("USAGE")
 mode,root=sys.argv[1],Path(sys.argv[2]).resolve()
 if not (root/"artisan").is_file() or not (root/"vendor/autoload.php").is_file():abort("NOT_LARAVEL_ROOT")
 path=root/SOURCE
 if not path.is_file():abort("SERVICE_MISSING")
 raw=path.read_bytes()
 print("FIX129_MODE="+mode)
 print("FIX129_SOURCE_SHA256="+sha(raw))
 if MARKER.encode() in raw:
  print("FIX129_ALREADY_APPLIED=YES");return
 if sha(raw)!=EXPECTED:abort("UNKNOWN_SOURCE_REVISION_REFUSE_CHANGE")
 new=patched(raw)
 print("FIX129_PREVIEW_SOURCE_SHA256="+sha(new))
 if mode=='check':
  print("FIX129_CHECK_PASS_NO_WRITES=YES")
  print("FIX129_PURCHASE_AND_PAYMENT_DATA_UNCHANGED=YES")
  return
 before=source_state(root)
 if not before.get("expected_job") or not before.get("expected_vehicle"):abort("BILL_JC_ALLOCATION_UNVERIFIED")
 print("FIX129_BEFORE_DISPLAY_JC="+str(before['job_no'] or 'EMPTY'))
 print("FIX129_BEFORE_DISPLAY_VEHICLE="+str(before['vehicle'] or 'EMPTY'))
 with tempfile.TemporaryDirectory(prefix="fix129_stage_") as d:
  staged=Path(d)/"ExpensePaymentService.php"
  staged.write_bytes(new)
  cmd(["php","-l",str(staged)],root)
  print("FIX129_PHP_LINT=PASS")
  folder=root/"storage/app/am-backups";folder.mkdir(parents=True,exist_ok=True)
  tag=datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
  backup=folder/("FIX129_ExpensePaymentService_"+tag+".php.bak")
  with os.fdopen(os.open(backup,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o600),"wb") as w:
   w.write(raw);w.flush();os.fsync(w.fileno())
  if sha(backup.read_bytes())!=EXPECTED:abort("BACKUP_SHA_FAILED")
  print("FIX129_BACKUP_VERIFIED="+str(backup))
  if sha(path.read_bytes())!=EXPECTED:abort("CONCURRENT_EDIT_REFUSE_CHANGE")
  temp=path.with_name("."+path.name+".fix129.tmp")
  if temp.exists():abort("TEMP_ALREADY_EXISTS")
  temp.write_bytes(new)
  os.chmod(temp,path.stat().st_mode&0o777)
  try:
   os.replace(temp,path)
   after=source_state(root)
   if (str(after["key"])!=str(before["key"])
       or abs(float(after["outstanding"])-float(before["outstanding"]))>0.001
       or str(after["payee"])!=str(before["payee"])
       or str(after["job_no"])!=str(after["expected_job"])
       or str(after["vehicle"])!=str(after["expected_vehicle"])):
    raise RuntimeError("POST_PATCH_DISPLAY_OR_AMOUNT_VERIFY_FAILED")
   print("FIX129_SELECTED_BILL="+BILL)
   print("FIX129_JC_NO="+str(after["job_no"]))
   print("FIX129_VEHICLE="+str(after["vehicle"]))
   print("FIX129_OUTSTANDING_PRESERVED="+str(after["outstanding"]))
   print("FIX129_STAGING_DISPLAY_PATCH_APPLIED=YES")
   print("FIX129_NO_DB_WRITES=YES")
  except BaseException as e:
   print("FIX129_VERIFY_ROLLBACK_REASON="+str(e))
   path.write_bytes(raw)
   abort("ROLLED_BACK_TO_ORIGINAL_SOURCE")
  finally:
   temp.unlink(missing_ok=True)
if __name__=='__main__':main()
