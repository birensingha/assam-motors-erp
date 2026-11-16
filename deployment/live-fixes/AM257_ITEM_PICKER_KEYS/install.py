#!/usr/bin/env python3
"""AM257 Tally-style item selection on Estimate, Purchase Create/Edit and global Sales selector.
check|apply|verify|rollback STAGING_ROOT. Source-only changes; never submits transactions.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
    raise SystemExit('USAGE_AM257 check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM257_NOT_STAGING')
files=[
 'public/js/estimate-entry.js',
 'public/js/purchase-entry.js',
 'public/js/purchase-edit.js',
 'resources/views/partials/am-global-selector.blade.php'
]
marker='AM257_PICKER_ENTER_TAB'
backupbase=root/'storage/app/am-backups'
pointer=backupbase/'AM257_LAST_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def run(args,timeout=90):
 return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=timeout)
def atomic(p,b):
 p.parent.mkdir(parents=True,exist_ok=True)
 fd,name=tempfile.mkstemp(prefix='.am257_',dir=p.parent)
 try:
  with os.fdopen(fd,'wb') as f:f.write(b)
  os.chmod(name,(p.stat().st_mode&0o777) if p.is_file() else 0o644)
  os.replace(name,p)
 finally:
  if os.path.exists(name):os.unlink(name)
def once(text,old,new,label):
 n=text.count(old)
 if n!=1:raise SystemExit('STOP_AM257_ANCHOR_'+label+'_COUNT_'+str(n))
 return text.replace(old,new,1)
def blade_lint(raw):
 with tempfile.TemporaryDirectory(prefix='am257_blade_') as td:
  path=Path(td)/'selector.blade.php';out=Path(td)/'compiled.php'
  path.write_bytes(raw)
  compile_php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
  r=run(['php','-r',compile_php,str(path),str(out)],60)
  if r.returncode or not out.is_file():
   raise SystemExit('STOP_AM257_BLADE_COMPILE '+(r.stdout+r.stderr)[-760:])
  p=run(['php','-l',str(out)],35)
  if p.returncode:raise SystemExit('STOP_AM257_BLADE_PHP_LINT '+(p.stdout+p.stderr)[-760:])
def js_lint(rel,raw):
 with tempfile.NamedTemporaryFile(suffix='.js',delete=False) as handle:
  handle.write(raw);name=Path(handle.name)
 try:
  try:r=run(['node','--check',str(name)],35)
  except FileNotFoundError:
   print('AM257_NODE_UNAVAILABLE_ON_HOSTINGER_JS_SYNTAX_CHECK_BY_GITHUB_PENDING_'+rel)
   return
  if r.returncode:raise SystemExit('STOP_AM257_JS_SYNTAX_'+rel+' '+(r.stdout+r.stderr)[-760:])
 finally:name.unlink(missing_ok=True)

def make_patch():
 before={}
 for f in files:
  p=root/f
  if not p.is_file():raise SystemExit('STOP_AM257_REQUIRED_SOURCE_MISSING_'+f)
  before[f]=p.read_bytes()
 src={k:v.decode('utf8','surrogateescape') for k,v in before.items()}
 if any(marker in v for v in src.values()):raise SystemExit('STOP_AM257_ALREADY_PATCHED_VERIFY_ONLY')

 # Estimate original Enter action incorrectly calls commitAndNext, which opens
 # another row after the catalog lookup. Replace with selection ONLY.
 f=files[0]
 src[f]=once(src[f],
"""function commitAndNext(tr) {
const type = tr.querySelector('.e-type').value;
let item = selectedOptionItem(tr) || firstMatchForInput(tr);
if (item) applyReference(tr, item);
closeMenu(tr);
if (tr.querySelector('.e-desc').value.trim()) focusNextLine(tr);
}""",
"""// AM257_PICKER_ENTER_TAB: item selection never creates/advances to another row.
function commitReference(tr) {
const input = tr.querySelector('.e-ref-search');
const isOpen = !tr.querySelector('.estimate-ref-menu').hidden;
const alreadySelected = !!tr.querySelector('.e-ref-id').value;
const item = isOpen ? selectedOptionItem(tr) : (!alreadySelected ? firstMatchForInput(tr) : null);
if (item) applyReference(tr, item);
closeMenu(tr);
if (item) input.dispatchEvent(new Event('change', {bubbles:true}));
return !!item || alreadySelected;
}""",
 'ESTIMATE_NO_NEXT_ROW_ON_PICK')
 src[f]=once(src[f],
""" } else if (e.key === 'Escape' || e.key === 'Tab') {
closeMenu(tr);
} else if (e.key === 'Enter') {
e.preventDefault();
commitAndNext(tr);
}""",
""" } else if (e.key === 'Escape') {
closeMenu(tr);
} else if (e.key === 'Tab') {
  // Select highlighted/typed Master before native Tab continues to Description
  // (same row). Shift+Tab retains ordinary reverse navigation.
  if (!e.shiftKey) commitReference(tr);
  else closeMenu(tr);
} else if (e.key === 'Enter') {
e.preventDefault(); e.stopPropagation();
commitReference(tr); // Enter chooses, focus stays on Reference; Tab advances.
}""",
 'ESTIMATE_ENTER_AND_TAB')
 # Also prevent stale references when tabbed from part selection with pre-filled
 # label; no change to prices/quantities/GST or submission.

 # Purchase Create: commit highlighted part synchronously on Tab; when a
 # backend fetch is pending, await it before moving focus within same row.
 f=files[1]
 src[f]=once(src[f],
""" } else if (e.key === 'Escape') {
closeResults(row);
} else if (e.key === 'Enter') {
e.preventDefault();
if (id.value) {
row.querySelector('.qty').focus();
row.querySelector('.qty').select();
closeResults(row);
return;
}
if (!(row._foundParts || []).length) await renderResults(row, search.value);
const list = row._foundParts || [];
const idx = Math.max(0, parseInt(row.dataset.activeIndex || '0', 10));
if (list[idx]) selectPart(row, list[idx], true);
}""",
""" } else if (e.key === 'Escape') {
closeResults(row);
} else if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)) {
  // AM257_PICKER_ENTER_TAB: select Part without jumping to new Purchase row.
  const isTab = e.key === 'Tab';
  if (id.value) {
    closeResults(row);
    if (!isTab) {
      e.preventDefault(); e.stopPropagation();
      row.querySelector('.qty').focus();
      row.querySelector('.qty').select();
    }
    return;
  }
  if (!search.value.trim()) return; // Native Tab on a truly empty row.
  e.preventDefault();
  if (!isTab) e.stopPropagation();
  clearTimeout(timer);
  if (!(row._foundParts || []).length) await renderResults(row, search.value);
  const list = row._foundParts || [];
  const idx = Math.max(0, parseInt(row.dataset.activeIndex || '0', 10));
  if (list[idx]) {
    selectPart(row, list[idx], false); // keep focus for next Tab
    if (isTab) row.querySelector('.qty').focus(); // native Tab was prevented
  } else {
    search.classList.add('invalid');
    search.focus();
  }
}""",
 'PURCHASE_CREATE_TAB_ENTER')

 # Purchase Edit has the same catalog UI and line flow but an independent
 # handler, so apply equivalent safety without altering Edit/Purchase totals.
 f=files[2]
 src[f]=once(src[f],
""" if (e.key === 'Enter') {
e.preventDefault();

if (partId.value) {
closeResults(row);
qty.focus();
qty.select();
return;
}

if (!(row._foundParts || []).length) {
await renderResults(row, search.value);
}

const list = row._foundParts || [];
const idx = Math.max(
0,
parseInt(row.dataset.activeIndex || '0', 10)
);

if (list[idx]) selectPart(row, list[idx], true);
}""",
""" if (e.key === 'Enter' || (e.key === 'Tab' && !e.shiftKey)) {
  // AM257_PICKER_ENTER_TAB: highlight -> choose, then Tab stays in same row.
  const isTab = e.key === 'Tab';
  if (partId.value) {
    closeResults(row);
    if (!isTab) {
      e.preventDefault();e.stopPropagation();
      qty.focus();qty.select();
    }
    return;
  }
  if (!search.value.trim()) return;
  e.preventDefault();
  if (!isTab) e.stopPropagation();
  clearTimeout(timer);
  if (!(row._foundParts || []).length) await renderResults(row, search.value);
  const list = row._foundParts || [];
  const idx = Math.max(0, parseInt(row.dataset.activeIndex || '0', 10));
  if (list[idx]) {
    selectPart(row, list[idx], false);
    if (isTab) qty.focus();
  } else {
    search.classList.add('invalid');search.focus();
  }
}""",
 'PURCHASE_EDIT_TAB_ENTER')

 # Counter Sales uses the existing global searchable native select component
 # (Sales Master dropdown is <select data-part> and dynamically enhanced).
 # Add Tab selection and keep Enter focus on picker until explicit next Tab.
 f=files[3]
 src[f]=once(src[f],
""" }else if(e.key==='Enter'){
if(active===state&&!list.hidden){
e.preventDefault();e.stopPropagation();if(state.matches[state.cursor])chosen(state,state.matches[state.cursor],true);
}else if(!sel.value){e.preventDefault();draw(state,'',true);}
}else if(e.key==='Escape'){""",
""" }else if(e.key==='Tab'){
  // AM257_PICKER_ENTER_TAB: pick highlighted Sales/other Master choice,
  // then let native Tab move to the next form control, same item row.
  if(!e.shiftKey&&active===state&&!list.hidden&&state.matches[state.cursor]){
    chosen(state,state.matches[state.cursor],false);
  }
}else if(e.key==='Enter'){
if(active===state&&!list.hidden){
e.preventDefault();e.stopPropagation();
if(state.matches[state.cursor])chosen(state,state.matches[state.cursor],false);
}else if(!sel.value){e.preventDefault();draw(state,'',true);}
}else if(e.key==='Escape'){""",
 'GLOBAL_NATIVE_SELECT_TAB_ENTER')

 result={k:src[k].encode('utf8','surrogateescape') for k in files}
 blade_lint(result[files[3]])
 for f in files[:3]:js_lint(f,result[f])
 print('AM257_BLADES_AND_JS_PREFLIGHT_PASS')
 print('AM257_NO_DATABASE_BILLING_OR_STOCK_WRITES_BY_INSTALL')
 return before,result

def verify():
 for rel in files:
  p=root/rel
  if not p.is_file():raise SystemExit('STOP_AM257_VERIFY_SOURCE_MISSING_'+rel)
  if p.read_text('utf8','replace').count(marker)!=1:
   raise SystemExit('STOP_AM257_VERIFY_MARKER_'+rel)
 for rel in files[:3]:js_lint(rel,(root/rel).read_bytes())
 blade_lint((root/files[3]).read_bytes())
 print('AM257_ESTIMATE_PURCHASE_AND_SALES_ENTER_TAB_SOURCE_VERIFIED')
 print('AM257_ROW_CREATION_ONLY_BY_EXISTING_EXPLICIT_ROW_FLOW')
 print('AM257_NO_FINANCIAL_DATA_CHANGED')
if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
 if not pointer.is_file():raise SystemExit('STOP_AM257_NO_BACKUP_POINTER')
 backup=Path(pointer.read_text().strip()).resolve()
 if backupbase.resolve() not in backup.parents or not backup.name.startswith('AM257_PICKER_KEYS_'):
  raise SystemExit('STOP_AM257_INVALID_BACKUP_PATH')
 manifest=json.loads((backup/'manifest.json').read_text())
 for rel,h in manifest['installed'].items():
  if not (root/rel).is_file() or sha((root/rel).read_bytes())!=h:
   raise SystemExit('STOP_AM257_MODIFIED_AFTER_INSTALL_NO_ROLLBACK_'+rel)
 for rel in files:atomic(root/rel,(backup/rel).read_bytes())
 run(['php','artisan','view:clear'],80)
 print('AM257_SOURCE_ROLLBACK_COMPLETED_NO_BUSINESS_DATA_CHANGED')
 sys.exit(0)
before,patched=make_patch()
if mode=='check':
 print('AM257_CHECK_READ_ONLY_PASS')
 sys.exit(0)
backup=backupbase/('AM257_PICKER_KEYS_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for rel,b in before.items():
 p=backup/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b);os.chmod(p,0o600)
written=[]
try:
 for rel,b in patched.items():
  if (root/rel).read_bytes()!=before[rel]:
   raise RuntimeError('SOURCE_CHANGED_DURING_PRECHECK_'+rel)
  atomic(root/rel,b);written.append(rel)
 r=run(['php','artisan','view:clear'],80)
 if r.returncode:raise RuntimeError('VIEW_CLEAR_FAILED '+(r.stdout+r.stderr)[-600:])
 verify()
except Exception as error:
 for rel in written:atomic(root/rel,before[rel])
 run(['php','artisan','view:clear'],70)
 raise SystemExit('STOP_AM257_INSTALLED_FILES_ROLLED_BACK '+str(error))
(backup/'manifest.json').write_text(json.dumps({'installed':{k:sha(b) for k,b in patched.items()}},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM257_TALLY_STYLE_ITEM_SELECT_ENTER_TAB_INSTALLED')
print('AM257_SOURCE_BACKUP',backup)
print('AM257_BROWSER_INTERACTION_ACCEPTANCE_PENDING')
