# AM261 — HSN/SAC in Parts, Labour/ROT and OSL Masters

22 Nov 2026: User requested HSN code input on Parts Master Create/Edit and SAC code on Labour, ROT and OSL Master Create/Edit, stored in authoritative Master database and available for Estimate/Invoice classification.

## Existing STAGING audit
- Parts Master already has hsn_code, but Create/Edit controller and Blade ignored it.
- Labour/ROT Master already has sac_code and 6-digit validation, but Create/Edit UI included a hardcoded service SAC default for all items.
- OSL Master had no SAC column and its Register and Job Card Quick Create did not accept SAC.
- Estimate already picked Parts HSN, but Labour/ROT/OSL SAC was not projected.

## Implemented
- Parts Create/Edit use HSN 4, 6 or 8 digits; server validates and persists existing parts_master.hsn_code, with search and Master list display.
- Labour/ROT Create/Edit display SAC Code with 6 digits and remove the unverified hardcoded default. Saved service classification is still in labour_master.sac_code.
- Additive nullable workshop_osl_master.sac_code column created only after fresh full private STAGING SQL backup; no existing OSL row changed.
- Configuration OSL Register Create/Edit adds SAC, list/search; controller validates and saves SAC and audits changes.
- Job Card inline Create OSL Master takes six-digit SAC and sends it to the same Admin-only OSL Master endpoint.
- Estimate new Part/Labour/OSL item lookup maps saved HSN/SAC from each Master into the existing hsn_sac line, without altering tax, rate, discount, stock or existing historical documents.

## Automated STAGING deployment
https://github.com/birensingha/assam-motors-erp/actions/runs/45001857596

PASS: AM261_PATCH_SOURCE_ANCHORS_PASS 9; AM261_CONTROLLER_PHP_LINT_PASS; AM261_FIVE_BLADE_VIEWS_COMPILE_LINT_PASS; AM246_FRESH_STAGING_DB_BACKUP_EXPORT_VERIFIED; AM261_HSN_PARTS_SAC_ROT_AND_OSL_SCHEMA_new_PASS; AM261_CLASSIFICATION_VALIDATOR_SAMPLE_PASS; AM261_HSN_SAC_MASTER_FORMS_SERVER_AND_ESTIMATE_MAPPING_VERIFIED; AM261_STAGING_PARTS_HSN_LABOUR_ROT_OSL_SAC_MASTER_VERIFIED.

## Acceptance outstanding
1. Reopen Parts Master Edit Part AM-0295 and confirm HSN input visible. Save correct HSN and reopen to check round trip.
2. Create new Part with correct HSN; select it on a new unsaved Estimate line.
3. Create/edit Labour/ROT with correct six-digit SAC; no arbitrary default value.
4. Create/edit OSL Master and verify SAC in register, and on Job Card quick-create.
5. Select Labour/OSL on an unsaved Estimate to verify saved SAC appears in its HSN/SAC field.

Actual authenticated browser Save/Edit and rendered invoice checks are pending. Do not invent HSN/SAC classifications or backfill old records automatically. No business records were edited during installation.