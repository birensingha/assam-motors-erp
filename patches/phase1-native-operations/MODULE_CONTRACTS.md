# Module Contracts

## 1. Booking & Orders

Purpose:
central Admin list for website/showroom bookings and customer orders.

Recommended columns:
- Booking/Order ID
- Customer
- Mobile
- Product/Vehicle
- Source
- Booking Date
- Preferred Date/Time where applicable
- Status
- Assigned Staff
- Last Updated

Search/filter:
- Booking/Order ID
- Customer/Mobile
- Product/Vehicle
- Date range
- Status
- Assigned Staff

Rules:
- status changes audited;
- cancellation requires reason where business policy requires it;
- do not hard-delete historical bookings/orders.

## 2. Users

Purpose:
ERP login identity/permissions.

Recommended fields:
- Name
- Username/Email
- Role
- Active/Inactive
- Last Login
- Created At

Rules:
- password never displayed;
- password reset/change uses secure hashing through existing auth framework;
- role escalation requires authorised Admin;
- user cannot accidentally remove the last Super/Admin account if the live ERP relies on one;
- prefer deactivate over delete.

## 3. Products

Purpose:
website/catalog/product master, distinct from Workshop Part Master.

Recommended fields:
- Product Code/SKU
- Product Name
- Category
- Brand
- Model/Variant if relevant
- Price/display price according to current website
- Active
- Publish/Visibility
- Sort Order

Rules:
- Product Master must not be confused with Part Master;
- website visibility and stock/workshop inventory are separate concerns unless live schema explicitly links them;
- historical booking/order references preserved.

## 4. Staff

Purpose:
employee/technician master used by Staff App, Attendance and ROT assignment.

Recommended fields:
- Staff Code
- Name
- Mobile
- Role/Designation
- Department
- Active
- App Login Enabled
- Technician Eligible
- Attendance Enabled
- Joined Date

Rules:
- Staff identity used by Android APIs must be server authoritative;
- disabling Staff login must not delete historical Attendance/ROT data;
- Staff Code unique when the live ERP uses one;
- Admin User account and Staff record may be linked but are not automatically the same record.

## 5. Job Applications

Purpose:
Admin review of recruitment/job-application submissions.

Recommended columns:
- Application ID
- Applicant Name
- Mobile/Email
- Position
- Applied Date
- Status
- Resume/document link if stored
- Admin Note

Status example:
- NEW
- REVIEWED
- SHORTLISTED
- INTERVIEW
- REJECTED
- HIRED

Rules:
- status changes audited;
- applicant data protected by Admin permission;
- document URLs must not be publicly enumerable.

## 6. Staff Location

Purpose:
latest Staff location / location history used by operational Admin.

Recommended columns:
- Staff
- Last Location Time
- Latitude/Longitude or map link according to current privacy design
- Accuracy
- Source
- Check-In state
- Staleness/age

Filters:
- Staff
- Active/Inactive
- Date/time
- Checked-In only
- stale/current

Rules:
- read-only operational view by default;
- must not fake Check-In/Check-Out;
- preserve existing location retention/privacy policy;
- latest point should show captured timestamp, not page-load time;
- stale location clearly marked.

## 7. Service Reminder

Purpose:
schedule/follow up future customer vehicle service.

Recommended fields:
- Customer
- Vehicle
- Last Job Card / Service Date
- Next Service Date
- Next KM
- Reminder Status
- Contact Status
- Assigned Staff
- Note
- Last Reminder Sent/Called

Search/filter:
- Customer/Mobile
- Vehicle
- Due date range
- Due/Overdue/Completed
- Assigned Staff

Rules:
- Customer/Vehicle references validated server-side;
- completion/closure audited;
- reminder is not automatically deleted when a new Job Card is created;
- new service may resolve/supersede the prior reminder according to explicit business rules;
- historical reminder/contact activity retained.
