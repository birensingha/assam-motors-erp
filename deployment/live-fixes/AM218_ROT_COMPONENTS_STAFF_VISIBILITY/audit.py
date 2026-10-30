#!/usr/bin/env python3
"""AM218 READ ONLY inspect ROT staff JSON/API and staff portal source code.
Never prints DB rows, auth tokens, credentials, or user data."""
import os,re,sys
from pathlib import Path
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM218_STOP_NOT_STAGING')
print('AM218_AUDIT_STARTED')
bases=[root/'public',root/'resources/views/staff',root/'app/Http/Controllers',
       root.parent/'public_html/workshop',root/'routes']
extensions=('.php','.blade.php','.js','.html')
hits=[]
for base in bases:
    if not base.exists():continue
    for folder,dirs,files in os.walk(base):
        depth=len(Path(folder).relative_to(base).parts)
        if depth>5:dirs[:]=[];continue
        dirs[:]=[x for x in dirs if x not in ['vendor','node_modules','storage','.git','images','uploads','cache','backup','backups']]
        for name in files:
            p=Path(folder)/name
            if not name.endswith(extensions) or p.stat().st_size>380000:continue
            if not re.search(r'(?i)staff|rot|portal|api|routes|work',str(p)):continue
            if re.search(r'(?i)(staff-rot-v3|StaffRot|rot-work|staff/rots|staff.portal|work_procedure)',name):
                hits.append(p);continue
            try:
                content=p.read_text('utf8','replace')
                if any(x in content for x in ('staff-rot-v3','/staff/rots','rot_work','work_procedure')):
                    hits.append(p)
            except OSError:pass
def safe(p):
    try:return str(p.relative_to(root))
    except ValueError:return str(p.relative_to(root.parent)) if root.parent in p.parents else p.name
print('AM218_SOURCE_CANDIDATES',len(hits))
for p in sorted(set(hits),key=lambda v:str(v))[:45]:
    print('AM218_FILE',safe(p),'BYTES',p.stat().st_size)
    try:lines=p.read_text('utf8','replace').splitlines()
    except OSError:continue
    pat=re.compile(r'(?i)(staff-rot-v3|staff/rots|rot_code|rot_description|work_procedure|labour_master|json_encode|response\(\)->json|function.*rot|route\(.+staff|completed|work_status)')
    matches=[i for i,l in enumerate(lines) if pat.search(l)]
    for i in matches[:36]:
        text=lines[i].strip()
        if re.search(r'(?i)(password|token|secret|cookie|authorization|\\.env|api_key|password_hash|private_key)',text):continue
        if len(text)>700:text=text[:700]
        print('AM218_LINE',i+1,text)
# Source column-only ROT session schema, no records
try:
    p=root/'database/migrations'
    for k in ('rot_sessions','job_card_rot','workshop_job_card_labour'):
        matches=sorted(p.glob('*'+k+'*')) if p.is_dir() else []
        for m in matches[:4]:print('AM218_MIGRATION_FILENAME',m.name)
except Exception:pass
print('AM218_AUDIT_FINISHED_NO_WRITES')
