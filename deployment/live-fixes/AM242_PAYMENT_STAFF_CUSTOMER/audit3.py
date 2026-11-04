#!/usr/bin/env python3
"""AM242 customer payout identity/ledger and UI audit. No data writes."""
from pathlib import Path
import re,sys,subprocess,tempfile
root=Path(sys.argv[1]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM242_ROOT')
targets={
 'resources/views/payments/create.blade.php':[(275,393),(402,464),(920,1020)],
 'app/Services/ExpensePaymentService.php':[(1,42),(375,424)],
 'resources/views/expenses/create.blade.php':[(1,78)],
 'app/Http/Controllers/Web/AdminMoneyReceiptController.php':[(1,170)],
 'app/Http/Controllers/Web/AdminStaffFinanceLedgerController.php':[(1,110)],
 'routes/web.php':[(175,207),(284,314)],
}
for rel,ranges in targets.items():
 p=root/rel
 if not p.is_file():continue
 l=p.read_text('utf8','replace').splitlines()
 print('AM242_DETAIL_FILE',rel)
 for start,end in ranges:
  for i in range(start-1,min(end,len(l))):
   s=l[i]
   if re.search(r'password|secret|private.key|api[_-]?key|env\(',s,re.I):s='[REDACTED]'
   print('AM242_DETAIL',rel,i+1,s[:600])
# Inspect code references without outputting customer records.
matcher=re.compile(r'am_customer_master|am_erp_customers|customer_ledger|customer_master',re.I)
for d in ('app/Http/Controllers/Web','app/Services','app/Models'):
 for p in (root/d).rglob('*.php'):
  if p.stat().st_size>200000:continue
  src=p.read_text('utf8','replace').splitlines()
  hits=[i for i,s in enumerate(src) if matcher.search(s)]
  if hits and len(hits)<150:
   rel=p.relative_to(root).as_posix()
   print('AM242_CUSTOMER_REF_FILE',rel,','.join(str(i+1) for i in hits[:70]))
print('AM242_CUSTOMER_SOURCE_END',flush=True)
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
foreach(['am_customer_master','am_customer_sources_v2','customer_master','am_erp_customers','customer_ledger','workshop_payment_vouchers','am_erp_customer_sources'] as $t){
 if(!Schema::hasTable($t))continue;
 $cols=Schema::getColumnListing($t);
 echo 'AM242_MAPPING_SCHEMA '.$t.' '.implode(',',$cols)."\n";
}
$counts=DB::table('am_customer_sources_v2')->select('source_type')->selectRaw('COUNT(*) AS n')->groupBy('source_type')->get();
foreach($counts as $r)echo 'AM242_SOURCE_TYPE '.($r->source_type??'null').' '.$r->n."\n";
$ledger=DB::table('customer_ledger')->selectRaw('COUNT(*) AS total, COUNT(DISTINCT customer_id) AS ids')->first();
echo 'AM242_LEDGER_COUNTS '.json_encode($ledger)."\n";
echo 'AM242_CUSTOMER_DB_READONLY_COMPLETE'."\n";
"""
with tempfile.TemporaryDirectory(prefix='am242customer_') as d:
 f=Path(d)/'mapping.php';f.write_text(php)
 p=subprocess.run(['php',str(f),str(root)],cwd=root,capture_output=True,text=True,timeout=40)
 print(p.stdout[:12000],flush=True);print('AM242_MAPPING_EXIT',p.returncode,p.stderr[-230:],flush=True)
print('AM242_DETAIL_FINISHED')
