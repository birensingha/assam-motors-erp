#!/usr/bin/env python3
"""AM210 protected read-only Staff GPS interval endpoint, STAGING only.

check   = inspect existing POST Staff Location route & auth, no writes.
prepare = copy only new controller after preflight, no route modifications.
apply   = add GET next to existing POST in same route group, verify identical
          route middleware, backup and rollback on any mismatch.

No Staff/GPS/Attendance/ROT/geofence data is changed. Never authorizes
settings with only an untrusted staff ID. The existing Staff token middleware
must protect the new GET as it protects the old POST.
"""
from pathlib import Path
from datetime import datetime,timezone
import argparse,json,os,re,shutil,subprocess,tempfile
parser=argparse.ArgumentParser()
parser.add_argument("mode",choices=("check","prepare","apply"))
parser.add_argument("root")
args=parser.parse_args()
root=Path(args.root).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM210_API_STOP_NOT_STAGING")
routes=root/"routes/api.php"
controller=root/"app/Http/Controllers/Api/V1/GpsReportingIntervalController.php"
source=Path(__file__).resolve().parent/"files/app/Http/Controllers/Api/V1/GpsReportingIntervalController.php"
if not routes.is_file() or not source.is_file():
    raise SystemExit("AM210_API_STOP_REQUIRED_SOURCE_MISSING")
new_controller=source.read_text(encoding="utf8")
if controller.is_file() and controller.read_text(encoding="utf8")!=new_controller:
    raise SystemExit("AM210_API_STOP_EXISTING_CONTROLLER_DIFFERS")

def run(command):
    p=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=80)
    if p.returncode:
        raise RuntimeError("AM210_API_CMD_FAILED "+str(command)+" "+p.stderr[-300:])
    return p.stdout
def routing():
    return json.loads(run(["php","artisan","route:list","--json"]))
old_routes=routing()
def gps_post(routes):
    rows=[r for r in routes if
          (r.get("uri") or "").strip("/").lower()=="api/v1/staff/location"
          and "POST" in (r.get("method") or "").upper().split("|")]
    if len(rows)!=1:
        raise SystemExit("AM210_API_STOP_POST_GPS_ROUTE_NOT_UNIQUE")
    return rows[0]
post=gps_post(old_routes)
middleware=post.get("middleware") or []
print("AM210_EXISTING_POST_ROUTE",post.get("uri"))
print("AM210_EXISTING_POST_ACTION",post.get("action"))
print("AM210_EXISTING_POST_MIDDLEWARE",json.dumps(middleware))
if not middleware or not any(
    re.search(r"auth|sanctum|token|staff",str(m),re.I) for m in middleware
):
    raise SystemExit("AM210_API_STOP_EXISTING_GPS_ROUTE_HAS_NO_OBVIOUS_AUTH")

content=routes.read_text(encoding="utf8")
marker="AM210_STAFF_GPS_REPORTING_INTERVAL_GET"
if marker in content:
    existing=[r for r in old_routes if (r.get("uri") or "").strip("/").lower()
              =="api/v1/staff/location/reporting-settings"]
    if len(existing)!=1:
        raise SystemExit("AM210_API_STOP_PARTIAL_EXISTING_ROUTE")
    if existing[0].get("middleware")!=middleware:
        raise SystemExit("AM210_API_STOP_GET_POST_MIDDLEWARE_DIFFER")
    print("AM210_API_ALREADY_ROUTED_AUTH_IDENTICAL")
    raise SystemExit(0)
if "GpsReportingIntervalController" in content or "staff/location/reporting-settings" in content:
    raise SystemExit("AM210_API_STOP_ROUTE_COLLISION")

# Only supported insertion: one-line POST with literal /staff/location inside
# existing api/v1 route group. Do not try to guess nested route definitions.
line_matches=[
    (i,line) for i,line in enumerate(content.splitlines(keepends=True))
    if re.search(r"Route::post\s*\(\s*['\"][^'\"]*/staff/location['\"]",line)
]
if len(line_matches)!=1:
    print("AM210_API_POST_ACTION_FOR_MANUAL_ANCHOR_REVIEW",
          str(post.get("action",""))[:180])
    raise SystemExit("AM210_API_STOP_POST_SOURCE_ANCHOR_NOT_UNIQUE_USE_AUDIT")
line_idx,post_line=line_matches[0]
if "StaffLocationController" not in post_line:
    raise SystemExit("AM210_API_STOP_POST_HANDLER_NOT_VERIFIED")
if "->middleware(" in post_line:
    raise SystemExit("AM210_API_STOP_INLINE_MIDDLEWARE_MUST_BE_COPIED_MANUALLY")
if "->" not in post_line or ";" not in post_line:
    raise SystemExit("AM210_API_STOP_POST_ROUTE_NOT_ONE_LINE")
indent=re.match(r"^\s*",post_line).group(0)
addition=(
    indent+"// "+marker+": same route group as authorized POST GPS\n"
    +indent+"Route::get('/staff/location/reporting-settings', "
    +r"[\App\Http\Controllers\Api\V1\GpsReportingIntervalController::class, 'show']);"
    +"\n"
)
lines=content.splitlines(keepends=True)
lines.insert(line_idx+1,addition)
candidate="".join(lines)
# PHP source syntax check without touching production/staging source files.
for label,code in [("routes",candidate),("controller",new_controller)]:
    fd,tmp=tempfile.mkstemp(prefix=".am210_api_",suffix=".php")
    try:
        with os.fdopen(fd,"w",encoding="utf8") as f:f.write(code)
        run(["php","-l",tmp])
    finally:os.unlink(tmp)
print("AM210_API_PHP_LINT_PASS")
print("AM210_API_READ_ONLY_ENDPOINT_READY_FOR_CONTROLLED_INSTALL")
if args.mode=="check":
    print("AM210_API_CHECK_COMPLETE_NO_WRITES")
    raise SystemExit(0)
if args.mode=="prepare":
    if not controller.is_file():
        controller.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(source,controller)
    print("AM210_API_CONTROLLER_PREPARED_NO_ROUTE_CHANGES")
    raise SystemExit(0)
if os.environ.get("AM210_STAGING_APPROVED")!="YES":
    raise SystemExit("AM210_API_STOP_EXPLICIT_APPROVAL_REQUIRED")
if not controller.is_file():
    raise SystemExit("AM210_API_STOP_PREPARE_REQUIRED")
backup=root/"storage/app/am-backups"/(
    "AM210_GPS_API_"+datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
shutil.copy2(routes,backup/"api.php")
(backup/"api.php").chmod(0o600)
try:
    fd,tmp=tempfile.mkstemp(prefix=".am210_route_",dir=routes.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8") as f:f.write(candidate)
        os.chmod(tmp,routes.stat().st_mode&0o777)
        os.replace(tmp,routes)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    run(["php","artisan","route:clear"])
    new=routing()
    get=[r for r in new if
         (r.get("uri") or "").strip("/").lower()=="api/v1/staff/location/reporting-settings"
         and "GET" in (r.get("method") or "").upper().split("|")]
    if len(get)!=1:
        raise RuntimeError("AM210_API_GET_ROUTE_MISSING")
    if get[0].get("middleware")!=gps_post(new).get("middleware"):
        raise RuntimeError("AM210_API_GET_POST_AUTH_MIDDLEWARE_MISMATCH")
    if "GpsReportingIntervalController" not in (get[0].get("action") or ""):
        raise RuntimeError("AM210_API_WRONG_HANDLER")
    print("AM210_API_SECURE_ROUTE_ACTIVATED_PASS")
    print("AM210_API_MIDDLEWARE_MATCH_POST_GPS_PASS")
    print("AM210_API_BACKUP",backup)
except Exception as e:
    shutil.copy2(backup/"api.php",routes)
    subprocess.run(["php","artisan","route:clear"],cwd=root,capture_output=True,text=True)
    raise SystemExit("AM210_API_ROLLED_BACK "+str(e))
