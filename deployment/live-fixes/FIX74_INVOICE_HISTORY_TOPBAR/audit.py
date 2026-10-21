from pathlib import Path
import re,sys,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').is_file()
for rel,lo,hi in [('resources/views/layouts/erp.blade.php',1,142),
 ('resources/views/layouts/new-admin.blade.php',1,90),
 ('app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',470,500),
 ('app/Services/PaidInvoiceAdvanceService.php',1,105)]:
 p=root/rel
 if not p.exists():continue
 s=p.read_bytes().decode('utf8','replace').splitlines()
 print('FIX74_FILE',rel,'sha',hashlib.sha256(p.read_bytes()).hexdigest())
 for n in range(lo,min(hi,len(s))+1):
  if re.search('password|secret|token|api.key',s[n-1],re.I):continue
  print('FIX74_CODE',rel,n,s[n-1][:290])
for p in sorted((root/'storage/logs').glob('*.log'),key=lambda p:p.stat().st_mtime,reverse=True)[:1]:
 lines=p.read_bytes()[-300000:].decode('utf8','replace').splitlines()
 for x in lines:
  if '19:35:20' in x and 'UniqueConstraintViolationException' in x:
   match=re.search(r'for key [^A-Za-z0-9]*([A-Za-z0-9_$.]+)',x)
   print('FIX74_500_UNIQUE_INDEX',match.group(1) if match else 'unresolved')
print('FIX74_HEADER_READONLY_PASS')
