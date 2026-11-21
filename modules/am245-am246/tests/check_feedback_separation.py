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
# Suggestions are also canonicalized: unverified adapter search labels must
# never be returned directly to the browser, even before a save attempt.
suggestions = controller.split("public function feedbackTargets(", 1)[1].split("public function feedback(", 1)[0]
assert "$source->resolve($customerId,$vehicleId,$jobCardId)" in suggestions
assert "array_slice($results,0,15)" in suggestions
assert "($context['vehicle_id']??null)!==null" in suggestions
assert "($context['job_card_id']??null)!==null" in suggestions
assert "'customer_name'=>(string)$context['customer_name']" in suggestions
assert "'vehicle'=>(string)($context['vehicle']??'')" in suggestions
assert "'job_card_ref'=>(string)($context['job_card_ref']??'')" in suggestions
assert "'customer_name'=>(string)($r['customer_name']??'')" not in suggestions

assert "public function resolve(" in contract
assert "$source->resolve(" in controller
assert "($context['vehicle_id']??0)===$vehicleId" in controller
assert "($context['vehicle_id']??null) === null" in controller
assert "($context['job_card_id']??null) === null" in controller
assert "($context['vehicle_id']??0)===$vehicleId" in controller
assert "($context['job_card_id']??0)===$jobCardId" in controller
assert "vehicle_id:int|null" in contract
assert "job_card_id:int|null" in contract
assert "$t->unsignedBigInteger('vehicle_id')->nullable()->index()" in migration
assert "$t->unsignedBigInteger('job_card_id')->nullable()->index()" in migration
assert "'vehicle_id'=>$vehicleId" in controller
assert "'job_card_id'=>$jobCardId" in controller
assert "is_finite((float)$raw)" in controller
assert "Ledger amount out of range" in controller
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
# Both register and CSV must reject unbounded search strings, arrays and
# unexpected filter values before constructing LIKE clauses.
assert controller.count("'q'=>['nullable','string','max:80']") == 2
assert controller.count("'rating'=>['nullable',Rule::in(['1','2','3','4','5'])]") == 2
assert controller.count("'complaint_status'=>['nullable',Rule::in(['open','resolved','none'])]") == 2
# Search filters must treat wildcard-looking customer input literally.
# The same feedbackQuery() drives both the register and CSV export.
assert "str_replace([" in controller
assert "$pattern = '%'.$escaped.'%';" in controller
assert "use ($pattern)" in controller
assert "orWhere('am_customer_feedback.vehicle','like',$pattern)" in controller
assert "orWhere('am_customer_feedback.job_card_ref','like',$pattern)" in controller
assert "orWhere('vehicle','like','%'.$search.'%')" not in controller

assert "throttle:10,1" in routes
assert "'can:view-customer-ledger'" in routes
feedback_only_routes = (root / "feedback-only-routes.php").read_text()
feedback_only_menu = (root / "resources/views/admin/engagement/feedback-only-sidebar.blade.php").read_text()
whatsapp_migration = (root / "database/migrations/2026_11_19_000000_create_am246_customer_message_log.php").read_text()
stage_tool = (root / "tools/stage_feedback.py").read_text()
assert "am_customer_message_log" not in migration
assert "am_customer_message_log" in whatsapp_migration
# AM246's operator checkbox must have a server-side audit timestamp, while
# never being misrepresented as customer opt-in or delivery confirmation.
assert "$t->timestamp('permission_attested_at')->nullable()" in whatsapp_migration
assert "$t->string('permission_attestation',120)->nullable()" in whatsapp_migration
assert "'consent'=>['accepted']" in controller
assert "'permission_attested_at'=>now()" in controller
assert "'permission_attestation'=>'operator_confirmed_permitted_messaging'" in controller
assert "'status'=>'whatsapp_opened_not_sent'" in controller
assert "permission_attested_at" not in migration

assert "am.customer-engagement." in feedback_only_routes
assert "am.customer-messaging." not in feedback_only_routes
assert "can:view-customer-ledger" not in feedback_only_routes
assert "can:manage-customer-engagement" in feedback_only_routes
assert "am.customer-messaging." not in feedback_only_menu
assert "route('am.customer-engagement.index')" in feedback_only_menu
assert 'default="feedback"' in stage_tool
assert "FEEDBACK_COPY_MAP" in stage_tool
print("AM245 static feedback/financial-separation and source validation checks PASSED")
