# Remaining Native Admin Route Map

Use the live ERP's actual middleware/permission names.

```php
Route::middleware(['auth', 'admin'])->prefix('erp/native')->group(function () {
    Route::get('/bookings-orders', ...);

    Route::get('/users', ...);
    Route::post('/users', ...);
    Route::put('/users/{user}', ...);

    Route::get('/products', ...);
    Route::post('/products', ...);
    Route::put('/products/{product}', ...);

    Route::get('/staff', ...);
    Route::post('/staff', ...);
    Route::put('/staff/{staff}', ...);

    Route::get('/job-applications', ...);
    Route::put('/job-applications/{application}', ...);

    Route::get('/staff-location', ...);

    Route::get('/service-reminders', ...);
    Route::post('/service-reminders', ...);
    Route::put('/service-reminders/{reminder}', ...);
});
```

## Navigation

Every corresponding sidebar/menu link should target the native ERP route.

Do not use Legacy pages as the normal path.

## Legacy bookmark compatibility

If an old Legacy URL must remain reachable:
- perform a plain redirect to the native ERP page;
- let the native page enforce normal ERP login/permission;
- do not create a second login/session bridge.

## Search/pagination

All list pages should support server-side pagination and search, with filters preserved in the query string.
