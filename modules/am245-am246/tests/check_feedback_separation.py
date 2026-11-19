#!/usr/bin/env python3
"""Static guardrails for AM245 Feedback module; does not require Laravel startup."""
from pathlib import Path

root = Path("modules/am245-am246")
feedback = (root / "resources/views/admin/engagement/index.blade.php").read_text()
messages = (root / "resources/views/admin/engagement/messaging.blade.php").read_text()
controller = (root / "app/Http/Controllers/Admin/AmCustomerEngagementController.php").read_text()
routes = (root / "routes.php").read_text()
contract = (root / "app/Contracts/AmFeedbackCustomerSource.php").read_text()

assert "feedback-target-search" in feedback
assert "feedback-entry-form" in feedback
assert "am.customer-engagement.targets" in feedback
assert "feedback-customer-id" in feedback
assert "feedback-vehicle-id" in feedback
assert "feedback-job-card-id" in feedback
assert "am.customer-messaging.message" not in feedback
assert "am.customer-messaging.message" in messages
assert "public function feedbackTargets(" in controller
assert "public function resolve(" in contract
assert "$source->resolve(" in controller
assert "'customer_name'=>['required'" not in controller
assert "->name('targets')" in routes
assert "'can:view-customer-ledger'" in routes
print("AM245 static feedback/financial-separation and source validation checks PASSED")
