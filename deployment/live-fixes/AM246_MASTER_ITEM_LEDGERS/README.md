# AM246 — Parts / ROT-Labour / OSL Master Item Transaction Ledger

## User requirement (Assam Motors staging)
When someone types a Parts / Labour (ROT) / OSL service **name or code**, show a Tally-like, date-wise register: when purchased, purchased from which vendor, supplier invoice, job card / vehicle, customer billed, amount, and for Parts, opening stock, physical inward/outward and running stock balance.

## Staging delivery
- **GET /erp/masters/item-ledger** — compact searchable read-only transaction screen with master type (PART/LABOUR/OSL), code/name typeahead, date range and browser Print Ledger.
- **GET /erp/masters/item-ledger/search** — authorized search JSON, 50 results per query, includes inactive master items.
- Parts Master rows have **Transactions** direct links and master header has **Search All Parts Transactions**.
- ROT/Labour Master rows have **Transactions** direct links and master header has **Search All ROT / Labour Transactions**.
- OSL Master has **Search OSL Item Ledger** shortcut.
- **PARTS:** Current `parts_master.stock_qty`, reconstructed first recorded opening, physical stock ledger from `inventory_movements`, movement-in, movement-out, chronological balance, current master reconciliation difference. Independent supplier purchases from `workshop_purchases` and legacy `purchases/purchase_items`. JC part allocations and invoice linkage shown separately, with customer, vehicle and job card number. Direct counter invoices are distinct financial entries from physical stock-out.
- **LABOUR:** linked JC labour lines and ROT execution sessions with Mechanic, duration, status, JC/vehicle/customer and saved JC line amount.
- **OSL:** purchases from `workshop_purchases` linked to OSL Master and supplier, vendor cost, linked Job Card billing lines, customer/vehicle and saved customer charge.
- **Crucial accounting rule:** a Purchase document, JC planned item, physical stock issue and customer invoice are separate transaction event types. They are not summed as one stock movement or one financial transaction.
- Show deleted/voided supplier purchase records explicitly with audit status.
- Admin authorization, view-only GET requests, no database migrations or financial posting.

### Limitations disclosed on the screen
1. Historic Parts opening stock is **reconstructed from the earliest recorded stock movement**, not fabricated as a stored opening-entry voucher. Any discrepancy with Parts Master current stock is highlighted. Where there is no inventory movement, historical opening cannot be verified.
2. A JC with a linked Invoice is not necessarily proof of an exact original *item-level invoice snapshot*. Ledger labels JC line/invoice evidence honestly; direct invoice lines are genuine saved item rows.
3. Unlinked historical OSL lines without a reliable OSL master ID/code match are not guessed into a service ledger.
4. The report shows the latest 3,000 events if a chosen item has more, with a clear warning and date filtering; totals from truncated event lines must not be treated as all-time totals.

## Staging checks (2026-11-04)
[AM246 guarded deployment and authenticated Chrome QA PASS](https://github.com/birensingha/assam-motors-erp/actions/runs/41875619203)

- PHP syntax, Laravel Blade compilation and route registration PASS.
- Server-side Laravel controller QA using real staging data: PART **11** events, LABOUR **16** events, OSL **3** events. All three searchable master lookups PASS; unauthorized access 403 PASS.
- Browser: Parts, Labour, OSL Master shortcuts PASS; real name/code typeahead, Parts ledger opening/closing KPIs, LABOUR and OSL page transitions PASS.
- No stock, purchases, JC, invoices or master rows changed during implementation tests. Source backup and automatic rollback available.

## Package
`apply.py check` runs strict read-only anchor preflight. `apply.py apply` backs up 4 source files and installs the controller + Blade. `apply.py rollback` restores the original views/routes and removes the new files. The GitHub action applies and tests, automatically rolling back on failure.

## Future enhancements requiring separate financial reconciliation
Saved JC invoice-item snapshot drilldown, credit-note line reversal history, standalone OSL legacy master linkage cleanups, stock adjustment vouchers with verified opening balance and CSV/Excel exports, vendor price history and cost margin summaries.
