#!/usr/bin/env python3
"""AM485 STAGING-only reviewed authenticated API route activation/rollback.

No database mutation, no payment POSTs, no unreviewed route group guesses.
The operator must first inspect routes/api.php and place exactly one marker
INSIDE the existing verified /api/v1 authenticated group:

    // AM483_AUTHENTICATED_V1_INSERTION_REVIEWED

The route edit only inserts a require at that specific marker.
Do not run this on production. Stage AM484 source files first.

Commands:
  python3 activate-finance-routes.py check /path/to/staging-laravel
  python3 activate-finance-routes.py activate /path/to/staging-laravel \
      --expected-sha256 <from-check> --reviewed
  python3 activate-finance-routes.py rollback /path/to/staging-laravel

Actual LIVE STAGING route:list, Admin/Staff/anonymous HTTP and ledger checks
remain mandatory before describing the finance API as deployed.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import stat
import subprocess
import tempfile
from pathlib import Path
from urllib.parse import urlsplit

MARKER = "// AM483_AUTHENTICATED_V1_INSERTION_REVIEWED"
REQUIRE = "require base_path('app/Services/NativeFinanceV1/finance-read-routes.inc.php');"
STAMP = ".am485_finance_routes_activation.json"
STAGE_MANIFEST = ".am463_native_staff_finance_stage_manifest.json"
ROUTES = "routes/api.php"
FRAGMENT = "app/Services/NativeFinanceV1/finance-read-routes.inc.php"
ORIGIN = "staging.assammotors.com"


def sha256(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def fail(message: str):
    raise RuntimeError("AM485_BLOCKED: " + message)


def staging_root(root: Path) -> tuple[Path, Path]:
    root = root.resolve(strict=True)
    env = root / ".env"
    if not env.is_file() or env.is_symlink():
        fail("STAGING .env unavailable or symlinked")
    lines = env.read_text(encoding="utf8").splitlines()
    values: dict[str, str] = {}
    for raw in lines:
        if raw.strip().startswith("#") or "=" not in raw:
            continue
        key, value = raw.split("=", 1)
        if key in {"APP_URL", "APP_ENV"}:
            values[key] = value.strip().strip("'\"")
    app_url = urlsplit(values.get("APP_URL", ""))
    if app_url.scheme != "https" or app_url.hostname != ORIGIN:
        fail("APP_URL must be HTTPS Assam Motors STAGING, never production")
    if values.get("APP_ENV", "").lower() not in {"staging", "local", "testing"}:
        fail("APP_ENV must explicitly identify a non-production environment")
    route_path = root / ROUTES
    if not route_path.is_file() or route_path.is_symlink():
        fail("Missing/symlinked Laravel routes/api.php")
    if not (root / "artisan").is_file() or not (root / "vendor/autoload.php").is_file():
        fail("Expected Laravel root prerequisites missing")
    return root, route_path


def preflight(root: Path) -> tuple[Path, bytes, str]:
    root, route_path = staging_root(root)
    manifest_path = root / STAGE_MANIFEST
    fragment_path = root / FRAGMENT
    if not manifest_path.is_file() or manifest_path.is_symlink():
        fail("AM484 Stage manifest missing: run guarded source staging first")
    manifest = json.loads(manifest_path.read_text(encoding="utf8"))
    if manifest.get("package") != "AM463_NATIVE_STAFF_FINANCE_READ_ONLY" or \
       manifest.get("route_activated") is not False or \
       manifest.get("files", {}).get(FRAGMENT) is None:
        fail("Source-stage manifest does not match AM484 route-only package")
    if not fragment_path.is_file() or fragment_path.is_symlink():
        fail("Inert Finance routes fragment not staged")
    if sha256(fragment_path.read_bytes()) != manifest["files"][FRAGMENT]:
        fail("Staged route fragment has changed unexpectedly")
    raw = route_path.read_bytes()
    text = raw.decode("utf8")
    if text.count(MARKER) != 1:
        fail("Require exactly one operator-reviewed authenticated v1 route marker")
    if REQUIRE in text or (root / STAMP).exists():
        fail("Finance route activation already present or needs review")
    # Marker must not be in a comment that mentions a route activation.
    if not re.search(r"(?m)^([ \t]*)" + re.escape(MARKER) + r"[ \t]*$", text):
        fail("Reviewed marker must appear by itself on one line")
    candidate = text.replace(MARKER, REQUIRE + "\n" + MARKER, 1)
    if candidate.count(REQUIRE) != 1:
        fail("Route require insertion is ambiguous")
    php = shutil.which("php")
    if php is None:
        fail("PHP CLI not installed")
    with tempfile.TemporaryDirectory(prefix="am485-route-preflight-") as tmp:
        test = Path(tmp) / "api.php"
        test.write_text(candidate, encoding="utf8")
        result = subprocess.run([php, "-l", str(test)], capture_output=True, text=True)
        if result.returncode != 0:
            fail("Route source fails PHP lint before activation")
    return route_path, raw, candidate


def replace_atomically(target: Path, data: bytes, permissions: int):
    handle, scratch = tempfile.mkstemp(
        prefix=".am485-", suffix=".tmp", dir=str(target.parent))
    try:
        with os.fdopen(handle, "wb") as f:
            f.write(data)
            f.flush()
            os.fsync(f.fileno())
        os.chmod(scratch, permissions)
        os.replace(scratch, target)
    finally:
        if os.path.exists(scratch):
            os.unlink(scratch)


def activate(root: Path, expected: str, reviewed: bool):
    if not reviewed:
        fail("Require --reviewed after manual authenticated API group verification")
    if not re.fullmatch(r"[0-9a-f]{64}", expected):
        fail("--expected-sha256 must be an exact lowercase SHA-256")
    route_path, old, candidate = preflight(root)
    if sha256(old) != expected:
        fail("routes/api.php changed since the operator reviewed it")
    root = route_path.parent.parent
    backup_dir = root / "storage/app/am-backups"
    backup_dir.mkdir(parents=True, exist_ok=True)
    backup = backup_dir / ("AM485_routes_" + expected[:16] + ".php.bak")
    if backup.exists() or backup.is_symlink():
        fail("Route backup name already exists. Investigate before activating")
    permissions = stat.S_IMODE(route_path.stat().st_mode)
    # Never overwrite an existing backup; read-only code stage owns no DB rows.
    with backup.open("xb") as output:
        output.write(old)
        output.flush()
        os.fsync(output.fileno())
    os.chmod(backup, 0o600)
    new = candidate.encode("utf8")
    new_hash = sha256(new)
    stamp = {
        "package": "AM485_STAGING_FINANCE_READ_ROUTES",
        "backup": str(backup.relative_to(root)),
        "original_sha256": expected,
        "active_sha256": new_hash,
        "route_path": ROUTES,
        "db_modified": False,
        "only_get_routes": True,
    }
    try:
        # Recheck in case another administrator updated routes concurrently.
        if sha256(route_path.read_bytes()) != expected:
            fail("Route changed during activation; no file overwritten")
        replace_atomically(route_path, new, permissions)
        with (root / STAMP).open("x", encoding="utf8") as output:
            json.dump(stamp, output, indent=2, sort_keys=True)
    except Exception:
        # Restore only if our inserted bytes are still present.
        if sha256(route_path.read_bytes()) == new_hash:
            replace_atomically(route_path, old, permissions)
        raise
    print("AM485_STAGING_ROUTES_ACTIVATED_SOURCE_ONLY")
    print("AM485_FINANCE_POST=DISABLED")
    print("AM485_REQUIRE_NEXT=CHECK_ROUTE_LIST_AND_ADMIN_STAFF_ANONYMOUS_HTTP")
    print("AM485_ORIGINAL_SHA256=" + expected)
    print("AM485_ACTIVE_SHA256=" + new_hash)


def rollback(root: Path):
    root, routes = staging_root(root)
    stamp_file = root / STAMP
    if not stamp_file.is_file() or stamp_file.is_symlink():
        fail("No AM485 route manifest; rollback would be unsafe")
    state = json.loads(stamp_file.read_text(encoding="utf8"))
    if state.get("package") != "AM485_STAGING_FINANCE_READ_ROUTES" or \
       state.get("route_path") != ROUTES:
        fail("Unknown route activation manifest")
    backup = root / state.get("backup", "")
    if backup.is_symlink() or not backup.is_file():
        fail("AM485 route backup missing or symlinked")
    original = backup.read_bytes()
    if sha256(original) != state.get("original_sha256"):
        fail("Route backup checksum mismatch")
    if sha256(routes.read_bytes()) != state.get("active_sha256"):
        fail("routes/api.php changed after activation: manual merge/rollback required")
    perms = stat.S_IMODE(routes.stat().st_mode)
    replace_atomically(routes, original, perms)
    stamp_file.unlink()
    # Preserve backup for audit; never erase a valid pre-activation route backup.
    print("AM485_STAGING_ROUTES_ROLLED_BACK")
    print("AM485_ORIGINAL_ROUTE_SHA256=" + sha256(routes.read_bytes()))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("operation", choices=("check", "activate", "rollback"))
    parser.add_argument("laravel_root", type=Path)
    parser.add_argument("--expected-sha256", default="")
    parser.add_argument("--reviewed", action="store_true")
    args = parser.parse_args()
    try:
        if args.operation == "check":
            _, raw, _ = preflight(args.laravel_root)
            print("AM485_PRECHECK_PASS_SOURCE_ONLY")
            print("AM485_REVIEWED_ROUTE_SHA256=" + sha256(raw))
            print("AM485_REQUIRED_NEXT=VERIFY_AUTH_GROUP_AND_EXPLICIT_ACTIVATION")
        elif args.operation == "activate":
            activate(args.laravel_root, args.expected_sha256, args.reviewed)
        else:
            rollback(args.laravel_root)
        return 0
    except (RuntimeError, OSError, UnicodeError, ValueError) as exc:
        print(str(exc), file=__import__("sys").stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
