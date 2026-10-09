# FIX26 — Parts Purchase Search + Job Card / Vehicle Allocation

Target:
- Part Name / Part Code search tolerant of spaces/dashes/slashes/dots.
- Per-line Purchase Against filter by Job Card No / Vehicle Registration / Customer.
- Vehicle formats such as `AS01DS6283`, `AS-01-DS-6283`, and `AS 01 DS 6283` normalize consistently.
- Existing server-side Job Card eligibility validation remains authoritative.
- No DB migration.

Files modified on staging:
- `app/Http/Controllers/Web/AdminPurchasePageController.php`
- `app/Services/PurchaseService.php`
- `resources/views/purchases/create.blade.php`
- `public/js/purchase-entry.js`

Direct deployment:

```bash
bash CHECK.sh /home/u956497103/domains/assammotors.com/assam-erp-staging
bash APPLY.sh /home/u956497103/domains/assammotors.com/assam-erp-staging
```

`APPLY.sh` creates a timestamped `ASSAM_MOTORS_FIX26_BACKUP_...` before modifying files.

Validation performed before commit:
- PHP lint PASS
- JavaScript `node --check` PASS
- patch markers matched the post-FIX24 purchase source
- no Python dependency
- no `/dev/fd` process substitution
