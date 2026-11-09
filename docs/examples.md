# Laravel Demo Mode: integration examples

These examples assume the package is installed, the config and assets are published, and `DEMO_MODE_ENABLED=true`. Replace the sample route names and account IDs with your own. The package has no dependency on an admin panel or a JavaScript framework.

## Shared Blade layout

Add the helpers to the layout used by your storefront and dashboards. Keep your original layout, authentication links, and application styles.

```blade
{{-- resources/views/layouts/site.blade.php --}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Portfolio Store')</title>
    {{ demo_mode_assets() }}
</head>
<body>
    {{ demo_mode_notice('site-notice') }}
    <main>
        @yield('content')
    </main>
</body>
</html>
```

`demo_mode_notice()` may also sit inside a dashboard header. If two layouts are nested, call it only once so that flash feedback is not rendered twice. `demo_mode_assets()` only adds the demo CSS and JS; it does not intercept form submissions.

## Storefront and admin login pages

Seed a customer and three demo staff accounts. Put roles and role assignment in your application's seeder. In this example the staff share the `admin` guard; their roles determine which dashboard sections each can view.

```php
// config/demo-mode.php
'accounts' => [
    'customer' => ['guard' => 'web', 'id' => 101, 'label' => 'Customer', 'redirect' => '/account/orders'],
    'admin' => ['guard' => 'admin', 'id' => 201, 'label' => 'Store admin', 'redirect' => '/admin'],
    'super-admin' => ['guard' => 'admin', 'id' => 202, 'label' => 'Super admin', 'redirect' => '/admin'],
    'accountant' => ['guard' => 'admin', 'id' => 203, 'label' => 'Accountant', 'redirect' => '/admin/reports'],
],
'allow' => [
    'logout' => ['POST'],
    'admin.logout' => ['POST'],
],
```

On the storefront, leave your normal sign-in form unchanged and add the demo account picker after it:

```blade
@extends('layouts.site')

@section('content')
    <h1>Sign in</h1>
    <form action="{{ route('login') }}" method="POST">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" required>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button type="submit">Sign in</button>
    </form>

    {{ demo_mode_accounts('web') }}
@endsection
```

The example assumes `route('login')` generates the URL your password login handler accepts. Normal sign-in POST requests are blocked in demo mode by default; the dedicated demo buttons use the package's permitted sign-in endpoint. Leave password sign-in blocked on public demos unless you have reviewed the login handler and want to support it.

In your existing admin sign-in template, put the following **outside** its own form:

```blade
{{ demo_mode_accounts('admin', 'admin-demo-accounts') }}
```

Use `demo_mode_accounts()` without a guard on a combined demo sign-in page. Allow each existing logout route by exact name only after confirming it invalidates the session.

## Store management forms stay visible

Use the same Blade and routes in the demo and the regular application:

```php
// routes/web.php
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:admin')->prefix('admin')->name('admin.')->group(function () {
    Route::resource('products', ProductController::class);
});
```

```blade
@extends('layouts.site')

@section('content')
    <h1>Edit product</h1>

    <form action="{{ route('admin.products.update', $product) }}" method="POST">
        @csrf
        @method('PATCH')
        <label for="name">Product name</label>
        <input id="name" name="name" value="{{ old('name', $product->name) }}" required>
        <button type="submit">Save product</button>
    </form>

    <form action="{{ route('admin.products.destroy', $product) }}" method="POST">
        @csrf
        @method('DELETE')
        <button type="submit">Delete product</button>
    </form>
@endsection
```

With `DEMO_MODE_MODE=both`, the user can open the edit page and click either button. The request is blocked before the resource controller updates or deletes anything. The browser returns to the edit page, the buttons still render, and the layout shows the denial as a toast. Under `banner` or `badge` the message appears in the notice; `silent` renders a plain inline message, and `page` returns a standalone 403. The same pattern applies to checkout, refunds, inventory adjustments, and uploads.

If a legacy named GET endpoint dispatches an export job or changes order state, add its name to `block`. Reserve `allow` for methods and route names you have reviewed.

## JSON checkout feedback

In a same-origin JavaScript client, use the existing checkout endpoint and include the session and CSRF token expected by your application. Send a JSON accept header and display `message` as text:

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
<p id="checkout-feedback" role="status"></p>
```

```js
async function submitCheckout(payload) {
    const response = await fetch('/checkout', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (response.status === 403 && data.code === 'DEMO_MODE_READ_ONLY') {
        document.querySelector('#checkout-feedback').textContent = data.message;
        return;
    }

    if (response.status === 403 && data.code === 'DEMO_SESSION_EXPIRED') {
        window.location.assign('/login');
        return;
    }

    // Continue with your application's usual success and error handling.
}
```

On protected API and JSON routes, changing the display mode does not change the HTTP status or code. If your app uses Vue, React, or Inertia, call your own toast or dialog after receiving the response. Never insert the response message as raw HTML.

## Add an application translation

Publish the English template:

```bash
php artisan vendor:publish --tag=demo-mode-lang
```

Use the destination printed by Artisan. In an app with the standard `lang` directory, copy `lang/en/demo-mode.php` to `lang/es/demo-mode.php` and translate the values. A partial translation can contain just these keys.

```php
<?php

return [
    'accounts_title' => 'Prueba una cuenta de demostración',
    'blocked' => 'Esta acción está desactivada en el modo de demostración.',
];
```

With `DEMO_MODE_LOCALE=auto`, the Blade UI and JSON response use Spanish when the host application calls `app()->setLocale('es')` before demo protection runs. Set `DEMO_MODE_LOCALE=es` to show the demo in Spanish independently of the site's language switch. Missing keys use Laravel's fallback locale and then English. For a right-to-left language, add `'direction' => 'rtl'`. To translate an account button using the same file, add a key such as `catalog_manager` and set `'label_key' => 'demo-mode.catalog_manager'` for that account.

## Check a custom admin route group

If a plugin registers routes outside the `web` or `api` groups, add `demo.read-only` to the plugin's existing middleware stack. For example:

```php
Route::middleware(['web', 'auth:admin', 'demo.read-only'])
    ->prefix('warehouse')
    ->group(base_path('routes/warehouse.php'));
```

Run `php artisan demo-mode:audit --all`, then try one real save, delete, and checkout request against the isolated demo. Check that the original data is unchanged and that feedback appears. For a shared POST transport such as Livewire or GraphQL, see the [integration notes](../README.md#livewire-filament-graphql-and-custom-protocols).
