# Parts Purchase — Part Search & Job Card / Vehicle Allocation Fix

This package addresses two reported Parts Purchase problems:

1. Typing a Part Name/Code does not return matching Part Master items.
2. Purchase Against → Job Card / Vehicle search shows "No Job Card/Vehicle Found" even when a valid Job Card/vehicle exists.

## Target behaviour

### Part search
- Search by Part Code, Part Name, Description and alternate/OEM number when available.
- Start searching from 2 typed characters.
- Debounce requests by about 250 ms.
- Return up to 20 useful matches.
- Display code + part name + current unit/rate context.
- Keyboard Up/Down + Enter selection.
- Each purchase line has its own independent search state.

### Job Card / Vehicle allocation search
- Search by:
  - Job Card No.
  - Vehicle Registration No.
  - Customer name/mobile when available.
- Normalize spaces, dashes and case for Job Card / vehicle search.
- Search the authoritative Job Card table first, then LEFT JOIN vehicle/customer details.
- Do not require a vehicle join to succeed before returning the Job Card.
- By default return open/editable workshop Job Cards.
- If no open match exists but a closed match exists, return it as non-selectable context instead of incorrectly saying the record never existed.

## Important

The live ERP PHP/Laravel source and exact production table/column names are not present in this repository. The examples in this package are integration references and must be adapted to the live schema rather than executed blindly.
