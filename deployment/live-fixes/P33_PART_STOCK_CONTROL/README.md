# P33 — Job Card Part Requisition → Issue → Fitted

## Workflow and UI
On **Job Card > Parts**, Admin now sees one step-specific action per part:
- PLANNED → **Requisition** (creates assignment/request status and audit, no stock movement)
- REQUISITIONED → **Issue from Stock** (WIP only, validates Parts Master stock, deducts once, writes `JOB_ISSUE` inventory movement and audit)
- ISSUED → **Mark Fitted** (verifies exact Job Card Part reference and quantity, no extra stock deduction)
- FITTED → read-only completed badge

The per-line issue reference is `JC-PART-{job_card_part_id}-ISSUE`. Admin identity and reason required for each step. Server locks Job Card, Part line and Master stock row in one DB transaction.

`REQUISITIONED` and `ISSUED` remain pending for Work Complete. A requisitioned item can be deleted as an unissued planned item; an issued item uses existing stock-safe reverse-and-delete with audit.

## IMPORTANT: JC 34
Job Card #34 Part line #525 was physically fitted according to workshop owner, but the ERP records PLANNED and Parts Master #139 stock is **-1**. No automatic adjustment, stock-out or fitting record was submitted. Reconcile real purchase/stock history and on-hand stock before using **Issue from Stock**. Do not invent a Purchase or manually increase stock to force completion. If the Part was actually issued before but no movement exists, investigate missing issue history rather than duplicate issue.

## Verification
Read-only source audit Actions #38556108833 PASS. P33 source/controller/Blade preflight passed inside guarded staging deployment **#38557625757**:
- `P33_AUTHENTICATED_JOB_CARD_REQUISITION_RENDER_PASS`
- `P33_THREE_STEP_ADMIN_PERMISSIONS_AND_STOCK_SAFETY_PASS`
- `P33_NEGATIVE_STOCK_AND_DUPLICATE_ISSUE_BLOCKS_PRESERVED`
- `P33_NO_STOCK_ISSUE_OR_AUDIT_POST_DURING_QA`
- `P33_STAGING_REQUISITION_ISSUE_FITTED_DEPLOY_PASS`

Original source backed up under `storage/app/p33_parts_issue_backup_20261014_170544_104789/`.

## Pending acceptance
Admin must test real requisition -> issue -> fitted on a controlled Job Card where physical stock and stock ledger agree. No real stock POST was run by automated tests. This P33 patch does not implement Staff App store requisitions or purchase stock regularization.
