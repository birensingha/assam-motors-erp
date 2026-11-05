#!/usr/bin/env python3
"""AM465: synthetic dry-run of guarded STAGING stage/rollback. NO real ERP."""
from pathlib import Path
import tempfile
import subprocess
import sys
import hashlib

here = Path(__file__).resolve().parent
script = here / "stage-staging.py"


def call(command: str, root: Path, expected: int = 0) -> str:
    result = subprocess.run(
        [sys.executable, str(script), command, str(root)],
        capture_output=True, text=True
    )
    if result.returncode != expected and (expected != 1 or result.returncode == 0):
        raise AssertionError(
            f"{command} expected exit {expected} got {result.returncode}: "
            + result.stdout + result.stderr
        )
    return result.stdout + result.stderr


with tempfile.TemporaryDirectory(prefix="am465-synthetic-") as temp:
    root = Path(temp)
    for name in (
        "artisan", "vendor/autoload.php", "bootstrap/app.php",
        "routes/api.php", "app/Services/StaffMultiExpensePaymentService.php"
    ):
        file = root / name
        file.parent.mkdir(parents=True, exist_ok=True)
        file.write_text("<?php\n", encoding="utf8")
    routes = root / "routes/api.php"
    original = hashlib.sha256(routes.read_bytes()).hexdigest()
    assert "AM463_PRECHECK_PASS" in call("check", root)
    assert "AM463_STAGED_PHP_CODE_ONLY" in call("stage", root)
    assert hashlib.sha256(routes.read_bytes()).hexdigest() == original
    assert (root / "app/Http/Controllers/Api/V1/Am461StaffFinanceReadController.php").is_file()
    assert (root / "app/Services/NativeFinanceV1/FinanceReadContract.php").is_file()
    assert "AM463_BLOCKED" in call("stage", root, expected=1)
    assert "AM463_STAGED_PHP_FILES_ROLLED_BACK" in call("rollback", root)
    assert hashlib.sha256(routes.read_bytes()).hexdigest() == original
    assert not (root / "app/Services/NativeFinanceV1/FinanceReadContract.php").exists()
    assert "AM463_BLOCKED" in call("rollback", root, expected=1)

    # Refuse to overwrite pre-existing ERP code even when names collide.
    target = root / "app/Services/NativeFinanceV1/FinanceReadContract.php"
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text("DO NOT OVERWRITE", encoding="utf8")
    assert "AM463_BLOCKED" in call("stage", root, expected=1)
    assert target.read_text(encoding="utf8") == "DO NOT OVERWRITE"
    target.unlink()

    # Refuse an unverified route configuration; no silent route duplication.
    routes.write_text("<?php Route::get('/finance/v1/payees', fn()=>null);\n")
    assert "AM463_BLOCKED" in call("check", root, expected=1)

print("AM465 guarded STAGING stage, rollback and source-protection tests PASS")
