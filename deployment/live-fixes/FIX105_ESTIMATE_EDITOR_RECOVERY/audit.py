#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib,subprocess,urllib.parse,os
root=Path(sys.argv[1]); assert (root/'artisan').exists()
names=['resources/views/estimates/create.blade.php','resources/views/estimates/show.blade.php',
       'resources/views/estimates/print.blade.php',
       'public/js/estimate-entry.js','public/js/estimate-entry-fix104.js',
       'public/css/erp.css']
for name in names:
 p=root/name;print('FIX105_FILE',name,p.exists())
 if not p.exists():continue
 s=p.read_text(errors='replace');ls=s.splitlines()
 print('FIX105_HASH',name,hashlib.sha256(p.read_bytes()).hexdigest(),len(ls))
 if name.endswith('.js'):
  ids=range(len(ls))
 elif name.endswith('create.blade.php'):
  ids=range(min(len(ls),175))
 elif name.endswith('print.blade.php'):
  ids=range(min(len(ls),90))
 else:
  ids=[i for i,l in enumerate(ls) if any(x in l.lower() for x in ['ref-menu','estimate','line-scroll','.e-ref-search','line-table','preview'])]
 for i in ids:
  line=ls[i]
  if re.search(r'password|secret\s*[=:]|private.key|bearer',line,re.I):continue
  if 'css/erp' in name and i>100:continue
  print('FIX105_CODE',name,i+1,line[:720].encode('ascii','backslashreplace').decode('ascii'))
print('FIX105_ASSET_TEST_START')
urls=['https://staging.assammotors.com/js/estimate-entry-fix104.js?v=104',
 'https://staging.assammotors.com/erp/js/estimate-entry-fix104.js?v=104',
 'https://staging.assammotors.com/erp/js/estimate-entry.js?v=fix103',
 'https://staging.assammotors.com/js/estimate-entry.js?v=fix103']
for url in urls:
 run=subprocess.run(['curl','-sSL','--max-time','10','-o','/dev/null','-w','%{http_code} %{content_type} %{size_download}',url],capture_output=True,text=True)
 print('FIX105_HTTP',url,run.stdout[:135])
print('FIX105_DONE')
