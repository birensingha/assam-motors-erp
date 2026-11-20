#!/usr/bin/env python3
"""Unit tests: stage tool must never change existing ERP files or DB."""
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest

SOURCE = Path(__file__).resolve().parents[1] / "tools/stage_feedback.py"
spec = importlib.util.spec_from_file_location("am245_stage", SOURCE)
stage_tool = importlib.util.module_from_spec(spec)
spec.loader.exec_module(stage_tool)


class SafeStageTests(unittest.TestCase):
    def setUp(self):
        self.folder = tempfile.TemporaryDirectory()
        self.root = Path(self.folder.name).resolve()
        for relative in ("app", "routes", "resources/views",
                         "database/migrations", "storage/app"):
            (self.root / relative).mkdir(parents=True, exist_ok=True)
        (self.root / "artisan").write_text("<?php
")
        (self.root / "routes/web.php").write_text("ORIGINAL_ROUTES")

    def tearDown(self):
        self.folder.cleanup()

    def entries(self, scope="feedback"):
        stage_tool.verify_project(self.root)
        return stage_tool.mapped_files(self.root, scope)

    def manifest(self):
        return self.root / stage_tool.MANIFEST

    def test_dry_run_is_read_only(self):
        entries = self.entries()
        self.assertTrue(entries)
        self.assertFalse(self.manifest().exists())
        self.assertTrue(all(item[3] == "new" for item in entries))
        self.assertEqual((self.root / "routes/web.php").read_text(),
                         "ORIGINAL_ROUTES")
        self.assertFalse((self.root / "app/Contracts/AmFeedbackCustomerSource.php").exists())

    def test_staging_and_rollback_preserve_existing_erp(self):
        stage_tool.stage(self.root, self.entries(), self.manifest())
        self.assertTrue(self.manifest().is_file())
        self.assertTrue((self.root / "routes/am245-feedback.php").is_file())
        self.assertEqual((self.root / "routes/web.php").read_text(),
                         "ORIGINAL_ROUTES")
        # Staging must not create files to activate Gate/provider or run migrations.
        self.assertFalse((self.root / "app/Providers/AppServiceProvider.php").exists())
        self.assertFalse((self.root / "database/migrations/migrations").exists())
        stage_tool.rollback(self.root, self.manifest())
        self.assertFalse(self.manifest().exists())
        self.assertFalse((self.root / "routes/am245-feedback.php").exists())
        self.assertEqual((self.root / "routes/web.php").read_text(),
                         "ORIGINAL_ROUTES")

    def test_feedback_only_has_no_finance_migration_or_routes(self):
        entries = self.entries()
        targets = [item[2] for item in entries]
        self.assertIn("routes/am245-feedback.php", targets)
        self.assertNotIn("routes/am245-am246.php", targets)
        self.assertNotIn("app/Contracts/AmEngagementLedger.php", targets)
        self.assertNotIn("resources/views/admin/engagement/messaging.blade.php", targets)
        self.assertNotIn("database/migrations/2026_11_19_000000_create_am246_customer_message_log.php", targets)
        self.assertIn("resources/views/admin/engagement/feedback-only-sidebar.blade.php", targets)
        stage_tool.stage(self.root, entries, self.manifest())
        self.assertFalse((self.root / "routes/am245-am246.php").exists())
        self.assertFalse((self.root / "app/Contracts/AmEngagementLedger.php").exists())
        stage_tool.rollback(self.root, self.manifest())

    def test_all_scope_has_finance_files_but_does_not_activate_routes(self):
        entries = self.entries("all")
        paths = [item[2] for item in entries]
        self.assertIn("routes/am245-am246.php", paths)
        self.assertIn("app/Contracts/AmEngagementLedger.php", paths)
        self.assertIn("database/migrations/2026_11_19_000000_create_am246_customer_message_log.php", paths)
        stage_tool.stage(self.root, entries, self.manifest())
        self.assertEqual((self.root / "routes/web.php").read_text(), "ORIGINAL_ROUTES")
        stage_tool.rollback(self.root, self.manifest())

    def test_conflicting_existing_file_blocks_all_copy(self):
        target = self.root / "app/Contracts/AmFeedbackCustomerSource.php"
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text("USER_OWNED_FILE")
        entries = self.entries()
        self.assertTrue(any(e[3] == "CONFLICT" for e in entries))
        with self.assertRaisesRegex(ValueError, "refusing to overwrite"):
            stage_tool.stage(self.root, entries, self.manifest())
        self.assertEqual(target.read_text(), "USER_OWNED_FILE")
        self.assertFalse(self.manifest().exists())

    def test_race_created_file_is_never_overwritten(self):
        # A second deployer can create a destination after preflight.
        entries = self.entries()
        target = self.root / "routes/am245-feedback.php"
        target.write_text("OTHER_DEPLOYER_OWNED")
        with self.assertRaises(FileExistsError):
            stage_tool.stage(self.root, entries, self.manifest())
        self.assertEqual(target.read_text(), "OTHER_DEPLOYER_OWNED")
        self.assertFalse(self.manifest().exists())
        self.assertFalse((self.root / "app/Contracts/AmFeedbackCustomerSource.php").exists())

    def test_existing_manifest_symlink_refused(self):
        outside = self.root / "manifest-outside.json"
        outside.write_text("DO_NOT_TOUCH")
        self.manifest().symlink_to(outside)
        with self.assertRaisesRegex(ValueError, "Existing AM245 staging manifest"):
            stage_tool.stage(self.root, self.entries(), self.manifest())
        self.assertEqual(outside.read_text(), "DO_NOT_TOUCH")

    def test_rollback_refuses_modified_files(self):
        stage_tool.stage(self.root, self.entries(), self.manifest())
        changed = self.root / "routes/am245-feedback.php"
        changed.write_text("OPERATOR_CHANGED_FILE")
        with self.assertRaisesRegex(ValueError, "File changed"):
            stage_tool.rollback(self.root, self.manifest())
        self.assertEqual(changed.read_text(), "OPERATOR_CHANGED_FILE")
        self.assertTrue(self.manifest().exists())

    def test_duplicate_manifest_records_abort_without_deleting_files(self):
        stage_tool.stage(self.root, self.entries(), self.manifest())
        payload = json.loads(self.manifest().read_text())
        self.assertTrue(payload["created"])
        payload["created"].append(dict(payload["created"][0]))
        self.manifest().write_text(json.dumps(payload))
        with self.assertRaisesRegex(ValueError, "Duplicate or invalid manifest path"):
            stage_tool.rollback(self.root, self.manifest())
        for record in payload["created"]:
            self.assertTrue((self.root / record["path"]).exists())
        self.assertTrue(self.manifest().exists())

    def test_malformed_manifest_record_abort_without_deleting_files(self):
        stage_tool.stage(self.root, self.entries(), self.manifest())
        payload = json.loads(self.manifest().read_text())
        payload["created"].append(None)
        self.manifest().write_text(json.dumps(payload))
        with self.assertRaisesRegex(ValueError, "Invalid manifest record"):
            stage_tool.rollback(self.root, self.manifest())
        for record in payload["created"][:-1]:
            self.assertTrue((self.root / record["path"]).exists())
        self.assertTrue(self.manifest().exists())

    def test_rollback_prevalidates_every_file_before_any_deletion(self):
        stage_tool.stage(self.root, self.entries(), self.manifest())
        payload = json.loads(self.manifest().read_text())
        self.assertGreaterEqual(len(payload["created"]), 2)
        first = self.root / payload["created"][0]["path"]
        last = self.root / payload["created"][-1]["path"]
        last.write_text("OPERATOR_CHANGED_LAST_FILE")
        with self.assertRaisesRegex(ValueError, "File changed"):
            stage_tool.rollback(self.root, self.manifest())
        self.assertTrue(first.exists(), "rollback must not delete earlier valid files")
        self.assertEqual(last.read_text(), "OPERATOR_CHANGED_LAST_FILE")
        self.assertTrue(self.manifest().exists())

    def test_symlinked_destination_directory_is_rejected(self):
        outside = self.root / "outside"
        outside.mkdir()
        contract_dir = self.root / "app/Contracts"
        contract_dir.symlink_to(outside, target_is_directory=True)
        with self.assertRaisesRegex(ValueError, "Refusing symlinked path"):
            self.entries()
        self.assertEqual(list(outside.iterdir()), [])
        self.assertFalse(self.manifest().exists())

    def test_symlink_swap_after_plan_is_rejected_before_copy(self):
        entries = self.entries()
        outside = self.root / "outside"
        outside.mkdir()
        contract_dir = self.root / "app/Contracts"
        contract_dir.rmdir() if contract_dir.exists() else None
        contract_dir.symlink_to(outside, target_is_directory=True)
        with self.assertRaisesRegex(ValueError, "Refusing symlinked path"):
            stage_tool.stage(self.root, entries, self.manifest())
        self.assertEqual(list(outside.iterdir()), [])
        self.assertFalse(self.manifest().exists())

    def test_existing_identical_file_not_deleted_by_rollback(self):
        first_source = stage_tool.MODULE / "feedback-only-routes.php"
        identical = self.root / "routes/am245-feedback.php"
        identical.write_bytes(first_source.read_bytes())
        stage_tool.stage(self.root, self.entries(), self.manifest())
        stage_tool.rollback(self.root, self.manifest())
        self.assertTrue(identical.exists())
        self.assertEqual(identical.read_bytes(), first_source.read_bytes())


if __name__ == "__main__":
    unittest.main()
