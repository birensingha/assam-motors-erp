# Payment Voucher Contract

## Create screen

Route:
`GET /erp/payments/create`

Must:
- require normal Admin authentication/permission;
- return HTTP 200;
- render even when optional lookup collections are empty;
- not assume a default account exists;
- use Asia/Kolkata business date for the initial voucher date;
- never display raw exception/stack trace.

## Required form fields

- Voucher Date
- Pay From Account
- Payee Type
- Payment Mode
- Amount

Optional:
- Vendor/Payee
- Reference No.
- Narration

Adapt field names to the existing accounting design.

## Save

Route:
`POST /erp/payments`

Server must:
- validate account exists and is eligible;
- validate payee according to payee type;
- validate amount > 0;
- generate voucher number server-side;
- save header + ledger impact in one DB transaction;
- write audit;
- prevent duplicate submission according to existing ERP pattern.

## Accounting integrity

Do not implement a Payment Voucher as a UI-only record if the ERP accounting module expects ledger entries.

Before enabling POST, identify:
- voucher header table/model;
- voucher sequence/numbering rule;
- cash/bank/ledger posting tables;
- debit/credit convention;
- fiscal-year handling;
- reversal/edit policy.

The GET create-page 500 can be fixed independently first.
