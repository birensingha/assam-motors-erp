# AM254 — Global Auto Draft Save for Assam Motors STAGING ERP

## Request
After JC-54 Estimate validation lost visually entered lines, owner requested **autosave in every form**.

## Design / scope
One shared `am254-global-form-autosave.js` module is embedded in both ERP/Admin Blade layouts and authenticated standalone Staff, HR, Job Card and workshop entry pages that have a complete HTML body and editable POST forms. Views that extend the shared layout inherit the module. Dynamic AJAX modal forms added later are detected.

For **eligible data-entry forms**, automatically save a browser-local draft after 500 ms of inactivity and on tab switch/pagehide. Preserve all named safe field values, checkbox and radio state, select state, hidden business reference IDs, multi-select values, dynamic-row names and positions. A nonintrusive status indicator says **Auto Draft Saved on this browser (not submitted)**. On returning/reloading the same record URL, show **Restore Draft** / **Discard Draft**. Explicit user confirmation is required before restoring over visible data. Drafts expire after **24 hours** and are scoped by ERP/Staff account, URL, form index, action, form ID. Prefer browser `localStorage` with per-tab `sessionStorage` fallback.

A POST **never auto-runs**: payment, invoice, ledger, customer approvals, work completion, attendance, receipt or purchase entry still require explicit business Save/Submit. Drafts are only removed after a successful Laravel flash redirect, never merely because a submit event happened; validation errors keep the data.

Forms with password/OTP, login, logout, destructive status/action buttons, and sensitive fields (tokens, GPS/location, credential/bank IDs) are excluded. File uploads cannot be persisted in browser drafts. A standalone existing Estimate-specific AM253 auto draft feature remains authoritative (AM254 skips `#estimate-form` to prevent two restore prompts).

## Dynamic rows
All named row inputs are captured, including those created by JS. On restoring, all matching controls are set and input/change events fired to refresh totals. **No uncontrolled click on Add Line buttons** (some might create server records). If previously saved rows are not currently rendered, the original complete backup is retained unchanged with a warning. User can add missing rows using the form's own safe UI and click Restore again.

## Safety
- Browser local storage contains potentially confidential financial/customer form drafts until expiry; use only on trusted workshop devices, log out and clear the browser's site data on shared devices. Data is never sent to external services by this module.
- No database schema or financial records modified during installation.
- Source-checked installer `check|apply|verify|rollback`; per-view Blade compile/PHP lint, backup each original and restore on any failed deploy.
- Only modern STAGING root. **Not native Android**, not other domains, nor login/password forms.
- Explicit no autosave opt out: add `data-no-autosave` on a form/field subtree.

## Verification
Review GitHub Actions STAGING deployment. End-user test on any authenticated new Purchase, Expense, Job Card or Estimate (AM253): fill text + rate without saving; refresh; use **Restore Draft**; verify values and totals; change a field; Save manually. For a failed server validation, ensure draft remains recoverable.

Actual browser acceptance remains pending until tested in the authenticated user session.
