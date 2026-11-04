#!/usr/bin/env python3
"""AM240: read-only source/schema inspection for editing OSL purchases."""
from pathlib import Path
import sys,re,subprocess,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM240_NOT_STAGING')
targets={
'app/Services/PurchaseService.php':[(55,135),(161,184),(320,350),(785,1040),(1095,1160)],
'app/Http/Controllers/Web/AdminPurchasePageController.php':[(1,140),(175,310)],
'resources/views/purchases/osl-create.blade.php':[(1,430)],
'resources/views/purchases/edit.blade.php':[(1,240)],
'resources/views/purchases/show.blade.php':[(1,240)],
'resources/views/purchases/index.blade.php':[(45,96)],
}
for rel,ranges in targets.items():
 p=root/rel
 if not p.is_file():
  print('AM240_FILE_MISSING',rel)
  continue
 lines=p.read_text('utf8','replace').splitlines()
 print('AM240_FILE',rel,'LINES',len(lines))
 for lo,hi in ranges:
  for i in range(lo-1,min(len(lines),hi)):
   s=lines[i]
   if re.search(r'(password|secret|private.key|api[_-]?key|env\()',s,re.I):s='[REDACTED]'
   print('AM240_SRC',rel,i+1,s[:450])
print('AM240_SOURCE_READ_ONLY_DONE')
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;
$tables=DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name');
foreach($tables as $tbl){
 $name=(string)$tbl->t;
 if(!preg_match('/vendor|payment|voucher|osl|job_card.*osl|purchase|ledger|bill|liabilit|account/', $name))continue;
 echo 'AM240_TABLE '.$name.' '.implode(',',Schema::getColumnListing($name))."\\n";
}
echo 'AM240_SCHEMA_READ_ONLY_DONE'."\\n";
"""
with tempfile.TemporaryDirectory(prefix='am240_audit_') as d:
 f=Path(d)/'audit.php';f.write_text(php)
 # use simpler separate PHP static to avoid parse errors from string quoting.
 ret=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=55)
 print(ret.stdout[:16000])
 print('AM240_PHP_EXIT',ret.returncode,ret.stderr[-350:])
print('AM240_READ_ONLY_AUDIT_FINISHED')
