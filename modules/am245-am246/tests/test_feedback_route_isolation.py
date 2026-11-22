#!/usr/bin/env python3
"""Regression checks: AM245 routes cannot accidentally expose AM246 finance."""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
ROUTE = re.compile(
    r"Route::(get|post)\('([^']+)',\s*\[AmCustomerEngagementController::class,'([^']+)'\]\)"
    r"(?:->middleware\('([^']+)'\))?->name\('([^']+)'\);"
)


def routes(source):
    return ROUTE.findall(source)


class RouteIsolationTests(unittest.TestCase):
    def test_feedback_only_has_exactly_six_admin_routes(self):
        source = (ROOT / "feedback-only-routes.php").read_text()
        found = routes(source)
        self.assertEqual(len(found), 6, "Unexpected or unparsed feedback route")
        self.assertEqual(
            {(method, action, name) for method, _, action, _, name in found},
            {
                ("get", "index", "index"),
                ("get", "exportCsv", "export"),
                ("get", "feedbackTargets", "targets"),
                ("post", "feedback", "feedback"),
                ("post", "reply", "reply"),
                ("post", "complaint", "complaint"),
            },
        )
        self.assertIn("['web','auth','can:manage-customer-engagement']", source)
        for forbidden in ("customer-messaging", "AmEngagementLedger",
                          "view-customer-ledger", "->name('message')"):
            self.assertNotIn(forbidden, source)
        throttles = {name: throttle for _, _, _, throttle, name in found}
        self.assertEqual(throttles["export"], "throttle:10,1")
        self.assertEqual(throttles["targets"], "throttle:30,1")

    def test_combined_financial_routes_have_separate_stricter_gate(self):
        source = (ROOT / "routes.php").read_text()
        self.assertEqual(len(routes(source)), 9, "Unexpected combined route")
        feedback, messaging = source.split("// Separate page and stricter finance permission", 1)
        self.assertEqual(len(routes(feedback)), 6)
        self.assertEqual(len(routes(messaging)), 3)
        self.assertNotIn("view-customer-ledger", feedback)
        self.assertIn(
            "['web','auth','can:manage-customer-engagement','can:view-customer-ledger']",
            messaging,
        )
        self.assertEqual(messaging.count("can:view-customer-ledger"), 1)
        self.assertEqual(
            {(method, action, name) for method, _, action, _, name in routes(messaging)},
            {("get", "messaging", "index"), ("get", "customers", "customers"),
             ("post", "message", "message")},
        )
        self.assertIn("->name('am.customer-messaging.')", messaging)
        self.assertIn("->name('am.customer-engagement.')", feedback)


if __name__ == "__main__":
    unittest.main()
