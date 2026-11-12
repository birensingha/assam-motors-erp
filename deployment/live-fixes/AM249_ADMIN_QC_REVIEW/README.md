# AM249 — Fix JC-50 Admin QC Review 403 without bypassing Staff Portal

## Root cause from STAGING AM248 diagnostics
- Latest QC for Job Card #50 = QC #38, **PENDING**, assigned **staff.id=4** / AM5077 / BIREN SINGHA.
- ERP Admin account `erp_auth_users.id=3` is also linked to **staff_id=4**, role `admin`, active=1.
- The Job Card's `Open Staff QC Review (staff login required)` button opens `GET /staff/qc/50`; that route uses a **different `auth:staff_web` guard and `EnsureStaffUser` middleware**. An Admin ERP account logged into `auth:web` is not thereby a linked Staff Portal identity. The Staff QC ownership 403 must remain intact.
- Existing Admin QC submission route: `POST /erp/job-cards/{job}/lifecycle/qc-result` under `auth:web` + `EnsureErpAdmin`.

## AM249 behavior
1. Add separate **Admin QC Review** GET `/erp/job-cards/{job}/admin-qc-review` and protected POST `/erp/job-cards/{job}/admin-qc-review/result`, inserted alongside the existing Admin QC lifecycle route, inheriting the same Admin authorization.
2. On the Admin Job Card page, rewire **only** the specific **Open Staff QC Review** button to the Admin QC Review page. The actual Staff Portal's QC routes and ownership check remain untouched.
3. Show QC ID, result, assigned staff identity, logged-in Admin linked Staff ID, and saved notes. Show actionable PASS/FAIL forms **only** when latest QC is pending, JC completed/in Quality Check, and Admin account's own linked staff_id equals QC's assigned_staff_id.
4. POST additionally checks active Admin identity, mandatory physical review acknowledgment and detailed decision notes, locks latest QC and job for consistency, refuses stale QC IDs, and enforces the exact assigned staff match **server-side**. It sets assigned_staff_id from the saved QC record (never request input) and delegates to the existing `AdminJobCardLifecyclePageController::qcResult()`. The existing QC handler's inspection defect verification, QC audit and JC lifecycle guards stay authoritative. The new controller never writes PASS/FAIL itself.
5. No employee impersonation, QC reassignment, payroll or invoice changes. Only actual human QC result submission can change state; installation does not.

## Command (STAGING only)
```bash
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
python3 install.py check "$ROOT"
python3 install.py apply "$ROOT"  # Only if check PASS
```

The installer validates existing protected Admin route, PHP lint on original/updated routes and controller, Blade compilation and PHP lint on original/updated Job Card and new Admin QC view, uniqueness of Staff QC link anchor, and matches exact existing Admin-side route. It stores immutable backups and supports hash-checked `rollback`. It does not touch QC rows or account mappings.

## Browser verification
- Admin login opens `https://staging.assammotors.com/erp/job-cards/50` and clicks **Open Admin QC Review** (not `/staff/qc/50`).
- New URL `https://staging.assammotors.com/erp/job-cards/50/admin-qc-review`.
- Confirm QC #38 PENDING, staff #4 AM5077, linked Admin Staff #4. Do **not** submit PASS just to test UI; actual car safety checks must be performed.
- A different Admin account without linked staff #4 gets a read-only warning. Anonymous and ordinary Staff account must be forbidden by middleware.
- Submitting PASS/FAIL requires actual QC verification and must respect existing defect/independent verification rules; any pre-existing mandatory attestation fields in live Admin handler may still require a follow-up form field.
- Existing `GET /staff/qc/50` continues to enforce `auth:staff_web` and the original staff ownership 403.
- On failure, installer stops without source modifications (or restores backups) and prints the reason.

## Scope / status
GitHub source is ready for STAGING preflight; not deployed or browser-tested. AM248 diagnostic evidence supports the identity mismatch, but does not establish QC sign-off readiness or replace a physical vehicle inspection.
