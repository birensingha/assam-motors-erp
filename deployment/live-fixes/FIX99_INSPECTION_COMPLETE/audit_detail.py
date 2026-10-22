#!/usr/bin/env python3
from pathlib import Path
import sys,re,hashlib
root=Path(sys.argv[1]);assert (root/'artisan').exists()
for rel in ['app/Http/Controllers/Api/V1/StaffExtraController.php',
'app/Http/Controllers/Web/StaffAssignedInspectionController.php',
'resources/views/staff/inspection-work.blade.php',
'android-staff-app/app/src/main/java/com/assammotors/staff/ui/MainActivity.kt',
'android-staff-app/app/src/main/res/layout/activity_main.xml',
'android-staff-app/app/src/main/AndroidManifest.xml',
'android-staff-app/app/build.gradle.kts',
'android-staff-app/app/build.gradle']:
 p=root/rel
 print('FIX99_DETAIL_FILE',rel,'exists',p.exists())
 if not p.exists():continue
 txt=p.read_text(errors='replace');lines=txt.splitlines()
 print('FIX99_DETAIL_STATS',rel,len(lines),hashlib.sha256(p.read_bytes()).hexdigest())
 if 'StaffExtraController' in rel:
  keys=['function saveInspection','function inspection(','function inspections(','public function inspection']
 elif 'StaffAssignedInspectionController' in rel:keys=['function complete(','function show(']
 elif 'inspection-work.blade.php' in rel: keys=['<form','Complete','Save','submit','required','<script']
 elif 'MainActivity.kt' in rel:keys=['WebView','loadUrl','inspection','javascript','setOnClickListener','MainActivity']
 else:keys=[]
 hits=[i for i,l in enumerate(lines) if any(k.lower() in l.lower() for k in keys)]
 positions=set()
 for i in hits[:18]: positions.update(range(max(0,i-8),min(len(lines),i+(150 if 'saveInspection' in lines[i] or 'function complete(' in lines[i] else 24))))
 if not positions and len(lines)<150:positions=set(range(len(lines)))
 for i in sorted(positions)[:420]:
  l=lines[i]
  if re.search(r'password|bearer|secret\\s*[=:]|api.key|private.key|keystore',l,re.I):continue
  print('FIX99_DETAIL_CODE',rel,i+1,l[:440].encode('ascii','backslashreplace').decode('ascii'))
# inventory relevant mobile UI sources
for area in [root/'android-staff-app',root/'public']:
 if area.exists():
  items=[]
  for p in area.rglob('*'):
   if p.is_file() and p.suffix.lower() in ['.html','.js','.kt','.java','.vue','.php','.xml']:
    if any(t in str(p).lower() for t in ['inspect','checklist','mainactivity','app.js','mobile','staff']):items.append(str(p.relative_to(root)))
  print('FIX99_UI_CANDIDATES',str(area.relative_to(root)),items[:110])
print('FIX99_DETAIL_DONE')
