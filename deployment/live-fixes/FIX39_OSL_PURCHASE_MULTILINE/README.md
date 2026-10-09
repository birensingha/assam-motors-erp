# FIX39 — OSL Purchase Multi-Line

Read-only live-staging discovery for rebuilding OSL Purchase in the same visual/transaction pattern as Parts Purchase.

This inspector maps:
- OSL routes and controller methods;
- PurchaseService OSL save rules;
- current OSL form;
- current Parts Purchase multi-line UI;
- existing batch/header tables;
- Job Card / Vehicle allocation/search fields;
- live table schemas and recent OSL rows.

No source file or database row is modified.

Run:

```bash
bash INSPECT.sh /home/u956497103/domains/assammotors.com/assam-erp-staging
```

Target guarded apply package:
- one Vendor / Bill / Purchase Date header;
- 10–50 OSL lines;
- OSL code/description search;
- line-wise Job Card / Vehicle allocation;
- Qty, Vendor Rate, Discount, Purchase GST;
- Customer Rate / Customer GST where live schema supports it;
- line totals + document total;
- Add / Remove line;
- one DB transaction;
- server calculation authoritative;
- Parts Purchase-like readable blue/green/amber/red UI.
