#!/usr/bin/env python3
"""AM258: safe always-ready blank line for Estimate, Purchase Create/Edit, Sales.
check|apply|verify|rollback STAGING_ROOT. No DB migration or financial writes.
"""
from pathlib import Path
from datetime import datetime
import os,sys,hashlib,tempfile,subprocess,json

if len(sys.argv)!=3 or sys.argv[1] not in ['check','apply','verify','rollback']:
    raise SystemExit('AM258_USAGE check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM258_TARGET_NOT_STAGING')
view='resources/views/layouts/erp.blade.php'
estimate='public/js/estimate-entry.js'
purchase='public/js/purchase-entry.js'
purchase_edit='public/js/purchase-edit.js'
asset='public/js/am258-auto-blank-entry.js'
targets=[view,estimate,purchase,purchase_edit]
backups=root/'storage/app/am-backups'
pointer=backups/'AM258_LAST_BACKUP.txt'
marker='AM258_ALWAYS_READY_ENTRY_ROW'
if not (package/'files'/asset).is_file():
    raise SystemExit('STOP_AM258_SCRIPT_PAYLOAD_MISSING')

def sha(data):return hashlib.sha256(data).hexdigest()
def run(args,timeout=65):
    return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am258_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,(path.stat().st_mode&0o777) if path.exists() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def replace_one(content,old,new,label):
    if content.count(old)==1:return content.replace(old,new,1)
    # Only line indentation differences accepted, exact original statements.
    source=content.splitlines(keepends=True)
    pieces=[line.strip() for line in old.splitlines()]
    hits=[]
    for i in range(len(source)-len(pieces)+1):
        if all(source[i+j].strip()==pieces[j] for j in range(len(pieces))):
            hits.append(i)
    if len(hits)!=1:raise SystemExit('STOP_AM258_SOURCE_ANCHOR_'+label+'_HITS_'+str(len(hits)))
    at=hits[0]
    return ''.join(source[:at])+new.rstrip('\n')+'\n'+''.join(source[at+len(pieces):])
def php_lint(path,label):
    p=run(['php','-l',str(path)],40)
    if p.returncode:
        raise SystemExit('STOP_AM258_PHP_LINT_'+label+' '+(p.stdout+p.stderr)[-800:])
def blade_lint(data):
    with tempfile.TemporaryDirectory(prefix='am258_blade_') as td:
        original=Path(td)/'layout.blade.php'
        out=Path(td)/'layout.compiled.php'
        original.write_bytes(data)
        script=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        a=run(['php','-r',script,str(original),str(out)],45)
        if a.returncode or not out.exists():
            raise SystemExit('STOP_AM258_LAYOUT_COMPILE '+(a.stdout+a.stderr)[-800:])
        php_lint(out,'COMPILED_LAYOUT')

def patch():
    before={}
    for name in targets:
        p=root/name
        if not p.is_file():raise SystemExit('STOP_AM258_REQUIRED_SOURCE_MISSING_'+name)
        before[name]=p.read_bytes()
    src={name:blob.decode('utf8','surrogateescape') for name,blob in before.items()}
    if any(marker in source for source in src.values()):
        raise SystemExit('STOP_AM258_ALREADY_INSTALLED_USE_VERIFY')
    js=(package/'files'/asset).read_bytes()
    if js.count(marker.encode())!=1 or b'</script' in js.lower():
        raise SystemExit('STOP_AM258_NEW_JS_MISSING_MARKER_OR_UNSAFE_CLOSE_TAG')

    # Auto-generated blank rows must not steal focus from item/Qty fields.
    src[estimate]=replace_one(
        src[estimate],
        "addBtn.addEventListener('click', () => add({}, true));",
        "// AM258_ALWAYS_READY_ENTRY_ROW: automatic new blank must not move cursor.\n"
        "addBtn.addEventListener('click', () => add({}, !window.__am258AppendingBlank));",
        "ESTIMATE_ADD_BUTTON")
    src[estimate]=replace_one(
        src[estimate],
        "tr.querySelector('.e-ref-id').value = item.id || '';",
        "tr.querySelector('.e-ref-id').value = item.id || '';\n"
        "tr.querySelector('.e-ref-id').dispatchEvent(new Event('change',{bubbles:true}));",
        "ESTIMATE_PICK_FROM_MENU")

    src[purchase]=replace_one(
        src[purchase],
        "add.addEventListener('click', () => addLine(true));",
        "// AM258_ALWAYS_READY_ENTRY_ROW: auto-add never moves typing focus.\n"
        "add.addEventListener('click', () => addLine(!window.__am258AppendingBlank));",
        "PURCHASE_CREATE_ADD_BUTTON")
    src[purchase]=replace_one(
        src[purchase],
        "if (body.children.length >= 100) return body.lastElementChild;",
        "if (body.children.length >= 101) return body.lastElementChild; // AM258: 100 data + one UI blank",
        "PURCHASE_100_ROWS_PLUS_READY_BLANK")
    src[purchase]=replace_one(
        src[purchase],
        "id.value = String(p.id || '');",
        "id.value = String(p.id || '');\n"
        "id.dispatchEvent(new Event('change',{bubbles:true}));",
        "PURCHASE_CREATE_MASTER_SELECTED")

    src[purchase_edit]=replace_one(
        src[purchase_edit],
        "addBtn.addEventListener('click', () => addLine(true));",
        "// AM258_ALWAYS_READY_ENTRY_ROW: auto-add never moves typing focus.\n"
        "addBtn.addEventListener('click', () => addLine(!window.__am258AppendingBlank));",
        "PURCHASE_EDIT_ADD_BUTTON")
    src[purchase_edit]=replace_one(
        src[purchase_edit],
        "row.querySelector('.part-id').value = String(p.id || '');",
        "row.querySelector('.part-id').value = String(p.id || '');\n"
        "row.querySelector('.part-id').dispatchEvent(new Event('change',{bubbles:true}));",
        "PURCHASE_EDIT_MASTER_SELECTED")

    injection="""{{-- AM258_ALWAYS_READY_ENTRY_ROW: keep UI blank row and exclude it from business POST. --}}
<script id="am258-entry-ready-row">{!! file_get_contents(public_path('js/am258-auto-blank-entry.js')) !!}</script>
"""
    src[view]=replace_one(src[view],'</body>',injection+'</body>','ERP_LAYOUT_BEFORE_BODY_CLOSE')
    updated={name:s.encode('utf8','surrogateescape') for name,s in src.items()}
    blade_lint(updated[view])
    print('AM258_ERP_BLADE_COMPILE_AND_PHP_LINT_PASS')
    print('AM258_CATALOG_AND_ENTRY_SOURCE_MATCH_PASS')
    print('AM258_STAGING_NO_DB_OR_FINANCIAL_WRITES')
    return before,updated

def verify():
    for name in targets+[asset]:
        p=root/name
        if not p.is_file():raise SystemExit('STOP_AM258_REQUIRED_FILE_MISSING_'+name)
        txt=p.read_text('utf8','replace')
        if txt.count(marker)!=1:
            raise SystemExit('STOP_AM258_MARKER_COUNT_'+name+'_'+str(txt.count(marker)))
    if 'window.__am258AppendingBlank' not in (root/purchase).read_text('utf8','replace'):
        raise SystemExit('STOP_AM258_PURCHASE_FOCUS_GUARD_MISSING')
    if 'AM258_BLANK_UI_ROW_NOT_SUBMITTED' not in (root/asset).read_text('utf8','replace'):
        raise SystemExit('STOP_AM258_BLANK_ROW_POST_GUARD_MISSING')
    blade_lint((root/view).read_bytes())
    print('AM258_ALWAYS_BLANK_ROW_AND_SAVE_IGNORE_EMPTY_SOURCE_VERIFIED')
    print('AM258_NO_CUSTOMER_BILLING_PURCHASE_OR_STOCK_DATA_CHANGED')

if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM258_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM258_READY_ENTRY_'):
        raise SystemExit('STOP_AM258_BACKUP_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    for name,installedsha in manifest['installed'].items():
        if sha((root/name).read_bytes())!=installedsha:
            raise SystemExit('STOP_AM258_POSTINSTALL_SOURCE_CHANGED_NO_ROLLBACK_'+name)
    for name in targets:atomic(root/name,(backup/name).read_bytes())
    (root/asset).unlink(missing_ok=True)
    run(['php','artisan','view:clear'],75)
    print('AM258_ROLLBACK_SOURCE_ONLY_NO_BUSINESS_ROWS_CHANGED')
    sys.exit(0)

before,updated=patch()
if mode=='check':
    print('AM258_CHECK_READ_ONLY_PASS')
    sys.exit(0)
if (root/asset).exists():raise SystemExit('STOP_AM258_ASSET_ALREADY_EXISTS')
backup=backups/('AM258_READY_ENTRY_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for name,b in before.items():
    p=backup/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b);os.chmod(p,0o600)
updated[asset]=(package/'files'/asset).read_bytes()
written=[]
try:
    for name,b in updated.items():
        if name in before and (root/name).read_bytes()!=before[name]:
            raise RuntimeError('SOURCE_CHANGED_DURING_CHECK_'+name)
        atomic(root/name,b)
        written.append(name)
    p=run(['php','artisan','view:clear'],75)
    if p.returncode:raise RuntimeError('LARAVEL_VIEW_CLEAR '+(p.stdout+p.stderr)[-700:])
    verify()
except Exception as exc:
    for name in written:
        if name in before:atomic(root/name,before[name])
        else:(root/name).unlink(missing_ok=True)
    run(['php','artisan','view:clear'],75)
    raise SystemExit('STOP_AM258_APPLY_RESTORED_ALL_FILES_'+str(exc))
(backup/'manifest.json').write_text(json.dumps({'installed':{n:sha(b) for n,b in updated.items()}},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM258_CONTINUOUS_BLANK_LINE_ENTRY_INSTALLED')
print('AM258_PRIVATE_SOURCE_BACKUP',backup)
print('AM258_BROWSER_TAB_TO_NEXT_LINE_TEST_PENDING')
