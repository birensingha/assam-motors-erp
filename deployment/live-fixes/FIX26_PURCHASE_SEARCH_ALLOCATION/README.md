# FIX26 — Parts Purchase Search + Job Card / Vehicle Allocation

Target:
- Part Name / Part Code live search.
- Per-line Purchase Against search by Job Card No, Vehicle Registration, Customer or Mobile.
- Normalize vehicle formats such as `AS01DS6283`, `AS-01-DS-6283`, and `AS 01 DS 6283`.
- Keep existing server-side Job Card eligibility validation authoritative.

Current live-source review shows:
- Parts search already uses a server endpoint.
- Purchase Against currently relies on a preloaded/static Job Card selector.
- `PurchaseService::searchJobs()` is the correct server-side search source.
- No new DB table is required.

First run `INSPECT.sh` on the current staging state. The APPLY patch is intentionally gated on the exact post-FIX24/FIX25 live markers so it does not overwrite OSL or Customer/Job Card work.
