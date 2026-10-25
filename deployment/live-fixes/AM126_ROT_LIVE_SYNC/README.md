# AM126 — Completed ROT corrected TAT on Main Job Card

**Purpose**: User reported Main JC ROT-CUS-0198 still showed red `03:18:06 / OVER +02:06:06` after authorised Admin Full ROT Correction changed Start/End to 24 Oct 2026 09:49–10:36. GitHub read-only staging inspection found both `job_card_labour.rot_start/rot_end` and `rot_sessions.first_start_at/end_time` correctly saved, while `RotLiveClock::snapshot($session)` intentionally uses *productive* `rot_sessions.total_seconds`, which was **11,886 seconds = 03:18:06**.

## Principle
**Elapsed TAT and actual productive work are different measures; never overwrite productive work.**

AM126 changes only `resources/views/dashboard/partials/rot-live-clock.blade.php`:
- For a Completed session whose labour ID matches the current JC ROT line, derive primary **COMPLETED ELAPSED TAT** from audited `job_card_labour.rot_start -> rot_end`. Use `standard_hours` for remaining/overtime and progress. Display corrected Started, Expected End and Completed timestamps. Show **Productive Work Saved** separately.
- Existing Running/Paused source `RotLiveClock::snapshot` unchanged; no rewrites of start/pause/resume events, paid productivity seconds, work segments, attendance, invoices or ledger.
- Missing/invalid Completed dates fall back to the original V639 productive-time snapshot instead of fabricating a timestamp.
- Browser's V639 1-second clock already uses `data-am-elapsed`, `data-am-standard`, `data-am-running`. Completed status keeps `running=0` so no subsequent tick resets adjusted time on re-render.

## Concrete example
JC-20261022-00040 ROT-CUS-0198, read-only audit 25 Oct 2026:
- corrected 09:49–10:36 -> elapsed TAT = 47 minutes
- Std Hours 1.200 -> 1h 12m
- Remaining from corrected TAT = 25m
- Productive saved = 03:18:06, unchanged (not reconciled or reduced)
- Red overdue bar on *productive* vs Std hours becomes a corrected **TAT remaining** bar for Completed only.

## Staging deploy
The GitHub Actions workflow `.github/workflows/deploy-am126-rot-live-tat-staging.yml` runs upon main merge, using existing staging SSH secrets. It performs guarded source check, timestamped backups, atomic view edit, Laravel Blade compile + compiled PHP lint. A **read-only Laravel view-render QA** compares displayed TAT with database lines, verifies unchanged productive seconds, and ensures running/paused displays keep V639 semantics. If code compile/QA/login-health fails, workflow restores pre-apply view backup and re-caches it.

**No action is requested from owner on Hostinger.** Do not test using actual edits; read-only rendered snapshot validation is sufficient for deployment. Authenticated Main JC browser test is a separate acceptance item; verified source/Blade/HTML rendering is not equivalent to a full browser click test.

## Strict scope
- No migrations, models, controller routes or database UPDATE statements.
- Existing Alteration, V647 timeline controls and original Approval/Estimate history untouched.
- No production ERP deployment.
