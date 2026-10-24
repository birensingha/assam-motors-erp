#!/usr/bin/env python3
"""FIX123: keep Work Complete visible but disabled when WIP gates fail.

Usage: python3 apply_fix123_staging.py check /path/to/staging
       python3 apply_fix123_staging.py apply /path/to/staging
No DB or controller changes. Refuse unknown Blade revisions.
"""
import hashlib
import os
from pathlib import Path
import subprocess
import sys
from datetime import datetime, timezone

BASELINE_SHA256 = "c8a2642f217ce7ca34886dd50a0cd25532f877400d9765bdca955a228d7f00d4"
SOURCE = Path("resources/views/job-cards/lifecycle-panel.blade.php")
ANCHOR = "        {{-- FIX48_WORK_COMPLETE_GUARDS: don't offer premature Work Complete submit --}}\n"
EXISTING_GATE = "        @if($amLifeStage==='WIP' && $diagnosisReady && !$amLifeRotActive && !$amLifeLabourPending && !$amLifePartPending && !$amLifeOslPending)"
MARKER = "FIX123_WIP_WORK_COMPLETE_VISIBLE_DISABLED"
INSERT = """        {{-- FIX123_WIP_WORK_COMPLETE_VISIBLE_DISABLED: visibility only; never submit when blocked. --}}
        @if($amLifeStage==='WIP' && (!$diagnosisReady || $amLifeRotActive || $amLifeLabourPending || $amLifePartPending || $amLifeOslPending))
          <button type="button" class="btn" disabled aria-disabled="true"
             title="Complete Inspection, Diagnosis, Technician Decision, ROT, Labour, Parts and OSL before Work Complete"
             style="cursor:not-allowed;opacity:.72;background:#e5e7eb;color:#475569;border:1px solid #cbd5e1"
          >✓ Work Complete</button>
        @endif
"""

def fail(message):
    print("FIX123_ABORT=" + message, flush=True)
    sys.exit(2)

def sha256(data):
    return hashlib.sha256(data).hexdigest()

def verify_compiled(root, source_file):
    """Compile modified Blade *before* replacing live source, then lint the PHP."""
    from tempfile import TemporaryDirectory
    with TemporaryDirectory(prefix="fix123_php_") as d:
        compiled = str(Path(d) / "lifecycle_compiled.php")
        php = r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$compiled=\Illuminate\Support\Facades\Blade::compileString(file_get_contents($argv[1]));
file_put_contents($argv[2],$compiled);"""
        a = subprocess.run(["php", "-r", php, str(source_file), compiled],
                           cwd=root, capture_output=True, text=True, timeout=45)
        if a.returncode != 0:
            fail("BLADE_COMPILE_FAILED")
        b = subprocess.run(["php", "-l", compiled], cwd=root,
                           capture_output=True, text=True, timeout=30)
        if b.returncode != 0:
            fail("COMPILED_BLADE_PHP_LINT_FAILED")
    print("FIX123_BLADE_COMPILE_AND_PHP_LINT=PASS")

def main():
    if len(sys.argv) != 3 or sys.argv[1] not in ("check", "apply"):
        fail("USAGE_CHECK_OR_APPLY_AND_APP_ROOT")
    mode, root = sys.argv[1], Path(sys.argv[2]).resolve()
    if not (root / "artisan").is_file() or not (root / "vendor/autoload.php").is_file():
        fail("NOT_LARAVEL_ROOT")
    target = root / SOURCE
    if not target.is_file():
        fail("LIFECYCLE_VIEW_MISSING")
    raw = target.read_bytes()
    old = raw.decode("utf-8")
    print("FIX123_MODE=" + mode)
    print("FIX123_LIVE_SHA256=" + sha256(raw))
    if MARKER in old:
        print("FIX123_ALREADY_APPLIED=YES")
        return
    if sha256(raw) != BASELINE_SHA256:
        fail("BASELINE_SHA_MISMATCH; REFUSE_OVERWRITE")
    if old.count(ANCHOR) != 1 or old.count(EXISTING_GATE) != 1:
        fail("EXPECTED_WORK_COMPLETE_ANCHORS_MISSING_OR_AMBIGUOUS")
    if old.index(ANCHOR) > old.index(EXISTING_GATE):
        fail("UNEXPECTED_ANCHOR_ORDER")
    updated = old.replace(ANCHOR, ANCHOR + INSERT, 1)
    if updated.count(MARKER) != 1 or updated.count(EXISTING_GATE) != 1:
        fail("POST_PATCH_ANCHOR_CHECK_FAILED")
    print("FIX123_ACTION_FORM_UNCHANGED=YES")
    print("FIX123_CONTROLLER_ROUTE_DB_UNCHANGED=YES")
    if mode == "check":
        print("FIX123_CHECK_PASS_NO_WRITES")
        return
    backup_dir = root / "storage/app/am-backups"
    backup_dir.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backup = backup_dir / ("FIX123_lifecycle_panel_" + stamp + ".blade.php.bak")
    with os.fdopen(os.open(backup, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600), "wb") as f:
        f.write(raw)
        f.flush()
        os.fsync(f.fileno())
    if sha256(backup.read_bytes()) != BASELINE_SHA256:
        fail("BACKUP_HASH_MISMATCH")
    print("FIX123_BACKUP=" + str(backup))
    temp = target.with_name(".lifecycle-panel.fix123.tmp.blade.php")
    if temp.exists():
        fail("TEMP_FILE_ALREADY_EXISTS")
    try:
        temp.write_text(updated, encoding="utf-8")
        os.chmod(temp, os.stat(target).st_mode & 0o777)
        verify_compiled(root, temp)
        if sha256(target.read_bytes()) != BASELINE_SHA256:
            fail("LIVE_CHANGED_DURING_TEST")
        os.replace(temp, target)
        new_raw = target.read_bytes()
        if sha256(new_raw) != sha256(updated.encode("utf-8")):
            os.replace(backup, target)
            fail("POST_WRITE_MISMATCH_ROLLBACK_DONE")
        print("FIX123_STAGING_UI_PATCH=APPLIED")
        print("FIX123_NEW_SHA256=" + sha256(new_raw))
        print("FIX123_NO_STOCK_OR_BILLING_WRITES=YES")
    finally:
        temp.unlink(missing_ok=True)

if __name__ == "__main__":
    main()
