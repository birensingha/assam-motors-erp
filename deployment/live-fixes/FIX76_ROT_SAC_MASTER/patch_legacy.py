#!/usr/bin/env python3
"""FIX76 legacy screenshot popup + API + CSV: SAC/HSN is saved end-to-end.
Strict exact-source guard; no changes to old invoices / JC records.
"""
from pathlib import Path
import hashlib,os,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: patch_legacy.py check|apply LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
assert (root/'artisan').is_file()
html=root/'public/legacy/workshop/labour-master.html'
api=root/'public/legacy/api/labour-crud.php'
expected={'html':'38ae8fe17213c5a710491a995cf9f2d3dbb5fe98a8d9f79afb2805a9bbc5533f',
          'api':'1a7d70dca9e8f803793c7a6152d0e0df98f23138095755fe018cfe042b284bb0'}
paths={'html':html,'api':api}
raw={k:x.read_bytes() for k,x in paths.items()}
data={k:b.decode('utf8',errors='surrogateescape') for k,b in raw.items()}
marker='FIX76_SAC_MODAL_API'
if any(marker in s for s in data.values()):
 if all(marker in s for s in data.values()):
  print('FIX76_LEGACY_ALREADY_APPLIED');sys.exit(0)
 raise SystemExit('FIX76_PARTIAL_DEPLOY_ABORT')
for k,b in raw.items():
 digest=hashlib.sha256(b).hexdigest()
 print('FIX76_LEGACY_SHA',k,digest)
 if digest!=expected[k]:raise SystemExit('FIX76_LEGACY_SOURCE_CHANGED_'+k)
def one(s,a,b,name):
 n=s.count(a);print('FIX76_LEGACY_ANCHOR',name,n)
 if n!=1:raise SystemExit('FIX76_LEGACY_ANCHOR_FAILED_'+name)
 return s.replace(a,b,1)
h=data['html']
h=one(h,'<label>GST %</label><input type="number" id="lfGstPercent" step="0.01" value="18">',
 '''<label>GST %</label><input type="number" id="lfGstPercent" step="0.01" value="18">''',
 'noop') if False else h
h=one(h,
 '<div class="ws-form-group"><label>GST %</label><input type="number" id="lfGstPercent" step="0.01" value="18"></div>',
 '''<div class="ws-form-group"><label>GST %</label><input type="number" id="lfGstPercent" step="0.01" value="18"></div>
          <!-- FIX76_SAC_MODAL_API -->
          <div class="ws-form-group">
            <label>SAC / HSN Code *</label>
            <input type="text" id="lfSacCode" name="sac_code" value="998714" inputmode="numeric"
                   maxlength="6" pattern="[0-9]{6}" placeholder="998714" title="6-digit SAC for automobile maintenance and repair" required>
          </div>''','modal_input')
h=one(h,
 "    document.getElementById('lfId').value = '';",
 "    document.getElementById('lfId').value = '';\n    document.getElementById('lfSacCode').value = '998714';",
 'new_default')
h=one(h,
 "    document.getElementById('lfGstPercent').value = l.gst_percent || 18;",
 "    document.getElementById('lfGstPercent').value = (l.gst_percent ?? 18);\n    document.getElementById('lfSacCode').value = l.sac_code || '998714';",
 'edit_value')
h=one(h,
 "      rot_code: document.getElementById('lfRotCode').value.trim(),",
 "      rot_code: document.getElementById('lfRotCode').value.trim(),\n      sac_code: document.getElementById('lfSacCode').value.trim(),",
 'post_payload')
h=one(h,
 "    if (!data.rot_description) return showToast('Description required', true);",
 "    if (!data.rot_description) return showToast('Description required', true);\n    if (!/^[0-9]{6}$/.test(data.sac_code)) return showToast('SAC Code must be six digits, e.g. 998714', true);",
 'browser_validation')
h=one(h,
 "        '<td>' + (l.rot_description || '—') + '</td>' +",
 "        '<td>' + (l.rot_description || '—') + '</td>' +\n        '<td><strong>' + (l.sac_code || 'NOT SET') + '</strong></td>' +",
 'table_cell')
h=one(h,
 '<th>ROT Code</th><th>Description</th><th>Category</th><th>Hours</th>',
 '<th>ROT Code</th><th>Description</th><th>SAC / HSN</th><th>Category</th><th>Hours</th>',
 'table_header')
h=one(h,
 "             (l.category||'').toLowerCase().indexOf(q) > -1;",
 "             (l.category||'').toLowerCase().indexOf(q) > -1 ||\n             (l.sac_code||'').toLowerCase().indexOf(q) > -1;",
 'sac_search')
h=one(h,
 'Search labour by code, description, category...',
 'Search labour by code, description, SAC...',
 'search_placeholder')
h=one(h,
 "        rot_code: r['ROT Code'] || r['rot_code'] || '',",
 "        rot_code: r['ROT Code'] || r['rot_code'] || '',\n        sac_code: String(r['SAC Code'] || r['sac_code'] || '998714').trim(),",
 'bulk_upload_sac')
h=one(h,
 "var csv = 'ROT Code,ROT Description,Category,Standard Hours,Labour Rate,GST %\\n' +",
 "var csv = 'ROT Code,ROT Description,Category,Standard Hours,Labour Rate,GST %,SAC Code\\n' +",
 'csv_header')
h=one(h,
 "'ROT-TEST-1,Sample Labour Work,Custom,1.5,500,18\\n' +",
 "'ROT-TEST-1,Sample Labour Work,Custom,1.5,500,18,998714\\n' +",
 'csv_example_one')
h=one(h,
 "'ROT-TEST-2,Another Labour,Brakes,2,800,18';",
 "'ROT-TEST-2,Another Labour,Brakes,2,800,18,998714';",
 'csv_example_two')
apiSource=data['api']
apiSource=one(apiSource,
 '            rot_code VARCHAR(50) UNIQUE NOT NULL,',
 '            rot_code VARCHAR(50) UNIQUE NOT NULL,\n            sac_code VARCHAR(8) NULL, // FIX76_SAC_MODAL_API',
 'table_create_sac')
# Replace PHP SQL comment in quoted CREATE TABLE by SQL comment rather than PHP.
apiSource=apiSource.replace('sac_code VARCHAR(8) NULL, // FIX76_SAC_MODAL_API','sac_code VARCHAR(8) NULL,')
apiSource=one(apiSource,
 "    $rot_description = trim($input['rot_description'] ?? '');",
 """    $rot_description = trim($input['rot_description'] ?? '');
    // FIX76_SAC_MODAL_API — server authoritative code validation.
    $sac_code = trim((string)($input['sac_code'] ?? ''));
    if(!preg_match('/^[0-9]{6}$/D',$sac_code)){
        sendJson(['error'=>'SAC/HSN must be six numeric digits (example: 998714).'],400);
    }""",
 'api_validation')
apiSource=one(apiSource,
 "        'rot_code' => $rot_code,",
 "        'rot_code' => $rot_code,\n        'sac_code' => $sac_code,",
 'save_sac')
apiSource=one(apiSource,
 '(rot_code, rot_description, category, standard_hours, labour_rate, gst_percent, active)',
 '(rot_code, rot_description, category, standard_hours, labour_rate, gst_percent, sac_code, active)',
 'bulk_insert_columns')
apiSource=one(apiSource,
 "VALUES (?, ?, ?, ?, ?, ?, 'Yes')",
 "VALUES (?, ?, ?, ?, ?, ?, ?, 'Yes')",
 'bulk_placeholder')
apiSource=one(apiSource,
 '        gst_percent = VALUES(gst_percent)',
 '        gst_percent = VALUES(gst_percent),\n        sac_code = VALUES(sac_code)',
 'bulk_update_code')
apiSource=one(apiSource,
 "                (float)($it['gst_percent'] ?? 18)",
 """                (float)($it['gst_percent'] ?? 18),
                (string)($it['sac_code'] ?? '998714')""",
 'bulk_value')
apiSource=one(apiSource,
 "    foreach ($items as $it) {\n        try {",
 """    foreach ($items as $it) {
        try {
            $incomingSac=trim((string)($it['sac_code']??'998714'));
            if(!preg_match('/^[0-9]{6}$/D',$incomingSac)){
                throw new RuntimeException('Invalid six-digit SAC on import');
            }""",
 'bulk_validation')
apiSource=one(apiSource,
 "                (string)($it['sac_code'] ?? '998714')",
 "                $incomingSac",
 'bulk_normalized')
for key,source in [('html',h),('api',apiSource)]:
 if source.count(marker)!=1:raise SystemExit('FIX76_LEGACY_MARKER_MISSING_'+key)
 if key=='html' and ('lfSacCode' not in source or 'SAC / HSN' not in source):raise SystemExit('FIX76_FORM_MISSING')
data={'html':h,'api':apiSource}
if mode=='check':
 print('FIX76_LEGACY_MODAL_API_PREFLIGHT_PASS');sys.exit(0)
for k,path in paths.items():
 fd,tmp=tempfile.mkstemp(prefix='.fix76legacy_',dir=path.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(data[k])
  os.chmod(tmp,path.stat().st_mode&0o777)
  os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
print('FIX76_LEGACY_MODAL_AND_BACKEND_SAC_READY')
