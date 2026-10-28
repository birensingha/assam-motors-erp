#!/usr/bin/env python3
"""AM181 — restore classic, polished NEW ERP Job Applications dashboard UI.

Targets ONLY resources/views/admin/native/job-applications.blade.php, with a
self-contained scoped CSS design. Does not alter controllers, status actions,
routes, applicant data, or the public Hiring website.
Optional: safely deduplicate a Job Applications sidebar link cloned by AM180.

Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import datetime,difflib,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback STAGING_LARAVEL_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
view=root/"resources/views/admin/native/job-applications.blade.php"
if not (root/"artisan").is_file() or not view.is_file():
    raise SystemExit("AM181_STOP_STAGING_OR_VIEW_MISSING")
marker="AM181_CLASSIC_HIRING_DASHBOARD"
backup_dir=root/"storage/app/am-backups/AM181_JOB_APPS_DASHBOARD"
navpaths=[
 root/"resources/views/layouts/erp.blade.php",
 root/"resources/views/layouts/partials/sidebar.blade.php",
 root/"resources/views/layouts/sidebar.blade.php",
 root/"resources/views/partials/sidebar.blade.php",
 root/"resources/views/admin/partials/sidebar.blade.php",
]
def read(p):
    return p.read_text(encoding="utf8",errors="surrogateescape")
def write_atomic(p,data):
    fd,path=tempfile.mkstemp(prefix=".am181_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as out:out.write(data)
        os.chmod(path,p.stat().st_mode & 0o777)
        os.replace(path,p)
    finally:
        if os.path.exists(path):os.unlink(path)
def save(p,backup):
    shutil.copy2(p,backup)
    os.chmod(backup,0o600)
def php_blade_check(candidate):
    # PHP cannot parse Blade directly; static Blade integrity/structure checks
    # and php artisan view:cache after deployment protect against syntax errors.
    if candidate.count("@section('content')")!=1 or candidate.count("@endsection")<1:
        raise SystemExit("AM181_STOP_BLADE_SECTION_STRUCTURE_CHANGED")
    if candidate.count('<div id="am181-job-applications">')!=1:
        raise SystemExit("AM181_STOP_WRAPPER_MISSING")
    if candidate.count("AM181_CLASSIC_HIRING_DASHBOARD")!=1:
        raise SystemExit("AM181_STOP_STYLE_MARKER_COUNT")
    if candidate.count("<!-- AM181 END -->")!=1:
        raise SystemExit("AM181_STOP_WRAPPER_END")
    if not candidate.rstrip().endswith("@endsection"):
        raise SystemExit("AM181_STOP_VIEW_END_MARKER_CHANGED")

css=r"""
<style id="AM181_CLASSIC_HIRING_DASHBOARD">
/* Scoped to the Job Applications page only: no global ERP/layout changes. */
#am181-job-applications {
  --am181-navy:#13345e;--am181-ink:#182c48;--am181-muted:#64748b;
  --am181-blue:#2465c9;--am181-border:#dce5ef;--am181-surface:#fff;
  width:100%;max-width:100%;color:var(--am181-ink);
  font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:13px;
}
#am181-job-applications *{box-sizing:border-box}
#am181-job-applications .page-head{
  display:flex;align-items:center;justify-content:space-between;gap:15px;
  background:linear-gradient(105deg,#fff 67%,#eef5ff);
  padding:18px 22px;margin:0 0 14px;border-radius:12px;
  border:1px solid var(--am181-border);box-shadow:0 2px 8px rgba(20,52,91,.035)
}
#am181-job-applications .page-head h1{font-size:22px;line-height:1.2;
  margin:0 0 5px;font-weight:750;letter-spacing:-.4px;color:#14355e}
#am181-job-applications .page-head p{
  margin:0;color:#687c98;font-size:12px;line-height:1.5}
#am181-job-applications .page-head .badge{
  display:inline-flex;align-items:center;border-radius:30px;
  padding:8px 13px;background:#e6f0ff;color:#174a99;font-size:12px;
  font-weight:800;white-space:nowrap;border:1px solid #d0dff6
}
#am181-job-applications .ah-stats{display:grid!important;
  grid-template-columns:repeat(7,minmax(0,1fr))!important;gap:10px;
  margin:0 0 15px;width:100%}
#am181-job-applications .ah-stat{
  display:flex;flex-direction:column;justify-content:center;
  min-width:0;min-height:83px;padding:12px 15px;border-radius:11px;
  background:#fff;border:1px solid var(--am181-border);
  box-shadow:0 2px 7px rgba(20,52,91,.04);border-top:3px solid #7596c9
}
#am181-job-applications .ah-stat:nth-child(2){border-top-color:#0d9488}
#am181-job-applications .ah-stat:nth-child(3){border-top-color:#875dcc}
#am181-job-applications .ah-stat:nth-child(4){border-top-color:#d99627}
#am181-job-applications .ah-stat:nth-child(5){border-top-color:#16a34a}
#am181-job-applications .ah-stat:nth-child(6){border-top-color:#2c5c97}
#am181-job-applications .ah-stat:nth-child(7){border-top-color:#e27d4e}
#am181-job-applications .ah-stat-label{
  display:block;color:#73839a;font-size:10px;font-weight:800;
  letter-spacing:.7px;text-transform:uppercase;line-height:1.35;
  margin-bottom:7px}
#am181-job-applications .ah-stat-value{
  font-weight:800;letter-spacing:-.5px;font-size:25px;
  line-height:1.05;color:#193960;font-variant-numeric:tabular-nums}
#am181-job-applications .ah-filters{
  display:grid!important;
  grid-template-columns:minmax(190px,1.8fr) minmax(135px,.85fr) minmax(140px,.85fr) auto!important;
  gap:10px;align-items:center;margin:0 0 15px;padding:13px;
  border:1px solid var(--am181-border);border-radius:11px;background:#fff;
  box-shadow:0 2px 7px rgba(20,52,91,.035)}
#am181-job-applications .ah-input{
  appearance:auto;width:100%;min-width:0;height:39px!important;min-height:39px;
  max-height:39px;border:1px solid #d7e2ef!important;
  border-radius:7px!important;background:#f9fbff!important;color:#253d60;
  padding:0 11px!important;margin:0!important;font-size:12px!important;
  box-shadow:none!important;outline:none}
#am181-job-applications .ah-input:focus{
  background:#fff!important;border-color:#2f6bc0!important;
  box-shadow:0 0 0 3px rgba(52,115,203,.12)!important}
#am181-job-applications .ah-btn-refresh{
  display:inline-flex;align-items:center;justify-content:center;
  padding:0 16px!important;height:39px!important;min-height:39px;min-width:118px;
  border:1px solid #2058a7!important;border-radius:7px!important;
  background:#1758aa!important;color:#fff!important;font-size:12px!important;
  font-weight:700;cursor:pointer;white-space:nowrap;margin:0!important}
#am181-job-applications .ah-btn-refresh:hover{background:#104586!important}
#am181-job-applications .ah-list{
  display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr));
  gap:14px;align-items:stretch;width:100%}
#am181-job-applications .ah-card{
  min-width:0;overflow:hidden;display:flex;flex-direction:column;
  background:#fff;border:1px solid var(--am181-border);
  border-radius:12px;box-shadow:0 3px 11px rgba(18,49,92,.05)}
#am181-job-applications .ah-card:hover{
  border-color:#b9cce7;box-shadow:0 5px 18px rgba(18,49,92,.08)}
#am181-job-applications .ah-card-head{
  display:flex;gap:11px;align-items:flex-start;min-width:0;
  border-bottom:1px solid #e8eef6;padding:15px 16px 13px;
  background:linear-gradient(180deg,#fff,#fbfdff)}
#am181-job-applications .ah-avatar{
  flex:0 0 41px;width:41px;height:41px;border-radius:10px;
  display:flex;align-items:center;justify-content:center;
  background:#e7eef9;color:#21569c;font-weight:800;font-size:13px}
#am181-job-applications .ah-avatar.shortlisted{background:#e2f4e9;color:#168044}
#am181-job-applications .ah-avatar.rejected{background:#ffeded;color:#bd4545}
#am181-job-applications .ah-avatar.hired{background:#dff3e8;color:#13824d}
#am181-job-applications .ah-card-info{min-width:0;flex:1}
#am181-job-applications .ah-card-name{
  display:flex;flex-wrap:wrap;gap:5px 7px;align-items:center;
  font-size:14px;line-height:1.35;font-weight:800;color:#1c3659;overflow-wrap:anywhere}
#am181-job-applications .ah-pos,#am181-job-applications .ah-appid{
  display:inline-flex;align-items:center;white-space:nowrap;
  border-radius:5px;padding:3px 7px;font-size:10px;font-weight:700}
#am181-job-applications .ah-pos{color:#236197;background:#e9f3ff}
#am181-job-applications .ah-pos.helper{color:#7c529e;background:#f2eafd}
#am181-job-applications .ah-appid{color:#64748b;background:#edf1f6}
#am181-job-applications .ah-card-meta{
  margin-top:7px;display:flex;flex-wrap:wrap;gap:4px 11px;
  color:#64748b;font-size:11px;line-height:1.5}
#am181-job-applications .ah-card-meta a{color:#1d62b4;text-decoration:none;font-weight:650}
#am181-job-applications .ah-card-right{
  flex:0 0 auto;display:flex;flex-direction:column;align-items:flex-end;
  text-align:right;gap:7px;max-width:125px}
#am181-job-applications .ah-status{
  display:inline-flex;align-items:center;padding:4px 9px;border-radius:50px;
  color:#936213;background:#fff2d8;border:1px solid #f4dfae;
  font-size:10px;font-weight:800;white-space:nowrap}
#am181-job-applications .ah-status.shortlisted{color:#14713f;background:#e3f5eb;border-color:#c7e9d4}
#am181-job-applications .ah-status.hired{color:#086c4c;background:#d7f6e8;border-color:#b5e9d1}
#am181-job-applications .ah-status.rejected{color:#ac4141;background:#ffeded;border-color:#f2c8c8}
#am181-job-applications .ah-card-date{font-size:10px;color:#8291a7;line-height:1.5}
#am181-job-applications .ah-card-body{
  display:grid;align-content:start;gap:8px;flex:1;
  min-height:72px;padding:12px 16px}
#am181-job-applications .ah-card-row{display:flex;gap:12px;min-width:0;line-height:1.5}
#am181-job-applications .ah-lbl{
  flex:0 0 101px;font-size:11px;color:#8290a4;font-weight:650}
#am181-job-applications .ah-val{
  flex:1;min-width:0;color:#324763;font-size:11.5px;overflow-wrap:anywhere}
#am181-job-applications .ah-card-row .btn.small{
  display:inline-flex;align-items:center;gap:5px;padding:5px 9px;
  border-radius:6px;color:#1a579b;background:#edf5ff;border:1px solid #cbdff6;
  text-decoration:none;font-size:11px;line-height:1.3;font-weight:650}
#am181-job-applications .ah-card-actions{
  display:flex;align-items:center;flex-wrap:wrap;gap:6px;
  padding:12px 16px;border-top:1px solid #e8eef6;background:#f9fbff}
#am181-job-applications .ah-card-actions form{
  display:inline-flex!important;margin:0!important;padding:0!important;
  border:0!important;background:transparent!important}
#am181-job-applications .ah-btn{
  display:inline-flex;align-items:center;justify-content:center;gap:4px;
  border:1px solid #cedcef!important;border-radius:6px!important;
  background:#fff!important;color:#315176!important;
  font-size:11px!important;font-weight:700;padding:7px 10px!important;
  line-height:1.25!important;min-height:31px;white-space:nowrap;
  cursor:pointer;text-decoration:none!important;margin:0!important}
#am181-job-applications .ah-btn.wa{color:#137949!important;border-color:#b5e1c8!important;background:#eaf8f0!important}
#am181-job-applications .ah-btn.del{color:#b23d3d!important;border-color:#ecc7c7!important;background:#fff5f5!important}
#am181-job-applications .ah-btn:hover{filter:brightness(.955)}
#am181-job-applications .ah-empty-box{
  grid-column:1/-1;text-align:center;padding:45px 15px;
  border:1px dashed #becde0;border-radius:12px;background:#fff;color:#7c8ba0}
#am181-job-applications .ah-empty-icon{font-size:27px}
@media(max-width:1240px){
 #am181-job-applications .ah-stats{grid-template-columns:repeat(4,minmax(0,1fr))!important}
 #am181-job-applications .ah-list{grid-template-columns:1fr}
}
@media(max-width:820px){
 #am181-job-applications .ah-stats{grid-template-columns:repeat(3,minmax(0,1fr))!important}
 #am181-job-applications .ah-filters{grid-template-columns:repeat(2,minmax(0,1fr))!important}
 #am181-job-applications .ah-btn-refresh{width:100%}
}
@media(max-width:540px){
 #am181-job-applications .page-head{padding:14px}
 #am181-job-applications .page-head h1{font-size:19px}
 #am181-job-applications .ah-stats{grid-template-columns:repeat(2,minmax(0,1fr))!important}
 #am181-job-applications .ah-stat{min-height:72px;padding:11px}
 #am181-job-applications .ah-filters{grid-template-columns:1fr!important}
 #am181-job-applications .ah-card-head{flex-wrap:wrap;padding:13px}
 #am181-job-applications .ah-card-right{flex-direction:row;max-width:none;flex-basis:100%;
   width:100%;align-items:center;text-align:left;justify-content:space-between}
 #am181-job-applications .ah-card-body{padding:12px 13px}
 #am181-job-applications .ah-lbl{flex-basis:84px}
 #am181-job-applications .ah-card-actions{padding:11px 13px}
}
</style>
"""
def patch_view(source):
    if marker in source:
        return source,"ALREADY_STYLED"
    if any(x not in source for x in
        ["@section('content')","@endsection","class=\"ah-stats\"",
         "class=\"ah-filters\"","class=\"ah-list\"","class=\"ah-card\""]):
        raise SystemExit("AM181_STOP_VIEW_MARKUP_NOT_EXPECTED")
    if source.count("@section('content')")!=1:
        raise SystemExit("AM181_STOP_MULTIPLE_CONTENT_SECTIONS")
    start="@section('content')"
    source=source.replace(start,start+"\n"+css+"\n<div id=\"am181-job-applications\">\n",1)
    end=source.rfind("@endsection")
    source=source[:end]+"</div><!-- AM181 END -->\n"+source[end:]
    php_blade_check(source)
    return source,"CSS_INJECTED"
def nav_patch(source):
    """Rewrite existing Job Applications menu link to native route; remove
    only the exact link that AM180 generated from Attendance when safe."""
    # Keep the original item from the ADMIN PANEL, not the cloned one.
    # The cloned link from AM180 is predictable from the Attendance anchor.
    anchor_pattern=r'<a\b[^>]{0,850}>[\s\S]{0,300}?</a>'
    anchors=list(re.finditer(anchor_pattern,source,re.I))
    attendance=[m for m in anchors if "erp.attendance" in m.group() and "route(" in m.group()]
    if len(attendance)!=1:
        return source,"NAV_SKIP_NO_UNIQUE_ATTENDANCE"
    am180=attendance[0].group().replace("erp.attendance","erp.job-applications.index",1)
    am180=re.sub(r'\bAttendance\b','Job Applications',am180,flags=re.I)
    candidates=[m for m in anchors if "Job Applications" in m.group() and "erp.job-applications.index" in m.group()]
    original=[m for m in anchors if "Job Applications" in m.group() and m.group()!=am180]
    if not original or len(original)!=1 or len(candidates)!=1 or candidates[0].group()!=am180:
        return source,"NAV_SKIP_AMBIGUOUS_DUPLICATE"
    old=original[0].group()
    # Do not modify nav if it has Blade control structures.
    if any(x in old for x in ("@if","@foreach","@endif")):
        return source,"NAV_SKIP_COMPLEX_ORIGINAL_LINK"
    # Bind original link to the new secure Laravel route. Preserve styling.
    # A Blade route helper inside double-quoted href is supported.
    matched=re.search(r'\bhref\s*=\s*"[^"]*"',old)
    if not matched:
        return source,"NAV_SKIP_ORIGINAL_LINK_UNRECOGNIZED"
    rewired=old[:matched.start()]+"""href="{{ route('erp.job-applications.index') }}\""""+old[matched.end():]
    if rewired.count("route('erp.job-applications.index')")!=1:
        return source,"NAV_SKIP_REWIRE_FAILED"
    result=source.replace(old,rewired,1)
    # Remove EXACT cloned AM180 link plus the newline added by that patch.
    needle="\n"+am180
    if result.count(needle)!=1:
        return source,"NAV_SKIP_CLONE_NOT_UNIQUE"
    result=result.replace(needle,"",1)
    return result,"NAV_DUPLICATE_REMOVED_ORIGINAL_REWIRED"

if mode=="rollback":
    vp=backup_dir/"job-applications.blade.php.before_AM181"
    if not vp.is_file():
        raise SystemExit("AM181_STOP_NO_VIEW_BACKUP")
    if marker not in read(view):
        raise SystemExit("AM181_STOP_STYLES_NOT_INSTALLED")
    write_atomic(view,read(vp))
    # Restore only nav files this patch touched; do not revert AM180 routes.
    for p in navpaths:
        backup=backup_dir/(p.name+".nav.before_AM181")
        if backup.is_file() and p.is_file():
            write_atomic(p,read(backup))
    print("AM181_VIEW_AND_NAV_RESTORED")
    print("AM181_ROUTES_APPLICATION_DATA_UNCHANGED")
    sys.exit(0)
v0=read(view)
v1,status=patch_view(v0)
print("AM181_VIEW",status)
nav_changes=[]
for p in navpaths:
    if not p.is_file():continue
    src=read(p)
    result,nstat=nav_patch(src)
    print("AM181_NAV",p.relative_to(root),nstat)
    if result!=src:
        nav_changes.append((p,result))
if status=="ALREADY_STYLED":
    print("AM181_ALREADY_INSTALLED_NO_WRITES")
    sys.exit(0)
if mode=="check":
    print("AM181_CHECK_PASS_NO_WRITES")
    sys.exit(0)
if backup_dir.exists() and (backup_dir/"job-applications.blade.php.before_AM181").exists():
    raise SystemExit("AM181_STOP_BACKUP_ALREADY_EXISTS")
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
save(view,backup_dir/"job-applications.blade.php.before_AM181")
for p,updated in nav_changes:
    backup=backup_dir/(p.name+".nav.before_AM181")
    if backup.exists():
        raise SystemExit("AM181_STOP_NAV_BACKUP_COLLISION")
    save(p,backup)
write_atomic(view,v1)
for p,updated in nav_changes:
    write_atomic(p,updated)
print("AM181_JOB_APPLICATIONS_DASHBOARD_STYLED")
print("AM181_NAV_CHANGED",len(nav_changes))
print("AM181_BACKUPS_STAGING_STORAGE_ONLY")
print("AM181_NO_APPLICANT_OR_STATUS_DATA_TOUCHED")
print("AM181_RUN_ARTISAN_VIEW_CLEAR_AND_TEST")
