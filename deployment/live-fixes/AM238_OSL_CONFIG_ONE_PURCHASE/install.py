#!/usr/bin/env python3
"""AM238: Config OSL Master tile, no duplicate OSL purchase, simplify notices.
Usage: install.py check|apply|rollback STAGING_ROOT.
Strict anchors, PHP/Blade/JS preflight, backup and rollback. NO database writes.
"""
from pathlib import Path
import sys, os, re, json, hashlib, tempfile, subprocess, shutil
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM238_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM238_STOP_NOT_STAGING')
paths={
    'config':'resources/views/dashboard/configuration.blade.php',
    'view':'resources/views/purchases/osl-create.blade.php',
    'controller':'app/Http/Controllers/Web/AdminPurchasePageController.php',
    'service':'app/Services/PurchaseService.php',
    'picker':'public/js/am232-osl-purchase.js',
}
files={key:root/path for key,path in paths.items()}
backup=root/'storage/app/am-backups/AM238_OSL_CONFIG_ONE_PURCHASE'
manifest=backup/'hashes.json'
mark='AM238_OSL_CONFIG_ONE_PURCHASE'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
    fd,t=tempfile.mkstemp(prefix='.am238_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(t,p.stat().st_mode&0o777)
        os.replace(t,p)
    finally:
        if os.path.exists(t):os.unlink(t)
if any(not f.is_file() or f.is_symlink() for f in files.values()):
    raise SystemExit('AM238_STOP_SOURCE_MISSING')
original={n:read(p) for n,p in files.items()}
if mode=='rollback':
    if not manifest.is_file():raise SystemExit('AM238_STOP_BACKUP_MISSING')
    hashes=json.loads(read(manifest))
    if any(sha(original[n])!=hashes['after'][n] for n in files):
        raise SystemExit('AM238_STOP_CONCURRENT_CHANGE')
    for n,p in files.items():atomic(p,read(backup/(n+'.before')))
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    print('AM238_ROLLBACK_COMPLETE');sys.exit(0)
if all(mark in original[n] for n in files):
    print('AM238_ALREADY_INSTALLED');sys.exit(0)
if any(mark in original[n] for n in files):raise SystemExit('AM238_STOP_PARTIAL_INSTALL')
if 'AM233_OSL_MASTER_REGISTER' not in read(root/'routes/web.php'):
    raise SystemExit('AM238_STOP_REGISTER_NOT_PRESENT')
new=dict(original)
def replace(n,before,after,label):
    count=new[n].count(before)
    if count!=1:raise SystemExit('AM238_STOP_ANCHOR_'+label+'_'+str(count))
    new[n]=new[n].replace(before,after,1)
# 1 Configuration: OSL Master beside Parts and ROT master tiles.
labour=""" <a class="module-tile" href="{{ route('erp.labour') }}"><div class="tile-icon">🔧</div><h3>ROT / Labour Master</h3><p>ROT code, labour description, standard time and rates.</p></a>"""
newTile=""" {{-- AM238_OSL_CONFIG_ONE_PURCHASE: OSL master is configured alongside Parts and ROT. --}}
 <a class="module-tile" href="{{ route('erp.osl-masters.index') }}"><div class="tile-icon">🧰</div><h3>OSL Master</h3><p>Outside Labour service code, description, default cost, GST and active status.</p></a>"""
replace('config',labour,labour+'\n'+newTile,'CONFIG_MASTER_TILE')
# 2 Remove ONLY two informational panels; preserve GST basis controls and math.
multiline="""<div class="osl-help"><strong>Multi-line OSL:</strong> ek supplier bill me multiple outside jobs add kijiye. Har row me Job Card / Vehicle, OSL work, Vendor Rate aur Customer Billing Rate select kijiye. Save hone par existing Job Card Labour billing linkage use hogi.</div>"""
gstText="""<div class="osl-help" style="border-left:4px solid #178356">
<strong>Supplier GST Mode, per line:</strong> <b>Extra</b> = GST added to taxable; <b>Included</b> = entered Vendor Rate already includes GST; <b>Not Applicable</b> = 0 GST.
Customer Rate &amp; Customer GST are separate JC billing settings. Server will recalculate before save.
</div>"""
replace('view',multiline,'{{-- AM238_OSL_CONFIG_ONE_PURCHASE: multi-line information panel removed. --}}','TOP_NOTICE')
replace('view',gstText,'','GST_NOTICE')
# Register now belongs to Configuration, not as a toolbar item in Purchase.
linkStart='<script id="am232-osl-master-register-link">'
start=new['view'].find(linkStart)
if start<0 or new['view'].count(linkStart)!=1:raise SystemExit('AM238_STOP_MASTER_TOOLBAR_ANCHOR')
end=new['view'].find('</script>',start)
if end<start:raise SystemExit('AM238_STOP_MASTER_LINK_SCRIPT_END')
new['view']=new['view'][:start]+new['view'][end+len('</script>'):]
# 3 Active JC OSL billing lines only: purchased lines must not be offered again.
needle="""            ->where('billing_included',1)
            ->orderBy('id')->get(['id','job_card_id','description','standard_hours','labour_rate','gst_percent','total']);"""
replacement="""            ->where('billing_included',1)
            // AM238_OSL_CONFIG_ONE_PURCHASE: list only not-yet-purchased OSL lines.
            ->whereNotExists(function($q){
                $q->selectRaw('1')->from('workshop_purchases as purchased')
                  ->whereColumn('purchased.labour_line_id','job_card_labour.id')
                  ->whereColumn('purchased.job_card_id','job_card_labour.job_card_id')
                  ->where('purchased.purchase_type','OSL')
                  ->whereRaw("COALESCE(purchased.record_status,'ACTIVE') <> 'DELETED'")
                  ->whereNull('purchased.deleted_at');
            })
            ->orderBy('id')->get(['id','job_card_id','description','standard_hours','labour_rate','gst_percent','total']);"""
replace('controller',needle,replacement,'ONLY_UNPURCHASED_JC_LINES')
# 4 Hard rule in locked transaction. Fallback description matching is allowed ONLY
# when uniquely identifying an EXISTING JC OSL line; otherwise fail. Never
# auto-create a new customer billing line via supplier purchase.
anchor="""                if($matched->count()===1)$lineId=(int)$matched->first()->id;
            }
            if($lineId && DB::table('workshop_purchases')"""
updated="""                if($matched->count()===1)$lineId=(int)$matched->first()->id;
            }
            // AM238_OSL_CONFIG_ONE_PURCHASE: supplier inward cannot create a new
            // JC OSL billing line. Add it via Estimate / Job Card first.
            if($lineId<=0){
                throw ValidationException::withMessages(['labour_line_id'=>
                    'First add this OSL work to the Job Card (via Estimate or JC). Every JC OSL line can be purchased only once.']);
            }
            if($lineId && DB::table('workshop_purchases')"""
replace('service',anchor,updated,'REQUIRE_EXISTING_OSL_LINE')
# Active JC OSL line must be in billing (planned/issued) workspace.
replace('service',
"""            if ($lineId && (!$billingLine || strtoupper(trim((string)$billingLine->mechanic_name)) !== 'OSL')) {""",
"""            if ($lineId && (!$billingLine || strtoupper(trim((string)$billingLine->mechanic_name)) !== 'OSL'
                || (int)($billingLine->billing_included ?? 0)!==1)) {""",
 'BILLING_INCLUDED_VALIDATION')
# Add one explicit marker to JS; remove misleading option to create new billing from Purchase.
oldText="No OSL line transferred to this JC. Release approved Estimate → Send to Job Card, or select Master to create a new customer OSL line."
newText="No unpurchased JC OSL work available. Add a new OSL to Estimate/Job Card first; already-purchased OSL lines cannot be purchased twice."
replace('picker',oldText,newText,'NO_OSL_LINE_MESSAGE')
replace('picker',
"""   master.hidden=false;master.style.display='';
   note.textContent='No unpurchased JC OSL work available.""",
"""   master.hidden=true;master.style.display='none';
   // AM238_OSL_CONFIG_ONE_PURCHASE: no fallback Master-to-new-billing line.
   billing.value='';billing.dispatchEvent(new Event('change',{bubbles:true}));
   note.textContent='No unpurchased JC OSL work available.""",
 'NO_MASTER_FALLBACK')
for n in files:
    if mark not in new[n]:raise SystemExit('AM238_STOP_PATCH_MARKER_'+n)
for label,needle in [
 ('PRESERVE_GST_MODE', 'name="lines[__INDEX__][gst_mode]"'),
 ('PRESERVE_ADD_LINE','id="add-osl-line"'),
 ('PRESERVE_VENDOR','name="vendor_id"'),
 ('PRESERVE_JC','name="lines[__INDEX__][job_card_id]"'),
 ('PRESERVE_OSL_SELECTOR','am232-osl-purchase-inline')
]:
    if needle not in new['view']:raise SystemExit('AM238_STOP_'+label)
with tempfile.TemporaryDirectory(prefix='am238qa_') as d:
    folder=Path(d)
    for n in ('controller','service'):
        f=folder/(n+'.php');f.write_text(new[n],encoding='utf8',errors='surrogateescape')
        p=subprocess.run(['php','-l',str(f)],cwd=root,capture_output=True,text=True,timeout=35)
        if p.returncode:raise SystemExit('AM238_STOP_PHP_LINT_'+n+' '+(p.stdout+p.stderr)[-400:])
    node=shutil.which('node')
    if node:
        f=folder/'purchase.js';f.write_text(new['picker'],encoding='utf8',errors='surrogateescape')
        p=subprocess.run([node,'--check',str(f)],cwd=root,capture_output=True,text=True,timeout=35)
        if p.returncode:raise SystemExit('AM238_STOP_JS_LINT '+p.stderr[-400:])
    h=folder/'compile.php'
    h.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    for n in ('view','config'):
        f=folder/(n+'.blade.php');f.write_text(new[n],encoding='utf8',errors='surrogateescape')
        out=folder/(n+'.compiled.php')
        p=subprocess.run(['php',str(h),str(root),str(f),str(out)],cwd=root,capture_output=True,text=True,timeout=50)
        if p.returncode:raise SystemExit('AM238_STOP_BLADE_COMPILE_'+n+' '+p.stderr[-450:])
        p=subprocess.run(['php','-l',str(out)],cwd=root,capture_output=True,text=True,timeout=35)
        if p.returncode:raise SystemExit('AM238_STOP_BLADE_LINT_'+n+' '+(p.stdout+p.stderr)[-400:])
print('AM238_ALL_PHP_BLADE_JS_PREFLIGHT_PASS')
if mode=='check':
    print('AM238_CHECK_ONLY_NO_SOURCE_OR_DB_WRITES');sys.exit(0)
if backup.exists():raise SystemExit('AM238_STOP_BACKUP_COLLISION')
if any(read(files[n])!=original[n] for n in files):
    raise SystemExit('AM238_STOP_CONCURRENT_SOURCE_CHANGE')
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for n in files:
    p=backup/(n+'.before');p.write_text(original[n],encoding='utf8',errors='surrogateescape');os.chmod(p,0o600)
try:
    for n,p in files.items():atomic(p,new[n])
    manifest.write_text(json.dumps({'after':{n:sha(new[n]) for n in files}}),encoding='ascii');os.chmod(manifest,0o600)
    for command in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        p=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=85)
        if p.returncode:raise RuntimeError('AM238_VIEW_CACHE '+p.stderr[-450:])
except BaseException as ex:
    for n,p in files.items():atomic(p,original[n])
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=30)
    raise SystemExit('AM238_APPLY_FAILED_ROLLED_BACK '+str(ex))
print('AM238_STAGING_SOURCE_APPLIED_NO_FINANCIAL_DB_WRITES')
print('AM238_BROWSER_JC_LINE_POSTING_QA_PENDING')
