#!/usr/bin/env python3
"""AM218: make sidebar Payment Voucher find vendor / Parts / OSL source.
check|apply|rollback STAGING_ROOT. Changes Blade and one frontend JS only.
No payment posting, vendor DB changes, or ledger operations.
"""
from pathlib import Path
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM218_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM218_STOP_NOT_STAGING')
view=root/'resources/views/payments/create.blade.php'
target=root/'public/js/am218-payment-source-search.js'
package=Path(__file__).resolve().parent
payload=package/'payment-source-search.js'
backup=root/'storage/app/am-backups/AM218_PAYMENT_SIDENAV_OSL'
manifest=backup/'manifest.json'
expected='0d9f2f1559dc59d2154411f1cc37163b9f2afaeaefb47be9dd2eb5acc988b83a'
marker='AM218_PAYMENT_SIDENAV_OSL'
def digest(s): return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def read(p):return p.read_text('utf8',errors='surrogateescape')
def atomic(p,s):
    fd,t=tempfile.mkstemp(dir=p.parent,prefix='.am218_')
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f: f.write(s)
        if p.exists():os.chmod(t,p.stat().st_mode&0o777)
        else:os.chmod(t,0o644)
        os.replace(t,p)
    finally:
        if os.path.exists(t):os.unlink(t)
if not view.is_file() or not payload.is_file():raise SystemExit('AM218_STOP_INPUTS_MISSING')
source=read(view);js=read(payload)
if 'AM218: one search for existing PART + OSL' not in js:
    raise SystemExit('AM218_STOP_WRONG_JS')
if mode=='rollback':
    if not manifest.is_file():raise SystemExit('AM218_STOP_BACKUP_MISSING')
    m=json.loads(read(manifest))
    if digest(source)!=m['view_sha_after'] or not target.is_file() or digest(read(target))!=m['js_sha']:
        raise SystemExit('AM218_STOP_SOURCE_MODIFIED_SINCE_INSTALL')
    atomic(view,read(backup/'create.blade.php.before'))
    target.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    print('AM218_ROLLBACK_RESTORED_VIEW_AND_REMOVED_OWN_JS')
    sys.exit(0)
if marker in source:
    if target.is_file() and marker in source and digest(read(target))==digest(js):
        print('AM218_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM218_STOP_PARTIAL_OR_CHANGED_INSTALL')
if digest(source)!=expected:raise SystemExit('AM218_STOP_VIEW_SHA_MISMATCH_FOR_SAFETY')
if target.exists() or backup.exists():raise SystemExit('AM218_STOP_EXISTING_TARGET_OR_BACKUP')
anchors=[
 '<section class="p133-group"><h2>1. Payment Against / Vendor</h2>\n<div class="p133-grid">',
 '</style>',
 '<script src="{{ asset(\'js/payment-voucher-multi.js\') }}?v=133" defer></script>',
]
for a in anchors:
    if source.count(a)!=1:raise SystemExit('AM218_STOP_ANCHOR_'+str(anchors.index(a)))
header='''<section class="p133-group"><h2>1. Payment Against / Vendor</h2>
{{-- AM218_PAYMENT_SIDENAV_OSL: generic Payment entry finds both Parts and OSL supplier payables. --}}
<div id="am218-search-wrap" class="am218-wrap">
 <label class="p133-label" for="am218-source-search">Search Vendor / Purchase Bill — Parts + OSL</label>
 <input id="am218-source-search" class="am218-input" type="search"
   autocomplete="off" placeholder="Type vendor name, OSL / purchase number, JC or vehicle"
   role="combobox" aria-autocomplete="list" aria-haspopup="listbox"
   aria-controls="am218-source-results" aria-expanded="false">
 <div id="am218-source-results" class="am218-results" role="listbox"
   aria-label="Matching unpaid Parts and OSL purchase bills" hidden></div>
 <div id="am218-source-choice" class="am218-help" aria-live="polite">
   Search outstanding Parts and OSL bills together. Select one to fill the correct reference and vendor.
 </div>
</div>
<div class="p133-grid">'''
style='''/* AM218 unified supplier bill picker, menu floats instead of increasing form height. */
.am218-wrap{position:relative;margin:0 0 13px;z-index:8}
.am218-input{box-sizing:border-box;display:block;min-height:42px;width:100%;padding:9px 12px;
 border:1px solid #96b9d7;border-radius:8px;background:#fff;color:#143c62;
 font-size:13px;font-weight:500}
.am218-input:focus{outline:2px solid #79aee1}
.am218-help{margin-top:6px;color:#456d8d;font-size:11px;line-height:1.55}
.am218-results[hidden]{display:none!important}
.am218-results{position:absolute;left:0;right:0;top:calc(100% - 19px);z-index:50;
 border:1px solid #9fbeda;border-radius:9px;background:white;
 max-height:min(46vh,330px);overflow-y:auto;box-shadow:0 15px 35px #193f633d;padding:4px}
.am218-result{width:100%;display:flex;flex-direction:column;gap:3px;text-align:left;
 background:#fff;border:0;border-radius:6px;padding:10px 11px;color:#163e62;cursor:pointer}
.am218-result+.am218-result{border-top:1px solid #edf2f7}
.am218-result strong{font-size:12px}
.am218-result span{font-size:11px;color:#456783;overflow-wrap:anywhere}
.am218-result small{font-size:11px;font-weight:750;color:#176641}
.am218-result:hover,.am218-result[aria-selected="true"],.am218-result:focus-visible{
 background:#e7f2fd;outline:1px solid #a4c5e4}
.am218-empty{padding:11px;font-size:12px;color:#56738d}
'''
changed=source.replace(anchors[0],header,1).replace(anchors[1],style+'</style>',1)
changed=changed.replace(anchors[2],anchors[2]+
 "\n<script src=\"{{ asset('js/am218-payment-source-search.js') }}?v=218\" defer></script>",1)
if changed.count(marker)!=1 or digest(changed)==digest(source):
    raise SystemExit('AM218_STOP_CANDIDATE_INVARIANT')
# Compile candidate through Laravel's Blade compiler, lint compiled PHP.
with tempfile.TemporaryDirectory(prefix='am218qa_') as t:
    folder=Path(t)
    inp=folder/'create.blade.php';compiled=folder/'compiled.php';compiler=folder/'compile.php'
    inp.write_text(changed,encoding='utf8',errors='surrogateescape')
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    proc=subprocess.run(['php',str(compiler),str(root),str(inp),str(compiled)],
                        cwd=root,capture_output=True,text=True,timeout=55)
    if proc.returncode:raise SystemExit('AM218_STOP_BLADE_COMPILE '+proc.stderr[-350:])
    proc=subprocess.run(['php','-l',str(compiled)],cwd=root,capture_output=True,text=True,timeout=35)
    if proc.returncode:raise SystemExit('AM218_STOP_BLADE_PHP_LINT '+(proc.stdout+proc.stderr)[-350:])
    node=__import__('shutil').which('node')
    if node:
        proc=subprocess.run([node,'--check',str(payload)],cwd=root,capture_output=True,text=True,timeout=35)
        if proc.returncode:raise SystemExit('AM218_STOP_JS_SYNTAX '+proc.stderr[-350:])
print('AM218_BLADE_PHP_AND_JS_PREFLIGHT_PASS')
print('AM218_PARTS_AND_OSL_UNIFIED_SEARCH_READY')
if mode=='check':
    print('AM218_CHECK_PASS_NO_WRITES');sys.exit(0)
if read(view)!=source:raise SystemExit('AM218_STOP_VIEW_CHANGED_DURING_CHECK')
backup.mkdir(parents=True,exist_ok=False)
os.chmod(backup,0o700)
(backup/'create.blade.php.before').write_text(source,encoding='utf8',errors='surrogateescape')
os.chmod(backup/'create.blade.php.before',0o600)
try:
    atomic(view,changed)
    atomic(target,js)
    atomic(manifest,json.dumps({'view_sha_before':digest(source),
                                'view_sha_after':digest(changed),'js_sha':digest(js)}))
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        proc=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if proc.returncode:raise RuntimeError('VIEW_CACHE_ERROR '+proc.stderr[-300:])
except BaseException as e:
    atomic(view,source)
    if target.is_file() and digest(read(target))==digest(js):target.unlink()
    if manifest.is_file():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    raise SystemExit('AM218_APPLY_FAILED_ROLLED_BACK '+str(e))
print('AM218_STAGING_UI_APPLIED_NO_FINANCIAL_DATA_WRITES')
