# AM179 — Optional Assam Motors Vehicle Health Report (STAGING package)

## Approved user-facing rules
- Report title **VEHICLE HEALTH REPORT**. Creation/printing/sharing is **optional**; it must NOT block Job Card status, inspection submission or QC.
- A4 print-grade Assam Motors header/footer, inspection details, category score, overall Health %, observed defects, valuation and signatures. No vehicle photographs.
- System suggests an **indicative**, benchmark-based vehicle value. Admin enters reference market price, odometer and expected repair cost, then can approve/change final value with mandatory justification. All modifications append an audit row.
- WhatsApp sharing should send a **PDF attachment**, never a publicly accessible confidential report URL. The browser report page uses the device's share sheet where available; user confirms the recipient and Send. Otherwise download and attach the PDF manually.
- Existing inspection submission is authoritative: a mobile-only draft **does not** qualify for a report.
- Previously agreed **Option A** is separate: valid defect-containing inspection can be submitted; defects remain OPEN and block QC PASS until repair + verified re-inspection. **This AM179 package does not implement that QC enforcement.**

## Scope already coded in this package
1. Read-only Health scoring from existing `job_card_inspection` lines of completed Job Cards. Safety-critical items have weight 2; other graded items weight 1. Good/Yes/Available/Normal/Pass=100, Fair/Low/High=55, Poor/No/Unavailable/Fail=0; N/A and unrecognized text readings excluded.
2. Overall + category score and observed issue table. Does not mutate or clear any existing inspection records.
3. Reference-market-based estimate: factor for vehicle year, odometer, health and repairs; no fabricated live resale feeds. Snapshot at every Admin valuation change.
4. Protected Admin/Staff view and PDF routes inside **existing authorized Laravel web route groups**. Inspector gets access only when assigned or recorded as submitter. All downloads are private, no-store.
5. A4 HTML-to-PDF template and browser PDF share function.
6. Database valuation and append-only audit migration. No altered existing tables.

## Critical requirements before STAGING apply
- Review the **live STAGING** route middleware, field schema and source anchors before installation. GitHub repository contains deployment patches, not the full running Laravel application.
- Install `dompdf/dompdf` using the project's own reviewed Composer process and ensure its license/security review. The installer intentionally refuses to apply without it.
- Run `python3 install.py check /full/path/to/assam-erp-staging` first. Preflight only; no writes.
- After reviewed preflight, `AM179_ALLOW_STAGING=YES python3 install.py apply /full/path/to/assam-erp-staging`.
- Operator separately runs the printed migration command after inspecting the migration and backing up the database.
- Verify `php -l` for controller/service/migration, clear route/view cache, inspect `php artisan route:list --name=vehicle-health -v`, confirm both Admin and Staff routes have **appropriate auth middleware**.
- Before issuing customer PDFs: confirm official Assam Motors logo is stored at `public/images/assam-motors-logo.png` (otherwise AM monogram fallback).
- Do not run on production. Save backup location printed by installer for route rollback.

## Acceptance tests required on STAGING
- Pending inspection: report unavailable with explicit error. Completed inspection: correct Job Card, current submitted point rows and Health %.
- Missing/N/A/text selections excluded from score. Defects visible and never silently marked cleared.
- Admin valuation: reference + KM + repairs saved; system suggestion calculated, final value optional. Final value requires reason. Two edits produce two audit rows.
- PDF: visually review page wrapping for 20/30/custom checks, company header/footer, Unicode/₹ font, mobile readability, download and print.
- Owner/Admin can access ERP report; assigned staff only can open staff web report; unrelated staff, anonymous requests and URL guessing get 403/redirect. No sensitive customer data in public URLs.
- Device share: file attachment, WhatsApp recipient selected explicitly, no auto send.
- Existing JC inspection, diagnosis, ROT, QC, invoice screens unaltered by this package.

## NOT YET BUILT (must not be reported complete)
- Admin-managed dynamic 20/30/custom inspection master, typed answers (fuel, yes/no, available), versioned report snapshots.
- Point-by-point server validation and reliable owner/Android app completion API.
- Full defect OPEN/repair/re-inspection register and QC PASS server guard.
- Automatic **live market** resale pricing source (needs vetted/licensed provider or benchmark entered by Admin).
- Native Android Staff App report-PDF authenticated API + share-sheet integration. A native login does **not** automatically log into the ERP staff web route. Until that integration exists, browser sharing is only for users with valid ERP Staff web sessions.
- Deploy confirmation / real staging acceptance tests. GH Actions may be blocked; code in GitHub alone is not deployed.

This package intentionally starts with the optional branded report + valuation without modifying existing operational/financial records.
