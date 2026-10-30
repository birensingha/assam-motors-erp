# AM217 — Edit Part and Edit ROT same popup as New

**Requested:** The existing Parts and ROT master Edit buttons must receive the same clean OLD ERP style popup and dropdown capabilities already provided in New Create.

## Implemented design
- On existing Parts Master/Edit button `amToggle('partEdit<ID>')` and ROT Master/Edit button `amToggle('rotEdit<ID>')`, open an accessible responsive two-column modal.
- **Move the existing original Edit form DOM node; never clone it or create a separate form.** All saved values, original CSRF token, the original Update POST route, and AM214 dropdown listeners stay attached.
- Heading: Edit Part / Edit Labour / ROT; same blue popup header, Close and the original Save/Cancel actions.
- Category, Sub Category, Unit, Vehicle Make/Model and GST in Parts retain AM214 type-to-search options. ROT Category retains AM214 suggestions. ROT technical components field from AM212 remains unchanged.
- Existing browser form validation, mapped master IDs, stock history and finance records remain untouched. No database schema change, no SQL writes by installer.
- Existing New buttons, Delete, Search, table sorting, pagination and route guards are not changed.

## Guarded STAGING deployment

Auto deploy via `.github/workflows/deploy-am217-edit-master-modals.yml` (GitHub Issues #124 reports result).

Manual preflight / apply if needed:

```bash
cd ~/assam-motors-deploy && git pull --ff-only origin main
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
PATCH="deployment/live-fixes/AM217_EDIT_MASTER_MODALS/install.py"
python3 -m py_compile "$PATCH" && python3 "$PATCH" check "$ROOT"
# After preflight PASS:
python3 "$PATCH" apply "$ROOT"
```

Rollback source-only, iff neither native view has subsequently changed:

```bash
python3 "$PATCH" rollback "$ROOT"
(cd "$ROOT" && php artisan view:clear)
```

## Browser acceptance (not equivalent to server preflight)
1. Open **Parts Master** on STAGING as Admin, Edit a record; verify popup, prefilled Part Code, Part Name, Category, Make/Model, Unit, GST, prices.
2. Click category and GST, type and select; cancel and confirm no save; reopen, edit a safe test record; Save/Refresh should retain it.
3. Open **ROT Master**, Edit `ROT-CUS-0211`; popup retains code, description, Category, SAC, STD Hrs, Rate, GST, and **Parts / Components to Remove & Refit**. Save a safe change, refresh and confirm full text.
4. Verify clicking Edit doesn't stretch table rows; mobile view must scroll inside modal. Create forms remain unchanged.
5. Verify prior closed invoices and historical Job Cards retain original snapshot amounts.

Until these tests pass, mark **server installed / browser pending**, not complete.
