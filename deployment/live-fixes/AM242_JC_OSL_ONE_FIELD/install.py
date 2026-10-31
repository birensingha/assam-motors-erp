#!/usr/bin/env python3
"""AM242: one searchable OSL service field + Create, no supplier purchases in Add OSL Billing.
Usage: install.py check|apply|rollback STAGING_ROOT.
Changes source only (Job Card Blade + one JS asset), no DB writes, full guarded rollback.
"""
from pathlib import Path
import os,sys,hashlib,json,tempfile,subprocess,shutil,re
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM242_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM242_STOP_NOT_STAGING')
patch=Path(__file__).resolve().parent
view=root/'resources/views/job-cards/show.blade.php'
js=root/'public/js/am242-jc-osl-one-field.js'
backup=root/'storage/app/am-backups/AM242_JC_OSL_ONE_FIELD'
manifest=backup/'hashes.json';marker='AM242_JC_OSL_ONE_FIELD'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def digest(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 p.parent.mkdir(parents=True,exist_ok=True)
 fd,t=tempfile.mkstemp(prefix='.am242_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as w:w.write(s)
  os.chmod(t,p.stat().st_mode&0o777 if p.exists() else 0o644)
  os.replace(t,p)
 finally:
  if os.path.exists(t):os.unlink(t)
if not view.is_file() or view.is_symlink() or not (patch/'one-field.js').is_file():
 raise SystemExit('AM242_STOP_MISSING_SOURCE')
old=read(view);asset=read(patch/'one-field.js')
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM242_STOP_NO_BACKUP')
 hashes=json.loads(read(manifest))
 if digest(old)!=hashes['view_after'] or not js.is_file() or digest(read(js))!=hashes['js_after']:
  raise SystemExit('AM242_STOP_CONCURRENT_MODIFICATION')
 atomic(view,read(backup/'show.blade.php.before'))
 js.unlink()
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=35)
 subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,timeout=90)
 print('AM242_ROLLBACK_RESTORED_JOB_CARD_UI');sys.exit(0)
if marker in old:
 if js.is_file() and old.count(marker)==1 and digest(read(js))==digest(asset):
  print('AM242_ALREADY_INSTALLED');sys.exit(0)
 raise SystemExit('AM242_STOP_PARTIAL_INSTALL')
if js.exists():raise SystemExit('AM242_STOP_ASSET_ALREADY_EXISTS')
if 'AM241_JC_OSL_MASTER_LIVE' not in old or 'am241-jc-osl-master-live' not in old:
 raise SystemExit('AM242_STOP_LIVE_MASTER_PREREQUISITE_MISSING')
if 'AM146_OSL_MASTER' not in old or 'am91-osl-dialog' not in old:
 raise SystemExit('AM242_STOP_MASTER_OR_DIALOG_MISSING')
# Remove only previously purchased supplier panel from the NEW customer OSL form.
# Accept whitespace-indentation differences across the staged Blade patch history.
begin_matches=list(re.finditer(r"(?m)^[ \t]*if\(!editing\)\{\r?\n[ \t]*var toolsTemplate=document\.getElementById\('am93-osl-tools-template'\);",old))
finish_matches=list(re.finditer(r"(?m)^[ \t]*var form=document\.createElement\('form'\);form\.className='am91-osl-form';",old))
if len(begin_matches)!=1 or len(finish_matches)!=1:
 raise SystemExit('AM242_STOP_PURCHASE_INJECT_ANCHOR_COUNT_'+str(len(begin_matches))+'_'+str(len(finish_matches)))
a=begin_matches[0].start();b=finish_matches[0].start()
if b<=a:raise SystemExit('AM242_STOP_PURCHASE_INJECTION_ORDER')
block=old[a:b]
required=[
 "toolsBlock.className='am93-tools-in-dialog'",
 "toolsHeading.textContent='Select Linked OSL / Supplier Purchase Options'",
 "document.importNode(toolsTemplate.content,true)",
 "button[data-am93-use-osl]",
 "    }\n"]
if any(item not in block for item in required) or len(block)>5500:
 raise SystemExit('AM242_STOP_UNEXPECTED_SUPPLIER_PANEL_BLOCK')
new=old[:a]+"    // AM242_JC_OSL_ONE_FIELD: supplier purchases are NOT part of Add OSL Billing.\n"+old[b:]
# Keep only one visible combo; original OSL Master select and service description
# are kept as hidden, named native fields so legacy Laravel validation and pricing
# routines continue to work without changes to any Purchase, JC or ledger records.
anchor='<style id="am146-osl-styles">'
style="""<style id="am242-osl-one-field-style">
/* Single searchable OSL service and adjacent create button. */
.am91-osl-dialog form.am91-osl-form>label:has(>input[name="description"]){display:none!important}
.am91-osl-dialog .am146-master-row>label:has(>select[name="osl_master_id"]){display:none!important}
.am91-osl-dialog .am146-master-row{display:flex!important;flex-wrap:wrap!important;align-items:flex-end!important;gap:8px!important}
.am91-osl-dialog .am242-combo{position:relative;flex:1 1 330px;min-width:0}
.am91-osl-dialog .am242-label{display:flex!important;flex-direction:column;gap:5px}
.am91-osl-dialog .am242-search{width:100%!important;min-height:37px!important;border-radius:7px!important;
border:1px solid #acc6de!important;background:#fff!important;padding:8px 10px!important;font-size:12px!important}
.am91-osl-dialog .am242-options[hidden]{display:none!important}
.am91-osl-dialog .am242-options{position:absolute;top:100%;left:0;right:0;z-index:1000000;
background:white;border:1px solid #aac8df;border-radius:7px;box-shadow:0 12px 25px #12395830;
max-height:240px;overflow-y:auto;padding:3px;box-sizing:border-box}
.am91-osl-dialog .am242-option{display:flex;width:100%;gap:8px;align-items:center;text-align:left;
padding:9px;border:0;border-bottom:1px solid #e7edf4;background:white;cursor:pointer;color:#244f73}
.am91-osl-dialog .am242-option:hover,.am91-osl-dialog .am242-option.active{background:#eaf5ff}
.am91-osl-dialog .am242-option strong{font-size:11px;flex:1 1 auto;font-weight:750}
.am91-osl-dialog .am242-option small{font-size:10px;color:#67829b}
.am91-osl-dialog .am242-empty,.am91-osl-dialog .am242-refresh{font-size:11px;padding:9px;color:#45647e}
.am91-osl-dialog .am242-refresh{width:100%;text-align:left;cursor:pointer;background:#f0f7fd;
border:0;border-radius:5px;font-weight:700;color:#174c75}
.am91-osl-dialog .am242-help{font-size:10px;color:#52738e;margin-top:5px}
.am91-osl-dialog .am146-new-master{white-space:nowrap!important;flex:0 0 auto!important}
.am91-osl-dialog .am241-refresh-master[hidden]{display:none!important}
@media(max-width:580px){.am91-osl-dialog .am242-combo{flex-basis:100%}
.am91-osl-dialog .am146-new-master{width:100%}}
</style>
<script src="{{ asset('js/am242-jc-osl-one-field.js') }}?v=AM242-20261031" defer></script>
"""
if new.count(anchor)!=1:raise SystemExit('AM242_STOP_STYLES_ANCHOR_COUNT_'+str(new.count(anchor)))
new=new.replace(anchor,style+anchor,1)
if new.count(marker)!=1 or new.count('am242-osl-one-field-style')!=1 or new.count('am242-jc-osl-one-field.js')!=1:
 raise SystemExit('AM242_STOP_OUTPUT_INVARIANTS')
if old.count('am93-osl-tools-template')<=new.count('am93-osl-tools-template'):
 raise SystemExit('AM242_STOP_LINKED_SUPPLIER_INJECTION_NOT_REMOVED')
for source in ('am91-osl-form','am146-osl-calculations','am241-jc-osl-master-live','form.elements.namedItem','Select Linked OSL'):
 if source in ('Select Linked OSL',):continue
 if source not in new:raise SystemExit('AM242_STOP_REQUIRED_FORM_SOURCE_LOST_'+source)
if 'AM242_JC_ONE_OSL_FIELD' not in asset or '</script' in asset.lower():raise SystemExit('AM242_STOP_INVALID_JS_SOURCE')
with tempfile.TemporaryDirectory(prefix='am242qa_') as d:
 t=Path(d)
 n=shutil.which('node')
 if n:
  p=t/'one-field.js';p.write_text(asset,encoding='utf8',errors='surrogateescape')
  test=subprocess.run([n,'--check',str(p)],cwd=root,text=True,capture_output=True,timeout=35)
  if test.returncode:raise SystemExit('AM242_STOP_JS_LINT '+test.stderr[-450:])
 src=t/'show.blade.php';src.write_text(new,encoding='utf8',errors='surrogateescape')
 out=t/'show.compiled.php';helper=t/'compile.php'
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 test=subprocess.run(['php',str(helper),str(root),str(src),str(out)],cwd=root,text=True,capture_output=True,timeout=65)
 if test.returncode:raise SystemExit('AM242_STOP_BLADE_COMPILE '+test.stderr[-500:])
 test=subprocess.run(['php','-l',str(out)],cwd=root,text=True,capture_output=True,timeout=35)
 if test.returncode:raise SystemExit('AM242_STOP_COMPILED_PHP_LINT '+(test.stdout+test.stderr)[-500:])
 if 'am242-jc-osl-one-field.js' not in read(out):raise SystemExit('AM242_STOP_COMPILED_JS_URL_MISSING')
print('AM242_BLADE_JS_SOURCE_PREFLIGHT_PASS')
if mode=='check':
 print('AM242_CHECK_ONLY_NO_SOURCE_OR_DB_WRITES');sys.exit(0)
if backup.exists() or read(view)!=old:raise SystemExit('AM242_STOP_BACKUP_COLLISION_OR_SOURCE_RACE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
b=backup/'show.blade.php.before'
b.write_text(old,encoding='utf8',errors='surrogateescape');os.chmod(b,0o600)
try:
 atomic(view,new);atomic(js,asset)
 manifest.write_text(json.dumps({'view_after':digest(new),'js_after':digest(asset)}),encoding='ascii')
 os.chmod(manifest,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  res=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
  if res.returncode:raise RuntimeError('AM242_STOP_CACHE '+res.stderr[-500:])
except BaseException as ex:
 atomic(view,old)
 if js.exists():js.unlink()
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 raise SystemExit('AM242_APPLY_FAILED_AND_ROLLED_BACK '+str(ex))
print('AM242_STAGING_ONE_OSL_BOX_DEPLOYED_NO_FINANCIAL_WRITES')
print('AM242_AUTHENTICATED_MODAL_BROWSER_QA_PENDING')
