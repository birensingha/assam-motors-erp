#!/usr/bin/env python3
"""AM176 PUBLIC Hiring -> NEW/STAGING ERP, guarded database-connection fix.

CHECK: read-only compare of the *actual* public API getDB() connection and the
Laravel ERP connection. No applicant rows printed, no new applications created.
APPLY: if distinct, back up and patch ONLY public_html/api/job-apply.php to use
Laravel's authenticated DB configuration for INSERTs. Existing email/response
flow is preserved. No live DB mutations during install.

Usage: python3 bridge.py check|apply LARAVEL_STAGING_ROOT
       python3 bridge.py rollback LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import datetime,hashlib,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: bridge.py check|apply|rollback LARAVEL_STAGING_ROOT")
action=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if not (root/"artisan").is_file():
    raise SystemExit("AM176_STOP_NOT_LARAVEL_ROOT")
domain=root.parent
public=domain/"public_html"
target=public/"api/job-apply.php"
cfg=public/"api/config.php"
js=public/"hiring/hiring.js"
# Backups must never be web-accessible.
safe_backups=root/"storage/app/am-backups/hiring-bridge"
marker="AM176_PUBLIC_HIRING_DIRECT_NEW_ERP_DB"
if not all(p.is_file() for p in (target,cfg,js,root/"bootstrap/app.php",root/"vendor/autoload.php")):
    raise SystemExit("AM176_STOP_REQUIRED_PUBLIC_OR_ERP_FILE_MISSING")
source=target.read_text(encoding="utf8",errors="surrogateescape")
if action=="rollback":
    if marker not in source:raise SystemExit("AM176_ROLLBACK_NOT_INSTALLED")
    backups=sorted(safe_backups.glob("job-apply.php.AM176_*.bak"),reverse=True)
    # Accept historical backups from the old script until they are moved safely.
    if not backups:
        backups=sorted(target.parent.glob("job-apply.php.AM176_*.bak"),reverse=True)
    if not backups:raise SystemExit("AM176_STOP_NO_BACKUP")
    backup=backups[0]
    candidate=backup.read_text(encoding="utf8",errors="surrogateescape")
    if marker in candidate:raise SystemExit("AM176_STOP_BACKUP_HAS_MARKER")
    with tempfile.TemporaryDirectory(prefix="am176rollback_") as d:
        sample=Path(d)/"test.php";sample.write_text(candidate,encoding="utf8",errors="surrogateescape")
        test=subprocess.run(["php","-l",str(sample)],capture_output=True,text=True)
        if test.returncode:raise SystemExit("AM176_BACKUP_PHP_LINT_FAILED")
    shutil.copy2(backup,target)
    print("AM176_ROLLED_BACK_TO",backup.name)
    sys.exit(0)

# Source preflight from AM175: do not overwrite a changed public handler.
if marker in source:
    print("AM176_ALREADY_INSTALLED")
    sys.exit(0)
if not hashlib.sha256(target.read_bytes()).hexdigest().startswith("cbdd7c570cc9c67c80e8"):
    raise SystemExit("AM176_STOP_PUBLIC_HANDLER_CHANGED_REVIEW_BEFORE_PATCH")
if source.count("$db = getDB();")!=1 or source.count("INSERT INTO job_applications")!=1:
    raise SystemExit("AM176_STOP_UNKNOWN_PUBLIC_INSERT_IMPLEMENTATION")
if "require_once 'config.php';" not in source:
    raise SystemExit("AM176_STOP_PUBLIC_CONFIG_INCLUDE_CHANGED")
jss=js.read_text(encoding="utf8",errors="surrogateescape")
if "fetch(HIRE_API" not in jss or "job-apply.php" not in jss:
    raise SystemExit("AM176_STOP_HIRING_JS_NOT_TARGETING_PUBLIC_APPLY_API")
print("AM176_PUBLIC_HANDLER_ANCHORS_PASS")

# Use distinct processes to avoid old PHP config and Laravel config clashing.
# Only deterministic SHA-256 connection identities are shared with this Python
# process. Never display host, DB name, credentials, applicant rows or queries.
legacy_script=r"""<?php
require $argv[1];
try{
 $pdo=getDB();
 if(!($pdo instanceof PDO))throw new RuntimeException('NO_PDO');
 $q=$pdo->query('SELECT DATABASE() AS db_name, @@hostname AS db_host, @@port AS db_port');
 $row=$q->fetch(PDO::FETCH_ASSOC);
 if(!$row || !$row['db_name'])throw new RuntimeException('NO_DATABASE');
 $count=$pdo->query('SELECT COUNT(*) FROM job_applications')->fetchColumn();
 echo json_encode(['fingerprint'=>hash('sha256',json_encode($row)),'count'=>(int)$count]);
}catch(Throwable $e){fwrite(STDERR,'PUBLIC_DB_PROBE_FAILED: '.get_class($e));exit(3);}
"""
erp_script=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
 $pdo=$app->make('db')->connection()->getPdo();
 $q=$pdo->query('SELECT DATABASE() AS db_name, @@hostname AS db_host, @@port AS db_port');
 $row=$q->fetch(PDO::FETCH_ASSOC);
 if(!$row || !$row['db_name'])throw new RuntimeException('NO_DATABASE');
 $count=$pdo->query('SELECT COUNT(*) FROM job_applications')->fetchColumn();
 echo json_encode(['fingerprint'=>hash('sha256',json_encode($row)),'count'=>(int)$count]);
}catch(Throwable $e){fwrite(STDERR,'ERP_DB_PROBE_FAILED: '.get_class($e));exit(4);}
"""
with tempfile.TemporaryDirectory(prefix="am176db_") as temp:
    import json
    d=Path(temp)
    public_check=d/"public-check.php";public_check.write_text(legacy_script,encoding="utf8")
    erp_check=d/"erp-check.php";erp_check.write_text(erp_script,encoding="utf8")
    p=subprocess.run(["php",str(public_check),str(cfg)],capture_output=True,text=True,timeout=25)
    if p.returncode:
        raise SystemExit("AM176_STOP_PUBLIC_DB_CHECK_FAILED "+p.stderr[:180])
    e=subprocess.run(["php",str(erp_check),str(root)],capture_output=True,text=True,timeout=25)
    if e.returncode:
        raise SystemExit("AM176_STOP_ERP_DB_CHECK_FAILED "+e.stderr[:180])
    try:a,b=json.loads(p.stdout),json.loads(e.stdout)
    except ValueError:raise SystemExit("AM176_STOP_PROBE_OUTPUT_INVALID")
    print("AM176_PUBLIC_DATABASE_APPLICATION_COUNT",a["count"])
    print("AM176_ERP_DATABASE_APPLICATION_COUNT",b["count"])
    if a["fingerprint"]==b["fingerprint"]:
        print("AM176_SAME_DB_ALREADY_NO_BRIDGE_NEEDED")
        print("AM176_NEXT_CHECK_ERP_FILTERS_ACCESS_AND_PAGE_REFRESH")
        sys.exit(0)
    print("AM176_DIFFERENT_DATABASES_CONFIRMED")

# Bootstrap NEW ERP Laravel inside server-side public PHP only, never in client JS.
# Importing Laravel is allowed only if hosting's open_basedir permits access;
# a separate web-runtime smoke test is required before considering LIVE verified.
bridge=r"""
/* AM176_PUBLIC_HIRING_DIRECT_NEW_ERP_DB
   Public applicant data is inserted directly into NEW ERP's authoritative DB.
   Use server-side Laravel config; never publish DB credentials in JS or HTML.
*/
function am176ErpHiringPdo(): PDO {
    $root = dirname(__DIR__, 2) . '/assam-erp-staging';
    if (!is_file($root . '/bootstrap/app.php') || !is_file($root . '/vendor/autoload.php')) {
        throw new RuntimeException('ERP integration temporarily unavailable');
    }
    require_once $root . '/vendor/autoload.php';
    $app = require $root . '/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $pdo = $app->make('db')->connection()->getPdo();
    if (!($pdo instanceof PDO)) {
        throw new RuntimeException('ERP database connection unavailable');
    }
    return $pdo;
}
"""
candidate=source.replace("require_once 'config.php';",
    "require_once 'config.php';\n"+bridge,1)
candidate=candidate.replace("$db = getDB();","$db = am176ErpHiringPdo();",1)
# The legacy endpoint returned raw SQL exception text to the applicant.
# Suppress database details while logging only the exception class server-side.
old="sendJson(['error' => 'Server error: ' . $e->getMessage()], 500);"
if candidate.count(old)==1:
    candidate=candidate.replace(old,
        "error_log('AM176 hiring API failure: ' . get_class($e));\n    sendJson(['error' => 'Unable to submit application right now. Please try again later.'], 500);",1)
    candidate=candidate.replace("} catch (Exception $e) {\n    error_log('AM176 hiring API failure:",
        "} catch (\\Throwable $e) {\n    error_log('AM176 hiring API failure:",1)
if candidate.count(marker)!=1 or candidate.count("am176ErpHiringPdo();")!=1:
    raise SystemExit("AM176_STOP_CANDIDATE_INTEGRITY_FAILED")
with tempfile.TemporaryDirectory(prefix="am176check_") as d:
    check=Path(d)/"job-apply.php"
    check.write_text(candidate,encoding="utf8",errors="surrogateescape")
    test=subprocess.run(["php","-l",str(check)],text=True,capture_output=True)
    if test.returncode:
        raise SystemExit("AM176_PHP_SYNTAX_FAIL "+(test.stdout+test.stderr)[-350:])
print("AM176_PHP_SYNTAX_PASS")
if action=="check":
    print("AM176_PREFLIGHT_PASS_NO_WRITES")
    sys.exit(0)
stamp=datetime.datetime.now().strftime("%Y%m%d_%H%M%S")
safe_backups.mkdir(parents=True,exist_ok=True)
os.chmod(safe_backups,0o700)
backup=safe_backups/(target.name+".AM176_"+stamp+".bak")
shutil.copy2(target,backup)
os.chmod(backup,0o600)
# Old backups from this patch's first deployment may remain under public_html.
# Move them outside the public document root; do not delete historical backups.
for legacy in target.parent.glob("job-apply.php.AM176_*.bak"):
    destination=safe_backups/legacy.name
    if not destination.exists():
        shutil.move(str(legacy),str(destination))
        os.chmod(destination,0o600)
fd,filename=tempfile.mkstemp(prefix=".am176_",dir=target.parent)
try:
    with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:
        f.write(candidate)
    os.chmod(filename,target.stat().st_mode & 0o777)
    os.replace(filename,target)
finally:
    if os.path.exists(filename):os.unlink(filename)
print("AM176_PUBLIC_HIRING_DB_BRIDGE_INSTALLED")
print("AM176_BACKUP",backup.name)
print("AM176_NO_APPLICANT_RECORDS_MODIFIED_DURING_INSTALL")
print("AM176_WEB_RUNTIME_QA_REQUIRED_BEFORE_LIVE_VERIFIED")
