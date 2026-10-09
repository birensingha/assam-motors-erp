# FIX27 — Purchase + Estimate Same-Page Readability

User requirement:
- no separate large window;
- Purchase and Estimate should be easier to read on the same ERP page;
- offer 100% / 115% / 125% comfortable view controls;
- default to 115%;
- print/PDF behavior must remain independent.

Scope:
- resources/views/purchases/create.blade.php
- resources/views/estimates/create.blade.php

Behavior:
- adds a compact "View" control in each page header;
- 115% is default;
- selected view is remembered in browser localStorage;
- enlarges form text, inputs/selects, line rows and spacing without changing server calculations;
- Print media resets the readability enlargement.

No DB migration. No controller/service/save logic changes.
