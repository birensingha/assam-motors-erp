# Assam Motors — Automated Staging Control

This keeps the existing Hostinger staging ERP. It does **not** create a new ERP.

## What this control layer does

After one-time setup, GitHub Actions can:

- connect to Hostinger staging over SSH;
- inspect staging status;
- clear Laravel caches;
- run guarded CHECK / INSPECT / APPLY / VERIFY packages;
- log in to the staging ERP with a dedicated test Admin account;
- open critical Workshop pages in Chromium;
- capture screenshots;
- fail on HTTP 500 / SQLSTATE / PHP fatal-error indicators.

This removes most manual terminal copy/paste from the development loop.

## Required GitHub Actions secrets

In the repository:

Settings → Secrets and variables → Actions → Secrets

Add:

- `STAGING_SSH_HOST`
- `STAGING_SSH_PORT`
- `STAGING_SSH_USER`
- `STAGING_SSH_PRIVATE_KEY`
- `STAGING_ADMIN_LOGIN`
- `STAGING_ADMIN_PASSWORD`

Do not put these values in Git, issues, release notes, screenshots or chat messages.

## SSH key rule

Use a dedicated staging deployment key.

1. Generate a dedicated ED25519 key pair on a trusted machine.
2. Put the PUBLIC key in the Hostinger SSH account's `~/.ssh/authorized_keys`.
3. Put only the PRIVATE key in GitHub secret `STAGING_SSH_PRIVATE_KEY`.
4. Do not reuse a personal workstation private key if a dedicated deployment key can be used.

## Optional GitHub Actions variables

Settings → Secrets and variables → Actions → Variables

- `STAGING_APP_ROOT`
  - default: `/home/u956497103/domains/assammotors.com/assam-erp-staging`
- `STAGING_BASE_URL`
  - default: `https://staging.assammotors.com`
- `STAGING_LOGIN_PATH`
  - default: `/erp/login`

## Workflow 1 — Staging SSH Control

Workflow name:

`Assam Motors Staging SSH Control`

Allowed operations:

- `status`
- `optimize_clear`
- `check_fix`
- `inspect_fix`
- `verify_fix`
- `apply_fix`

Fix operations only accept directories under:

`deployment/live-fixes/FIX_NAME`

`apply_fix` also requires:

`confirm_apply = APPLY-STAGING`

Arbitrary remote shell input is deliberately not exposed.

## Workflow 2 — Staging Browser Smoke

Workflow name:

`Assam Motors Staging Browser Smoke`

It runs manually and also after a successful SSH Control workflow.

Current checks:

- `/erp/job-cards`
- `/erp/purchases/osl/create`
- `/erp/customers`
- `/legacy/workshop/customers.php`

It uploads:

- after-login screenshot;
- Job Card screenshot;
- OSL Purchase screenshot;
- Customer screenshot;
- Legacy Customer redirect screenshot;
- `result.json`.

## Safety

- Staging only.
- Never use production credentials in these staging secrets.
- Existing FIX packages still own backup/rollback behavior.
- `.env`, DB dumps, signing keys and Firebase service-account files are never uploaded.
- Browser test should use a dedicated staging Admin account.
- SSH success alone is not DONE; browser/device workflow must pass.

## Target operating model

Once secrets are connected:

Code change → GitHub → guarded staging deploy → Laravel cache handling → browser smoke → screenshot/error evidence → next fix.

The user should mainly be required for business decisions and final acceptance, not repetitive terminal input/output.

## Next browser expansion

Extend the smoke test to the canonical Workshop journey:

Customer
→ Vehicle
→ Job Card
→ Inspection
→ Diagnosis
→ Estimate / Direct Work
→ Parts + Labour/ROT + OSL
→ Work Complete
→ QC
→ Ready for Delivery
→ Close
→ Invoice
→ Payment.
