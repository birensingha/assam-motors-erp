#!/usr/bin/env python3
"""Static integration guardrails for unmounted AM245 invite/public survey code.

Full Laravel E2E tests require the missing deployed Hostinger ERP source and
canonical Job Card adapter; these tests do not assert staging deployment.
"""
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


def read(p):
    return (ROOT / p).read_text()


class InvitationFlowSafetyTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.routes = read("feedback-invitation-routes.php")
        cls.controller = read("app/Http/Controllers/Admin/AmFeedbackInvitationController.php")
        cls.schema = read("database/migrations/2026_11_22_000000_create_am245_feedback_invitations.php")
        cls.contract = read("app/Contracts/AmFeedbackInvitationSource.php")
        cls.page = read("resources/views/admin/engagement/public-feedback.blade.php")
        cls.action = read("resources/views/admin/engagement/job-card-feedback-action.blade.php")

    def test_route_separation_and_permissions(self):
        admin, public = self.routes.split("// Public customer survey", 1)
        self.assertIn("'can:manage-customer-engagement'", admin)
        self.assertIn("'auth'", admin)
        self.assertIn("Route::post('/invite'", admin)
        self.assertIn("'throttle:10,1'", admin)
        self.assertNotIn("view-customer-ledger", self.routes)
        self.assertNotIn("AmEngagementLedger", self.routes + self.controller)
        self.assertIn("Route::get('/{token}'", public)
        self.assertIn("Route::post('/{token}'", public)
        self.assertIn("['web']", public)
        self.assertNotIn("'auth'", public)
        self.assertIn("'throttle:20,1'", public)
        self.assertIn("'throttle:5,1'", public)
        self.assertIn("'[a-f0-9]{64}'", public)

    def test_server_side_association_status_consent_and_phone(self):
        for invariant in (
            "'consent' => ['accepted']",
            "$source->forJobCard($jobCardId)",
            "$c['job_card_id'] === $jobCardId",
            "'service_finished'",
            "'messaging_permitted'",
            "mb_strlen($name) <= 160",
            "mb_strlen($vehicle) <= 32",
            "mb_strlen($ref) <= 64",
            "Verified Job Card labels required",
            "AmFeedbackInvitationToken::issue()",
            "AmJobCardFeedbackInvite::compose",
            "route('am.feedback.public.show'",
            "https://wa.me/",
            "rawurlencode($message)",
            "redirect()->away(",
            "private, no-store",
        ):
            self.assertIn(invariant, self.controller, invariant)
        self.assertIn("forJobCard(int $jobCardId)", self.contract)
        self.assertIn("company scope", self.contract)
        self.assertNotIn("AmEngagementLedger", self.contract)

    def test_no_raw_token_or_phone_in_database_schema(self):
        for field in ("'token_hash'", "'job_card_id'", "'responded_at'",
                      "'expires_at'", "'requested_by'", "'feedback_id'"):
            self.assertIn(field, self.schema)
        self.assertIn("->unique()", self.schema)
        self.assertNotIn("$t->string('phone'", self.schema)
        self.assertNotIn("$t->string('token'", self.schema)
        self.assertIn("'token_hash' => $issued['hash']", self.controller)
        self.assertIn("'compose_redirected_at'", self.controller)
        self.assertNotIn("whatsapp_sent", self.controller)

    def test_expiry_one_time_submission_and_canonical_snapshot(self):
        for invariant in ("whereNull('responded_at')", "'expires_at', '>', now()",
                          "lockForUpdate()", "DB::transaction",
                          "where('token_hash'", "'responded_at' => $now",
                          "'feedback_id' => $feedbackId",
                          "'source' => 'whatsapp_invite'",
                          "'created_by' => null",
                          "request_count' => $existing->request_count + 1"):
            self.assertIn(invariant, self.controller, invariant)
        for snapshot in ("customer_name_snapshot", "vehicle_snapshot",
                         "job_card_ref_snapshot"):
            self.assertIn(snapshot, self.controller)

    def test_customer_form_is_csrf_protected_and_private(self):
        self.assertIn("@csrf", self.page)
        self.assertIn("route('am.feedback.public.submit'", self.page)
        self.assertIn('name="rating"', self.page)
        self.assertIn('name="comment"', self.page)
        self.assertIn("required", self.page)
        self.assertIn("noindex,nofollow,noarchive", self.page)
        self.assertIn("no-referrer", self.page)
        self.assertNotIn("customer_name_snapshot", self.page)
        self.assertNotIn("view-customer-ledger", self.page)
        self.assertIn("@can('manage-customer-engagement')", self.action)
        self.assertIn("@csrf", self.action)
        self.assertIn('name="consent"', self.action)
        self.assertIn('name="job_card_id"', self.action)
        self.assertNotIn('name="phone"', self.action)


if __name__ == "__main__":
    unittest.main()
