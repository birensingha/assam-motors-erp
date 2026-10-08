# Reporting Query / Integration Notes

The live ROT session table name is not present in this repository, so this file avoids inventing a production table name.

The Admin report should start from the **existing authoritative ROT assignment/session table**, because Assigned/Waiting rows may have no event history yet.

Then LEFT JOIN:

- Staff master → technician name/code
- Job Card → Job Card No.
- Vehicle → registration
- Labour/ROT master → code, description, standard hours
- `rot_event_audit` → event timestamps, pause count/reason/note

## Event aggregation pattern

For each ROT session:

```sql
SELECT
    rot_session_id,
    MIN(CASE WHEN event_type = 'START' THEN event_at_local END) AS first_start_at,
    MAX(CASE WHEN event_type = 'COMPLETE' THEN event_at_local END) AS last_end_at,
    SUM(CASE WHEN event_type = 'PAUSE' THEN 1 ELSE 0 END) AS pause_count,
    MAX(CASE WHEN event_type = 'PAUSE' THEN pause_reason END) AS last_pause_reason
FROM rot_event_audit
GROUP BY rot_session_id;
```

Do not calculate productive duration simply as `last_end - first_start`; that would incorrectly include paused time.

## Recommended source fields

Normalize every row to:

- rot_session_id
- staff_id / staff_name
- job_card_id / job_card_no
- vehicle_reg_no
- rot_code / rot_description
- status
- standard_seconds
- productive_seconds
- first_start_at
- last_end_at
- pause_count
- last_pause_reason
- last_pause_note

Then run `am_rot_perf_row()` / `am_rot_perf_summary()`.

## Filters

Use parameterized queries only.

Date filtering should use Asia/Kolkata event/business date and cover:
- assigned_at for never-started Assigned rows;
- first_start/event date for started rows;
- completion date where completion-oriented report is requested.

Default Admin view: current date, all technicians.
