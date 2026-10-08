# Invoice View / Print Rules

## View Invoice

View route must display the persisted Invoice snapshot.

Do not re-read current Part/Labour master rates to rebuild historical invoice values.

Recommended sections:

1. Assam Motors header
2. Invoice No. / Date
3. Customer details
4. Vehicle details
5. Job Card reference
6. Parts table
7. Labour table
8. Tax / totals summary
9. Narration / terms if required
10. Authorised signature area

## Parts first, Labour second

Keep the same Workshop reading order:

### Parts

| Code | Description | Qty | Rate | Discount | Tax | Amount |

### Labour

| Code | Description | Qty | Rate | Tax | Amount |

## Print

Recommended route:

`GET /erp/invoices/{invoice}/print`

Print output should:
- use invoice snapshot;
- be A4-friendly;
- avoid navigation/sidebar;
- repeat table header when browser print supports it;
- show Invoice No. prominently;
- show Job Card No.;
- show vehicle registration;
- show Grand Total;
- include tax breakup required by the live accounting/GST design;
- print consistently in browser/PDF.

## No image conversion as accounting source

If a downloadable image preview is later provided, it is a convenience format only.

The authoritative invoice remains the ERP Invoice record / printable document.

## Historical integrity

After invoice creation:
- changing Part Master name/rate must not change old invoice;
- changing Labour Master rate must not change old invoice;
- changing Customer address may update Customer Master but must not silently rewrite the historical invoice snapshot;
- reopening/adjusting an invoiced transaction requires an explicit authorised accounting workflow, not normal Job Card Edit.
