#!/usr/bin/env python3
"""AM219 read-only source/selector audit. Prints names of attributes and small
non-sensitive code snippets; no database writes or secrets."""
from pathlib import Path
import hashlib,re,sys
if len(sys.argv)!=2:raise SystemExit('AM219_USAGE STAGING_ROOT')
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM219_STOP_NOT_STAGING')
files={
 'VIEW':'resources/views/payments/create.blade.php',
 'PAYMENT_SCRIPT':'public/js/payment-voucher-multi.js',
 'PRIMARY_JS':'public/js/payment-voucher.js',
 'AM218_SCRIPT':'public/js/am218-payment-source-search.js',
 'SERVICE':'app/Services/ExpensePaymentService.php',
 'CONTROLLER':'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
}
interesting={
 'VIEW':[r'AM_PAYMENT_SOURCES',r'sources',r'@json\(',r'Js::from',r'p133-multi',r'source-type',r'source-key',r'payment-vendor',r'Search Vendor',r'@foreach',r'option value',r'multi_bill'],
 'PRIMARY_JS':[r'AM_PAYMENT_SOURCES',r'function ',r'type',r'source',r'payee',r'change',r'option',r'window'],
 'PAYMENT_SCRIPT':[r'AM_PAYMENT_SOURCES',r'sourceType',r'source-type',r'source-key',r'payment-vendor',r'option',r'dispatchEvent',r'p133-multi'],
 'AM218_SCRIPT':[r'AM_PAYMENT_SOURCES',r'source-type',r'source-key',r'type.dispatchEvent',r'key.dispatchEvent'],
 'SERVICE':[r'function vendors',r'function payableSources',r"'type'=>'OSL_JOB'",r"'type'=>'PURCHASE_INVOICE'",r"'key'",r"'outstanding'"],
 'CONTROLLER':[r'function createPayment',r'payableSources',r'compact\(']
}
print('AM219_SOURCE_DIAGNOSTIC_READ_ONLY_BEGIN')
for tag,path in files.items():
 p=root/path
 if not p.is_file():print('MISSING',tag,path);continue
 lines=p.read_text('utf8','replace').splitlines()
 print('SOURCE',tag,'LINES',len(lines),'SHA256',hashlib.sha256(p.read_bytes()).hexdigest()[:16])
 for q in interesting[tag]:
  hits=[i for i,l in enumerate(lines) if re.search(q,l,re.I)]
  print('MARKER',tag,q,'COUNT',len(hits),'LINE_NUMBERS',','.join(str(i+1) for i in hits[:12]))
 if tag in ['VIEW','PAYMENT_SCRIPT','CONTROLLER','PRIMARY_JS']:
  selected=set()
  for q in interesting[tag]:
   for i in [i for i,l in enumerate(lines) if re.search(q,l,re.I)][:5]:
    selected.update(range(max(0,i-1),min(len(lines),i+2)))
  count=0
  for i in sorted(selected):
   line=lines[i].strip()
   if re.search(r'(?i)(token|secret|password|key_material|csrf|bearer|ssh|api_key|authorization)',line) and not re.search(r'(?i)(source.key|source_key|source-key|request_key|key\]\]|AM_PAYMENT_SOURCES)',line):continue
   if len(line)>320:line=line[:320]+'…'
   print('LINE',tag,i+1,line)
   count+=1
   if count>=180:break
j=root/'public/js/payment-voucher.js'
if j.is_file():
 print('AM219_PRIMARY_JS_FULL_BEGIN')
 for i,line in enumerate(j.read_text('utf8','replace').splitlines()):
  if i>=230:break
  print('JS',i+1,line[:500])
 print('AM219_PRIMARY_JS_FULL_END')
for tag,path,limit in [
 ('MULTI_JS','public/js/payment-voucher-multi.js',190),
 ('PAYMENT_VIEW','resources/views/payments/create.blade.php',176)]:
 p=root/path
 if p.is_file():
  print('AM222_'+tag+'_FULL_BEGIN')
  for i,line in enumerate(p.read_text('utf8','replace').splitlines()[:limit],1):
   # Source code only; no customer or request data
   print(tag,i,line[:700])
  print('AM222_'+tag+'_FULL_END')
print('AM222_PAYMENT_ALLOCATIONS_METHOD_MAP')
p=root/'app/Services/ExpensePaymentService.php'
if p.is_file():
 lines=p.read_text('utf8','replace').splitlines()
 for i,line in enumerate(lines):
  if any(term in line for term in ('function savePayment','function createVoucher','function payableSources','multi_bill','allocations','source_type','allocation_amount')):
   print('SERVICE_LINE',i+1,line.strip()[:360])
print('AM219_AUDIT_COMPLETE_NO_DB_ACCESS')
