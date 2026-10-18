#!/usr/bin/env python3
"""Adjust ONLY money-panel height and the invoice record note in staging Blade.
Preserves financial calculations, HSN groups, invoice rows, header and ledger.
"""
import hashlib
import os
import shutil
import stat
import sys
import time
from pathlib import Path

root=Path(sys.argv[1]).resolve()
p=root/'resources/views/invoices/print.blade.php'
if not (root/'artisan').is_file() or not p.is_file():
    raise SystemExit('SAFE_STOP: expected staging Laravel invoice template is missing')
old=p.read_text(encoding='utf-8')
digest=hashlib.sha256(old.encode()).hexdigest()
print('ORIGINAL_PRINT_SHA',digest[:16])
if 'AM_HSN_HEIGHT_NOTE_V1' in old:
    print('HSN_HEIGHT_NOTE_ALREADY_APPLIED')
    raise SystemExit(0)
if digest[:16]!='1079052a30502c56' or 'AM_HSN_PANEL_V1' not in old or 'AM_INVOICE_ROW_V2' not in old:
    raise SystemExit('SAFE_STOP: staging invoice has changed; re-audit needed')
s=old

def replace_once(before,after):
    global s
    count=s.count(before)
    if count!=1:
        raise SystemExit('SAFE_STOP: expected one template anchor, found '+str(count)+': '+before[:80])
    s=s.replace(before,after,1)

replace_once(
    '.money-area{display:grid;grid-template-columns:minmax(0,1fr) 47%;gap:13px;align-items:start;margin-top:14px}',
    '.money-area{display:grid;grid-template-columns:minmax(0,1fr) 47%;gap:13px;align-items:stretch;margin-top:14px}'
)
replace_once(
    '.money-message{border-left:3px solid #2f6ca5;background:#f1f6fb;padding:10px;font-size:9px;color:#4c6982;line-height:1.55}',
    '.money-message{display:flex;flex-direction:column;min-height:100%;box-sizing:border-box;background:transparent;padding:0;font-size:9px;color:#4c6982;line-height:1.4}'
)
replace_once(
    '.hsn-panel{margin-top:10px;background:#fff;border:1px solid #bcd1e5;border-radius:6px;padding:7px 7px 5px;color:#204367}',
    '.hsn-panel{margin-top:0;flex:1;min-height:100%;box-sizing:border-box;background:#fff;border:1px solid #bcd1e5;border-radius:6px;padding:5px 6px 4px;color:#204367}'
)
replace_once(
    '.hsn-title{font-size:9px;font-weight:800;letter-spacing:.02em;color:#16477f;margin-bottom:5px}',
    '.hsn-title{font-size:9px;font-weight:800;letter-spacing:.02em;color:#16477f;margin-bottom:3px}'
)
replace_once(
    '.hsn-summary-table th{background:#eaf1f9;color:#244a73;font-size:6.9px;text-transform:uppercase;text-align:left;padding:4px 2px;border-bottom:1px solid #b9ccdf}',
    '.hsn-summary-table th{background:#eaf1f9;color:#244a73;font-size:6.9px;text-transform:uppercase;text-align:left;padding:3px 2px;border-bottom:1px solid #b9ccdf}'
)
replace_once(
    '.hsn-summary-table td{font-size:7.5px;padding:4px 2px;border-bottom:1px solid #e5ecf4;overflow-wrap:anywhere}',
    '.hsn-summary-table td{font-size:7.5px;padding:3px 2px;border-bottom:1px solid #e5ecf4;overflow-wrap:anywhere}'
)
replace_once(
    '.hsn-panel-note{margin:5px 0 0;font-size:7.1px;color:#6c6173;line-height:1.3}',
    '.hsn-panel-note{margin:3px 0 0;font-size:7.1px;color:#6c6173;line-height:1.25}'
)
replace_once(
    '    <strong>Invoice No. {{ $invoiceNo }}</strong><br>\n    All amounts below come from the saved invoice record. Printing another format does not create another invoice or ledger entry.\n',
    ''
)
replace_once(
    '  @if($hasRateAndNoTax)',
    '''  <div class="invoice-record-note" style="font-size:8.2px;color:#657b91;margin-top:6px">
    <strong>Invoice No. {{ $invoiceNo }}</strong> &nbsp;·&nbsp;
    All amounts come from the saved invoice. Changing print format does not create another invoice or ledger entry.
  </div>
  @if($hasRateAndNoTax)'''
)
# Read-only mutation assertion: only CSS and moving explanatory lines
assert s.count('AM_HSN_PANEL_V1')==1
assert s.count('AM_INVOICE_ROW_V2')==1
assert s.count('HSN / SAC-wise Tax Summary')==1
assert s.count('class="amounts"')==1
assert s.count('class="money-message"')==1
assert s.count('class="invoice-record-note"')==1
assert s.count('Invoice No. {{ $invoiceNo }}')==1
assert s.count('All amounts below come from the saved invoice record.')==0
# version marker remains within CSS to make idempotence test deterministic
replace_once('/* AM_HSN_PANEL_V1 - compact', '/* AM_HSN_HEIGHT_NOTE_V1 */\n/* AM_HSN_PANEL_V1 - compact')
backup=p.with_name(p.name+'.before-hsn-height-'+time.strftime('%Y%m%d%H%M%S')+'.bak')
shutil.copy2(p,backup)
tmp=p.with_name(p.name+'.hsn-height-tmp')
tmp.write_text(s,encoding='utf-8')
os.chmod(tmp,stat.S_IMODE(p.stat().st_mode))
os.replace(tmp,p)
print('HSN_HEIGHT_NOTE_APPLIED','BACKUP='+backup.name,'NEW_HASH='+hashlib.sha256(s.encode()).hexdigest()[:16])
