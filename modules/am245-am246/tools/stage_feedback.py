#!/usr/bin/env python3
"""Safely STAGE AM245/AM246 in an existing Laravel ERP; never activate routes or DB.

This script does not execute artisan, bootstrap Laravel, read .env, migrate DB,
change existing application files, register Gates, or claim a working deployment.

Usage (run from anywhere after downloading a whole copy of the module):
    python3 tools/stage_feedback.py --root /path/to/laravel
    python3 tools/stage_feedback.py --root /path/to/laravel --stage
    python3 tools/stage_feedback.py --root /path/to/laravel --rollback
    python3 tools/stage_feedback.py --root /path/to/laravel --scope all --stage

The default scope is AM245 feedback-only (no AM246 messaging dependencies).
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
    "database/migrations/2026_11_19_000000_create_am246_customer_message_log.php":
        "database/migrations/2026_11_19_000000_create_am246_customer_message_log.php",
}

# Keep AM245 deployable without AM246's ledger binding, money/message tables,
# financial routes, or finance sidebar. This is a staged FILE COPY only.
FEEDBACK_COPY_MAP = {
    key: value for key, value in COPY_MAP.items()
    if key in {
        "app/Contracts/AmFeedbackCustomerSource.php",
        "app/Support/AmFeedbackCsv.php",
        "app/Http/Controllers/Admin/AmCustomerEngagementController.php",
        "resources/views/admin/engagement/index.blade.php",
        "database/migrations/2026_11_12_010000_create_am_customer_engagement.php",
    }
}
FEEDBACK_COPY_MAP.update({
    "feedback-only-routes.php": "routes/am245-feedback.php",
    "resources/views/admin/engagement/feedback-only-sidebar.blade.php":
        "resources/views/admin/engagement/feedback-only-sidebar.blade.php",
})

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


def mapped_files(root, scope="all"):
    if scope not in ("all", "feedback"):
        raise ValueError("Unknown scope")
    selected = FEEDBACK_COPY_MAP if scope == "feedback" else COPY_MAP
    result = []
    for src, dst in selected.items():
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
        # Atomic create-if-absent: unlike os.replace(), hard-linking the
        # complete temporary file refuses to overwrite a target created
        # after mapped_files() checked it (TOCTOU protection).
        os.link(name, target)
    finally:
        if os.path.exists(name):
            os.unlink(name)


def stage(root, entries, manifest):
    if manifest.exists() or manifest.is_symlink():
        raise ValueError("Existing AM245 staging manifest; rollback/review first")
    if any(status == "CONFLICT" for _, _, _, status in entries):
        raise ValueError("Existing files differ; refusing to overwrite")
    created = []
    try:
        for source, target, rel, status in entries:
            if status != "new":
                continue
            # Recheck after planning: a directory may have been replaced by a symlink.
            # This narrows the race window; it is not a substitute for fd-relative
            # filesystem operations against an adversarial concurrent writer.
            target = safe_target(root, rel)
            write_atomic(target, source.read_bytes())
            created.append({"path": rel, "sha256": checksum(target)})
        payload = {"format": 1, "root": str(root), "created": created,
                   "mode": "staged_only_routes_not_registered_migrations_not_run"}
        manifest = safe_target(root, MANIFEST)
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
    seen = set()
    for record in records:
        if not isinstance(record, dict):
            raise ValueError("Invalid manifest record; refusing rollback")
        rel = record.get("path", "")
        if not isinstance(rel, str) or rel in seen:
            raise ValueError("Duplicate or invalid manifest path; refusing rollback")
        seen.add(rel)
        if rel not in (set(COPY_MAP.values()) | set(FEEDBACK_COPY_MAP.values())):
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
    parser.add_argument("--scope", choices=["feedback", "all"], default="feedback",
                        help="Default: AM245 Feedback only; all includes AM246")
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
    entries = mapped_files(root, args.scope)
    print("AM245/AM246 STAGING FILE PLAN; SCOPE:", args.scope)
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
