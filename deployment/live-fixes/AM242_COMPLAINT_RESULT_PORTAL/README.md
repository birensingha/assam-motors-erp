# AM242 — Complaint Result, Register, JC Marker, Staff Portal

## Confirmed current case
JC-2026-0011 / Completed ROT R0T... (exact session identifier is stored in complaint #1) / Kapil Rajbongshi AM9472. Complaint #1 shows WORKMANSHIP_CONFIRMED and REVIEWED. Complaint description: Rear Hub Bolt not Fitted, vehicle rear Brake Shoe RH Side damaged. Supervisor finding and Admin-recorded employee response have already been saved. Do not alter or resubmit this decision automatically.

## Additive presentation changes
- Complaint #1 detail page becomes a readable print-friendly Result with evidence, finding, dates, employee response and append-only audit history.
- Investigation edit form remains accessible under a collapsed Admin-only correction section.
- New Admin Work Complaint Register route /erp/rot-work-complaints with filters for month, JC, staff, outcome; latest 200 matching records shown.
- Monthly complaint report adds per-staff Details link and Reported/Confirmed/Pending totals. No automatic salary deduction.
- Original Completed ROT in JC shows Complaint (N) · View Result link; original ROT assignment, rates, times and status unchanged.
- Web Staff Portal shows only authenticated staff member's complaints (MY completed work) with status, findings and admin-recorded statement.
- When AM241 is actually installed and new linked rework ROT is assigned, original mechanic additionally sees receiving mechanic/name and new ROT; receiving mechanic sees rework instructions and exact assigned session; no fabricated transfer.

## Safety constraints
- Staff-specific data determined only from auth()->user()->staff_id and isStaff(), not input query parameters.
- No new route lets staff access the Admin-only complaint register or impersonate another employee.
- No changes to ROT workflow, QC, job lifecycle, invoices, attendance, salary, or actual employee scoring rules.
- Staff Portal panel is read-only. Employee-self-submitted explanation and mobile Android-native UI are separate later integrations.
- No DB migrations or business-row writes in this patch. AM241 rework creation and notifications have not been installed.

## STAGING preflight
Use the guarded install.py check mode first. It validates source anchors and compiles each changed Blade template with actual staging Laravel compiler, checks PHP syntax, and changes no Laravel files. If any STOP_AM242 is printed do not force changes.
Apply backs up exactly six existing files, installs two new Blade files, verifies view cache and named route, then writes a hash manifest for conflict-aware rollback. rollback will refuse if someone modified an installed file after installation.

## Acceptance tests (browser, logged in)
1. Admin open /erp/rot-work-complaints/1: saved confirmed result visible without clicking edit. Complaint summary matches original report. Audit history shows REPORTED and INVESTIGATION_REVIEW; Back to JC and register work.
2. Admin open /erp/rot-work-complaints and filter staff Kapil / JC 11 / current month; Case #1 appears.
3. Admin open /erp/rot-work-complaints/monthly?month=2026-11: Kapil Reported 1, Confirmed 1 (assuming no other cases), Pending 0.
4. Admin JC-11 Completed ROT shows Complaint (1) and link. Original completed time and billing unchanged.
5. Log in with Kapil AM9472 Staff Portal: My Work Complaints displays Case #1 confirmed and investigation. Another unrelated staff does not see Kapil's complaint.
6. No new rework mechanic shown until actual complaint rework ROT is linked through approved AM241 workflow.
7. Reopen other existing Job Cards; test staff attendance/ROT start and lifecycle unchanged. On any error, rollback using installer with rollback.

## Deployment status
GitHub patch only until actually applied and browser-verified on Hostinger STAGING. No actual Staff Portal data update has happened just by committing this code.
