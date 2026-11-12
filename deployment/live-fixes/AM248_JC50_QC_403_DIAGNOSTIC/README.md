# AM248 — JC-20261109-00050 Staff QC 403 diagnosis

User screenshot: Job Card #50, AS11AG1468, COMPLETED / Quality Check, QC assigned to **Biren Singha**, button **Open Staff QC Review (staff login required)** navigates to a 403 page with `This Quality Check is not assigned to your Staff ID.`.

The 403 is a deliberate staff ownership restriction, not a PHP syntax error or license for an Admin impersonation/bypass. The user's screenshot does not prove which underlying staff identity is currently authenticated. A similar Admin user name does not prove equivalent Staff ID.

## Read-only STAGING audit
```bash
python3 audit.py "$HOME/domains/assammotors.com/assam-erp-staging"
```
Prints only relevant source excerpts from JC view, Staff QC controller, lifecycle controller, staff portal and routes; and non-secret QC metadata (latest row ID/status/assigned staff), in order to distinguish bad button routing from a genuine different logged-in staff member and identify the correct role-aware link.

## Intended fix after audit
- Assigned staff account gets Staff QC Review and can PASS/FAIL only its own assigned pending QC, retaining the existing safety/defect verification guards.
- Admin account gets clear **assigned staff + access instructions** instead of being sent into a staff-only endpoint with a guaranteed 403; a separate Admin QC view/action, if existing, must follow its own genuine Admin authentication rules and independent QC verification.
- If QC belongs to wrong staff or an Admin account without Staff identity, require auditable QC reassignment through an authorised admin action; do not change assignment based on display name.
- Do not update QC status, mark PASS, change assignee, edit job billing or Job Card workflow as part of diagnosis.
