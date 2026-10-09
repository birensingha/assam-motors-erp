# Targeted Live Source Handoff

After the sanitized inventory handoff, the exact staging file/table map is known.

The next step is a **targeted source-only review bundle** so integration changes can be written against the real Laravel/Legacy implementation instead of examples.

Run:

```bash
bash deployment/collect-targeted-live-source.sh /home/u956497103/domains/assammotors.com/assam-erp-staging
```

Expected output:

```text
TARGET SOURCE HANDOFF READY
Archive: /tmp/assam-motors-target-source-YYYYMMDD_HHMMSS.tar.gz
```

Upload that `.tar.gz`.

The script:
- copies only an explicit allowlist of Workshop/Admin/Staff source files;
- includes `route:list --json` when supported;
- excludes `.env`, vendor, storage logs and unrelated files;
- refuses to archive if common secret patterns are detected.

This bundle is for source review only. It does not modify staging.
