# FIX28 — Staff ROT Live Progress / Remaining / Overtime

Purpose:
- Staff ko pata chale ROT kitne time se chal raha hai.
- Standard time ke against kitna use hua / kitna balance hai.
- Standard end ke paas warning.
- Overtime clear warning.
- Started time / target end time visible.
- Latest pause reason/note visible.
- Existing Start / Pause / Resume / Complete rules preserved.

Server response additions:
- standard_seconds
- remaining_seconds
- overtime_seconds
- progress_percent
- near_standard_limit
- target_end_at
- time_state
- pause_count
- pause_reason
- pause_note

No DB migration. Existing rot_sessions.pauses JSON is reused.
