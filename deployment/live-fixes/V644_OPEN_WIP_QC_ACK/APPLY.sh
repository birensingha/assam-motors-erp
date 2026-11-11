#!/usr/bin/env bash
# V644 - only adjust WIP request validation; preserve QC retry audit and all JC/ROT state.
set -euo pipefail
ROOT="$1"
test "$ROOT" = "/home/u956497103/domains/assammotors.com/assam-erp-staging"
test -f "$ROOT/artisan" && test -f "$ROOT/vendor/autoload.php"
export V644_LARAVEL_ROOT="$ROOT"
python3 - <<'PY'
import os, re, subprocess
from pathlib import Path
from datetime import datetime,timezone
root=Path(os.environ["V644_LARAVEL_ROOT"])
target=root/"app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php"
original=target.read_bytes()
marker=b"public function markWip("
if original.count(marker)!=1:
 raise RuntimeError("V644 preflight: markWip method not unique")
start=original.index(marker)
next_method=re.search(rb"\b(public|private|protected)\s+function\s+\w+\s*\(",original[start+len(marker):])
end=(start+len(marker)+next_method.start()) if next_method else len(original)
before,method,after=original[:start],original[start:end],original[end:]
old=b"'acknowledge_qc_repeat'=>['nullable','accepted']"
new=b"'acknowledge_qc_repeat'=>['sometimes','boolean']"
if old not in method and new in method:
 print("V644_QC_ACK_FIELD_ALREADY_FIXED=YES")
else:
 if method.count(old)!=1 or method.count(new)!=0:
  raise RuntimeError("V644 expected original QC checkbox validator absent/ambiguous")
 # Still require explicit QC repeat acknowledgment for COMPLETED/rework.
 if b"if($status==='completed')" not in method or b"empty($data['acknowledge_qc_repeat'])" not in method:
  raise RuntimeError("V644 QC rework consent guard missing; refusing patch")
 method=method.replace(old,new,1)
modified=before+method+after
if modified==original:
 print("V644_QC_ACK_FIX=ALREADY_PRESENT")
else:
 preflight=subprocess.run(["php","-l"],input=modified,capture_output=True)
 if preflight.returncode:
  raise RuntimeError("V644 PHP syntax preflight failed: "+preflight.stderr.decode("utf-8","replace"))
 backup=root.parent/("BACKUP_V644_OPEN_WIP_ACK_"+datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ"))
 backup.mkdir(mode=0o700)
 saved=backup/"AdminJobCardLifecyclePageController.php"
 saved.write_bytes(original);saved.chmod(0o600)
 temporary=target.with_name("."+target.name+".v644tmp")
 try:
  temporary.write_bytes(modified);temporary.chmod(target.stat().st_mode & 0o777)
  os.replace(temporary,target)
  lint=subprocess.run(["php","-l",str(target)],capture_output=True,text=True)
  if lint.returncode:
   raise RuntimeError("V644 deployed PHP lint failed: "+lint.stdout+lint.stderr)
 except BaseException:
  target.write_bytes(original)
  print("V644_SOURCE_ROLLBACK=RESTORED")
  raise
 print("V644_BACKUP_PATH="+str(saved))
 print("V644_QC_ACK_FIX=APPLIED")
print("V644_OPEN_WIP_AND_QC_REWORK_VALIDATION=DEPLOYED")
PY
cd "$ROOT"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path=base_path('app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php');
$text=file_get_contents($path);
$start=strpos($text,'public function markWip(');
if($start===false)throw new RuntimeException('markWip absent');
$fragment=substr($text,$start,8000);
if(!str_contains($fragment,"'acknowledge_qc_repeat'=>['sometimes','boolean']") ||
   !str_contains($fragment,'if($status===') ||
   !str_contains($fragment,'empty($data['))
   throw new RuntimeException('WIP stage-specific consent validation not present');
$rules=['reason'=>['required','string','max:1000'],'acknowledge_qc_repeat'=>['sometimes','boolean']];
$open=\Illuminate\Support\Facades\Validator::make(['reason'=>'Work Released'], $rules);
if($open->fails())throw new RuntimeException('OPEN start WIP still rejects absent QC checkbox');
$rework=\Illuminate\Support\Facades\Validator::make(['reason'=>'Customer requested corrections before QC','acknowledge_qc_repeat'=>'1'],$rules);
if($rework->fails())throw new RuntimeException('QC rework confirmed checkbox rejected');
$bad=\Illuminate\Support\Facades\Validator::make(['reason'=>'QC correction','acknowledge_qc_repeat'=>'invalid'],$rules);
if(!$bad->fails())throw new RuntimeException('Invalid QC checkbox passed type check');
echo "V644_OPEN_START_WIP_WITHOUT_ACK=PASS\n";
echo "V644_COMPLETED_REWORK_ACK_GUARD=RETAINED\n";
echo "V644_QC_ACK_VALIDATION_CHECK=PASS\n";
$card=\Illuminate\Support\Facades\DB::table('job_cards')->where('job_no','JC-20261018-00037')->first();
echo "V644_JC37_READONLY_STATUS=".($card->status??'NOT_FOUND').PHP_EOL;
PHP
