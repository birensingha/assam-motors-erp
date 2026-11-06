#!/usr/bin/env python3
"""Safe STAGING-only deploy of optional audited release review. Requires existing AM189 controls."""
from pathlib import Path
from datetime import datetime
import subprocess, tempfile, shutil, sys

root=Path.home()/"domains/assammotors.com/assam-erp-staging"
repo=Path.home()/"assam-motors-deploy"
prefix="deployment/live-fixes/AM189_INSPECTION_QC_GUARD/files/"
paths=[
    "app/Services/InspectionQcGuard.php",
    "app/Http/Controllers/Web/InspectionQcDefectController.php",
    "resources/views/inspection-defects/index.blade.php",
    "database/migrations/2026_11_06_183000_create_qc_delivery_risk_reviews.php",
]
routes="routes/web.php"
if not (root/"artisan").is_file() or not (repo/".git").is_dir():
    raise SystemExit("STOP: wrong STAGING path or missing git checkout")
def gitshow(path):
    p=subprocess.run(["git","show","FETCH_HEAD:"+prefix+path],cwd=repo,capture_output=True,text=True)
    if p.returncode: raise SystemExit("STOP: missing patch "+path)
    return p.stdout
def lint(src):
    with tempfile.NamedTemporaryFile("w",suffix=".php",delete=False) as f:
        f.write(src); name=f.name
    try:
        p=subprocess.run(["php","-l",name],capture_output=True,text=True)
        if p.returncode: raise SystemExit("STOP PHP syntax: "+p.stderr[-600:]+p.stdout[-600:])
    finally:
        Path(name).unlink(missing_ok=True)

sources={p:gitshow(p) for p in paths}
g=(root/paths[0]).read_text()
c=(root/paths[1]).read_text()
v=(root/paths[2]).read_text()
routing=(root/routes).read_text()
if g.count("function assertDeliveryRiskReady")!=1 or "AM_QC_DECLINED_DELIVERY_HOLD" not in (root/"app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php").read_text():
    raise SystemExit("STOP: expected live delivery HOLD not present. No changes.")
if c.count("function reopenDeclined")!=1 or "function deliveryRiskReview" in c:
    raise SystemExit("STOP: live controller differs or review already installed.")
if "am-decision-picker" not in v or "QC of Approved Work — Separate from Full Vehicle QC" in v:
    raise SystemExit("STOP: expected simple defect UI not installed / duplicate panel present.")
if routing.count("Route::post('/inspection-defects/{defect}/reopen-declined'")!=1 or "erp.job-cards.inspection-defects.delivery-risk-review" in routing:
    raise SystemExit("STOP: route anchor mismatch / already installed.")
route='    Route::post(\'/job-cards/{job}/inspection-defects/delivery-risk-review\', [\\App\\Http\\Controllers\\Web\\InspectionQcDefectController::class,\'deliveryRiskReview\'])->whereNumber(\'job\')->name(\'erp.job-cards.inspection-defects.delivery-risk-review\');'
lines=routing.splitlines(keepends=True)
ix=[i for i,line in enumerate(lines) if "Route::post('/inspection-defects/{defect}/reopen-declined'" in line]
if len(ix)!=1: raise SystemExit("STOP: route location ambiguous")
lines.insert(ix[0]+1,route+"\n")
sources[routes]="".join(lines)
for path,src in sources.items():
    if path.endswith(".php"): lint(src)
print("AM_DELIVERY_REVIEW_PREFLIGHT_PASS")
backup=root/"storage/app/am-backups"/("AM_DELIVERY_RISK_REVIEW_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
for path in sources:
    existing=root/path
    if existing.is_file():
        dst=backup/path
        dst.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(existing,dst)
print("BACKUP:",backup)
written=[]
try:
    for path,src in sources.items():
        target=root/path
        target.parent.mkdir(parents=True,exist_ok=True)
        tmp=target.with_name(target.name+".amdelivery.tmp")
        tmp.write_text(src)
        if target.exists(): shutil.copymode(target,tmp)
        tmp.replace(target)
        written.append(path)
    migrated=subprocess.run(["php","artisan","migrate","--path=database/migrations/2026_11_06_183000_create_qc_delivery_risk_reviews.php","--force"],cwd=root)
    if migrated.returncode: raise RuntimeError("Migration failed; check schema before retry")
    for cmd in (["php","artisan","optimize:clear"],["php","artisan","view:cache"],["php","artisan","view:clear"]):
        result=subprocess.run(cmd,cwd=root)
        if result.returncode: raise RuntimeError("Post-install command failed: "+" ".join(cmd))
except Exception as exc:
    for path in written:
        old=backup/path
        dst=root/path
        if old.exists(): shutil.copy2(old,dst)
        else: dst.unlink(missing_ok=True)
    subprocess.run(["php","artisan","optimize:clear"],cwd=root,capture_output=True)
    raise SystemExit("STOP: restored code backup after error: "+str(exc))
print("AM_DELIVERY_RISK_REVIEW_STAGING_INSTALLED")
print("No existing job cards, QC outcomes or customer refusals were changed.")
print("Full QC PASS unchanged; delivery requires current signed risk review.")
print("Perform browser acceptance and verify route / DB audit before treating as solved.")
