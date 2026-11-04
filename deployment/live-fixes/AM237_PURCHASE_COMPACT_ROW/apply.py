#!/usr/bin/env python3
"""AM237 — guarded visual-only compact Parts Purchase row fix.
check/apply target exact staging view. rollback restores original bytes.
No purchase, stock, customer or ledger database changes.
"""
import sys
from pathlib import Path
import datetime,os,shutil,tempfile
if len(sys.argv) not in (3,4) or sys.argv[1] not in {'check','apply','rollback'}:
 raise SystemExit('Usage: python3 apply.py check|apply STAGING_ROOT [BACKUP_FOR_ROLLBACK]')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM237_STOP_NOT_STAGING')
p=root/'resources/views/purchases/create.blade.php'
if not p.is_file():raise SystemExit('AM237_STOP_NO_VIEW')
marker='AM237_PURCHASE_COMPACT_ROW'
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM237_ROLLBACK_BACKUP_REQUIRED')
 b=Path(sys.argv[3]).expanduser().resolve()
 if b.parent != root/'storage/app/am-backups' or not b.name.startswith('AM237_PURCHASE_ROW_'):
  raise SystemExit('AM237_ROLLBACK_INVALID_BACKUP')
 saved=b/'create.blade.php'
 if not saved.is_file():raise SystemExit('AM237_ROLLBACK_MISSING_BACKUP')
 shutil.copy2(saved,p)
 print('AM237_ROLLBACK_PASS')
 sys.exit(0)
original=p.read_text('utf8','surrogateescape')
if marker in original:
 print('AM237_ALREADY_INSTALLED')
 sys.exit(0)
anchor='<template id="line-template">'
if original.count(anchor)!=1:
 raise SystemExit('AM237_STOP_TEMPLATE_MISMATCH')
if 'AM234_PURCHASE_JC_PICKER_V2' not in original or 'id="purchase-form"' not in original:
 raise SystemExit('AM237_STOP_EXPECTED_PURCHASE_PICKER_MISSING')
css=(Path(__file__).resolve().parent/'compact.css').read_text('utf8')
if marker not in css or css.count('{')!=css.count('}'):
 raise SystemExit('AM237_STOP_CSS_INVALID')
addition='\n{{-- '+marker+' · no backend / JS changes --}}\n<style id="am237-purchase-compact-row">\n'+css+'\n</style>\n'
modified=original.replace(anchor,addition+anchor,1)
if modified.count('am237-purchase-compact-row')!=1:
 raise SystemExit('AM237_STOP_MODIFIED_SOURCE_INVALID')
print('AM237_PREFLIGHT_PASS')
if mode=='check':
 print('AM237_CHECK_NO_CHANGES')
 sys.exit(0)
backup=root/'storage/app/am-backups'/('AM237_PURCHASE_ROW_'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S'))
backup.mkdir(mode=0o750,parents=True,exist_ok=False)
shutil.copy2(p,backup/'create.blade.php')
tmp=None
try:
 with tempfile.NamedTemporaryFile(mode='w',encoding='utf8',errors='surrogateescape',dir=p.parent,prefix='.am237_row_',delete=False) as f:
  f.write(modified)
  tmp=Path(f.name)
 os.chmod(tmp,p.stat().st_mode&0o777)
 os.replace(tmp,p)
except Exception:
 if tmp is not None:tmp.unlink(missing_ok=True)
 shutil.copy2(backup/'create.blade.php',p)
 raise
print('AM237_APPLIED')
print('AM237_BACKUP='+str(backup))
print('AM237_FRONTEND_CSS_ONLY_NO_DATA_WRITES')
