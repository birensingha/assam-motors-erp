# Assam Motors WEB-ERP — Pending Work Register

## Phase 1 — Native Staging Navigation / Authentication
- [ ] Booking & Orders native staging page
- [ ] Users native staging page
- [ ] Products native staging page
- [ ] Staff native staging page
- [ ] Job Applications native staging page
- [ ] Staff Location native staging page
- [ ] Service Reminder native staging page
- [ ] Customer / Customer Master native staging page
- [ ] Vendor Master correct native route
- [ ] Part Master native staging page
- [ ] Labour Master native staging page
- [ ] Vehicle Master native staging page
- [ ] Labour / Part Mapping native staging page
- [x] Attendance native staging route already restored

## Phase 2 — Payment Voucher
- [ ] Fix /erp/payments/create HTTP 500

## Phase 3 — Job Card
- [ ] Create Job Card
- [ ] Edit Job Card
- [ ] Convert to Invoice
- [ ] View Invoice
- [ ] Print Invoice

## Phase 4 — OSL Purchase
- [ ] OSL purchase entry UI like multi-line Parts Purchase

## Phase 5 — ROT backend
- [ ] Asia/Kolkata timestamps for ROT actions/events
- [ ] Persist Android Pause reason/note in ERP ROT history
- [ ] Full ROT event/audit history

## Phase 6 — ROT Performance
- [ ] Waiting / Assigned ROT list
- [ ] Performance filters
- [ ] Technician Performance summary
- [ ] Detailed ROT History

## Phase 7 — Android ROT live progress
- [ ] Elapsed time
- [ ] Standard time
- [ ] Balance / percentage used
- [ ] Over-standard warning

## Phase 8-11 — Staff Alerts / Compliance
- [ ] Server-global alert identity per staff + ROT + alert type
- [ ] Three reminders then Admin escalation
- [ ] Alerts persist across Check-Out / later Check-In until business action resolves
- [ ] Merge Legacy Reminder Control + Staging Notification Compliance
- [ ] Admin notes, under-review, resolution audit
- [ ] Excel Staff Summary + Alert Detail export
- [ ] FCM push primary, Android polling fallback

## Phase 12 — Final Android synchronization
- [ ] Final API sync and signed production APK

## Parts Purchase — Search / Allocation
- [ ] Part Code / Part Name autocomplete must return Part Master matches
- [ ] Multi-line rows must keep independent autocomplete state
- [ ] Purchase Against search must find Job Card No. and Vehicle Registration
- [ ] Normalize spaces / dashes / case for Job Card and Vehicle search
- [ ] Valid Job Card must not disappear because of missing optional Vehicle/Customer relation
- [ ] Server must re-validate selected Job Card eligibility at Save time
