#!/usr/bin/env python3
"""Safely STAGE AM245/AM246 in an existing Laravel ERP; never activate routes or DB.

This script does not execute artisan, bootstrap Laravel, read .env, migrate DB,
change existing application files, register Gates, or claim a working deployment.

Usage (run from anywhere after downloading a whole copy of the module):
    python3 tools/stage_feedback.py --root /path/to/laravel
    python3 tools/stage_feedback.py --root /path/to/laravel --stage
    python3 tools/stage_feedback.py --root /path/to/laravel --rollback
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import sys
import tempfile

MODULE = Path(__file__).resolve().parent.parent
COPY_MAP = {
    "app/Contracts/AmEngagementLedger.php":
        "app/Contracts/AmEngagementLedger.php",
    "app/Contracts/AmFeedbackCustomerSource.php":
        "app/Contracts/AmFeedbackCustomerSource.php",
    "app/Support/AmFeedbackCsv.php":
        "app/Support/AmFeedbackCsv.php",
    "app/Http/Controllers/Admin/AmCustomerEngagementController.php":
        "app/Http/Controllers/Admin/AmCustomerEngagementController.php",
    "resources/views/admin/engagement/index.blade.php":
        "resources/views/admin/engagement/index.blade.php",
    "resources/views/admin/engagement/messaging.blade.php":
        "resources/views/admin/engagement/messaging.blade.php",
    "resources/views/admin/engagement/sidebar-links.blade.php":
        "resources/views/admin/engagement/sidebar-links.blade.php",
    "database/migrations/2026_11_12_010000_create_am_customer_engagement.php":
        "database/migrations/2026_11_12_010000_create_am_customer_engagement.php",
    "routes.php": "routes/am245-am246.php",
}
MANIFEST = "storage/app/am245-stage-manifest.json"


def checksum(path):
    h = hashlib.sha256()
    with path.open("rb") as source:
        for block in iter(lambda: source.read(65536), b""):
            h.update(block)
    return h.hexdigest()


def safe_target(root, rel):
    target = root / rel
    # Reject symlink components rather than following them to unexpected locations.
    cursor = target
    while cursor != root:
        if cursor.is_symlink():
            raise ValueError("Refusing symlinked path: " + rel)
        cursor = cursor.parent
    if not target.resolve().is_relative_to(root):
        raise ValueError("Target escapes project root: " + rel)
    return target


def verify_project(root):
    if not root.is_dir() or not (root / "artisan").is_file():
        raise ValueError("Laravel root must exist and contain artisan")
    for key in ("app", "routes", "resources/views", "database/migrations", "storage/app"):
        if not (root / key).is_dir():
            raise ValueError("Expected deployed Laravel directory missing: " + key)


def mapped_files(root):
    result = []
    for src, dst in COPY_MAP.items():
        source = MODULE / src
        if not source.is_file() or source.is_symlink():
            raise ValueError("Missing or linked module source: " + src)
        target = safe_target(root, dst)
        status = ("identical" if checksum(source) == checksum(target)
                  else "CONFLICT") if target.exists() else "new"
        result.append((source, target, dst, status))
    return result


def write_atomic(target, data):
    target.parent.mkdir(parents=True, exist_ok=True)
    fd, name = tempfile.mkstemp(prefix=".am245-", dir=target.parent)
    try:
        with os.fdopen(fd, "wb") as output:
            output.write(data)
        os.chmod(name, 0o640)
        os.replace(name, target)
    finally:
        if os.path.exists(name):
            os.unlink(name)


def stage(root, entries, manifest):
    if manifest.exists():
        raise ValueError("Existing AM245 staging manifest; rollback/review first")
    if any(status == "CONFLICT" for _, _, _, status in entries):
        raise ValueError("Existing files differ; refusing to overwrite")
    created = []
    try:
        for source, target, rel, status in entries:
            if status != "new":
                continue
            write_atomic(target, source.read_bytes())
            created.append({"path": rel, "sha256": checksum(target)})
        payload = {"format": 1, "root": str(root), "created": created,
                   "mode": "staged_only_routes_not_registered_migrations_not_run"}
        write_atomic(manifest, json.dumps(payload, indent=2).encode("utf-8"))
    except BaseException:
        # Remove only intact files created by this invocation, not user changes.
        for record in created:
            target = safe_target(root, record["path"])
            if target.is_file() and checksum(target) == record["sha256"]:
                target.unlink()
        raise
    print("STAGED_ONLY:", len(created), "new files; existing ERP routes and database unchanged")
    print("MANIFEST:", MANIFEST)
    print("NOT_LIVE: bind source, register auth gates/routes/sidebar and safely migrate after backup")


def rollback(root, manifest):
    if not manifest.is_file() or manifest.is_symlink():
        raise ValueError("No safe staging manifest found")
    payload = json.loads(manifest.read_text())
    if payload.get("format") != 1 or payload.get("root") != str(root):
        raise ValueError("Manifest root or format mismatch; refusing rollback")
    records = payload.get("created")
    if not isinstance(records, list):
        raise ValueError("Invalid created-file manifest")
    targets = []
    for record in records:
        rel = record.get("path", "")
        if rel not in COPY_MAP.values():
            raise ValueError("Unexpected manifest path, refusing deletion")
        target = safe_target(root, rel)
        if not target.is_file() or checksum(target) != record.get("sha256"):
            raise ValueError("File changed or missing; aborting rollback: " + rel)
        targets.append(target)
    for target in targets:
        target.unlink()
    manifest.unlink()
    print("ROLLBACK_OK: removed only exact unchanged staged files")
    print("Existing ERP routes and database were not modified by this script")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, required=True,
                        help="Absolute path of existing staging Laravel root")
    group = parser.add_mutually_exclusive_group()
    group.add_argument("--stage", action="store_true",
                       help="Copy new isolated files only; NO activation")
    group.add_argument("--rollback", action="store_true",
                       help="Remove only files staged by this tool, if unchanged")
    args = parser.parse_args()
    root = args.root.resolve()
    verify_project(root)
    manifest = safe_target(root, MANIFEST)
    if args.rollback:
        rollback(root, manifest)
        return
    entries = mapped_files(root)
    print("AM245/AM246 STAGING FILE PLAN")
    for _, _, rel, status in entries:
        print(status.upper() + ": " + rel)
    print("LARAVEL_ROUTING_FILE_EXISTS:", (root / "routes/web.php").is_file())
    print("APP_SERVICE_PROVIDER_EXISTS:", (root / "app/Providers/AppServiceProvider.php").is_file())
    print("COMPATIBILITY_APPROVAL_REQUIRED: yes")
    print("CUSTOMER_VEHICLE_JC_ADAPTER_BINDING_REQUIRED: yes")
    print("OWNER_ADMIN_GATE_REGISTRATION_REQUIRED: yes")
    print("DATABASE_BACKUP_AND_MIGRATION_REQUIRED: yes")
    print("CURRENT_LIVE_STATUS: unchanged")
    if args.stage:
        stage(root, entries, manifest)
    else:
        print("DRY_RUN_ONLY: no files written, no commands executed")


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, json.JSONDecodeError) as exc:
        print("AM245_STAGE_ERROR:", exc, file=sys.stderr)
        sys.exit(2)
