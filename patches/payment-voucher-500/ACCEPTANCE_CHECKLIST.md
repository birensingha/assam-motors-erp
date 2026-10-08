# Payment Voucher 500 — Acceptance Checklist

## GET create route

- [ ] Logged-in authorised Admin opens `/erp/payments/create` → HTTP 200.
- [ ] Logged-out user gets normal ERP login redirect, not HTTP 500.
- [ ] Non-authorised user gets normal permission response, not stack trace.
- [ ] Page loads if Vendor list is empty.
- [ ] Page loads if Payment Mode list is empty.
- [ ] Page does not crash because default Account is NULL.
- [ ] All Blade variables are always defined.
- [ ] View path exists.
- [ ] No raw PHP/Laravel exception is exposed.

## Server log

- [ ] Original 500 exception class/message/file/line captured before closing issue.
- [ ] Fix addresses that exact failure, not only a generic catch.
- [ ] `APP_DEBUG` remains false on public staging/production.
- [ ] Stale config/view/route cache cleared after deployment.

## POST / save

- [ ] Required validation works.
- [ ] Amount <= 0 rejected.
- [ ] Invalid account ID rejected server-side.
- [ ] Voucher number generated server-side.
- [ ] Double submit does not duplicate voucher.
- [ ] Header + accounting/ledger posting are atomic.
- [ ] Failure rolls back all writes.
- [ ] Successful voucher can be viewed/printed according to ERP workflow.
