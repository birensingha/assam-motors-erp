# P34 — Job Card ROT Billable / No Charge

## Current behavior confirmed
ROT Start/Pause/Resume/Complete records technician work sessions, TAT and execution status. Customer invoice's Labour amount is sourced from `job_card_labour` on the Job Card. Job Card #34 Labour line #69 has a Completed ROT session and **₹450** customer Labour amount.

## New Admin functionality
Each ROT card displays:
- **Work Status:** existing Assigned/Running/Paused/Completed status and elapsed actual time (unchanged).
- **Customer Billing: BILLABLE / NO CHARGE**, with Admin-only change form and mandatory reason.

The boolean `job_card_labour.billing_included` defaults to 1 on all existing rows, with nullable reason. `NO CHARGE` leaves original line charge, ROT session, timestamps, mechanic productivity and audit history intact. The change recalculates Job Card customer Labour/GST/round-off/grand totals in a DB transaction, with strict consistency checks and before/after audit. The Invoice create fallback and invoice detail/print exclude non-billable rows, including the all-labour-no-charge case.

When unrelated stored totals are inconsistent, changing the billing policy is REFUSED with a reconciliation message. Already Closed/Invoiced/Cancelled JCs or JCs with linked issued invoices cannot change the policy. Posted invoice/accounting reversal must happen through authorized audited flow.

## Deployment
Staging P34 preflight run **#38558184349** PASS.
Guarded deployment & authenticated real Job Card render **#38558349125** PASS:
- `P34_ACTUAL_JOB_CARD_ROT_BILLING_BUTTON_RENDER_PASS`
- `P34_INVOICE_NO_CHARGE_FILTERS_AND_TOTAL_GUARDS_PASS`
- `P34_JOB34_BILLABLE_DEFAULT_PRESERVED_PASS`
- `P34_NO_BILLING_POST_FINANCIAL_LEDGER_OR_ROT_DATA_CHANGED`
- `P34_STAGING_ROT_BILLING_TOGGLE_DEPLOY_PASS`

Original source backups: `storage/app/p34_rot_billing_backup_20261014_171151_474527/`.

## Outstanding acceptance
No real Billable/No Charge POST was executed in CI. Before using on production documents, use a controlled STAGING Job Card to validate charge decrease, GST round-off, JC summary, invoice conversion and print, and reverse to billable. No existing Job Card #34 price, ROT time, stock or customer ledger values were changed during this deployment.
