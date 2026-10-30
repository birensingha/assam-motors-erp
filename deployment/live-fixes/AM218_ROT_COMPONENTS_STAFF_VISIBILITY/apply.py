#!/usr/bin/env python3
"""AM218: read-only ROT components notes for authenticated Staff API + Web Portal.
No financial / timer / ROT state or database changes. check|apply|rollback ROOT.
"""
from pathlib import Path
import hashlib,os,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM218_USAGE apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM218_STOP_NOT_STAGING')
api=root/'public/legacy/api/staff-rot-v3.php'
web=root/'public/legacy/staff.js'
backup=root/'storage/app/am-backups/AM218_ROT_COMPONENTS_STAFF_VISIBILITY'
backapi=backup/'staff-rot-v3.php.before_AM218'
backweb=backup/'staff.js.before_AM218'
manifest=backup/'installed-sha256.txt'
mark='AM218_ROT_COMPONENTS_STAFF_VISIBILITY'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(x):return hashlib.sha256(x.encode('utf8',errors='surrogateescape')).hexdigest()
def save(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am218_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not api.is_file() or not web.is_file():raise SystemExit('AM218_STOP_STAFF_FILES_MISSING')
a=read(api);b=read(web)
if mode=='rollback':
    if not all(p.is_file() for p in (backapi,backweb,manifest)):
        raise SystemExit('AM218_ROLLBACK_BACKUP_MISSING')
    if read(manifest).splitlines()!=[sha(a),sha(b)]:
        raise SystemExit('AM218_STOP_ROLLBACK_CURRENT_SOURCE_CHANGED')
    save(api,read(backapi));save(web,read(backweb))
    print('AM218_ROLLBACK_RESTORED_NO_DB_WRITES');sys.exit(0)
if mark in a or mark in b:
    if mark in a and mark in b:
        print('AM218_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM218_STOP_PARTIAL_INSTALL')
schema=r"""<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo \Illuminate\Support\Facades\Schema::hasColumn('labour_master','work_procedure')?'READY':'MISSING';
"""
r=subprocess.run(['php','-r',schema[5:]],cwd=root,capture_output=True,text=True,timeout=30)
if r.returncode or r.stdout.strip()!='READY':raise SystemExit('AM218_STOP_AM212_COLUMN_MISSING')
query=("SELECT rs.*,jc.vehicle_reg_no,jc.vehicle_make,jc.vehicle_model,jc.status job_status "
 "FROM rot_sessions rs LEFT JOIN job_cards jc ON jc.id=rs.job_card_id "
 "WHERE rs.staff_id=? ORDER BY FIELD(rs.status,'Running','Paused','Assigned','Completed'),rs.created_at DESC LIMIT 200")
newquery=("SELECT rs.*,jc.vehicle_reg_no,jc.vehicle_make,jc.vehicle_model,jc.status job_status,"
 "COALESCE((SELECT lm.work_procedure FROM labour_master lm WHERE lm.rot_code=rs.rot_code ORDER BY lm.id DESC LIMIT 1),'') AS work_procedure "
 "FROM rot_sessions rs LEFT JOIN job_cards jc ON jc.id=rs.job_card_id "
 "WHERE rs.staff_id=? ORDER BY FIELD(rs.status,'Running','Paused','Assigned','Completed'),rs.created_at DESC LIMIT 200")
if a.count(query)!=1:raise SystemExit('AM218_STOP_AUTHENTICATED_SELECT_ANCHOR_'+str(a.count(query)))
a2=a.replace(query,"/* "+mark+" */ "+newquery)
entry="'rot_note'=>$r['rot_note']??'',"
if a2.count(entry)!=1:raise SystemExit('AM218_STOP_STAFF_JSON_PROJECTION')
a2=a2.replace(entry,entry+" 'work_procedure'=>$r['work_procedure']??'',")
if a.count('WHERE rs.staff_id=?')!=a2.count('WHERE rs.staff_id=?'):
    raise SystemExit('AM218_STOP_STAFF_IDENTITY_FILTER_CHANGED')
anchor="  function card(r, isCompleted) {"
if b.count(anchor)!=1:raise SystemExit('AM218_STOP_PORTAL_CARD_FUNCTION')
helper=(
  "  // "+mark+": show read-only ROT reference for Assigned and Completed.\n"
  "  function rotComponentsDetails(r) {\n"
  "    const components=String(r.work_procedure || '').trim();\n"
  "    if(!components)return '';\n"
  "    return '<details class=\"staff-rot-components\" style=\"margin:10px 0;border:1px solid #c9d9e9;border-radius:8px;background:#f7faff\">'+\n"
  "      '<summary style=\"cursor:pointer;padding:10px 12px;font-weight:750\">Details · Parts / Components to Remove &amp; Refit</summary>'+\n"
  "      '<div style=\"padding:10px 12px;white-space:pre-wrap;overflow-wrap:anywhere;border-top:1px solid #dce7f2\">'+escapeHtml(components)+'</div>'+\n"
  "      '</details>';\n"
  "  }\n"
)
b2=b.replace(anchor,helper+anchor)
actions="(!isCompleted ? '<div class=\"staff-rot-work-actions\">'"
if b2.count(actions)!=1:raise SystemExit('AM218_STOP_PORTAL_CARD_ACTION_ANCHOR')
b2=b2.replace(actions,"rotComponentsDetails(r) +\n        "+actions)
for token in ("staffApiCall('/staff-rot-v3.php?action=list')",
              "const completed = rows.filter(r => r.status === 'Completed')"):
    if b.count(token)!=b2.count(token):
        raise SystemExit('AM218_STOP_STAFF_WORKFLOW_CHANGED')
with tempfile.TemporaryDirectory(prefix='am218check_') as d:
    pp=Path(d)/'api.php';jj=Path(d)/'staff.js'
    pp.write_text(a2,encoding='utf8',errors='surrogateescape')
    jj.write_text(b2,encoding='utf8',errors='surrogateescape')
    for cmd in (['php','-l',str(pp)],['node','--check',str(jj)]):
        r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=30)
        if r.returncode:raise SystemExit('AM218_STOP_SYNTAX '+(r.stdout+r.stderr)[-450:])
print('AM218_STAFF_AUTH_ROT_JSON_AND_WEB_COMPLETED_DETAILS_PREFLIGHT_PASS')
print('AM218_STAFF_ID_FILTER_ACTIONS_AND_FINANCIALS_UNCHANGED')
if mode=='check':
    print('AM218_CHECK_PASS_NO_WRITES');sys.exit(0)
if any(p.exists() for p in (backapi,backweb,manifest)):
    raise SystemExit('AM218_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True)
os.chmod(backup,0o700)
shutil.copy2(api,backapi);shutil.copy2(web,backweb)
os.chmod(backapi,0o600);os.chmod(backweb,0o600)
try:
    save(api,a2);save(web,b2)
    manifest.write_text(sha(a2)+'\n'+sha(b2)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
except BaseException:
    save(api,a);save(web,b)
    if manifest.exists():manifest.unlink()
    raise SystemExit('AM218_SOURCE_ERROR_ROLLED_BACK')
print('AM218_AUTHENTICATED_STAFF_ROT_API_WEB_PORTAL_INSTALLED')
print('AM218_BROWSER_AND_APP_API_INTEGRATION_TEST_PENDING')
