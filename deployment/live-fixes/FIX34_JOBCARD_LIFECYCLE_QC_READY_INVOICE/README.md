# FIX34 — Job Card Lifecycle: QC -> Ready -> Close -> Invoice

User requirement:
- Job Card should not jump straight from work completion to invoice.
- Required lifecycle:
  1. Work In Progress
  2. Work Complete / Awaiting QC
  3. Quality Check
  4. Ready for Delivery
  5. Job Card Closed
  6. Invoice / Billing
- Existing audit/history should be reused.
- Existing Parts/Labour/ROT completion rules must be respected.
- No duplicate lifecycle table should be introduced if live schema already supports these states.

This first step is READ-ONLY. Run INSPECT.sh on staging; then prepare the exact APPLY patch from the live controller/service/schema.
