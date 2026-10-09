# FIX32 — Staff Reminder Compliance / 3-Reminder Escalation

Goal:
- Server-global reminder identity.
- Postpone/snooze count survives logout/relogin and device changes.
- Reminder 1/3 -> 2/3 -> 3/3.
- After the final reminder, Admin escalation remains active until the required action is resolved.
- FCM and Android polling read the same authoritative state.

This first step is intentionally READ-ONLY because the live reminder engine source is not tracked in Git.
Run INSPECT.sh on staging and review the output before applying any reminder patch.

No DB change is performed by the inspector.
