#!/usr/bin/env bash
set -euo pipefail

# Assam Motors targeted live source collector.
# Copies only named application source files needed for integration review.
# Never copies .env, credentials, storage logs, vendor, node_modules or binaries.

APP_ROOT="${1:-}"
OUT_BASE="${2:-/tmp}"

if [[ -z "$APP_ROOT" || ! -d "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: $0 /path/to/staging/app [/output/base]"
  exit 2
fi

STAMP="$(date +%Y%m%d_%H%M%S)"
OUT="$OUT_BASE/assam-motors-target-source-$STAMP"
ARCHIVE="$OUT_BASE/assam-motors-target-source-$STAMP.tar.gz"
mkdir -p "$OUT"

cd "$APP_ROOT"

# Exact route JSON avoids terminal-width truncation.
php artisan route:list --json > "$OUT/routes.json" 2>/dev/null || php artisan route:list > "$OUT/routes.txt" 2>&1 || true

FILES=(
  routes/web.php
  routes/api.php

  app/Http/Controllers/Web/AdminNativeModuleController.php
  app/Http/Controllers/Web/AdminVendorPageController.php
  app/Http/Controllers/Web/AdminExpensePaymentPageController.php
  app/Http/Controllers/Web/AdminJobCardPageController.php
  app/Http/Controllers/Web/AdminEstimatePageController.php
  app/Http/Controllers/Web/AdminPurchasePageController.php
  app/Http/Controllers/Web/AdminRotPageController.php
  app/Http/Controllers/Web/AdminStaffNotificationPageController.php
  app/Http/Controllers/Web/StaffPortalController.php
  app/Http/Controllers/Web/StaffWebAuthController.php

  app/Http/Controllers/Api/V1/AuthController.php
  app/Http/Controllers/Api/V1/AttendanceController.php
  app/Http/Controllers/Api/V1/PurchaseController.php
  app/Http/Controllers/Api/V1/RotWorkflowController.php
  app/Http/Controllers/Api/V1/StaffDashboardController.php
  app/Http/Controllers/Api/V1/StaffExtraController.php
  app/Http/Controllers/Api/V1/ReminderController.php
  app/Http/Controllers/Api/V1/StaffNotificationController.php
  app/Http/Controllers/Api/V1/AdminStaffComplianceController.php
  app/Http/Controllers/Api/V1/DeviceController.php

  app/Services/JobCardService.php
  app/Services/EstimateService.php
  app/Services/PurchaseService.php
  app/Services/ExpensePaymentService.php
  app/Services/RotWorkflowService.php
  app/Services/RotAssignmentService.php
  app/Services/StaffDashboardService.php
  app/Services/StaffReminderEngine.php
  app/Services/MandatoryNotificationService.php
  app/Services/FirebaseFcmService.php
  app/Services/VendorService.php

  app/Models/JobCard.php
  app/Models/JobCardPart.php
  app/Models/JobCardLabour.php
  app/Models/JobEstimate.php
  app/Models/JobEstimateItem.php
  app/Models/WorkshopPurchase.php
  app/Models/WorkshopPurchaseBatch.php
  app/Models/WorkshopPaymentVoucher.php
  app/Models/WorkshopVendor.php
  app/Models/WorkshopVendorPayment.php
  app/Models/PartMaster.php
  app/Models/LabourMaster.php
  app/Models/RotSession.php
  app/Models/RotSessionEvent.php
  app/Models/RotMechanicSegment.php
  app/Models/StaffReminderState.php
  app/Models/StaffReminderSetting.php
  app/Models/StaffDevice.php
  app/Models/NotificationOutbox.php
  app/Models/ErpAuthUser.php

  resources/views/admin/native/customers.blade.php
  resources/views/admin/native/parts.blade.php
  resources/views/admin/native/labour.blade.php
  resources/views/admin/native/vehicles.blade.php
  resources/views/admin/native/mappings.blade.php
  resources/views/job-cards/form.blade.php
  resources/views/job-cards/edit.blade.php
  resources/views/job-cards/show.blade.php
  resources/views/purchases/create.blade.php
  resources/views/purchases/edit.blade.php
  resources/views/purchases/osl-create.blade.php
  resources/views/payments/create.blade.php
  resources/views/estimates/create.blade.php
  resources/views/dashboard/rot-performance.blade.php
  resources/views/admin/staff-notifications.blade.php
  resources/views/staff/portal.blade.php

  public/js/purchase-entry.js
  public/js/purchase-edit.js
  public/js/payment-voucher.js
  public/js/estimate-entry.js

  public/legacy/api/staff-login.php
  public/legacy/api/staff-checkin.php
  public/legacy/api/staff-checkout.php
  public/legacy/api/staff-attendance.php
  public/legacy/api/staff-reminders.php
  public/legacy/api/staff-rot-v3.php
  public/legacy/api/staff-rot-performance.php
  public/legacy/api/invoices.php
  public/legacy/workshop/customers.php

  database/migrations/2026_10_05_000004_create_staff_devices_table.php
  database/migrations/2026_10_05_000005_create_notification_outbox_table.php
  database/migrations/2026_10_05_000009_add_staff_device_metadata.php
  database/migrations/2026_10_05_000010_create_staff_reminder_support_tables.php
  database/migrations/2026_10_06_000001_add_notification_delivery_tracking.php
  database/migrations/2026_10_06_000002_add_fcm_device_health_columns.php
  database/migrations/2026_10_07_000002_create_expense_payment_voucher_module.php
  database/migrations/2026_10_08_120000_create_job_card_change_audits_table.php
)

printf '%s\n' "${FILES[@]}" > "$OUT/requested-files.txt"
: > "$OUT/missing-files.txt"

for f in "${FILES[@]}"; do
  if [[ -f "$f" ]]; then
    mkdir -p "$OUT/$(dirname "$f")"
    cp -- "$f" "$OUT/$f"
  else
    echo "$f" >> "$OUT/missing-files.txt"
  fi
done

# Reject any unexpected secret-like contents before packaging.
HITS="$OUT/secret-scan.txt"
: > "$HITS"

grep -RInE \
  --exclude='secret-scan.txt' \
  -- \
  '-----BEGIN [A-Z ]*PRIVATE KEY-----|APP_KEY=base64:|DB_PASSWORD[[:space:]]*=|DB_USERNAME[[:space:]]*=|AIza[0-9A-Za-z_-]{30,}|github_pat_[0-9A-Za-z_]+|gh[pousr]_[0-9A-Za-z]+|"private_key"[[:space:]]*:' \
  "$OUT" > "$HITS" 2>/dev/null || true

if [[ -s "$HITS" ]]; then
  echo "REFUSING TO CREATE ARCHIVE: possible secret material found."
  echo "Inspect locally: $HITS"
  exit 3
fi

rm -f "$HITS"

tar -C "$OUT_BASE" -czf "$ARCHIVE" "$(basename "$OUT")"
sha256sum "$ARCHIVE" > "$ARCHIVE.sha256" 2>/dev/null || true

echo "TARGET SOURCE HANDOFF READY"
echo "Archive: $ARCHIVE"
[[ -f "$ARCHIVE.sha256" ]] && echo "SHA256:  $ARCHIVE.sha256"
echo "Upload only the .tar.gz archive."
