#!/usr/bin/env python3
"""AM250: guarded staging installer for Owner/Admin Money Receipt Void.
Commands: check | apply | rollback. This installer never voids a receipt.
"""
from pathlib import Path
from datetime import datetime
import hashlib, json, os, shutil, subprocess, sys, tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply", "rollback"):
    raise SystemExit("USAGE_AM250 install.py check|apply|rollback STAGING_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
if root.name != "assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("STOP_AM250_NOT_STAGING")
package = Path(__file__).resolve().parent
route = "routes/web.php"
index = "resources/views/money-receipts/index.blade.php"
show = "resources/views/money-receipts/show.blade.php"
controller = "app/Http/Controllers/Web/AdminMoneyReceiptVoidController.php"
existing = [route, index, show]
new = [controller]
backup_base = root/"storage/app/am-backups"
pointer = backup_base/"AM250_RECEIPT_VOID_LAST_BACKUP.txt"
marker = "AM250_RECEIPT_VOID_ROUTE"
view_marker = "AM250_RECEIPT_VOID_UI"

def digest(raw): return hashlib.sha256(raw).hexdigest()
def enc(value): return value.encode("utf-8", errors="surrogateescape")
def txt(raw): return raw.decode("utf-8", errors="surrogateescape")
def run(args, timeout=75):
    return subprocess.run(args, cwd=root, capture_output=True, text=True, timeout=timeout)
def atomic(path, content):
    path.parent.mkdir(parents=True, exist_ok=True)
    fd, temp = tempfile.mkstemp(dir=path.parent, prefix=".am250_")
    try:
        with os.fdopen(fd, "wb") as stream: stream.write(content)
        os.chmod(temp, path.stat().st_mode & 0o777 if path.exists() else 0o644)
        os.replace(temp, path)
    finally:
        if os.path.exists(temp): os.unlink(temp)
def replace_once(source, anchor, replacement, tag):
    count = source.count(anchor)
    if count != 1: raise SystemExit(f"STOP_AM250_ANCHOR_{tag}_{count}")
    return source.replace(anchor, replacement, 1)
def lint(blob, name):
    with tempfile.NamedTemporaryFile(suffix=".php", delete=False) as stream:
        stream.write(blob)
        path = Path(stream.name)
    try:
        result = run(["php", "-l", str(path)], 25)
        if result.returncode:
            raise SystemExit("STOP_AM250_PHP_LINT_"+name+" "+(result.stdout+result.stderr)[-450:])
    finally:
        path.unlink(missing_ok=True)
def blade_lint(blob, name):
    with tempfile.TemporaryDirectory(prefix="am250blade_") as folder:
        src = Path(folder)/"template.blade.php"
        compiled = Path(folder)/"compiled.php"
        src.write_bytes(blob)
        code = r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        result = run(["php", "-r", code, str(src), str(compiled)], 35)
        if result.returncode or not compiled.exists():
            raise SystemExit("STOP_AM250_BLADE_"+name+" "+(result.stdout+result.stderr)[-400:])
        lint(compiled.read_bytes(), "COMPILED_"+name)
def php_probe():
    code = r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=\Illuminate\Support\Facades\Schema::class;
$requirements=[
'customer_payments'=>['id','receipt_no','customer_id','invoice_id','total_amount','allocated_amount','payment_status','reversed_at','reversed_by','reversal_reason'],
'customer_payment_allocations'=>['id','payment_id','invoice_id','amount','status','reversed_at'],
'customer_ledger'=>['id','customer_id','payment_id','entry_type','reference_type','reference_id','debit','credit','amount','narration'],
'invoices'=>['id','customer_id','grand_total','paid_total','paid_amount','balance_total','balance_due','status'],
'am_customer_master'=>['id']];
foreach($requirements as $table=>$columns){
if(!$schema::hasTable($table)){fwrite(STDERR,"AM250_MISSING_TABLE_".$table);exit(30);}
foreach($columns as $column)if(!$schema::hasColumn($table,$column)){
fwrite(STDERR,"AM250_MISSING_COLUMN_".$table."_".$column);exit(31);
}}
echo "AM250_RECEIPT_ACCOUNTING_SCHEMA_PASS";"""
    response = run(["php", "-r", code], 45)
    if response.returncode or "AM250_RECEIPT_ACCOUNTING_SCHEMA_PASS" not in response.stdout:
        raise SystemExit("STOP_AM250_SCHEMA "+(response.stdout+response.stderr)[-400:])
    print("AM250_RECEIPT_ACCOUNTING_SCHEMA_PASS")

if mode == "rollback":
    if not pointer.is_file(): raise SystemExit("STOP_AM250_NO_ROLLBACK_POINTER")
    backup = Path(pointer.read_text().strip()).resolve()
    if backup_base.resolve() not in backup.parents or not backup.name.startswith("AM250_MONEY_RECEIPT_VOID_"):
        raise SystemExit("STOP_AM250_UNTRUSTED_BACKUP_PATH")
    manifest = json.loads((backup/"manifest.json").read_text())
    for rel, expected in manifest["installed"].items():
        if not (root/rel).exists() or digest((root/rel).read_bytes()) != expected:
            raise SystemExit("STOP_AM250_CURRENT_FILE_HAS_NEWER_CHANGES_"+rel)
    for rel in existing: atomic(root/rel, (backup/rel).read_bytes())
    for rel in new: (root/rel).unlink(missing_ok=True)
    r = run(["php","artisan","optimize:clear"], 70)
    if r.returncode: raise SystemExit("AM250_ROLLBACK_FILES_RESTORED_CACHE_CLEAR_ERROR")
    print("AM250_ROLLBACK_COMPLETE_NO_RECEIPTS_MODIFIED")
    sys.exit(0)

required = existing + [controller]
for rel in existing:
    if not (root/rel).is_file(): raise SystemExit("STOP_AM250_STAGING_SOURCE_MISSING_"+rel)
if not (package/"files"/controller).is_file():
    raise SystemExit("STOP_AM250_CONTROLLER_PACKAGE_MISSING")
raw = {rel:(root/rel).read_bytes() for rel in existing}
src = {rel:txt(raw[rel]) for rel in existing}
if marker in src[route] and all(view_marker in src[rel] for rel in [index,show]) and (root/controller).is_file():
    print("AM250_ALREADY_INSTALLED")
    sys.exit(0)
if marker in src[route] or any(view_marker in src[rel] for rel in [index,show]) or (root/controller).exists():
    raise SystemExit("STOP_AM250_PARTIAL_INSTALL_OR_SOURCE_CONFLICT")

anchor = "    Route::get('/money-receipts/{receipt}/print',"
new_route = ("    // AM250_RECEIPT_VOID_ROUTE: inherits existing secure ERP Admin route-group middleware.\n"
    "    Route::post('/money-receipts/{receipt}/void', "
    "[\\App\\Http\\Controllers\\Web\\AdminMoneyReceiptVoidController::class,'void'])"
    "->whereNumber('receipt')->name('erp.money-receipts.void');\n")
patched = {}
patched[route] = enc(replace_once(src[route], anchor, new_route+anchor, "ROUTE"))
index_anchor = '<td><a class="btn58 alt" href="{{ route(\x27erp.money-receipts.show\x27,$p->id) }}">Open</a> <a class="btn58 alt" target="_blank" rel="noopener" href="{{ route(\x27erp.money-receipts.print\x27,$p->id) }}">Print</a></td>'
index_new = '''<td>
     <a class="btn58 alt" href="{{ route('erp.money-receipts.show',$p->id) }}">Open</a>
     <a class="btn58 alt" target="_blank" rel="noopener" href="{{ route('erp.money-receipts.print',$p->id) }}">Print</a>
     {{-- AM250_RECEIPT_VOID_UI: owner/admin POST, CSRF, reason and confirmation. --}}
     @if(strtolower((string)$p->payment_status)==='posted' && !$p->reversed_at)
     <form action="{{ route('erp.money-receipts.void',$p->id) }}" method="post" style="display:inline-flex;flex-wrap:wrap;gap:4px;align-items:center;margin:4px 0"
           onsubmit="return confirm('VOID receipt {{ $p->receipt_no }}? This reverses customer credit and invoice allocation. Proceed only for duplicate or incorrect receipts.');">
       @csrf
       <input type="text" name="reason" required minlength="8" maxlength="300" aria-label="Mandatory Money Receipt void reason"
         placeholder="Reason: duplicate entry etc." style="width:175px;max-width:100%;font-size:11px;padding:7px">
       <button type="submit" class="btn58 alt" style="color:#a52129;border-color:#e4a1a6">Void</button>
     </form>
     @endif
    </td>'''
patched[index] = enc(replace_once(src[index], index_anchor, index_new, "REGISTER_ACTION"))
show_anchor = '<a class="action alt" href="{{ route(\x27erp.money-receipts.create\x27,[\x27customer\x27=>$receipt->customer_id]) }}">＋ New Receipt</a>'
show_new = '''<a class="action alt" href="{{ route('erp.money-receipts.create',['customer'=>$receipt->customer_id]) }}">＋ New Receipt</a>
     {{-- AM250_RECEIPT_VOID_UI: only available while posted. --}}
     @if(strtolower((string)$receipt->payment_status)==='posted' && !$receipt->reversed_at)
     <form action="{{ route('erp.money-receipts.void',$receipt->id) }}" method="post"
           style="display:flex;gap:6px;flex-wrap:wrap;align-items:center"
           onsubmit="return confirm('VOID Money Receipt {{ $receipt->receipt_no }}? Original receipt remains in audit; ledger credit and invoice allocation will reverse.');">
       @csrf
       <input name="reason" type="text" required minlength="8" maxlength="300"
         placeholder="Reason for cancellation (mandatory)" aria-label="Money Receipt cancellation reason"
         style="min-width:215px;max-width:100%;padding:9px;border:1px solid #ce9fa4;border-radius:8px">
       <button class="action alt" type="submit" style="color:#9e2229;border:1px solid #db9498;cursor:pointer">Void Receipt</button>
     </form>
     @endif'''
patched[show] = enc(replace_once(src[show], show_anchor, show_new, "DETAIL_ACTION"))
patched[controller] = (package/"files"/controller).read_bytes()
lint(patched[route], "ROUTES")
lint(patched[controller], "VOID_CONTROLLER")
for rel in [index,show]:
    blade_lint(raw[rel], "BEFORE_"+rel.split("/")[-1])
    blade_lint(patched[rel], "AFTER_"+rel.split("/")[-1])
php_probe()
print("AM250_TARGETED_SOURCE_AND_BLADE_PREFLIGHT_PASS")
if mode == "check":
    print("AM250_READONLY_CHECK_PASS")
    sys.exit(0)

backup_base.mkdir(parents=True, exist_ok=True)
backup = backup_base/("AM250_MONEY_RECEIPT_VOID_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(mode=0o700, exist_ok=False)
for rel in existing:
    file = backup/rel
    file.parent.mkdir(parents=True, exist_ok=True)
    file.write_bytes(raw[rel])
written = []
try:
    for rel, content in patched.items():
        destination = root/rel
        if rel in raw and destination.read_bytes() != raw[rel]:
            raise RuntimeError("STAGING_SOURCE_CHANGED_"+rel)
        atomic(destination, content)
        written.append(rel)
    for cmd in (["php","artisan","optimize:clear"],["php","artisan","view:cache"]):
        outcome = run(cmd, 90)
        if outcome.returncode: raise RuntimeError("ARTISAN_ERROR "+(outcome.stdout+outcome.stderr)[-400:])
    check = r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=\Illuminate\Support\Facades\Route::getRoutes();
$a=$routes->getByName('erp.money-receipts.store');
$b=$routes->getByName('erp.money-receipts.void');
if(!$a||!$b||!in_array('POST',$b->methods())||$a->gatherMiddleware()!==$b->gatherMiddleware())exit(40);
if(!str_contains(implode('|',$b->gatherMiddleware()),'EnsureErpAdmin'))exit(41);
echo "AM250_POST_ROUTE_IN_SAME_ADMIN_AUTH_GROUP_PASS";"""
    result = run(["php","-r",check], 40)
    if result.returncode or "AM250_POST_ROUTE_IN_SAME_ADMIN_AUTH_GROUP_PASS" not in result.stdout:
        raise RuntimeError("ADMIN_AUTH_ROUTE_VERIFICATION_FAILED "+(result.stdout+result.stderr)[-500:])
except BaseException as error:
    for rel in written:
        if rel in raw: atomic(root/rel, raw[rel])
        else: (root/rel).unlink(missing_ok=True)
    run(["php","artisan","optimize:clear"], 65)
    raise SystemExit("STOP_AM250_APPLY_RESTORED_"+str(error))
manifest = {"installed": {rel:digest(content) for rel,content in patched.items()}}
(backup/"manifest.json").write_text(json.dumps(manifest, indent=2))
pointer.write_text(str(backup)+"\n")
print("AM250_OWNER_ADMIN_RECEIPT_VOID_INSTALLED")
print("AM250_CUSTOMER_LEDGER_AND_INVOICE_REVERSAL_TRANSACTION_READY")
print("AM250_CODE_BACKUP="+str(backup))
print("AM250_NO_RECEIPTS_AUTOMATICALLY_VOIDED")
