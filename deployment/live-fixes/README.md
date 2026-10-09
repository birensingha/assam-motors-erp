# Assam Motors — Direct Live-Fix Deployment

From FIX26 onward, staging fixes are stored under `deployment/live-fixes/`.

One-time server setup:

```bash
cd ~
git clone https://github.com/birensingha/assam-motors-erp.git assam-motors-deploy
cd ~/assam-motors-deploy
git checkout main
```

For every later fix:

```bash
cd ~/assam-motors-deploy
git pull --ff-only origin main
```

Then run that fix's `CHECK.sh` / `INSPECT.sh` / `APPLY.sh` against:

```text
/home/u956497103/domains/assammotors.com/assam-erp-staging
```

Rules:
- CHECK/INSPECT must be read-only.
- APPLY must create a timestamped backup first.
- Do not store .env, DB dumps, signing keys or Firebase service-account files in this repository.
- Never deploy by replacing the whole staging application.
- No fix is called PASS until the relevant browser/device smoke test passes.
