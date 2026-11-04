#!/usr/bin/env python3
"""AM240 part 2 — focused OSL bill, payment, and schema audit. READ ONLY."""
from pathlib import Path
import sys,re,subprocess,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM240_NOT_STAGING')
targets={
'app/Services/PurchaseService.php':[(1,135),(161,181),(322,500),(1032,1084),(1125,1170)],
'resources/views/purchases/osl-create.blade.php':[(135,242)],
'resources/views/purchases/edit.blade.php':[(160,265)],
'resources/views/purchases/index.blade.php':[(110,150)],
'resources/views/purchases/show.blade.php':[(1,38)],
'app/Models/WorkshopPurchaseBatch.php':[(1,150)],
'app/Models/WorkshopPurchase.php':[(1,140)]
}
for rel,ranges in targets.items():
 p=root/rel
 if not p.is_file():print('AM240_MISSING',rel);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM240_DETAIL_FILE',rel,'LINES',len(lines))
 for lo,hi in ranges:
  for i in range(lo-1,min(len(lines),hi)):
   s=lines[i]
   if re.search(r'(password|secret|private.key|api[_-]?key|env\()',s,re.I):s='[REDACTED]'
   print('AM240_DETAIL',rel,i+1,s[:340])
print('AM240_DETAILS_READ_ONLY_DONE',flush=True)
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$tables=DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name');
foreach($tables as $tbl){
 $name=(string)$tbl->t;
 if(!preg_match('/vendor|payment|voucher|osl|job_card.*osl|purchase|ledger|bill|liabilit|account/', $name))continue;
 echo 'AM240_TABLE '.$name.' '.implode(',',Schema::getColumnListing($name))."\n";
}
echo 'AM240_SCHEMA_READ_ONLY_DONE'."\n";
"""
with tempfile.TemporaryDirectory(prefix='am240_audit_') as d:
 f=Path(d)/'audit.php';f.write_text(php)
 ret=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=55)
 print(ret.stdout[:18000],flush=True)
 print('AM240_SCHEMA_EXIT',ret.returncode,ret.stderr[-380:],flush=True)
print('AM240_DETAIL_AUDIT_FINISHED')
