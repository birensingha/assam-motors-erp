#!/usr/bin/env python3
"""AM269 STAGING-only Purchase Entry Part dropdown overlap fix.

check | apply | rollback <STAGING_ROOT>
Only modifies browser JavaScript and its cache-busting script URL:
  public/js/purchase-entry.js
  resources/views/purchases/create.blade.php
Does not modify inventory, stock movements, purchases, GRNs, vendors or Job Cards.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM269: install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():
    raise SystemExit('STOP_AM269_NOT_STAGING')
script='public/js/purchase-entry.js'
view='resources/views/purchases/create.blade.php'
paths=[script,view]
expected={
 script:'dc442b7a2391e1132f8803af37fe61985b7e49bba5bac85c3cd602ff2530a8a2',
 view:'963c0ea6e7408afdc42a87a2286e0405bd49e64d8a7ab0536ffe79fa90502244',
}
backupBase=root/'storage/app/am-backups'
pointer=backupBase/'AM269_PART_PICKER_LAST_CODE_BACKUP.txt'
def sha(data):return hashlib.sha256(data).hexdigest()
def run(args,timeout=115):return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(src,old,new,key):
    count=src.count(old)
    if count!=1:raise SystemExit('STOP_AM269_PATCH_ANCHOR_'+key+'_COUNT_'+str(count))
    return src.replace(old,new,1)
def atomic(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,temp=tempfile.mkstemp(prefix='.am269_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(temp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def blade(data):
    with tempfile.TemporaryDirectory(prefix='am269blade_') as td:
        source=Path(td)/'source.blade.php';target=Path(td)/'compiled.php';source.write_bytes(data)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        check=run(['php','-r',php,str(source),str(target)],75)
        if check.returncode or not target.is_file():
            raise SystemExit('STOP_AM269_BLADE_COMPILE: '+(check.stdout+check.stderr)[-750:])
        syntax=run(['php','-l',str(target)],65)
        if syntax.returncode:
            raise SystemExit('STOP_AM269_COMPILED_BLADE_SYNTAX: '+(syntax.stdout+syntax.stderr)[-750:])
        return True
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM269_NO_BACKUP_POINTER')
    backup=Path(pointer.read_text().strip()).resolve()
    if backupBase.resolve() not in backup.parents or not backup.name.startswith('AM269_PICKER_CODE_'):
        raise SystemExit('STOP_AM269_INVALID_BACKUP_PATH')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,digest in manifest['installed'].items():
        if not(root/path).is_file() or sha((root/path).read_bytes())!=digest:
            raise SystemExit('STOP_AM269_SOURCE_CHANGED_SINCE_INSTALL_'+path)
    for path in paths:atomic(root/path,(backup/path).read_bytes())
    result=run(['php','artisan','optimize:clear'],120)
    if result.returncode:raise SystemExit('AM269_SOURCE_RESTORED_BUT_CACHE_CLEAR_FAILED')
    print('AM269_PART_PICKER_CODE_ROLLED_BACK_PURCHASES_AND_STOCK_UNTOUCHED')
    sys.exit(0)
for path in paths:
    if not(root/path).is_file():raise SystemExit('STOP_AM269_EXISTING_FILE_MISSING_'+path)
original={path:(root/path).read_bytes() for path in paths}
if b'AM269_SINGLE_ACTIVE_PART_MENU' in original[script] and b'2692026111801' in original[view]:
    print('AM269_PART_PICKER_ALREADY_INSTALLED');sys.exit(0)
for path,digest in expected.items():
    if sha(original[path])!=digest:
        raise SystemExit('STOP_AM269_UNEXPECTED_STAGING_SOURCE_HASH_'+path)
js=original[script].decode('utf-8','strict')
bladeSource=original[view].decode('utf-8','strict')

js=once(js,
"""    list.classList.remove('open');
    list.replaceChildren();
    search.setAttribute('aria-expanded', 'false');
    row.dataset.activeIndex = '-1';
  }

  function drawResults(row, found, token) {
    if (Number(row.dataset.searchToken || 0) !== token) return;""",
"""    // AM269_SINGLE_ACTIVE_PART_MENU: invalidate previous async results.
    // A completed search must NEVER reopen a menu after leaving its row.
    row.dataset.searchToken = String(++requestSeq);
    row._foundParts = [];
    list.classList.remove('open');
    list.replaceChildren();
    search.setAttribute('aria-expanded', 'false');
    row.dataset.activeIndex = '-1';
  }

  function closeOtherPartResults(activeRow) {
    body.querySelectorAll('.purchase-line').forEach(otherRow => {
      if (otherRow !== activeRow) closeResults(otherRow);
    });
  }

  function drawResults(row, found, token) {
    if (Number(row.dataset.searchToken || 0) !== token) return;
    // Ignore delayed responses belonging to another active purchase row.
    if (document.activeElement !== row.querySelector('.part-search')) return;""",
'PREVENT_STALE_MENU')

js=once(js,
"""  async function renderResults(row, query) {
    const list = row.querySelector('.part-results');
    const search = row.querySelector('.part-search');
    const token = ++requestSeq;""",
"""  async function renderResults(row, query) {
    const list = row.querySelector('.part-results');
    const search = row.querySelector('.part-search');
    // AM269_SINGLE_ACTIVE_PART_MENU: inactive row timer must never
    // display stale unfiltered Parts behind the focused row's results.
    if (!row.isConnected || document.activeElement !== search) {
      closeResults(row);
      return [];
    }
    closeOtherPartResults(row);
    const token = ++requestSeq;""",
'ONLY_FOCUSED_ROW_OPENS')

bladeSource=once(bladeSource,
"""@push('scripts')<script src="{{ asset('js/purchase-entry.js') }}?v=23820261104112731" defer></script>@endpush""",
"""@push('scripts')<script src="{{ asset('js/purchase-entry.js') }}?v=2692026111801" defer></script>@endpush""",
'CACHE_BUST')

# Keep the Tally-style keyboard select flow exactly as previously accepted.
if not all(token in js for token in [
  'AM257_PICKER_ENTER_TAB','selectPart(row, list[idx], false)',
  "row.querySelector('.qty').focus()", "const isTab = e.key === 'Tab'",
  'closeOtherPartResults(row)','document.activeElement !== search'
]):
    raise SystemExit('STOP_AM269_KEYBOARD_PICKER_REGRESSION_DETECTED')
if js.count('AM269_SINGLE_ACTIVE_PART_MENU')!=2:
    raise SystemExit('STOP_AM269_PATCH_MARKER_COUNT')
patched={script:js.encode('utf-8'),view:bladeSource.encode('utf-8')}
blade(patched[view])
# Fast browser-script syntax check when Node is installed on Hostinger;
# CI also verifies JS source with the Node runtime.
if run(['bash','-lc','command -v node'],15).returncode==0:
    with tempfile.NamedTemporaryFile(suffix='.js',delete=False) as temp:
        temp.write(patched[script]);p=Path(temp.name)
    try:
        syntax=run(['node','--check',str(p)],55)
        if syntax.returncode:
            raise SystemExit('STOP_AM269_JS_SYNTAX: '+(syntax.stdout+syntax.stderr)[-850:])
        print('AM269_NODE_JS_SYNTAX_PASS')
    finally:p.unlink(missing_ok=True)
else:
    print('AM269_NODE_NOT_ON_SHARED_HOST_CI_WILL_VALIDATE_JS')
print('AM269_PURCHASE_DROPDOWN_SINGLE_MENU_PATCH_PREFLIGHT_PASS')
if mode=='check':
    print('AM269_NO_PURCHASES_INVENTORY_OR_DATABASE_CHANGED_PREFLIGHT')
    sys.exit(0)
backup=backupBase/('AM269_PICKER_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in original.items():
    b=backup/path;b.parent.mkdir(parents=True,exist_ok=True);b.write_bytes(data)
written=[]
try:
    for path,data in patched.items():
        if (root/path).read_bytes()!=original[path]:
            raise RuntimeError('AM269_CONCURRENT_EDIT_'+path)
        atomic(root/path,data);written.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        result=run(cmd,170)
        if result.returncode:
            raise RuntimeError('AM269_CACHE_COMPILE_FAILED '+(result.stdout+result.stderr)[-850:])
except BaseException as exc:
    for path in written:atomic(root/path,original[path])
    run(['php','artisan','optimize:clear'],120)
    raise SystemExit('STOP_AM269_INSTALL_ROLLED_BACK_'+str(exc))
(backup/'manifest.json').write_text(json.dumps({
  'installed':{path:sha(data) for path,data in patched.items()}
},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM269_PURCHASE_PART_DROPDOWN_FIX_INSTALLED_STAGING')
print('AM269_ONLY_FOCUSED_ROW_SUGGESTIONS_VISIBLE')
print('AM269_INACTIVE_ROW_ASYNC_SEARCH_CANNOT_REOPEN_MENU')
print('AM269_NO_PURCHASE_RECORD_STOCK_OR_BACKEND_CHANGED')
print('AM269_BACKUP='+str(backup))
