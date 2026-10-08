# Remaining Phase-1 Native Pages — Acceptance Checklist

## Common

For all seven modules:
- [ ] Native ERP route opens for authorised Admin.
- [ ] No separate Legacy Admin login is required.
- [ ] Logged-out user gets normal ERP login flow.
- [ ] Permission denial is handled normally.
- [ ] Main navigation points to native route.
- [ ] Search/pagination works server-side.
- [ ] No auth cookie-copy/query-string bypass exists.

## Booking & Orders

- [ ] Search customer/mobile/order ID.
- [ ] Status/date filters.
- [ ] Assigned Staff visible.
- [ ] Status change audited.
- [ ] Historical order/booking not hard-deleted.

## Users

- [ ] User list/search.
- [ ] Add/Edit authorised User.
- [ ] Password never displayed.
- [ ] Password hash handled by auth framework.
- [ ] Role escalation permission protected.
- [ ] Deactivate works without deleting audit/history.

## Products

- [ ] Product/SKU/name search.
- [ ] Product Master remains distinct from Workshop Part Master.
- [ ] Publish/visibility status editable according to website rules.
- [ ] Historical Booking/Order links preserved.

## Staff

- [ ] Staff Code/Name/Mobile search.
- [ ] App Login Enabled control.
- [ ] Technician Eligibility control.
- [ ] Attendance Enabled control.
- [ ] Disabled Staff keeps historical Attendance/ROT.
- [ ] Staff API identity cannot be changed by Android request body.

## Job Applications

- [ ] Search applicant/mobile/email/position.
- [ ] Status filters.
- [ ] Admin note/status update audited.
- [ ] Resume/document access protected.

## Staff Location

- [ ] Read-only latest/history view.
- [ ] Stored capture timestamp shown.
- [ ] Stale location visibly marked.
- [ ] Location view cannot edit attendance.
- [ ] Permission protected.

## Service Reminder

- [ ] Search Customer/Mobile/Vehicle.
- [ ] Due/Overdue/Completed filters.
- [ ] Next service date/KM visible.
- [ ] Assigned Staff/contact note supported.
- [ ] Completing reminder is audited.
- [ ] New Job Card does not silently delete reminder history.
