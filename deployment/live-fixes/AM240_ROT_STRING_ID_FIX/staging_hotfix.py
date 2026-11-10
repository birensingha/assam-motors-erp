#!/usr/bin/env python3
"""AM240 string ROT ID fix. STAGING only. Usage: staging_hotfix.py check|apply|rollback LARAVEL_ROOT.
This does not change any original ROT, Job Card, staff productivity or invoice rows.
"""
from pathlib import Path
from datetime import datetime
import json
import os
import shutil
import subprocess
import sys
import tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply", "rollback"):
    raise SystemExit("USAGE staging_hotfix.py check|apply|rollback STAGING_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
if root.name != "assam-erp-staging" or not (root / "artisan").is_file():
    raise SystemExit("STOP_NOT_STAGING_ROOT")
controller = root / "app/Http/Controllers/Web/RotWorkComplaintController.php"
migration_rel = "database/migrations/2026_11_10_133000_am240_rot_string_id_compat.php"
new_migration = root / migration_rel
packaged_migration = Path(__file__).resolve().parent / "files" / migration_rel
backup_pointer = root / "storage/app/am-backups/AM240_STRING_ROT_LAST_BACKUP.txt"
if mode == "rollback":
    if not backup_pointer.is_file():
        raise SystemExit("STOP_NO_HOTFIX_BACKUP")
    saved = Path(backup_pointer.read_text().strip()).resolve()
    allowed = (root / "storage/app/am-backups").resolve()
    if allowed not in saved.parents or not saved.name.startswith("AM240_ROT_ID_FIX_"):
        raise SystemExit("STOP_UNSAFE_BACKUP_POINTER")
    if not (saved / "controller.php").is_file():
        raise SystemExit("STOP_MISSING_BACKUP")
    original_text = (saved / "controller.php").read_bytes()
    if b"private function ownedRot(int $job,string $rot): object" not in controller.read_bytes():
        raise SystemExit("STOP_CURRENT_CONTROLLER_CHANGED")
    shutil.copy2(saved / "controller.php", controller)
    cleared = subprocess.run(["php", "artisan", "optimize:clear"], cwd=root, capture_output=True, text=True, timeout=90)
    if cleared.returncode:
        raise SystemExit("STOP_CONTROLLER_RESTORED_BUT_CACHE_CLEAR_FAILED")
    print("AM240_STRING_ROT_CONTROLLER_ROLLED_BACK")
    print("No complaint audit rows or database schema changes were removed.")
    sys.exit(0)

if not controller.is_file() or not packaged_migration.is_file():
    raise SystemExit("STOP_MISSING_AM240_CONTROLLER_OR_HOTFIX_MIGRATION")
source = controller.read_bytes().decode("utf-8", "surrogateescape")
changes = [
    ("private function ownedRot(int $job,int $rot): object",
     "private function ownedRot(int $job,string $rot): object"),
    ("public function create(Request $request,int $job,int $rot)",
     "public function create(Request $request,int $job,string $rot)"),
    ("public function store(Request $request,int $job,int $rot)",
     "public function store(Request $request,int $job,string $rot)"),
]
updated = source
for before, after in changes:
    count = updated.count(before)
    if count != 1:
        if updated.count(after) == 1:
            raise SystemExit("STOP_ALREADY_PATCHED_OR_PARTIAL_FIX: " + after)
        raise SystemExit("STOP_UNEXPECTED_CONTROLLER_SOURCE: " + before)
    updated = updated.replace(before, after, 1)
if new_migration.exists():
    raise SystemExit("STOP_HOTFIX_MIGRATION_COLLISION")

def lint_bytes(data, label):
    with tempfile.NamedTemporaryFile(mode="wb", suffix=".php", delete=False) as temporary:
        temporary.write(data)
        name = temporary.name
    try:
        result = subprocess.run(["php", "-l", name], capture_output=True, text=True, timeout=20)
        if result.returncode:
            raise SystemExit("STOP_PHP_LINT_" + label + " " + result.stdout[-350:] + result.stderr[-350:])
    finally:
        Path(name).unlink(missing_ok=True)

lint_bytes(updated.encode("utf-8", "surrogateescape"), "CONTROLLER")
lint_bytes(packaged_migration.read_bytes(), "MIGRATION")

# Read only: assess the source and target ID types and existing complaint rows.
probe = r"""
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$columns = \Illuminate\Support\Facades\DB::select(
 "SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name,
         DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS maxlen
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND ((TABLE_NAME='rot_sessions' AND COLUMN_NAME='id')
      OR (TABLE_NAME='am_rot_work_complaints' AND COLUMN_NAME='rot_session_id'))"
);
if (count($columns)!==2) {fwrite(STDERR,"AM240_EXPECTED_COLUMNS_MISSING");exit(12);}
$rows = \Illuminate\Support\Facades\DB::table('am_rot_work_complaints')->count();
echo json_encode(['columns'=>$columns,'complaint_rows'=>$rows],JSON_THROW_ON_ERROR);
"""
cmd = subprocess.run(["php", "-r", probe], cwd=root, capture_output=True, text=True, timeout=50)
if cmd.returncode:
    raise SystemExit("STOP_ROT_SCHEMA_PROBE_FAILED " + cmd.stderr[-400:])
try:
    info = json.loads(cmd.stdout)
except ValueError:
    raise SystemExit("STOP_INVALID_SCHEMA_PROBE_OUTPUT")
columns = {(x["table_name"], x["column_name"]): x for x in info["columns"]}
orig = columns[("rot_sessions", "id")]
target = columns[("am_rot_work_complaints", "rot_session_id")]
orig_type = orig["data_type"].lower()
target_type = target["data_type"].lower()
if orig_type in ("varchar", "char"):
    if int(orig.get("maxlen") or 0) > 191:
        raise SystemExit("STOP_ROT_ID_COLUMN_LONGER_THAN_191")
    if target_type not in ("varchar", "char") and int(info["complaint_rows"]) != 0:
        raise SystemExit("STOP_EXISTING_COMPLAINT_ROWS_REVIEW_DB_CONVERSION")
elif orig_type not in ("bigint", "int", "smallint", "mediumint", "tinyint"):
    raise SystemExit("STOP_UNSUPPORTED_ROT_ID_TYPE")
print("AM240_ROT_ID_SOURCE_TYPE", orig_type)
print("AM240_COMPLAINT_ID_STORAGE_TYPE", target_type)
print("AM240_EXISTING_COMPLAINT_ROW_COUNT", int(info["complaint_rows"]))
print("AM240_STRING_ROT_PREFLIGHT_PASS")
if mode == "check":
    print("AM240_CHECK_READ_ONLY_NO_WRITES")
    sys.exit(0)

backup = root / "storage/app/am-backups" / ("AM240_ROT_ID_FIX_" + datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True, exist_ok=False)
shutil.copy2(controller, backup / "controller.php")
print("BACKUP", backup)
migration_copied = False
try:
    shutil.copy2(packaged_migration, new_migration)
    migration_copied = True
    applied = subprocess.run(
        ["php", "artisan", "migrate", "--path=" + migration_rel, "--force"],
        cwd=root, capture_output=True, text=True, timeout=90
    )
    if applied.returncode:
        raise RuntimeError("MIGRATION_FAILED " + (applied.stdout + applied.stderr)[-700:])
    print("AM240_STRING_ROT_SCHEMA_COMPAT_PASS")
    print(applied.stdout[-400:])
    payload = updated.encode("utf-8", "surrogateescape")
    fd, tmp = tempfile.mkstemp(prefix=".am240rot_", dir=controller.parent)
    try:
        with os.fdopen(fd, "wb") as file:
            file.write(payload)
        os.chmod(tmp, controller.stat().st_mode & 0o777)
        os.replace(tmp, controller)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)
    if controller.read_bytes() != payload:
        raise RuntimeError("CONTROLLER_INSTALL_BYTE_MISMATCH")
    clear = subprocess.run(["php", "artisan", "optimize:clear"], cwd=root, capture_output=True, text=True, timeout=90)
    if clear.returncode:
        raise RuntimeError("CACHE_CLEAR_FAILED " + clear.stderr[-300:])
except Exception as exc:
    shutil.copy2(backup / "controller.php", controller)
    subprocess.run(["php", "artisan", "optimize:clear"], cwd=root, capture_output=True, timeout=50)
    print("AM240_CONTROLLER_RESTORED_AFTER_FAILURE")
    if migration_copied:
        print("NOTE: forward schema migration may remain; no user complaint data deleted")
    raise SystemExit("STOP_HOTFIX_FAILED " + str(exc))

backup_pointer.write_text(str(backup) + "\n")
print("AM240_ROT_STRING_ID_FIX_INSTALLED")
print("AM240_ORIGINAL_ROT_JOB_CARD_BILLING_UNTOUCHED")
print("BROWSER_GET_AND_COMPLAINT_POST_STILL_NEED_VERIFICATION")
