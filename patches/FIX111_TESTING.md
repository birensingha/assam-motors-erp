# FIX111 Mechanic Reassignment - scoped test evidence

**Source under examination:** previously supplied `resources/views/job-cards/show.blade.php` (not in GitHub main). Source SHA-256: `2e9a425e266b78fcbb7aec852b9380d1d02345a9325e38f0f79d09b578f304af`.

## Cause verified from source

- FIX87 handler creates an HTML dialog and clones the hidden `form.pop-form` with `cloneNode(true)`.
- FIX109 originally enhances the hidden original form and annotates the select with `data-am109-enhanced=1`.
- Cloning copies that attribute and the generated UI, but does not copy JavaScript event listeners.
- FIX109 refuses to enhance the clone, leaving an inert search field and arrow.
- FIX111 changes only the selector used by `scan()` to the *open* dialog; source form is left clean for cloning. MutationObserver initializes the open modal.

## Local browser smoke test (2026-10-23)

Used Chromium headless and a minimal DOM that reproduces the same `cloneNode(true)` behavior, with sample staff options.

| Check | Original FIX109 | FIX111 |
| --- | --- | --- |
| Popup search/arrow enhanced | Cloned, inert | Initialized |
| Menu opens when arrow is clicked | No | Yes |
| Selectable sample options | 0 | 2 |
| Selected hidden form `staff_id` | empty | `20` |

The exact patch also passed `patch --dry-run -p1` on the original uploaded file. This is **not** an authenticated live Laravel/Hostinger test, and staff list availability, POST handler, validation, audit and runtime compatibility remain unverified.

## Safety

No Blade/PHP directives, SQL, controllers, routes, stock, attendance, or ROT timers are changed. Never replace a newer Hostinger Job Card view with the old uploaded snapshot. Apply only this patch after checking the current file matches its context, back up the current file, compile views, and test on staging. Do not merge/deploy blindly.
