#!/usr/bin/env python3
"""FIX78 — Browser print URL/date footer replacement on all native print documents.
Preserves every invoice, receipt, purchase and job content and all previous A4 page
margins; uses Chromium >=131 CSS @page margin boxes on first and every print page.

No financial controllers, DB migrations, invoice lines or ledger changes.
"""
from pathlib import Path
import sys,os,hashlib,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply ROOT')
mode=sys.argv[1]; root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
paths={
 'resources/views/invoices/print.blade.php':'7af964e646ff07848c7dd98ad7f12746841dea3199b35fdd03c616eac1b6b0d1',
 'resources/views/invoices/direct-print.blade.php':'be49fd7154f907810c0ccf2c71645718b69ee2c32c40f4cda95afb499f10e3db',
 'resources/views/invoices/credit-note.blade.php':'cecf51e7ee9079e13c14a5de86ba12a0f61db05090a5d4a8ff2d2b8182789c6b',
 'resources/views/job-cards/summary-print.blade.php':'7c6362e5e9c3de67dbd007154bec28b07ad08ed1257bd595c3a1e8053719b329',
 'resources/views/money-receipts/print.blade.php':'65e4d41a8e5441ab845372eaba4e1c0e5f6427b745f51d0efda0c94a61d6203c',
 'resources/views/estimates/print.blade.php':'1546a922d2b701a7aee2674d74b502bd24ccfb33e49cc5c14958275693d8b536',
 'resources/views/purchases/print.blade.php':'73e0d44c0d27f3b499ecae891215bafb37a0f60ac9e65076d6dd0529908bc736',
 'resources/views/job-cards/inspection-report.blade.php':'e944eb2653106709838551bcda44177e53af61d30ce9f4eead0a5fbe331bf132',
}
marker='FIX78_BRANDED_PRINT_MARGIN_FOOTER'
css="""

/* FIX78_BRANDED_PRINT_MARGIN_FOOTER:
   Chrome/Edge 131+ custom print margin boxes suppress browser-generated
   page-title/date/url/page-number footers at their respective edges.
   Keep earlier @page size/margins unmodified to preserve A4 content layout.
   Printed website is an independent document-level brand, not staging URL. */
@page {
  @top-left    { content: ""; }
  @top-center  { content: ""; }
  @top-right   { content: ""; }
  @bottom-left {
    content: "www.assammotors.com";
    font-family: Arial, Helvetica, sans-serif;
    font-size: 8pt;
    font-weight: 700;
    color: #486582;
  }
  @bottom-center { content: ""; }
  @bottom-right  { content: ""; }
}
"""
results=[]
current=[]
for name,sha in paths.items():
 p=root/name
 if not p.is_file():raise SystemExit('FIX78_FILE_MISSING_'+name)
 raw=p.read_bytes()
 t=raw.decode('utf8','surrogateescape')
 if marker in t:
  current.append(name)
 else:
  actual=hashlib.sha256(raw).hexdigest()
  print('FIX78_SOURCE',name,actual)
  if actual!=sha:raise SystemExit('FIX78_LIVE_SOURCE_CHANGED_STOP_'+name)
  if t.count('<style')<1 or t.count('</style>')<1:raise SystemExit('FIX78_STYLE_BLOCK_NOT_FOUND_'+name)
  idx=t.index('</style>')
  if '<style' not in t[:idx]:raise SystemExit('FIX78_CSS_STYLE_SCOPE_BAD_'+name)
  updated=t[:idx]+css+t[idx:]
  if updated.count(marker)!=1:raise SystemExit('FIX78_INJECTION_BAD_'+name)
  if updated.count('www.assammotors.com')<1:raise SystemExit('FIX78_MISSING_SITE_'+name)
  results.append((p,updated))
if current:
 if len(current)==len(paths):
  print('FIX78_ALL_PRINT_DOCUMENTS_ALREADY_UPDATED');sys.exit(0)
 raise SystemExit('FIX78_PARTIAL_APPLY_STOP_'+','.join(current))
print('FIX78_TARGETS_VALIDATED',len(results))
if mode=='check':
 print('FIX78_PREFLIGHT_PASS_ALL_EIGHT_DOCUMENTS_NO_WRITES');sys.exit(0)
for p,content in results:
 fd,tmp=tempfile.mkstemp(prefix='.fix78_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(content)
  os.chmod(tmp,p.stat().st_mode&0o777)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
print('FIX78_PRINT_SITE_FOOTER_APPLIED_TO_EIGHT_VIEWS')
