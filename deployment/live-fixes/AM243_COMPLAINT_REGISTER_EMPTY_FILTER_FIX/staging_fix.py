#!/usr/bin/env python3
"""AM243: Guarded STAGING-only fix for missing month in Work Complaint Register.
Usage: staging_fix.py check|apply|rollback STAGING_ROOT
No database writes, no job card/ROT edits. Includes reversible file backup.
"""
from pathlib import Path
from datetime import datetime
import hashlib
import os
import shutil
import subprocess
import sys
import tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply", "rollback"):
    raise SystemExit("USAGE_AM243: staging_fix.py check|apply|rollback STAGING_ROOT")
mode = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
if root.name != "assam-erp-staging" or not (root / "artisan").is_file():
    raise SystemExit("STOP_AM243_NOT_STAGING")
target = root / "resources/views/work-complaints/register.blade.php"
backup_base = root / "storage/app/am-backups"
pointer = backup_base / "AM243_LAST_BACKUP.txt"
old = b'value="{{ $filters[\'month\'] }}"'
new = b'value="{{ $filters[\'month\'] ?? \'\' }}"'

def run(args, timeout=90):
    return subprocess.run(args, cwd=root, capture_output=True, text=True, timeout=timeout)

def digest(b):
    return hashlib.sha256(b).hexdigest()

def atomic_write(path, data):
    fd, tmp = tempfile.mkstemp(prefix=".am243_", dir=path.parent)
    try:
        with os.fdopen(fd, "wb") as f:
            f.write(data)
        os.chmod(tmp, path.stat().st_mode & 0o777)
        os.replace(tmp, path)
    finally:
        if os.path.exists(tmp):
            os.unlink(tmp)

def compile_blade(blob):
    with tempfile.TemporaryDirectory(prefix="am243_preflight_") as d:
        source = Path(d) / "register.blade.php"
        output = Path(d) / "compiled.php"
        source.write_bytes(blob)
        php = r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        compile_result = run(["php", "-r", php, str(source), str(output)], 45)
        if compile_result.returncode or not output.is_file():
            raise SystemExit("STOP_AM243_BLADE_COMPILE " + (compile_result.stdout + compile_result.stderr)[-700:])
        lint = run(["php", "-d", "display_errors=1", "-d", "log_errors=0", "-l", str(output)], 30)
        if lint.returncode:
            raise SystemExit("STOP_AM243_COMPILED_PHP_LINT " + (lint.stdout + lint.stderr)[-700:])

def smoke_render():
    php = r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!\Illuminate\Support\Facades\Route::has('erp.rot-work-complaints.register')) {
 fwrite(STDERR,'AM243_NO_REGISTER_ROUTE'); exit(20);
}
try {
 $html=view('work-complaints.register',['filters'=>[], 'cases'=>collect()])->render();
 if (strpos($html,'Employee Work Complaint Register')===false) {
  fwrite(STDERR,'AM243_NO_REGISTER_HEADING');exit(21);
 }
 echo 'AM243_EMPTY_FILTER_RENDER_PASS';
} catch (\Throwable $e) {
 fwrite(STDERR,get_class($e).': '.$e->getMessage());exit(22);
}"""
    return run(["php", "-d", "display_errors=1", "-r", php], 50)

if mode == "rollback":
    if not pointer.is_file():
        raise SystemExit("STOP_AM243_BACKUP_NOT_FOUND")
    backup = Path(pointer.read_text().strip()).resolve()
    if backup_base.resolve() not in backup.parents or not backup.name.startswith("AM243_COMPLAINT_REGISTER_"):
        raise SystemExit("STOP_AM243_BACKUP_PATH_INVALID")
    before = backup / "original_register.blade.php"
    installed_sha = backup / "installed_sha256.txt"
    if not before.is_file() or not installed_sha.is_file() or not target.is_file():
        raise SystemExit("STOP_AM243_ROLLBACK_FILES_MISSING")
    if digest(target.read_bytes()) != installed_sha.read_text().strip():
        raise SystemExit("STOP_AM243_TARGET_CHANGED_SINCE_INSTALL")
    atomic_write(target, before.read_bytes())
    cleared = run(["php", "artisan", "view:clear"], 60)
    if cleared.returncode:
        raise SystemExit("AM243_ROLLBACK_RESTORED_BUT_CACHE_CLEAR_FAILED")
    print("AM243_ROLLBACK_RESTORED_REGISTER_VIEW")
    raise SystemExit(0)

if not target.is_file():
    raise SystemExit("STOP_AM243_AM242_REGISTER_NOT_INSTALLED")
original = target.read_bytes()
if original.count(old) != 1:
    if original.count(new) == 1:
        print("AM243_REGISTER_ALREADY_FIXED_NO_CHANGES")
        raise SystemExit(0)
    raise SystemExit("STOP_AM243_UNEXPECTED_REGISTER_SOURCE_NO_CHANGES")
updated = original.replace(old, new, 1)
compile_blade(updated)
print("AM243_PATCHED_BLADE_COMPILATION_PASS")
print("AM243_ORIGINAL_SHA256", digest(original))
print("AM243_NEW_SHA256", digest(updated))
if mode == "check":
    print("AM243_PREFLIGHT_PASS_READ_ONLY")
    raise SystemExit(0)

backup = backup_base / ("AM243_COMPLAINT_REGISTER_" + datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True, exist_ok=False)
shutil.copy2(target, backup / "original_register.blade.php")
try:
    atomic_write(target, updated)
    clear = run(["php", "artisan", "view:clear"], 60)
    if clear.returncode:
        raise RuntimeError("VIEW_CLEAR_FAILED " + clear.stderr[-400:])
    rendered = smoke_render()
    if rendered.returncode or "AM243_EMPTY_FILTER_RENDER_PASS" not in rendered.stdout:
        raise RuntimeError("RENDER_FAILED " + (rendered.stdout + rendered.stderr)[-700:])
    print("AM243_EMPTY_FILTER_RENDER_PASS")
except Exception as e:
    atomic_write(target, original)
    run(["php", "artisan", "view:clear"], 60)
    raise SystemExit("STOP_AM243_APPLY_FAILED_RESTORED " + str(e))

(backup / "installed_sha256.txt").write_text(digest(updated) + "\n")
pointer.write_text(str(backup) + "\n")
print("AM243_COMPLAINT_REGISTER_NO_FILTER_FIX_INSTALLED")
print("AM243_COMPLAINT_AND_ORIGINAL_ROT_DATA_UNCHANGED")
print("BROWSER_CHECK_REQUIRED: /erp/rot-work-complaints and /erp/rot-work-complaints?month=2026-11")
