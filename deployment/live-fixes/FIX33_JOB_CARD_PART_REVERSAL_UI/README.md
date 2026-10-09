# FIX33 — Job Card Part Reversal + Parts/Labour UI

User issues:
1. Deleting a Job Card part shows: "This Part has already been issued/fitted. Direct Delete is blocked because Stock reversal is required."
2. Job Card Parts and Labour/ROT blocks should look like the Parts Purchase line-item format: aligned, bordered, readable and compact.

Safety:
- Issued/fitted parts must never be hard-deleted without reversing stock.
- Existing inventory/stock ledger mechanism must be reused.
- Reversal must be auditable and transactional.
- No new parallel stock table should be created.

This first step is READ-ONLY. Run INSPECT.sh and review the output before preparing the APPLY patch.
