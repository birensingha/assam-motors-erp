#!/usr/bin/env python3
"""AM171 READ-ONLY source + schema discovery for:
(1) Customer name changes -> existing Job Cards;
(2) Money Receipt correction;
(3) Payment Voucher correction.
DOES NOT WRITE TO DATABASE/APP. Avoids .env and customer financial rows.
Usage: python3 audit.py /path/to/laravel/root
"""
from pathlib import Path
import re, sys, hashlib, subprocess, tempfile

if len(sys.argv)!=2:
    raise SystemExit("Usage: python3 audit.py LARAVEL_STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if not (root/"artisan").is_file():
    raise SystemExit("AM171_STOP_NOT_LARAVEL_ROOT")
print("AM171_READ_ONLY_BEGIN")
print("ROOT_VALID yes")
SAFE=(
    "app/Http/Controllers/Web",
    "app/Services",
    "app/Models",
    "resources/views",
    "routes",
)
secret=re.compile(r'(?i)(password|secret|private.?key|bearer|access.?token|csrf|api.?key|authorization|(^|\s)env\()')
def safe_line(line):
    return "[sensitive code line suppressed]" if secret.search(line) else line[:245].rstrip()
def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()[:14]
def relatives(paths):
    return sorted(str(p.relative_to(root)) for p in paths if p.is_file())
def read(path):
    return path.read_text(encoding="utf8",errors="replace").splitlines()
def hits(lines,rx,max_out=30):
    found=[(i+1,safe_line(l)) for i,l in enumerate(lines) if rx.search(l)]
    for n,l in found[:max_out]:
        print(f"  L{n} {l}")
    if len(found)>max_out:print(f"  ... additional {len(found)-max_out} lines omitted")
def context(lines,rx,pre=2,post=5,max_windows=4):
    loc=[i for i,l in enumerate(lines) if rx.search(l)]
    for i in loc[:max_windows]:
        print(f"  -- CONTEXT L{i+1} --")
        for j in range(max(0,i-pre),min(len(lines),i+post+1)):
            print(f"  {j+1}: {safe_line(lines[j])}")
    if len(loc)>max_windows:print(f"  ... additional {len(loc)-max_windows} contexts omitted")
def valid_candidates(base,pattern):
    folder=root/base
    if not folder.is_dir():return []
    return sorted(p for p in folder.rglob(pattern) if p.is_file() and p.stat().st_size<900000)
def title(t):
    print("\n### "+t)
# 1) Routes: actual full route declaration snippets, no env.
title("ROUTES")
for route in valid_candidates("routes","*.php"):
    lines=read(route)
    rx=re.compile(r"customer|money.?receipt|payment|voucher|ledger|job.?cards",re.I)
    matches=[(i+1,l) for i,l in enumerate(lines) if rx.search(l)]
    if matches:
        print("FILE",route.relative_to(root),"SHA",sha(route),"MATCHES",len(matches))
        # Most pertinent route lines; prevent giant output.
        prio=[(i,l) for i,l in matches if re.search(r'money.?receipt|payment|voucher|ledger|customer',l,re.I)]
        for i,l in prio[:35]:print("  L",i,safe_line(l))
        if len(prio)>35:print("  ...",len(prio)-35,"more related route lines")
# 2) Controller/service map
title("CONTROLLERS_AND_SERVICES")
paths=valid_candidates("app/Http/Controllers/Web","*.php")+valid_candidates("app/Services","*.php")
k=re.compile(r"customer|money.?receipt|payment|voucher|ledger|jobcard|job.card|expense",re.I)
paths=[p for p in paths if k.search(p.name)]
for p in paths:
    lines=read(p)
    print("FILE",p.relative_to(root),"SHA",sha(p),"LINES",len(lines))
    rx=re.compile(r"\b(?:public|protected)\s+function\s+|^\s*public\s+static\s+function")
    methods=[(i+1,safe_line(l)) for i,l in enumerate(lines) if rx.search(l)]
    for i,l in methods[:27]: print("  METHOD",i,l)
    if len(methods)>27:print("  ...",len(methods)-27,"other methods")
    # Constrain excerpts to ONLY edit/update/correction and snapshot assignment logic.
    key=re.compile(r"function\s+(?:update|edit|save|store|void|reverse|reallocate|correct|changeCustomer|sync|post)\b|customer_name\s*=>|customer_name\s*=|customerName|allocated_amount|reference_kind",re.I)
    occurrences=[i for i,l in enumerate(lines) if key.search(l)]
    for i in occurrences[:12]:
        print(f"  EVIDENCE L{i+1}: {safe_line(lines[i])}")
    if len(occurrences)>12:print("  ...",len(occurrences)-12,"other evidence hits")
# 3) Relevant models
title("MODELS")
models=valid_candidates("app/Models","*.php")
for p in models:
    if not k.search(p.name):continue
    lines=read(p)
    print("FILE",p.relative_to(root),"SHA",sha(p))
    hits(lines,re.compile(r"function\s+|\\$table|\\$fillable|customer_name|customer_id|invoice_id|payment_id|allocated_amount|receipt_no|voucher_no",re.I),14)
# 4) UI routes/links and edit forms
title("VIEWS")
candidate=valid_candidates("resources/views","*.blade.php")
for p in candidate:
    path=str(p.relative_to(root))
    if not re.search(r"customer|ledger|money.?receipt|voucher|payment|job.card",path,re.I):continue
    lines=read(p)
    print("FILE",path,"SHA",sha(p),"LINES",len(lines))
    rx=re.compile(r"money.?receipt|payment.?voucher|edit|correct|revers|void|customer_name|customer_id|route\(['\"]erp\\.",re.I)
    matches=[(i+1,safe_line(l)) for i,l in enumerate(lines) if rx.search(l)]
    for i,l in matches[:10]:print("  L",i,l)
    if len(matches)>10:print("  ...",len(matches)-10,"more UI hits")
# 5) Staging DB schema, only table names and columns, no customer records.
title("SCHEMA_COLUMNS_ONLY")
try:
    with tempfile.TemporaryDirectory(prefix="am171_") as td:
        runner=Path(td)/"schema.php"
        runner.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$names=['am_erp_customers','customer_master','job_cards','invoices','customer_payments',
'customer_payment_allocations','customer_ledger','workshop_payment_vouchers',
'workshop_vendor_payments','workshop_vendors','job_card_change_audits'];
foreach($names as $table){
 try{
  if(!\Illuminate\Support\Facades\Schema::hasTable($table)){ echo "NOT_FOUND $table\n"; continue; }
  echo "TABLE $table COLUMNS ".implode(',',\Illuminate\Support\Facades\Schema::getColumnListing($table))."\n";
 }catch(\Throwable $e){echo "SCHEMA_UNAVAILABLE $table ".get_class($e)."\n";}
}
""",encoding="utf8")
        result=subprocess.run(["php",str(runner),str(root)],text=True,
            capture_output=True,timeout=22)
        if result.returncode:
            print("SCHEMA_UNAVAILABLE_PROCESS",safe_line(result.stderr[-350:]))
        else:print(result.stdout[:9000])
except (OSError, subprocess.TimeoutExpired) as error:
    print("SCHEMA_CHECK_UNAVAILABLE",type(error).__name__)
print("\nAM171_READ_ONLY_COMPLETE_NO_WRITES")
