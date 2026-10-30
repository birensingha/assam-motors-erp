# AM211 — OLD ERP Configuration Select/Create parity (Parts / ROT / Vehicle)

**Current stage:** read-only code and schema comparison. No live production data changes, no new CRUD code deployed yet.

## User's exact issue

Configuration tiles moved to NEW ERP under AM184/AM185, but *select an existing data value, search within dropdown, and quickly create a missing value* no longer matches OLD ERP. AM185 only wired native routes; it explicitly did not fabricate CRUD functionality.

## Acceptance contract for full parity

### Parts Master
- Existing Part Code / Part Name search with keyboard typing and selection.
- Existing record selected -> related properties (unit, HSN/GST, prices, status, OEM/alternate) populate from actual master.
- Missing part -> Create New via the same native page, save through existing authorised backend, then refresh/re-select the new item.
- Preserve existing IDs, existing Parts Purchase/Job Card/Invoice references and duplicate code checks.

### ROT / Labour Master
- Search ROT code, description, category; select existing, edit eligible master or create missing ROT.
- Standard Hours, SAC, price, GST and status use the actual schema and validations.
- Do not modify historical Job Card labour and invoice snapshots when the master is updated.

### Vehicle Master
- Existing Brand -> Model -> Variant (and other OLD ERP vehicle selection fields discovered in audit) should be linked, searchable where old UI allowed it, with **New Create** path for absent values.
- Respect customer/vehicle master links, registration uniqueness and old records; never silently create duplicate vehicle variants.
- Do not infer foreign keys or master table names without actual staging database metadata.

### Common
- One clean compact, responsive Admin native page per master. No OLD ERP redirects.
- Existing dropdown keyboard selection, add/create, validation and save result must work on desktop.
- Do not replace native ERP authorised action endpoints with guessed URLs.
- Old source is required for exact parity; do not claim parity if original workflow details cannot be verified.
- Security: existing Admin middleware; data updates must preserve audit, references, finance and inventory.

## First command (read-only)

```bash
cd ~/assam-motors-deploy && git pull --ff-only origin main
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
python3 -m py_compile deployment/live-fixes/AM211_CONFIG_SELECT_CREATE_PARITY/audit.py &&
python3 deployment/live-fixes/AM211_CONFIG_SELECT_CREATE_PARITY/audit.py "$ROOT" > "$HOME/am211-config-parity-audit.txt"
```

Audit searches native Laravel views/controllers/routes and accessible legacy code directories and retrieves table *names and columns only*, no individual rows. Upload the resulting local file through the ChatGPT conversation (it is not published on the website).

## Next

Use exact findings to implement a guarded AM212 parity patch with backup, PHP/JS/Blade lint, Admin auth smoke, and browser acceptance for each Parts/ROT/Vehicle form. **Do not mark complete until the user actually tests dropdown selection and New Create.**
