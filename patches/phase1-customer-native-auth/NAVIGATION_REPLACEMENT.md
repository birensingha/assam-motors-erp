# Navigation replacement

Replace links like:

```html
<a href="/legacy/workshop/customers.php">Customers</a>
```

with the native route:

Laravel Blade:

```blade
<a href="{{ route('erp.native.customers') }}">Customers</a>
```

or, when route helpers are unavailable:

```html
<a href="/erp/native/customers">Customers</a>
```

Do **not** add a separate Legacy-login button as the primary Customer Master path.
