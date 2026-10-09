# FIX37 — Job Card Core

Direct Job Card repair package.

Includes:
- issued/fitted Part delete becomes stock-safe reversal + delete;
- reversal returns qty to parts_master.stock_qty and writes inventory_movements;
- planned Part still deletes normally;
- direct Add Part without Estimate;
- direct Add Labour/ROT without Estimate;
- direct lines are audited as DIRECT_ADD;
- Job Card Parts + Labour/ROT get Purchase-style visual treatment;
- no DB migration.

Safety:
- issued/fitted delete refuses to invent stock if no matching non-purchase stock-out movement exists;
- Closed/Invoiced/Cancelled Job Cards remain locked;
- backup + rollback included.
