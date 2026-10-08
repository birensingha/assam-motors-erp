# ROT Performance API Contract

Recommended native Admin endpoint:

`GET /erp/api/rot-performance`

Query parameters:

- `from=YYYY-MM-DD`
- `to=YYYY-MM-DD`
- `staff_id=`
- `status=assigned|running|paused|completed|all`
- `job=`
- `vehicle=`
- `q=` for ROT code/description

## Response

```json
{
  "filters": {
    "from": "2026-10-01",
    "to": "2026-10-08",
    "staff_id": null,
    "status": "all"
  },
  "summary": {
    "assigned": 4,
    "running": 2,
    "paused": 1,
    "completed": 18,
    "standard_seconds": 61200,
    "productive_seconds": 59800,
    "efficiency_percent": 102.34,
    "over_standard_count": 5
  },
  "rows": [
    {
      "rot_session_id": "8841",
      "staff_id": 12,
      "staff_name": "Firajul",
      "job_card_no": "JC-1042",
      "vehicle_reg_no": "AS01AB1234",
      "rot_code": "ROT-15",
      "rot_description": "Front brake service",
      "status": "Completed",
      "standard_seconds": 3600,
      "productive_seconds": 3300,
      "variance_seconds": -300,
      "efficiency_percent": 109.09,
      "first_start_at": "2026-10-08 10:10:00",
      "last_end_at": "2026-10-08 11:05:00",
      "pause_count": 1,
      "last_pause_reason": "Parts Waiting",
      "last_pause_note": "Brake pad received"
    }
  ]
}
```

## Server rules

- Dates use Asia/Kolkata business dates.
- Staff ownership/labels come from server joins, not client input.
- Productive time is authoritative server duration.
- Paused duration must not be counted as productive duration.
- Running rows may expose live productive seconds as of server response time.
- Completed rows never continue accumulating.
- Do not derive money/commission values for Staff app.
