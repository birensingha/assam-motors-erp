#!/usr/bin/env bash
# AM245 Hostinger source-matched activation. No DB migrations/finance access.
set -euo pipefail
ROOT="${1:?Usage: activate_hostinger_feedback.sh /full/laravel/root --check|--apply}"
MODE="${2:---check}"
case "$MODE" in --check|--apply) ;; *) echo 'Use --check or --apply' >&2; exit 2;; esac
ROOT="$(cd "$ROOT" && pwd -P)"
MODULE="$(cd "$(dirname "$0")/.." && pwd -P)"
PATCH="$MODULE/hostinger-am245-integration.patch"
cd "$ROOT"

fail() { echo "AM245_STOP: $*" >&2; exit 2; }
[[ -f artisan && -f "$PATCH" ]] || fail 'Missing Laravel artisan or GitHub integration patch'
[[ -f storage/app/am245-stage-manifest.json ]] || fail 'AM245 stage manifest missing'
[[ -f app/Providers/Am245HostingerServiceProvider.php ]] || fail 'AM245 provider not staged'
[[ -f routes/am245-feedback.php && -f routes/am245-feedback-invitations.php ]] || fail 'AM245 routes not staged'
[[ -f config/am245.php ]] || fail 'AM245 safe config missing'
[[ ! -f storage/app/am245-activation-completed.marker ]] || fail 'Activation already recorded; do not run twice'

# All three hashes are from the user-supplied STAGING archive (26 Nov 2026).
while read -r expected rel; do
  [[ -n "$expected" ]] || continue
  [[ -f "$rel" && ! -L "$rel" ]] || fail "Missing or linked source: $rel"
  actual="$(sha256sum "$rel" | cut -d' ' -f1)"
  [[ "$actual" == "$expected" ]] || fail "Source changed since review: $rel"
done < "$MODULE/hostinger-am245-source-hashes.txt"

[[ ! -L bootstrap/providers.php ]] || fail 'Provider file is a symlink'
[[ "$(grep -Fc 'App\Providers\AppServiceProvider::class,' bootstrap/providers.php)" == 1 ]] || fail 'Unexpected provider structure'
! grep -Fq 'Am245HostingerServiceProvider' bootstrap/providers.php || fail 'AM245 provider already registered'
grep -Fq "'verified_single_company_scope' => false" config/am245.php || fail 'Unsafe company scope config'
grep -Fq "'verified_customer_opt_in_column' => null" config/am245.php || fail 'Unsafe customer opt-in config'
if compgen -G 'bootstrap/cache/routes-*.php' > /dev/null; then
  fail 'Laravel route cache exists; review before activation'
fi
command -v patch >/dev/null || fail 'System patch utility not available'
patch --dry-run --batch -p1 < "$PATCH" || fail 'Source patch dry-run failed'
php -l app/Providers/Am245HostingerServiceProvider.php >/dev/null || fail 'Invalid provider syntax'
php -l routes/am245-feedback.php >/dev/null || fail 'Invalid AM245 Feedback route syntax'
php -l routes/am245-feedback-invitations.php >/dev/null || fail 'Invalid AM245 invitation route syntax'
echo 'AM245_CHECK_PASS: staged files, source hashes, consent default and patch verified'
if [[ "$MODE" == '--check' ]]; then
  echo 'DRY_RUN_ONLY: no STAGING edits and no DB commands executed'
  exit 0
fi

# Caller has already completed and verified both AM245 migrations.
# Confirm the tables exist; this probe reads table schema only, never customer rows.
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (["am_customer_feedback", "am_customer_complaints", "am_customer_complaint_history", "am_feedback_invitations"] as $t) {
    if (!\Illuminate\Support\Facades\Schema::hasTable($t)) {fwrite(STDERR,"Missing AM245 table: $t\n"); exit(2);}
}
' || fail 'AM245 migrations not verified'

umask 077
BACKUP="$(mktemp -d "$ROOT/storage/app/am245-activation-XXXXXXXX")"
FILES=(routes/web.php bootstrap/providers.php resources/views/job-cards/show.blade.php resources/views/layouts/erp.blade.php)
for rel in "${FILES[@]}"; do
  mkdir -p "$BACKUP/$(dirname "$rel")"
  cp -p "$ROOT/$rel" "$BACKUP/$rel"
done
restore_on_error() {
  code=$?
  if [[ "$code" -ne 0 ]]; then
    for rel in "${FILES[@]}"; do cp -p "$BACKUP/$rel" "$ROOT/$rel"; done
    echo 'AM245_ROLLBACK: restored four original files; database unchanged' >&2
  fi
}
trap restore_on_error EXIT

patch --batch -p1 < "$PATCH"
python3 - <<'PY'
from pathlib import Path
p=Path('bootstrap/providers.php')
b=p.read_bytes()
a=b'    App\\Providers\\AppServiceProvider::class,'
assert b.count(a)==1 and b'Am245HostingerServiceProvider' not in b
p.write_bytes(b.replace(a, a+b'\n    App\\Providers\\Am245HostingerServiceProvider::class,',1))
PY
php -l bootstrap/providers.php >/dev/null
php -l routes/web.php >/dev/null
# Laravel 13 JSON route-list validates bootstrap and route resolution without DB writes.
php artisan route:list --json | python3 -c '
import json,sys
routes=json.load(sys.stdin)
names={r.get("name") for r in routes}
expected={"am.customer-engagement.index","am.customer-engagement.export", "am.customer-engagement.targets", "am.customer-engagement.feedback", "am.customer-engagement.reply", "am.customer-engagement.complaint", "am.feedback.invitations.open", "am.feedback.public.show", "am.feedback.public.submit"}
missing=expected-names
if missing: sys.exit("Missing AM245 routes: "+", ".join(sorted(missing)))
print("AM245_ROUTE_CHECK_OK: 9 routes registered")'
printf 'activated\n' > storage/app/am245-activation-completed.marker
trap - EXIT
echo "AM245_UI_ACTIVATED: provider, 9 routes, Job Card button and Feedback sidebar"
echo 'WHATSAPP_STILL_BLOCKED: verified customer opt-in and company scope required'
echo "BACKUP_DIRECTORY: $BACKUP"
