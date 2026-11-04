#!/usr/bin/env python3
"""AM239: Register header CTA layout only. No DB changes.
Usage: python3 apply.py check|apply STAGING_ROOT
       python3 apply.py rollback STAGING_ROOT BACKUP_DIRECTORY
"""
from pathlib import Path
import re,sys,os,shutil,tempfile,datetime
if len(sys.argv) not in (3,4) or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: python3 apply.py check|apply ROOT or rollback ROOT BACKUP')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM239_STOP_NOT_STAGING')
view=root/'resources/views/purchases/index.blade.php'
if not view.is_file():raise SystemExit('AM239_STOP_VIEW_MISSING')
if mode=='rollback':
    if len(sys.argv)!=4:raise SystemExit('AM239_STOP_BACKUP_ARGUMENT_MISSING')
    b=Path(sys.argv[3]).expanduser().resolve()
    if b.parent != root/'storage/app/am-backups' or not b.name.startswith('AM239_REGISTER_BUTTONS_') or not (b/'index.blade.php').is_file():
        raise SystemExit('AM239_STOP_INVALID_BACKUP')
    shutil.copy2(b/'index.blade.php',view)
    print('AM239_ROLLBACK_COMPLETE')
    sys.exit(0)
source=view.read_text('utf8','surrogateescape')
marker='AM239_RECON_BUTTON_ONE_ROW'
if marker in source:
    print('AM239_ALREADY_INSTALLED')
    sys.exit(0)
route="route('erp.purchases.reconciliation')"
if source.count(route)!=1:raise SystemExit('AM239_STOP_RECON_LINK_COUNT_'+str(source.count(route)))
if source.count('class="p18-actions"')!=1 or source.count('class="p18-create p18-osl"')!=1 or source.count('class="p18-create p18-parts"')!=1:
    raise SystemExit('AM239_STOP_HEADER_STRUCTURE_MISMATCH')
old=r'<a\b(?=[^>]*href="\{\{\s*route\(\s*\x27erp\.purchases\.reconciliation\x27\s*\)\s*\}\}")[^>]*>[^<]*</a>'
# Existing AM235 white ghost link only; never replace other links.
matches=list(re.finditer(old,source,re.DOTALL))
if len(matches)!=1 or 'AM235_PURCHASE_RECONCILIATION' not in matches[0].group(0):
    raise SystemExit('AM239_STOP_EXPECTED_OLD_WHITE_RECON_BUTTON_NOT_FOUND')
new_link='''<a class="p18-create p18-recon" href="{{ route('erp.purchases.reconciliation') }}"><span class="p18-plus" aria-hidden="true">≡</span>Parts Reconciliation</a>'''
source=source[:matches[0].start()]+new_link+source[matches[0].end():]
# Inline style must reside in the Purchase Register view where the existing P18 styles are.
css='''\n/* AM239_RECON_BUTTON_ONE_ROW: matches the two Purchase CTAs */\n
.p18-purchase-register .p18-actions{
  display:grid!important;
  grid-template-columns:repeat(3,minmax(0,1fr))!important;
  gap:9px!important;
  width:min(100%,660px)!important;
  min-width:0!important;
  align-items:stretch!important;
}
.p18-purchase-register .p18-actions .p18-create{
  min-width:0!important;
  min-height:45px!important;
  box-sizing:border-box!important;
  padding:9px 10px!important;
  text-align:center!important;
  font-size:12px!important;
  border-radius:11px!important;
  white-space:nowrap!important;
}
.p18-purchase-register .p18-actions .p18-recon{
  background:linear-gradient(135deg,#e34b61,#b92e48)!important;
  color:#fff!important;
  border:1px solid #b92e48!important;
}
.p18-purchase-register .p18-actions .p18-recon:hover,
.p18-purchase-register .p18-actions .p18-recon:focus-visible{
  background:linear-gradient(135deg,#f3566b,#c73451)!important;
  color:#fff!important;
}
@media(max-width:1150px){
  .p18-purchase-register .p18-hero{flex-wrap:wrap!important}
  .p18-purchase-register .p18-actions{width:100%!important;max-width:none!important}
}
@media(max-width:650px){
  .p18-purchase-register .p18-actions .p18-create{
    padding:9px 6px!important;
    font-size:10px!important;
    white-space:normal!important;
    line-height:1.25!important;
    gap:5px!important;
  }
  .p18-purchase-register .p18-actions .p18-plus{
    flex:0 0 19px!important;
    width:19px!important;
    height:19px!important;
    font-size:16px!important;
  }
}
'''
opening=source.index('<style>') if '<style>' in source else -1
closing=source.find('</style>',opening+len('<style>')) if opening>=0 else -1
if opening<0 or closing<0:raise SystemExit('AM239_STOP_NO_VIEW_STYLE_BLOCK')
source=source[:closing]+css+source[closing:]
if source.count(marker)!=1 or source.count(route)!=1 or source.count('p18-create p18-recon')!=1:
    raise SystemExit('AM239_STOP_OUTPUT_MISMATCH')
print('AM239_PREFLIGHT_PASS')
if mode=='check':
    print('AM239_READ_ONLY_CHECK_COMPLETE')
    sys.exit(0)
backup=root/'storage/app/am-backups'/('AM239_REGISTER_BUTTONS_'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S'))
backup.mkdir(mode=0o750,parents=True,exist_ok=False)
shutil.copy2(view,backup/'index.blade.php')
tmp=None
try:
    with tempfile.NamedTemporaryFile(mode='w',encoding='utf8',errors='surrogateescape',dir=view.parent,prefix='.am239_',delete=False) as f:
        tmp=Path(f.name)
        f.write(source)
    os.chmod(tmp,view.stat().st_mode&0o777)
    os.replace(tmp,view)
except Exception:
    if tmp:tmp.unlink(missing_ok=True)
    shutil.copy2(backup/'index.blade.php',view)
    raise
print('AM239_APPLIED')
print('AM239_BACKUP='+str(backup))
print('AM239_ONLY_PURCHASE_REGISTER_VIEW_CHANGED')
