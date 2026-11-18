#!/usr/bin/env python3
"""AM270 STAGING only. Purchase Part picker CSS+JS UI patch; no database writes."""
from pathlib import Path
import sys,hashlib,datetime,tempfile,os,subprocess,json

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM270 check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():
    raise SystemExit('STOP_AM270_NOT_STAGING')
jsname='public/js/purchase-entry.js'
viewname='resources/views/purchases/create.blade.php'
paths=[jsname,viewname]
shas={jsname:'75017fc67f0f135c07a7ed79f77be837b619b6cf1e7278afda71ec7baca01b7e',
      viewname:'604b31d9f356c0f5e24d46f2860af1a7b7adc4f9f05681720a06452ffbe2a75e'}
base=root/'storage/app/am-backups'
pointer=base/'AM270_LAST_PICKER_GHOST_FIX_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def once(s,a,b,label):
    n=s.count(a)
    if n!=1:raise SystemExit('STOP_AM270_ANCHOR_'+label+'_'+str(n))
    return s.replace(a,b,1)
def run(args,t=120):return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=t)
def put(p,data):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(dir=p.parent,prefix='.am270_')
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,(p.stat().st_mode&0o777) if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

if mode=='rollback':
    if not pointer.exists():raise SystemExit('STOP_AM270_BACKUP_MISSING')
    back=Path(pointer.read_text().strip()).resolve()
    if base.resolve() not in back.parents or not back.name.startswith('AM270_PICKER_CODE_'):
        raise SystemExit('STOP_AM270_UNSAFE_BACKUP')
    manifest=json.loads((back/'manifest.json').read_text())
    for name,after in manifest['installed'].items():
        if not(root/name).exists() or sha((root/name).read_bytes())!=after:
            raise SystemExit('STOP_AM270_SOURCE_CHANGED_SINCE_INSTALL_'+name)
    for name in paths:put(root/name,(back/name).read_bytes())
    if run(['php','artisan','optimize:clear']).returncode:
        raise SystemExit('AM270_SOURCE_ROLLBACK_CACHE_CLEAR_FAIL')
    print('AM270_SOURCE_ROLLBACK_NO_PURCHASE_OR_STOCK_DATA_WRITES')
    sys.exit(0)

old={name:(root/name).read_bytes() for name in paths}
if b'AM270_FOCUS_ONLY_PART_MENU' in old[jsname]:
    print('AM270_ALREADY_INSTALLED');sys.exit(0)
for name in paths:
    if sha(old[name])!=shas[name]:raise SystemExit('STOP_AM270_CONCURRENT_STAGING_SOURCE_CHANGE_'+name)
s=old[jsname].decode('utf8')
v=old[viewname].decode('utf8')
s=once(s,
"""    if (!row.isConnected || document.activeElement !== search) {
      closeResults(row);
      return [];
    }
    closeOtherPartResults(row);""",
"""    if (!row.isConnected || document.activeElement !== search) {
      closeResults(row);
      return [];
    }
    // AM270_FOCUS_ONLY_PART_MENU: no unfiltered suggestions on blank rows.
    if (!clean(query)) {
      closeResults(row);
      return [];
    }
    closeOtherPartResults(row);""",'EMPTY_ROWS')
s=once(s,
"""    let timer = null;
    search.addEventListener('focus', () => renderResults(row, search.value));
    search.addEventListener('input', () => {
      if (search.value !== search.dataset.selectedLabel) {""",
"""    let timer = null;
    search.addEventListener('focus', () => {
      // AM270: do not show all Parts when entering an empty Part input.
      if (!clean(search.value)) { closeResults(row); return; }
      renderResults(row, search.value);
    });
    search.addEventListener('blur', () => {
      clearTimeout(timer);
      window.setTimeout(() => {
        if (document.activeElement !== search) closeResults(row);
      }, 0);
    });
    search.addEventListener('input', () => {
      closeResults(row); // immediate invalidation of previous query results
      if (search.value !== search.dataset.selectedLabel) {""",'BLUR_AND_INPUT')
s=once(s,
"""  document.addEventListener('mousedown', e => {
    body.querySelectorAll('.purchase-line').forEach(row => {""",
"""  // AM270: focus changes close all other Part menus immediately.
  body.addEventListener('focusin', e => {
    const activePart=e.target.classList &&
        e.target.classList.contains('part-search');
    body.querySelectorAll('.purchase-line').forEach(row => {
      if (!activePart || row.querySelector('.part-search')!==e.target)
        closeResults(row);
    });
  });
  document.addEventListener('mousedown', e => {
    body.querySelectorAll('.purchase-line').forEach(row => {""",'EXCLUSIVE_FOCUS')
css="""
/* AM270_FOCUS_ONLY_PART_MENU — CSS fallback prevents 'ghost' lists behind
   focused rows even if asynchronous JavaScript still has an .open class. */
#purchase-form .line-table tr.purchase-line .part-combo:not(:focus-within) > .part-results,
#purchase-form .line-table tr.purchase-line .part-combo:not(:focus-within) > .part-results.open{
  display:none!important;
  pointer-events:none!important;
}
"""
v=once(v,".part-results.open{\n    display:block!important;\n}",".part-results.open{\n    display:block!important;\n}\n"+css,'CSS_GATE')
v=once(v,"?v=2692026111801","?v=2702026111801",'CACHE_BUST')
for x in ['AM269_SINGLE_ACTIVE_PART_MENU','AM257_PICKER_ENTER_TAB',
          'const isTab = e.key ===','selectPart(row, list[idx], false)',
          'AM270_FOCUS_ONLY_PART_MENU']:
    if x not in s:raise SystemExit('STOP_AM270_KEYBOARD_OR_SELECTION_GUARD_'+x)
new={jsname:s.encode(),viewname:v.encode()}
with tempfile.TemporaryDirectory(prefix='am270blade_') as td:
    source=Path(td)/'src.blade.php';dst=Path(td)/'compiled.php'
    source.write_bytes(new[viewname])
    snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    p=run(['php','-r',snippet,str(source),str(dst)],95)
    if p.returncode:raise SystemExit('STOP_AM270_BLADE_COMPILE_'+(p.stdout+p.stderr)[-500:])
    p=run(['php','-l',str(dst)],65)
    if p.returncode:raise SystemExit('STOP_AM270_BLADE_PHP_LINT_'+(p.stdout+p.stderr)[-500:])
if run(['bash','-lc','command -v node'],10).returncode==0:
    with tempfile.NamedTemporaryFile(suffix='.js',delete=False) as f:
        f.write(new[jsname]);fn=f.name
    try:
        p=run(['node','--check',fn],35)
        if p.returncode:raise SystemExit('STOP_AM270_JS_SYNTAX_'+(p.stdout+p.stderr)[-500:])
    finally:Path(fn).unlink(missing_ok=True)
print('AM270_CSS_JS_PATCH_PREFLIGHT_PASS')
if mode=='check':
    print('AM270_NO_STOCK_PURCHASE_OR_BROWSER_DRAFT_MUTATIONS')
    sys.exit(0)
backup=base/('AM270_PICKER_CODE_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for name,body in old.items():
    p=backup/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(body)
written=[]
try:
    for name,body in new.items():
        if (root/name).read_bytes()!=old[name]:
            raise RuntimeError('AM270_CONCURRENT_EDIT_'+name)
        put(root/name,body);written.append(name)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        result=run(cmd,165)
        if result.returncode:raise RuntimeError('AM270_CACHE_FAILED_'+(result.stdout+result.stderr)[-800:])
except BaseException as e:
    for name in written:put(root/name,old[name])
    run(['php','artisan','optimize:clear'],110)
    raise SystemExit('STOP_AM270_INSTALLED_CODE_ROLLED_BACK_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed':{n:sha(b) for n,b in new.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM270_STAGING_FOCUSED_PART_MENU_ONLY_INSTALLED')
print('AM270_BLANK_ROWS_NO_UNFILTERED_SUGGESTIONS')
print('AM270_NO_PURCHASE_STOCK_GRN_OR_BROWSER_DRAFT_DATA_MODIFIED')
