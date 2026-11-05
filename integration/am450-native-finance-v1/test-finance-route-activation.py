#!/usr/bin/env python3
"""AM486: entirely synthetic STAGING route activation/rollback tests.

Never invokes a live Hostinger root or any financial API. Runs PHP -l only.
"""
import hashlib
import json
import subprocess
import sys
import tempfile
from pathlib import Path

DIR = Path(__file__).resolve().parent
STAGE = DIR / "stage-staging.py"
ACTIVATE = DIR / "activate-finance-routes.py"
MARKER = "// AM483_AUTHENTICATED_V1_INSERTION_REVIEWED"
REQUIRE = "require base_path('app/Services/NativeFinanceV1/finance-read-routes.inc.php');"


def exec_script(file: Path, *args: str, ok: bool = True) -> str:
    result = subprocess.run([sys.executable, str(file), *args],
                            stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                            text=True, check=False)
    if ok and result.returncode:
        raise AssertionError(f"Expected PASS: {file.name}: {result.stdout} {result.stderr}")
    if not ok and result.returncode == 0:
        raise AssertionError(f"Expected BLOCKED: {file.name}: {result.stdout}")
    return result.stdout + result.stderr


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def create_laravel(root: Path):
    for name in (
        "artisan", "vendor/autoload.php", "bootstrap/app.php",
        "routes/api.php", "app/Services/StaffMultiExpensePaymentService.php"
    ):
        target = root / name
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text("<?php\n", encoding="utf8")
    (root / ".env").write_text(
        "APP_ENV=staging\nAPP_URL=https://staging.assammotors.com/erp\n",
        encoding="utf8"
    )
    route = root / "routes/api.php"
    route.write_text(
        "<?php\nuse Illuminate\\Support\\Facades\\Route;\n"
        "Route::middleware('auth:api')->prefix('v1')->group(function () {\n"
        f"    {MARKER}\n"
        "});\n",
        encoding="utf8"
    )


def main():
    with tempfile.TemporaryDirectory(prefix="am486-synthetic-") as folder:
        root = Path(folder)
        create_laravel(root)
        route = root / "routes/api.php"
        initial = sha256(route)
        exec_script(STAGE, "stage", str(root))
        fragment = root / "app/Services/NativeFinanceV1/finance-read-routes.inc.php"
        assert fragment.is_file()
        assert exec_script(ACTIVATE, "check", str(root)).find(initial) >= 0
        assert REQUIRE not in route.read_text()

        exec_script(ACTIVATE, "activate", str(root),
                    "--expected-sha256", initial, ok=False)
        assert sha256(route) == initial
        exec_script(ACTIVATE, "activate", str(root),
                    "--reviewed", "--expected-sha256", "0" * 64, ok=False)
        assert sha256(route) == initial

        assert "AM485_STAGING_ROUTES_ACTIVATED_SOURCE_ONLY" in exec_script(
            ACTIVATE, "activate", str(root), "--reviewed",
            "--expected-sha256", initial)
        assert route.read_text().count(REQUIRE) == 1
        state = json.loads((root / ".am485_finance_routes_activation.json").read_text())
        assert state["original_sha256"] == initial
        assert state["only_get_routes"] is True
        assert state["db_modified"] is False
        active = sha256(route)

        exec_script(ACTIVATE, "activate", str(root),
                    "--reviewed", "--expected-sha256", initial, ok=False)
        assert sha256(route) == active

        # If another developer changes the route, NEVER overwrite their work.
        route.write_text(route.read_text() + "\n// another person's change\n")
        changed = sha256(route)
        exec_script(ACTIVATE, "rollback", str(root), ok=False)
        assert sha256(route) == changed
        route.write_bytes(
            route.read_bytes().replace(b"\n// another person's change\n", b""))
        assert sha256(route) == active

        assert "AM485_STAGING_ROUTES_ROLLED_BACK" in exec_script(
            ACTIVATE, "rollback", str(root))
        assert sha256(route) == initial
        exec_script(ACTIVATE, "rollback", str(root), ok=False)
        # Source-stage rollback is independent and byte-checks every staged file.
        assert "AM463_STAGED_PHP_FILES_ROLLED_BACK" in exec_script(
            STAGE, "rollback", str(root))
        assert not fragment.exists()

        # Never silently activate on production or a real root without the marker.
        create_laravel(root)
        (root / ".env").write_text(
            "APP_ENV=production\nAPP_URL=https://assammotors.com/erp\n",
            encoding="utf8")
        exec_script(ACTIVATE, "check", str(root), ok=False)
        assert sha256(route) == initial

        print("AM486 route activation atomicity/rollback/production refusal PASS")


if __name__ == "__main__":
    main()
