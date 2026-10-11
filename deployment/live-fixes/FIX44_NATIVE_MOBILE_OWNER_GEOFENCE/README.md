# FIX44 — Native Mobile Owner/Admin + Staff 100m Geofence

Staging-only integration.

## What it changes
- Normal Staff native login requires fresh GPS and server-side workshop geofence.
- Owner/Admin native login is remote-access exempt.
- Attendance Check-In/Out requires fresh GPS and existing server GeoFenceService.
- Staff ROT Start/Resume requires fresh workshop geofence.
- Adds native duty location upload endpoint.
- Adds Owner/Admin mobile endpoints for dashboard, staff, locations, Job Cards, customers, broadcast and printable Job Card data.
- Enables existing staging geofence settings at 100m and tracking ON, but only if workshop coordinates are already configured.

## Existing system reused
No parallel location tables or alert tables are created. Existing:
- staff_location_settings
- staff_location_logs
- staff_devices
- notification_outbox
- staff_reminder_state
are reused.

## Rollback
APPLY creates a private timestamped backup under Laravel storage/app and restores source automatically on script failure.
