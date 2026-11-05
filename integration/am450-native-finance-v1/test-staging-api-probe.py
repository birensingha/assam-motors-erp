#!/usr/bin/env python3
"""Synthetic-only AM477 guard against unsafe STAGING smoke checks."""
from __future__ import annotations
import importlib.util
import pathlib
import unittest
from unittest.mock import patch
from urllib.error import HTTPError

ROOT = pathlib.Path(__file__).parent
SPEC = importlib.util.spec_from_file_location(
    "staging_probe", ROOT / "verify-staging-api.py")
assert SPEC and SPEC.loader
probe = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(probe)


class FinanceStagingProbeTest(unittest.TestCase):
    def sample(self, path, token):
        if token is None:
            return 401, {"message": "Unauthorized"}
        if token == "STAFF":
            return 403, {"message": "Forbidden"}
        if path.startswith("/admin/mobile/finance/v1/payees?type=VENDOR"):
            return 409, {"success": False, "error": "FINANCE_READ_CONTRACT_UNAVAILABLE"}
        if path.startswith("/admin/mobile/finance/v1/payees?type=STAFF"):
            return 200, {"contract": "am-finance-v1", "payees": [
                {"id": "42", "type": "STAFF", "name": "Synthetic Worker"}
            ]}
        if path.startswith("/admin/mobile/finance/v1/open-references"):
            return 200, {"contract": "am-finance-v1", "references": [
                {"id": "101", "payee_type": "STAFF", "payee_id": "42",
                 "outstanding_amount": "1942.00"}
            ]}
        if "master-items" in path:
            from urllib.parse import parse_qs, urlsplit
            typ = parse_qs(urlsplit(path).query)["type"][0]
            return 200, {"contract": "am-finance-v1", "resource": "master-items",
                         "items": [{"id": "12", "type": typ, "name": "Synthetic Item"}]}
        raise AssertionError("Unrecognized test route")

    def test_all_synthetic_checks_pass(self):
        with patch.object(probe, "get_json", side_effect=self.sample) as caller:
            probe.verify("ADMIN", "STAFF", "42")
            self.assertGreaterEqual(caller.call_count, 8)
            self.assertTrue(all(c.args[0].startswith("/admin/mobile/finance/v1/")
                                for c in caller.call_args_list))

    def test_reject_wrong_staff(self):
        with patch.object(probe, "get_json", side_effect=self.sample):
            with self.assertRaises(RuntimeError):
                probe.verify("ADMIN", None, "43")

    def test_refuse_production_and_non_finance_paths(self):
        # get_json rejects bad paths before it can open a network connection.
        for path in ("//example.com", "/admin/mobile/dashboard",
                     "/admin/mobile/finance/v1/../secret"):
            with self.assertRaises(ValueError):
                probe.get_json(path, "ADMIN")

    def test_fail_closed_on_malformed_backend(self):
        def bad(path, token):
            code, body = self.sample(path, token)
            if "type=PART" in path:
                return 200, {"contract": "am-finance-v1", "resource": "master-items",
                             "items": [{"id": "1", "type": "OSL"}]}
            return code, body
        with patch.object(probe, "get_json", side_effect=bad):
            with self.assertRaises(RuntimeError):
                probe.verify("ADMIN", None, None)


if __name__ == "__main__":
    unittest.main()
