#!/usr/bin/env python3
"""AM463: stage PHP code without enabling any new finance route or mutating ERP DB.

Usage:
    python3 stage-staging.py check /path/to/laravel/staging
    python3 stage-staging.py stage /path/to/laravel/staging
    python3 stage-staging.py rollback /path/to/laravel/staging

Idempotent for untouched staged copies. Refuses to overwrite unrelated content.
A separate authenticated route review is REQUIRED before these controllers are live.
"""
from __future__ import annotations

import hashlib
import json
import os
import shutil
import subprocess
import sys
from pathlib import Path

PACKAGE = Path(__file__).resolve().parent
STAMP = ".am463_native_staff_finance_stage_manifest.json"
SOURCES = {
    "FinanceReadContract.php": "app/Services/NativeFinanceV1/FinanceReadContract.php",
    "StaffExpenseFinanceProvider.php": "app/Services/NativeFinanceV1/StaffExpenseFinanceProvider.php",
    "LaravelStaffFinanceReadBridge.php": "app/Services/NativeFinanceV1/LaravelStaffFinanceReadBridge.php",
    "Am461StaffFinanceReadController.php":
        "app/Http/Controllers/Api/V1/Am461StaffFinanceReadController.php",
}


def digest(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def fail(reason: str) -> None:
    raise SystemExit("AM463_BLOCKED: " + reason)


def preflight(root: Path) -> None:
    if not root.is_dir():
        fail("Laravel root not found")
    for name in ("artisan", "vendor/autoload.php", "bootstrap/app.php", "routes/api.php"):
        if not (root / name).is_file():
            fail("Staging Laravel prerequisite missing: " + name)
    # Do not change or overwrite work in another active branch / unfinished module.
    if not (root / "app/Services/StaffMultiExpensePaymentService.php").is_file():
        fail("AM245 Staff multi-expense accounting service not installed")
    source = (root / "routes/api.php").read_text(encoding="utf8")
    if "/finance/v1/payees" in source or "/finance/v1/open-references" in source:
        fail("Finance read routes already present; inspect existing runtime before changing")
    php = shutil.which("php")
    if php is None:
        fail("php CLI missing")
    for src in SOURCES:
        path = PACKAGE / src
        if not path.is_file():
            fail("Package source missing: " + src)
        result = subprocess.run(
            [php, "-l", str(path)], capture_output=True, text=True, check=False
        )
        if result.returncode != 0:
            fail("Source PHP syntax check failed: " + src)


def stage(root: Path) -> None:
    preflight(root)
    manifest_file = root / STAMP
    if manifest_file.exists():
        fail("Staging manifest already present, rollback before a new stage")
    # Fully refuse overwrites: no backups or financial source replacements needed.
    for rel in SOURCES.values():
        if (root / rel).exists():
            fail("Destination already exists, no overwrite permitted: " + rel)
    created: list[str] = []
    try:
        for source, relative in SOURCES.items():
            dest = root / relative
            dest.parent.mkdir(parents=True, exist_ok=True)
            # Exclusive create avoids replacing a controller installed concurrently.
            with dest.open("xb") as output:
                output.write((PACKAGE / source).read_bytes())
            os.chmod(dest, 0o644)
            created.append(relative)
        manifest = {
            "package": "AM463_NATIVE_STAFF_FINANCE_READ_ONLY",
            "route_activated": False,
            "files": {rel: digest(root / rel) for rel in created},
        }
        with manifest_file.open("x", encoding="utf8") as output:
            json.dump(manifest, output, indent=2, sort_keys=True)
        print("AM463_STAGED_PHP_CODE_ONLY")
        print("AM463_ROUTES=NOT_MODIFIED; FINANCE_WRITES=NONE")
        print("AM463_REQUIRED_NEXT=VERIFY_EXISTING_AUTH_API_ROUTE_GROUP")
    except Exception:
        for rel in created:
            (root / rel).unlink(missing_ok=True)
        raise


def rollback(root: Path) -> None:
    manifest_file = root / STAMP
    if not manifest_file.is_file():
        fail("Staging manifest absent; refusing unsafe rollback")
    manifest = json.loads(manifest_file.read_text(encoding="utf8"))
    if manifest.get("package") != "AM463_NATIVE_STAFF_FINANCE_READ_ONLY":
        fail("Staging manifest identity mismatch")
    files = manifest.get("files")
    if not isinstance(files, dict) or set(files) != set(SOURCES.values()):
        fail("Staging file list mismatch")
    # Only remove files whose content is byte-identical to the package.
    for relative, expected in files.items():
        target = root / relative
        if not target.is_file() or digest(target) != expected:
            fail("File changed since stage; manual review required: " + relative)
    for relative in files:
        (root / relative).unlink()
    manifest_file.unlink()
    print("AM463_STAGED_PHP_FILES_ROLLED_BACK")
    print("AM463_FINANCIAL_DATA_UNCHANGED")


if __name__ == "__main__":
    if len(sys.argv) != 3 or sys.argv[1] not in {"check", "stage", "rollback"}:
        fail("Usage: stage-staging.py <check|stage|rollback> <laravel-root>")
    mode, root = sys.argv[1], Path(sys.argv[2]).resolve()
    if mode == "check":
        preflight(root)
        print("AM463_PRECHECK_PASS; ROUTES_UNCHANGED")
    elif mode == "stage":
        stage(root)
    else:
        rollback(root)
