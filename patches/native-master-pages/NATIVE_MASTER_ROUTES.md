# Native Master Route Contract

Use existing Admin middleware names from the live ERP.

```php
Route::middleware(['auth', 'admin'])->prefix('erp/native')->group(function () {
    Route::get('/vendors', ...);
    Route::post('/vendors', ...);
    Route::put('/vendors/{vendor}', ...);

    Route::get('/parts', ...);
    Route::post('/parts', ...);
    Route::put('/parts/{part}', ...);

    Route::get('/labours', ...);
    Route::post('/labours', ...);
    Route::put('/labours/{labour}', ...);

    Route::get('/vehicles', ...);
    Route::post('/vehicles', ...);
    Route::put('/vehicles/{vehicle}', ...);

    Route::get('/labour-part-mapping', ...);
    Route::post('/labour-part-mapping', ...);
    Route::delete('/labour-part-mapping/{mapping}', ...);
});
```

## Navigation

ERP sidebar/menu should link to these native routes.

Primary menu must not send a logged-in ERP Admin to:
- `/legacy/.../vendors.php`
- `/legacy/.../parts.php`
- `/legacy/.../labour.php`
- `/legacy/.../vehicles.php`
- Legacy Labour/Part Mapping pages

## Legacy compatibility

If old bookmarks must remain valid:
- old Legacy URL may redirect to the canonical native route;
- the native ERP route performs the actual authentication/authorization;
- do not authenticate inside the redirect shim.

## Search

Each master list should have server-side search/pagination.

Suggested search:

### Vendor
- Vendor Code
- Vendor Name
- Mobile
- GSTIN

### Part
- Part Code
- Part Name
- Description
- OEM/Alternate No.

### Labour
- Labour Code
- Labour Description
- ROT/Standard time keywords where applicable

### Vehicle
- Registration No.
- Customer
- Brand
- Model
- Chassis/VIN
- Engine No.

### Labour/Part Mapping
- Labour Code/Description
- Part Code/Name
