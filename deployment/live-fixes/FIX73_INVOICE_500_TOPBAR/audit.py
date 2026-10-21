#!/usr/bin/env python3
"""FIX73 READ ONLY: server code and most recent invoice-related exception locations.
Do not print runtime SQL queries, secrets or user identifiers.
"""
from pathlib import Path
import sys,hashlib,re,datetime
root=Path(sys.argv[1]).resolve();assert (root/'artisan').is_file()
for rel,lo,hi in [
 ('app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',337,478),
 ('resources/views/layouts/erp.blade.php',1,240),
 ('resources/views/job-cards/partials/lifecycle-panel.blade.php',1,70),
]:
 p=root/rel
 if not p.exists():print('FIX73_MISSING',rel);continue
 raw=p.read_bytes();ls=raw.decode('utf8',errors='replace').splitlines()
 print('FIX73_FILE',rel,'SHA256',hashlib.sha256(raw).hexdigest(),'LINES',len(ls))
 for i in range(lo-1,min(hi,len(ls))):
  line=ls[i]
  if re.search(r'password|secret|private_key|api_key|token',line,re.I):continue
  print('FIX73_CODE',rel,i+1,line[:230])
for p in (root/'resources/views').rglob('*.blade.php'):
 if 'vendor' in str(p):continue
 raw=p.read_bytes();lines=raw.decode('utf8',errors='replace').splitlines()
 matches=[(i,l) for i,l in enumerate(lines,1) if re.search(r'JC Amounts|OSL Purchases|Vendor Database|Voucher Editing|NEW ADMIN PAN|Logout|Live</|topbar_title',l,re.I)]
 if not matches:continue
 print('FIX73_TOP_NAV_CANDIDATE',str(p.relative_to(root)),'sha',hashlib.sha256(raw).hexdigest(),'lines',len(lines))
 for i,l in matches[:12]:print('FIX73_NAV_HIT',i,l[:200])
for p in sorted((root/'storage/logs').glob('*.log'),key=lambda p:p.stat().st_mtime,reverse=True)[:2]:
 print('FIX73_LOG_FILE',p.name,'bytes',p.stat().st_size)
 try:tail=p.read_bytes()[-650000:].decode('utf8',errors='replace').splitlines()
 except Exception as e:print('FIX73_LOG_UNREADABLE',type(e).__name__);continue
 headers=[(i,l) for i,l in enumerate(tail) if re.match(r'\[\d\d\d\d-\d\d-\d\d\s',l)]
 for i,line in headers[-35:]:
  match=re.match(r'\[(.*?)\].*?\.(ERROR|CRITICAL|ALERT):\s*(.*)',line)
  if not match:continue
  raw=match.group(3)
  exc=re.search(r'(?:(?:[A-Za-z_][\w]*\\)+[A-Za-z_]\w*|[A-Za-z_]\w*(?:Exception|Error))',raw)
  fp=re.search(r'(?:/home/[^ :]+/)?(app/[^ :]+\.php|resources/views/[^ :]+|vendor/laravel/[^ :]+):(\d+)',raw)
  relevant=bool(re.search(r'invoice|JobCard|Lifecycle|SQLSTATE|QueryException|ViewException|credit.?note|Trying to|undefined|Integrity',raw,re.I))
  print('FIX73_LOG_EXCEPTION',match.group(1), 'type',exc.group(0) if exc else 'unknown',
      'file',fp.group(1) if fp else 'hidden','line',fp.group(2) if fp else '-', 'related',relevant)
  # log trace callsites only, without exception messages, SQL and PII
  if relevant:
   for t in tail[i+1:i+25]:
    cm=re.search(r'(app/(?:Http/Controllers|Services|Models)/[^ :(]+\.php)\((\d+)\)',t)
    if cm:print('FIX73_APP_TRACE',cm.group(1),cm.group(2))
print('FIX73_READONLY_AUDIT_COMPLETE')
