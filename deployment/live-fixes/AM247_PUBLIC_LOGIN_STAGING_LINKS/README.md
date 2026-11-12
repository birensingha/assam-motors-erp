# AM247 — Link public Admin Login and Staff Login to STAGING ERP

Purpose: change **only two kinds of clickable login links** shown on the public `assammotors.com` website. Do not redirect all visitors or replace website homepage. The destinations are derived from real STAGING Laravel **GET** routes, not hardcoded or guessed.

## Usage (Hostinger SSH)

```bash
ROOT="$HOME/domains/assammotors.com/assam-erp-staging"
SITE="$HOME/domains/assammotors.com/public_html"
python3 site_links.py check "$ROOT" "$SITE"   # Read-only
python3 site_links.py apply "$ROOT" "$SITE"   # Only after PASS
python3 site_links.py rollback "$ROOT" "$SITE"  # Only if current files unchanged
```

Expected preflight: both `AM247_ADMIN_STAGING_DESTINATION` and `AM247_STAFF_STAGING_DESTINATION` confirmed from Laravel named routes, existing exact public Admin and Staff login anchors discovered, and `AM247_PREFLIGHT_PASS` / `AM247_CHECK_READ_ONLY`. The installer refuses to overwrite ambiguous links. In particular, WordPress database-generated menus need a WordPress-aware procedure and will stop.

Apply writes **only href values** in matched source HTML/PHP anchors (not ERP authentication), saves verified original website files and hashes outside `public_html` under STAGING private `storage/app/am-backups`, lints modified PHP before write, and supports hash-guarded rollback. Source match is intentionally limited to `Admin Login`, `Admin Portal`, `Staff Login`, `Staff Portal`, `Staff Portal Login`, or `Employee Login`. It does not change passwords, sessions, API routes or staff records.

## Browser acceptance (pending)
1. Load public `https://assammotors.com`, click **Admin Login**, confirm the real STAGING ERP Admin login page (correct URL; no legacy ERP).
2. Return to public homepage, click **Staff Login**, confirm STAGING staff login/portal route (redirecting through auth is okay).
3. Verify homepage and other public links are unchanged.
4. Confirm only intended website source files changed; no DB mutation.

This code lives in GitHub only until the Hostinger command runs and both buttons have been tested. If preflight STOPs, **do not force apply**: the error reveals whether the website is CMS/dynamic, static anchor labels differ, or staging routes are different.
