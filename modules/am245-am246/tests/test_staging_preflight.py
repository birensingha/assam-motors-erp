#!/usr/bin/env python3
"""Read-only staging inventory: regression tests for privacy and safe failure."""
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

SCRIPT = Path(__file__).resolve().parents[1] / "tools/staging_preflight.py"


class PreflightTests(unittest.TestCase):
    def run_tool(self, root):
        return subprocess.run(
            [sys.executable, str(SCRIPT)],
            cwd=root, text=True, capture_output=True, check=False,
            timeout=10,
        )

    def test_missing_artisan_fails_without_writing(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            before = list(root.iterdir())
            result = self.run_tool(root)
            self.assertEqual(result.returncode, 2)
            self.assertIn("containing artisan", result.stdout)
            self.assertEqual(list(root.iterdir()), before)

    def test_symlinked_route_outside_root_is_not_read(self):
        with tempfile.TemporaryDirectory() as folder, tempfile.TemporaryDirectory() as external:
            root = Path(folder)
            (root / "artisan").write_text("<?php")
            route_dir = root / "routes"
            route_dir.mkdir()
            outside = Path(external) / "private.php"
            outside.write_text("Route::get('/customer', 'DO_NOT_READ_OUTSIDE');\n")
            (route_dir / "web.php").symlink_to(outside)
            result = self.run_tool(root)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertNotIn("routes/web.php:1", result.stdout)
            self.assertNotIn("DO_NOT_READ_OUTSIDE", result.stdout)
            self.assertIn("ROUTES_WEB_PRESENT: False", result.stdout)

    def test_symlinked_artisan_outside_root_is_rejected(self):
        with tempfile.TemporaryDirectory() as folder, tempfile.TemporaryDirectory() as external:
            root = Path(folder)
            outside = Path(external) / "artisan"
            outside.write_text("NEVER_READ_EXTERNAL_ARTISAN")
            (root / "artisan").symlink_to(outside)
            result = self.run_tool(root)
            self.assertEqual(result.returncode, 2)
            self.assertNotIn("NEVER_READ_EXTERNAL_ARTISAN", result.stdout + result.stderr)

    def test_symlinked_composer_outside_root_is_ignored(self):
        with tempfile.TemporaryDirectory() as folder, tempfile.TemporaryDirectory() as external:
            root = Path(folder)
            (root / "artisan").write_text("<?php")
            outside = Path(external) / "composer.json"
            outside.write_text('{"require":{"laravel/framework":"NEVER_PRINT_EXTERNAL"}}')
            (root / "composer.json").symlink_to(outside)
            result = self.run_tool(root)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertIn("LARAVEL_COMPOSER_PRESENT: False", result.stdout)
            self.assertNotIn("NEVER_PRINT_EXTERNAL", result.stdout + result.stderr)

    def test_inventory_exposes_structure_not_secrets_or_source(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            (root / "artisan").write_text("<?php")
            (root / ".env").write_text("DB_PASSWORD=NEVER_PRINT_THIS_SECRET")
            (root / "composer.json").write_text(
                '{"require":{"laravel/framework":"^11.0"}}'
            )
            model = root / "app/Models/Customer.php"
            model.parent.mkdir(parents=True)
            model.write_text("<?php // NEVER_PRINT_MODEL_CONTENT")
            routes = root / "routes/web.php"
            routes.parent.mkdir(parents=True)
            routes.write_text(
                "Route::get('/customer', 'NEVER_PRINT_ROUTE_CONTENT');\n"
            )
            before = {str(p.relative_to(root)): p.read_bytes()
                      for p in root.rglob("*") if p.is_file()}
            result = self.run_tool(root)
            self.assertEqual(result.returncode, 0, result.stderr)
            self.assertIn("LARAVEL_VERSION_CONSTRAINT: ^11.0", result.stdout)
            self.assertIn("app/Models/Customer.php", result.stdout)
            self.assertIn("routes/web.php:1:customer", result.stdout)
            for secret in ("NEVER_PRINT_THIS_SECRET",
                           "NEVER_PRINT_MODEL_CONTENT",
                           "NEVER_PRINT_ROUTE_CONTENT", "DB_PASSWORD"):
                self.assertNotIn(secret, result.stdout + result.stderr)
            after = {str(p.relative_to(root)): p.read_bytes()
                     for p in root.rglob("*") if p.is_file()}
            self.assertEqual(before, after)


if __name__ == "__main__":
    unittest.main()
