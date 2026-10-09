# FIX25 — Legacy-style Customer Ledger + Job Card Summary UI (09-Oct-2026)

User reference screenshots were treated as the UI/workflow target.

## Customer
- Customer Database card UI with total Customer / Vehicle / Job Card / Receivable counters.
- Open Customer full ledger/profile page.
- Unlimited vehicles per customer after creation.
- Add Vehicle and Edit Vehicle.
- Customer creation may include vehicle rows.
- Vehicle-wise Job Card/service history.
- All-customer Job Card summary on customer ledger.
- Invoice and customer ledger summary.
- Print/Save PDF through browser print.
- Existing Customer/Vehicle/Job/Invoice/Ledger tables only.

## Job Cards
- summary counters
- search/status/service/inspection filters
- Parts/Labour/ROT work-record counts
- visible JC Summary action
- New Job Card action retained

No DB migration.

Package:
`ASSAM_MOTORS_FIX25_CUSTOMER_LEDGER_JOBCARD_UI.tar.gz`

SHA-256:
`7c7986745d94178689eafc9b4272bb7b02c0fc120024c96897d003f957d777d9`

Validation:
- controller PHP lint PASS
- route PHP lint PASS
- verification PHP lint PASS
- Blade directive sanity PASS
- Hostinger shell dependency scan PASS
- live-baseline CHECK synthetic smoke PASS
