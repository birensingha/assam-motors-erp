# FIX39 — OSL Purchase Multi-Line / Parts-style UI

Staging inspection confirmed the core OSL multi-line implementation is already present:
- `PurchaseService::saveOslBatch()`
- up to 50 OSL lines
- one transaction
- `purchase_batch_no` / `purchase_line_no`
- `workshop_purchase_batches`
- line-wise Job Card linkage
- vendor rate / discount / GST
- customer rate / customer GST
- existing OSL-to-Job-Card Labour billing linkage

FIX39 therefore does **not** replace the working backend.

It only closes the remaining Parts Purchase parity gap:
- JC / Vehicle / Customer text filter per OSL line
- Enter-to-select when one JC match remains
- 100% / 115% / 125% readability buttons
- Parts-Purchase-like blue/green/amber/red visual treatment
- larger readable controls
- safe horizontal table handling

No DB migration.

Apply:

```bash
bash APPLY.sh /home/u956497103/domains/assammotors.com/assam-erp-staging
```
