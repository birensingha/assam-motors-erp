# Assam Motors — Rollback Matrix

| Failure | First action | DB action | Android action |
|---|---|---|---|
| Native page/auth routing broken | Revert affected route/controller/navigation code | None | None |
| Payment create still 500 | Revert targeted Payment source change; preserve logs | None unless DB change caused it | None |
| Job Card Create/Edit broken | Disable/revert new Job Card routes/services | Keep additive audit table | None |
| Invoice conversion wrong | Disable Convert immediately; revert service | Preserve test invoice/audit rows for investigation; restore only reviewed scoped data | None |
| Parts/OSL search broken | Revert frontend/API wiring | Keep additive OSL table unless proven incompatible | None |
| ROT actions broken | Revert ROT endpoint integration | Keep `rot_event_audit` | Keep compatible Android/polling path |
| Alert polling broken | Revert alert API wiring | Keep alert/event tables | Android can use prior compatible endpoint |
| FCM sender broken | Disable FCM send hook | Keep device/delivery tables | Polling fallback remains |
| Excel export broken | Disable export action | None | None |
| Staff APK regression | Stop update-feed publication | None | Fix-forward with a higher versionCode |
| Wrong APK/checksum published | Disable update feed immediately | None | Republish only verified artifact/metadata |
| Signing mismatch | Stop distribution | None | Rebuild with authorised existing production key |

## Database rollback policy

Current prepared migrations are additive `CREATE TABLE IF NOT EXISTS`.

Default urgent rollback:
- do not drop new tables;
- stop application writes to the affected feature;
- preserve audit/history;
- decide schema removal later.

If a new table must be removed:
1. export it;
2. verify no active code references it;
3. record row count;
4. obtain explicit approval;
5. drop only the confirmed unused table.

## Data correction

Export affected rows first. Use a reviewed transaction and tightly scoped IDs/WHERE clauses. Never run broad UPDATE/DELETE during emergency recovery.

## Android rollback

Prefer fix-forward:
- same package ID;
- same signing identity;
- higher versionCode.

Do not regenerate signing keys and do not treat a lower-version APK as a routine in-place rollback.
