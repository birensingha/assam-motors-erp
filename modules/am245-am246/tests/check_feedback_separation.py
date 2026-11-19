#!/usr/bin/env python3
"""Static guardrails for AM245 Feedback module; does not require Laravel startup."""
from pathlib import Path

root = Path("modules/am245-am246")
feedback = (root / "resources/views/admin/engagement/index.blade.php").read_text()
messages = (root / "resources/views/admin/engagement/messaging.blade.php").read_text()
controller = (root / "app/Http/Controllers/Admin/AmCustomerEngagementController.php").read_text()
routes = (root / "routes.php").read_text()
contract = (root / "app/Contracts/AmFeedbackCustomerSource.php").read_text()
migration = (root / "database/migrations/2026_11_12_010000_create_am_customer_engagement.php").read_text()

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
assert "complaint_status" in feedback
assert "insertOrIgnore(" in controller
assert "$t->unique('feedback_id')" in migration
assert "am_customer_complaint_history" in migration
assert "resolved_by" in migration and "resolved_at" in migration
assert "am_customer_complaint_history" in controller
assert "'action'=>'opened'" in controller
assert "'action'=>'resolved'" in controller
assert "where('status','open')" in controller
assert "Complaint has already been resolved" in controller
assert "complaintHistory" in feedback
assert "name=\"rating\"" in feedback
assert "name=\"complaint_status\"" in feedback
assert "openComplaintCount" in feedback
assert "averageRating" in feedback
assert "am.customer-engagement.export" in feedback
assert "->name('export')" in routes
assert "AmFeedbackCsv::safeCell" in controller
assert "'created_by'=>(int)$request->user()->id" in controller
assert "limit(5000)" in controller
assert "throttle:10,1" in routes
assert "'can:view-customer-ledger'" in routes
print("AM245 static feedback/financial-separation and source validation checks PASSED")
