#!/usr/bin/env python3
"""FIX74: replace top navigation ONLY. Invoice schema via versioned migration.
No changes to JC invoicing controller, invoices, CN or ledger values.
"""
from pathlib import Path
import sys,os,hashlib,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();assert (root/'artisan').is_file()
view=root/'resources/views/layouts/erp.blade.php'
raw=view.read_bytes()
expected='33239658f21ab98acbfe23a0347ad6383889266f646379e32a54e673081b7592'
print('FIX74_LAYOUT_SHA',hashlib.sha256(raw).hexdigest())
if raw!=view.read_bytes():raise SystemExit('FIX74_SOURCE_RACE')
text=raw.decode('utf8',errors='surrogateescape')
marker='FIX74_CLEAN_BLUE_ERP_TOPBAR'
if marker in text:
 print('FIX74_HEADER_ALREADY_APPLIED');sys.exit(0)
if hashlib.sha256(raw).hexdigest()!=expected:raise SystemExit('FIX74_HEADER_SOURCE_CHANGED_ABORT')
topStart='    <div class="topbar-title">@yield(\\'topbar_title\\',\\'ASSAM MOTORS\\')</div>'
navStart='    <nav class="am50-top-shortcuts" aria-label="Workshop finance shortcuts">'
navEnd='    </nav>'
if text.count(topStart)!=1 or text.count(navStart)!=1:raise SystemExit('FIX74_HEADER_ANCHOR_MISSING')
start=text.index(navStart);end=text.index(navEnd,start)+len(navEnd)
oldNav=text[start:end]
if not all(t in oldNav for t in ('JC Amounts','OSL Purchases','Vendor Database','Voucher Editing')):raise SystemExit('FIX74_WRONG_TOP_SHORTCUTS')
text=text[:start]+text[end:]
newTitle='''    <div class="topbar-title am74-title">
       <span class="am74-page-eyebrow">ASSAM MOTORS · NEW ADMIN PANEL</span>
       <strong>{{ trim($__env->yieldContent('topbar_title')) ?: (trim($__env->yieldContent('title')) ?: 'ERP Dashboard') }}</strong>
    </div>'''
text=text.replace(topStart,newTitle,1)
beforeHead='</head>'
style="""<style id="FIX74_CLEAN_BLUE_ERP_TOPBAR">
/* Consistent workshop navy header, independent of legacy FIX50 shell rules. */
html body.erp-app header.erp-topbar{
 background:#102e56!important;color:#f4f8ff!important;
 border:0!important;border-bottom:1px solid #346391!important;
 box-shadow:0 2px 12px rgba(7,25,47,.23)!important;
 display:flex!important;align-items:center!important;gap:13px!important;
 min-height:61px!important;padding:8px 18px!important;
}
html body.erp-app header.erp-topbar .am74-title{
 display:flex!important;flex-direction:column!important;justify-content:center!important;
 gap:2px!important;flex:1 1 240px!important;min-width:0!important;
 color:#fff!important;max-width:none!important;margin:0!important;
 text-align:left!important;line-height:1.2!important;
}
html body.erp-app header.erp-topbar .am74-page-eyebrow{
 display:block!important;font-size:9px!important;font-weight:800!important;
 color:#8eb5e2!important;letter-spacing:.09em!important;white-space:nowrap;
}
html body.erp-app header.erp-topbar .am74-title strong{
 color:#fff!important;font-size:15px!important;font-weight:850!important;
 white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
html body.erp-app header.erp-topbar .sidebar-toggle{
 color:#fff!important;background:#214772!important;border-color:#436d9d!important;
 border-radius:8px!important;width:36px!important;height:34px!important;
 flex:0 0 36px!important;
}
html body.erp-app header.erp-topbar .topbar-live{
 background:#166448!important;color:#d8ffed!important;border:1px solid #318669!important;
 font-weight:850!important;padding:6px 10px!important;border-radius:22px!important;
 margin:0!important;flex:none!important;
}
html body.erp-app header.erp-topbar .topbar-date{
 font-size:11px!important;color:#d4e3f5!important;white-space:nowrap!important;
 margin:0!important;flex:none!important;
}
html body.erp-app header.erp-topbar .userbox{
 display:flex!important;align-items:center!important;gap:9px!important;
 flex:none!important;color:#f5faff!important;margin:0!important;
}
html body.erp-app header.erp-topbar .userbox>span{
 color:#c7def8!important;font-size:11px!important;max-width:140px;
 overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
html body.erp-app header.erp-topbar .userbox .linkbtn{
 background:#1e5797!important;color:#fff!important;border:1px solid #75a5db!important;
 border-radius:7px!important;min-width:66px;min-height:33px!important;
 padding:7px 10px!important;cursor:pointer!important;font-weight:800!important;
 opacity:1!important;visibility:visible!important;
}
html body.erp-app header.erp-topbar .userbox .linkbtn:hover{
 background:#3170b6!important;
}
html body.erp-app header.erp-topbar .am50-top-shortcuts{display:none!important;}
@media(max-width:780px){
 html body.erp-app header.erp-topbar{gap:8px!important;padding:7px 9px!important;flex-wrap:wrap!important}
 html body.erp-app header.erp-topbar .am74-title{flex:1 1 150px!important}
 html body.erp-app header.erp-topbar .am74-title strong{font-size:13px!important}
 html body.erp-app header.erp-topbar .topbar-date{font-size:10px!important}
 html body.erp-app header.erp-topbar .userbox>span{display:none!important}
}
@media print{html body.erp-app header.erp-topbar{display:none!important}}
</style>
"""
if text.count(beforeHead)!=1:raise SystemExit('FIX74_HTML_HEAD_ANCHOR_MISMATCH')
text=text.replace(beforeHead,style+beforeHead,1)
if text.count(marker)!=1 or text.count('class="am50-top-shortcuts"')!=0:raise SystemExit('FIX74_VALIDATION_FAILED')
for tok in ['class="topbar-live"','class="topbar-date"',"route('erp.logout')",'class="sidebar-nav am-android-nav"']:
 if tok not in text:raise SystemExit('FIX74_REQUIRED_NAV_FEATURE_LOST '+tok)
if mode=='check':
 print('FIX74_HEADER_PREFLIGHT_PASS');sys.exit(0)
fd,tmp=tempfile.mkstemp(prefix='.fix74-',dir=view.parent)
try:
 with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(text)
 os.chmod(tmp,view.stat().st_mode&0o777);os.replace(tmp,view)
finally:
 if os.path.exists(tmp):os.unlink(tmp)
print('FIX74_CLEAN_BLUE_NAV_DEPLOYED_NO_FINANCIAL_RECORD_WRITES')
