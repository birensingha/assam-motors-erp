# Native Master Pages — Acceptance Checklist

## Authentication / Navigation

For each Vendor / Part / Labour / Vehicle / Labour-Part Mapping page:

- [ ] Logged-in authorised Admin opens native ERP route directly.
- [ ] No Legacy Admin login is required.
- [ ] Logged-out user goes to normal ERP login.
- [ ] Non-authorised user gets normal permission response.
- [ ] Main sidebar/menu links to native route.
- [ ] No query-string/cookie auth bypass exists.

## Vendor

- [ ] Search by code/name/mobile/GSTIN.
- [ ] Add Vendor.
- [ ] Edit Vendor.
- [ ] Vendor with Purchase history cannot be destructively deleted.

## Part

- [ ] Search by Part Code.
- [ ] Search by Part Name.
- [ ] Description/OEM search works where fields exist.
- [ ] Part Code duplicate prevented.
- [ ] Part with Purchase/Job Card/Invoice history cannot be destructively deleted.
- [ ] Parts Purchase autocomplete reads the same Part Master.

## Labour

- [ ] Labour Code/Description search.
- [ ] Standard Hours/ROT time editable with validation.
- [ ] Rate/tax validation.
- [ ] Existing historical Job Card/Invoice snapshot does not change after Master edit.

## Vehicle

- [ ] Registration search ignores spaces/dashes/case.
- [ ] Customer relation required.
- [ ] Brand/Model/Variant/Fuel/Year/KM fields align with prototype.
- [ ] Chassis and Engine number supported.
- [ ] KM cannot be negative.
- [ ] Vehicle with Job Card history is not destructively deleted.

## Labour / Part Mapping

- [ ] Search Labour.
- [ ] Search Part.
- [ ] Add Required/Optional mapping.
- [ ] Default Qty > 0.
- [ ] Duplicate active Labour+Part pair rejected.
- [ ] Remove/disable mapping does not delete Labour/Part Master.
- [ ] Existing historical Job Cards remain unchanged.
- [ ] Mapping can prefill Job Card but final Job Card pricing/validation remains server-side.
