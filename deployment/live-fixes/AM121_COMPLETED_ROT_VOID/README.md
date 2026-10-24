# AM121 — Completed ROT Void (STAGING)

## Delivered
- `AdminCompletedRotVoidController.php`: Admin-only audited **billing void**, using a DB transaction and before/after snapshot; retains the Job Card labour line, all `rot_sessions`, mechanic events and timings.
- `completed-rot-void.blade.php`: visible inside Main Job Card ROT card for WIP, invoice-free Completed ROT, with mandatory reason (12–500), Admin note (5–1000), confirmation phrase `VOID ROT-...`, acknowledgement, and confirmation prompt.
- `deploy.py`: backup-first guarded source patch for `routes/web.php` and `show.blade.php`; adds new controller + partial and validates compiled Blade; automatic rollback on failure.
- `preflight.php`: **read only** schema and ROT status inspection.
- `scripts/AM121_DEPLOY_STAGING.sh`: controlled one-command staging download, preflight, code deployment and route inspection.

## Void semantics
Completed work remains **Completed** historically. The billing row remains present but financial amounts become zero; an immutable `COMPLETED_ROT_VOID_AM121` entry in `job_card_change_audits` stores the original complete row, linked sessions, old/new financial totals, actor and reason. Duplicate requests for the same row are blocked. The audit entry makes the Main JC label `VOIDED BILLING — Work history retained`.

This operation **does not** modify original Approved Estimate, stock purchases, staff attendance, GPS, work sessions or Credit Notes.

## Eligibility and guards
- Exact Admin user, active, authenticated, WIP/IN-PROGRESS Job Card.
- Positive billable amount, **all linked ROT sessions Completed**; no active, paused or assigned session.
- Exact current amount confirmed, mandatory Admin note/reason, typed ROT code and history acknowledgement.
- Any invoice history blocks this staging implementation (including previously reversed invoices). Credit Note and reopening require a separate financial review before permitting Void.
- JC payment/receipt references, purchase-linked lines, OSL, unknown financial schema, duplicate Void and pre-existing JC tax total discrepancies are blocked.
- Transaction tests after update require labour and JC totals to decrease, Parts to remain unchanged, GST not to increase; otherwise rollback.

## Important staging QA / limits
- No actual customer Job Card has been voided by installing this code.
- The existing ROT Work Service, Admin time correction and completed ROT Reopen endpoints are unchanged. Before **production**, extend all those backends to reject mutation of a `COMPLETED_ROT_VOID_AM121` labour row (not only hide controls in a screen). Until then, keep the implementation on STAGING and do not use it for live billing.
- Verify how `JobCardTaxTotals::forJob()` calculates the existing fields; preflight checks schema but cannot substitute for a real authorized end-to-end browser QA.
- Test one disposable invoice-free WIP JC: before amount, after amount, GST, original sessions/work minutes, ROT Performance report, audit event and duplicate attempt. Do not test on a billable customer JC first.
- Test negative cases: non-admin, missing code, missing note, running/paused ROT, invoice history, purchase-linked ROT, historical GST mismatch and double submission.
- Separate Amount Editor should stay until all its correction capabilities are verified in Main JC.

## Rollback
The Python installer records its backup directory, e.g. `storage/app/am-backups/AM121_TIMESTAMP_PID`. If there is a post-deploy UI regression, restore `routes.php` and `show.blade.php` from that backup, remove only the two AM121-created controller/partial files, and run `php artisan view:clear && php artisan view:cache`. **If a ROT has already been voided, code rollback does not reverse the audit/billing transaction; reconcile manually and never delete the audit.**

No automatic production deployment, no automatic voiding, no migration and no database writes at install.
