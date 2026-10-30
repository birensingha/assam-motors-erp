# AM214 — Native Parts/ROT master clickable dropdowns + corporate popup

User screenshots (30 Oct 2026): Part Category, Unit, Vehicle Make, Vehicle Model and GST dropdowns were not working; ROT Category likewise. New Part/ROT forms looked unlike OLD ERP modal.

## Implementation
- NEW ERP only: `/erp/parts` and `/erp/labour`, current Laravel Admin authentication, Create and Edit routes unchanged.
- Loads OLD ERP's original category option choices **read-only** from local historical HTML and merges them with existing STAGING master values (no copying legacy API behavior).
- Clickable drop-down list floating over the page; typing filters, mouse/pointer choice, up/down/Enter/Escape keyboard, no dropdown-driven form height growth.
- Parts: `category`, `sub_category`, `unit`, `vehicle_make`, `vehicle_model`, `gst_percent`.
- ROT: `category`.
- Vehicle Make/Model linked using `vehicle_makes` + `vehicle_models` and `am_vehicle_catalog_master`, falling back to existing Part master values; a newly typed value may use existing master Create route.
- Part New modal using original details panel. ROT New modal using original `newRotPanel` and `amToggle`; lightweight dark backdrop, compact two-column responsive form and Close control. Controls never redirect to OLD ERP.
- AM213 datalist attributes removed at runtime from these inputs to prevent duplicate dropdown UIs. AM214 may install whether or not AM213 was installed.
- ROT label and helper approved in AM212 are prerequisites for AM214; do not overwrite AM212 field/data.
- Does **not** change vendor/stock/Job Card/Invoice/Estimate, do not invent HSN/GST, preserve current backend validations.

## Safe STAGING commands
```bash
cd ~/assam-motors-deploy && git pull --ff-only origin main
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
PATCH="deployment/live-fixes/AM214_NATIVE_MASTER_MODAL_SELECT/install.py"
python3 -m py_compile "$PATCH"
python3 "$PATCH" check "$ROOT"
# Only when check reports PASS:
python3 "$PATCH" apply "$ROOT"
```

After installation, browser test Admin /erp/parts and /erp/labour Create/Edit; search/select existing/new categories; Vehicle Make→Model, GST; save and refresh; confirm no layout distortion on mobile width. If check STOPs, do not force apply.

Rollback only when current two views are unchanged:
```bash
python3 "$PATCH" rollback "$ROOT"
(cd "$ROOT" && php artisan view:clear)
```

## Deployment automation
See `.github/workflows/deploy-am212-am214-staging.yml`. It first performs guarded AM212 backup/migration/approved copy/verification, and only then checks/applies AM214. Full browser authentication and posted Save/Refresh **cannot** be asserted from server-side source tests.

## Remaining ERP-only improvements
OLD CSV/XLSX import/template, full Vehicle Master Make-Model-Variant Create/Select parity, master delete financial dependency safeguards, and other Configuration functions are separate pending work. Android remains last.
