# Laravel Demo Mode

**One-click demo sign-in and server-side read-only protection for Laravel applications.**

Let visitors explore a portfolio site, store, or admin dashboard using dedicated demo accounts. Existing guards and permissions still decide what visitors can see. Forms and buttons remain usable, and the server rejects modifying requests on protected routes with feedback for the visitor.

- Configure multiple demo accounts across Laravel session guards.
- Protect web and API routes with named exceptions and a route audit command.
- Choose a banner, toast, badge, inline message, or a dedicated denial page.
- Place and style Blade helpers inside your own layout, or replace the views entirely.
- Translate messages and account labels, including right-to-left layouts.

[Quick start](#quick-start) · [Demo accounts](#demo-accounts) · [Blade helpers](#blade-helpers) · [Display modes](#display-modes) · [Route protection](#route-protection) · [Customization](#customization) · [Deployment](#deployment) · [Configuration](#configuration-reference) · [Troubleshooting](#troubleshooting)

## Requirements

Demo login requires a working Laravel session and cache. The package is disabled by default and needs no package migrations, frontend build, or external service.

| Laravel | Minimum PHP |
| --- | --- |
| 10 | 8.1 |
| 11 and 12 | 8.2 |
| 13 | 8.3 |

Compatibility with Laravel 10 and 11 is for existing projects. Their framework releases are outside Laravel's security support window. For a publicly accessible demo, use a release that still receives security fixes and keep Composer's security checks enabled.

## Quick start

Install the package with Composer from your Laravel application directory.

```bash
composer require mahmudabbas0/laravel-demo-mode
```

Publish the configuration and the stylesheet and script used by the Blade helpers.

```bash
php artisan vendor:publish --tag=demo-mode-config
php artisan vendor:publish --tag=demo-mode-assets
```

Package discovery registers the provider automatically. If you disable discovery, add `PortfolioLabs\DemoMode\DemoModeServiceProvider::class` to the `providers` list in `config/app.php` on Laravel 10, or to `bootstrap/providers.php` on Laravel 11 and later.

Create a dedicated demo user with the appropriate application role. Add it to the `accounts` array in `config/demo-mode.php` and replace `101` with its authentication ID.

```php
'accounts' => [
    'preview' => [
        'guard' => 'web',
        'id' => 101,
        'label' => 'Explore the demo',
        'redirect' => '/dashboard',
    ],
],
```

Add the helpers to your Blade layout and sign-in page. Keep the account picker outside any existing form.

```blade
{{-- In your layout's <head> --}}
{{ demo_mode_assets() }}

{{-- Where the notice should appear --}}
{{ demo_mode_notice() }}

{{-- In your sign-in page, outside the existing form --}}
{{ demo_mode_accounts() }}
```

Set these values in the demo deployment's `.env`.

```dotenv
DEMO_MODE_ENABLED=true
DEMO_MODE_MODE=both
```

If the application caches its configuration, rebuild it with `php artisan config:cache` and reload long-running workers. Run `php artisan demo-mode:audit` to inspect route coverage. Then sign in using the demo button and try submitting a form on a protected route.

If the app uses a named logout route, allow its exact method as shown under [route protection](#route-protection).

## Demo accounts

Create users with your application's seeder, assign the roles your application already uses, then add each seeded account to `config/demo-mode.php`:

```php
'accounts' => [
    'customer' => [
        'guard' => 'web',
        'id' => 101,
        'label' => 'Customer',
        'redirect' => '/account/orders',
    ],
    'store-admin' => [
        'guard' => 'admin',
        'id' => 201,
        'label' => 'Store administrator',
        'label_key' => 'demo-accounts.store_admin',
        'description_key' => 'demo-accounts.store_admin_description',
        'redirect' => '/admin',
    ],
    'super-admin' => [
        'guard' => 'admin',
        'id' => 202,
        'label' => 'Super administrator',
        'redirect' => '/admin',
    ],
    'accountant' => [
        'guard' => 'admin',
        'id' => '1ede8f33-cc90-4bba-9462-014f5a5543b4',
        'label' => 'Accountant',
        'redirect' => '/admin/reports',
    ],
],
```

Replace the example IDs and paths with real values from your **demo database**. If all roles use `web`, set every account's guard to `web`. The package loads the user through the configured guard's provider; it does not assign a role, pick a tenant, or copy a production account.

| Field | Meaning |
| --- | --- |
| Array key | Public slug used in the form action. Starts with a lowercase letter, then lowercase letters, numbers, underscores, or hyphens; maximum 64 characters. |
| `guard` | Name from `config/auth.php`, default `web`. One-click login supports Laravel `SessionGuard` instances. |
| `id` | Required stable integer or string authentication identifier. UUIDs are supported. |
| `label` | Button label. Defaults to the public slug converted to a title. |
| `label_key` | Optional application translation key. When set, it replaces `label`. |
| `description_key` | Optional application translation key for text beneath the button. |
| `redirect` | Fixed root-relative page to open after login. Defaults to `/`. Request input cannot choose the destination. |

The package registers `POST /_demo/login/{account}` as `demo-mode.login`. The form submits the public slug and Laravel's CSRF token. The endpoint ignores request-supplied account IDs and guard names. It requires the `web` session stack, rate limits attempts by IP, rotates the session ID on login, and does not issue a remember-me cookie. It rejects a new demo login while a standard session guard is already signed in. The user should sign out first.

This is a direct session login for public demo identities. It does not go through your password login, SSO, or MFA screens. Downstream authorization and middleware such as `verified` still apply. Seed demo accounts that can pass the checks on their destination pages. Never configure a real account, a production tenant, or an identity with live service credentials.

### Optional tenant or account eligibility rule

Implement `PortfolioLabs\DemoMode\Contracts\DemoLoginPolicy` and bind it in an app service provider's `register()` method:

```php
use App\Security\PortfolioDemoLoginPolicy;
use PortfolioLabs\DemoMode\Contracts\DemoLoginPolicy;

$this->app->bind(DemoLoginPolicy::class, PortfolioDemoLoginPolicy::class);
```

```php
public function allows(
    \Illuminate\Http\Request $request,
    \PortfolioLabs\DemoMode\DemoAccount $account,
    \Illuminate\Contracts\Auth\Authenticatable $user,
): bool {
    return $request->getHost() === 'demo.example.test';
}
```

Use this hook to check the resolved tenant, confirm that the user is explicitly marked as a demo identity, or reject disabled users. A denied login returns 404. Set up the app's trusted host and proxy configuration as appropriate before using host-based eligibility.

## Blade helpers

In the Blade sign-in page, put the account picker **after** the existing login form. The helper renders its own POST forms:

```blade
{{ demo_mode_accounts() }}
```

On a separate admin sign-in page, show only accounts on the corresponding guard:

```blade
{{ demo_mode_accounts('admin') }}
```

In your layout, add assets inside `<head>` and the notice near the top of `<body>`:

```blade
{{-- In <head> --}}
{{ demo_mode_assets() }}

{{-- Near the top of <body> --}}
{{ demo_mode_notice('my-application-notice') }}
```

| Helper | Arguments |
| --- | --- |
| `demo_mode_assets()` | Loads the published stylesheet and toast script. Include it in the layout head. |
| `demo_mode_notice($class = null)` | Renders the configured notice. Pass an optional CSS class for layout or styling. |
| `demo_mode_accounts($guard = null, $class = null)` | Renders sign-in forms for every configured account or for one guard. The second argument adds a CSS class. |

These helpers return Laravel `HtmlString` values, so normal `{{ ... }}` Blade syntax renders the markup while the templates still escape dynamic values. They render nothing while demo mode is off. Publish the assets for the included styling, dismiss button, and toast timer. Ensure that the pages displaying login forms and flash messages are not publicly cached.

Keep the application's normal create, save, delete, and checkout controls in place. Demo Mode does not disable, hide, or rewrite application markup. The visitor clicks a normal control, and the server blocks the request before the controller runs. Put `demo_mode_notice()` on the page that receives the redirect so the visitor sees the message.

## Display modes

Choose a mode with `DEMO_MODE_MODE` in the demo application's environment.

| Mode | Visitor experience |
| --- | --- |
| `banner` | Persistent banner; a blocked action adds a temporary message inside it. |
| `toast` | Session flash displayed as a dismissible toast after a blocked web form submission. |
| `both` | Banner plus toast. Default. |
| `badge` | Compact read-only badge with a lock icon. Blocked actions add a message next to it, wrapping onto a new line when space is limited. |
| `page` | Dedicated localized HTTP 403 page for blocked browser requests. |
| `silent` | No persistent notice. A blocked action shows a plain inline message. |

All modes apply the same request rules. The default `html_response = 'redirect'` sends a 303 after a blocked form submission, using a validated same-origin referrer or `return_to`. A route explicitly listed in `block` gets a 403 page so that a blocked GET cannot cause a redirect loop. Set `html_response = 'page'` for browser 403s in any mode. Inline messages and toasts require a redirect and a Blade page that reads the flash.

See [integration examples](docs/examples.md) for a full Blade layout, separate storefront and admin logins, product forms that stay clickable, JSON checkout feedback, and adding a language.

## Route protection

By default, the package attaches `demo.read-only` to the `web` and `api` route middleware groups. It applies to guests and signed-in users, including administrators. Add the alias to any other route that needs protection:

```php
Route::middleware(['web', 'auth:admin', 'demo.read-only'])
    ->prefix('back-office')
    ->group(base_path('routes/back-office.php'));
```

To protect a custom middleware group globally, include its name in `middleware_groups`. To opt into manual coverage only, use `'middleware_groups' => []` and add `demo.read-only` to each appropriate group. Always include it on every route that accepts the demo session, especially if you plan to disable demo mode on the same deployment.

Requests with effective or original methods other than `GET`, `HEAD`, and `OPTIONS` are denied unless they match an exception. Explicit block rules take precedence over allow rules. The package's own demo sign-in endpoint is a dedicated exception.

```php
'allow' => [
    'logout' => ['POST'],
    'admin.logout' => ['POST'],
],

'block' => [
    'admin.exports.*',
    'admin.orders.mark-paid-via-link',
    'store.cart.add-via-link',
],
```

Use the exact **route name** and uppercase HTTP methods in `allow`. A method override does not qualify as a direct-method exception. Allow logout only after confirming the route invalidates the session. Prefer rewriting any side-effecting GET route as POST; use `block` when supporting a legacy endpoint. It accepts Laravel wildcard patterns against route names and denies every method.

Audit the registered routes before shipping each demo:

```bash
php artisan route:list -vv
php artisan demo-mode:audit
php artisan demo-mode:audit --all
```

The audit lists the middleware coverage and intended decision per route and method. It exits with status 1 for uncovered potentially modifying methods and uncovered routes explicitly listed in `block`. `--all` also lists ordinary browsing routes. The command does not inspect what a controller, middleware, or listener does. Verify each exception and check that browsing endpoints are free of business side effects.

### JSON clients and SPAs

Send `Accept: application/json` on fetch and Axios requests to get a stable response even when a route uses `web`. Stateless requests without a session also get JSON. Handle the `code` field in the client rather than matching localized text:

```json
{
    "code": "DEMO_MODE_READ_ONLY",
    "message": "This action is disabled in demo mode."
}
```

The status is `403 Forbidden`. Handle `DEMO_SESSION_EXPIRED` by clearing local UI state and offering the sign-in page. For React, Vue, or Inertia, build a notice using your own UI and expose `app(\PortfolioLabs\DemoMode\DemoMode::class)->enabled()` as a server-side page prop. The demo login form still needs to submit to the session-backed POST route with Laravel's CSRF protection. The package does not issue Sanctum, Passport, or other API tokens.

### Livewire, Filament, GraphQL, and custom protocols

Some frameworks send reads and writes to the same POST endpoint. Those requests are blocked by default, including read-only interactions on that endpoint. Keep browsing usable by serving it through GET pages, or expose dedicated read-only routes with no application writes. Do not blanket-allow a shared Livewire update or GraphQL endpoint. If you choose to permit a shared endpoint, the host application must enforce the demo restriction inside the transport, authorize every operation on the server, and test that write operations cannot execute. A disabled button or hidden form is only a visual cue.

## Customization

Pass a class to each helper and define the style in your application's stylesheet. Load it after the package styles.

```blade
{{ demo_mode_notice('dashboard-demo-notice') }}
{{ demo_mode_accounts('admin', 'dashboard-demo-accounts') }}
```

```css
.dashboard-demo-notice,
.dashboard-demo-accounts {
    --demo-mode-surface: #f0fdf4;
    --demo-mode-text: #14532d;
    --demo-mode-border: #bbf7d0;
    --demo-mode-radius: .5rem;
}
```

For a different layout, publish the Blade views and edit the copies in your application.

```bash
php artisan vendor:publish --tag=demo-mode-views
```

### Add languages to your application

Put a `demo-mode.php` file inside each existing locale directory in your app. For a site that supports English and Arabic, use `lang/en/demo-mode.php` and `lang/ar/demo-mode.php`. Use your app's configured language path if it differs from `lang`. Laravel loads the file alongside your other translations.

To start from the full English template, publish it to your app's English locale directory.

```bash
php artisan vendor:publish --tag=demo-mode-lang
```

Copy the template into each locale directory and translate the values. You can also create a partial file directly, such as `lang/ar/demo-mode.php`.

```php
<?php

return [
    'direction' => 'rtl',
    'title' => 'الوضع التجريبي',
    'badge' => 'نسخة تجريبية للقراءة فقط',
    'accounts_title' => 'جرّب حسابًا تجريبيًا',
    'blocked' => 'هذا الإجراء معطّل في الوضع التجريبي.',
    'staff' => 'جرّب كموظف',
];
```

An account can use `'label_key' => 'demo-mode.staff'` and an optional `description_key`. You can also point those settings to a key in any of the application's other translation files. Both use the selected demo locale.

### Choose how the locale is selected

| Setting | Behavior |
| --- | --- |
| `DEMO_MODE_LOCALE=auto` | Default. Follow Laravel's current locale on each request. A site's language switch applies when its middleware calls `app()->setLocale($locale)` before demo protection and rendering. |
| `DEMO_MODE_LOCALE=ar` | Always use Arabic for demo messages and translated account labels. The rest of the application keeps its own locale. |
| `'locale' => 'ar'` in `config/demo-mode.php` | Set a fixed locale in PHP configuration. This replaces the environment-driven value if you remove the default `env(...)` expression. |

The default config entry is `'locale' => env('DEMO_MODE_LOCALE', 'auto')`. Add that entry to an existing published config if you want the setting to be visible there. If the app caches config, rebuild it after changing the environment value.

For a site that stores its language preference in the session, call the following in your existing locale middleware before calling `$next($request)`. Run that middleware after the session starts and before demo protection. Use your application's supported locale list and preference key.

```php
$locale = $request->session()->get('locale', config('app.locale'));

if (in_array($locale, ['en', 'ar'], true)) {
    app()->setLocale($locale);
}
```

For each message, the package checks the chosen locale, Laravel's configured fallback locale, then English. In each locale, `lang/<locale>/demo-mode.php` takes precedence over the older `lang/vendor/demo-mode/<locale>/messages.php` format. Existing files in the older format still work.

### Customize the presentation

- **Change markup:** edit `resources/views/vendor/demo-mode/ui/accounts.blade.php`, `ui/notice.blade.php`, or the standalone `resources/views/vendor/demo-mode/blocked.blade.php`. Keep the login action, POST method, and `@csrf` in any replacement form.
- **Change colors or placement:** load app CSS after the package assets and override `--demo-mode-surface`, `--demo-mode-text`, `--demo-mode-border`, `--demo-mode-radius`, and `--demo-mode-toast-z`. You can also replace the published assets or omit `demo_mode_assets()` and use your own.
- **Set a toast timeout:** the default of `6500` hides the toast after 6.5 seconds, with the timer paused during hover or keyboard focus. Set `'toast_duration_ms' => 0` to keep it available until dismissal or navigation. Positive settings below 5000 are raised to 5000.

## Deployment

Run the demo as its own deployment with synthetic data, a separate database, separate sessions and cache, and sandboxed or disabled email, payment, storage, and fulfillment integrations. For shops, audit checkout, inventory, refund, notification, import, export, and webhook endpoints. Check that previews, signed URLs, and authorization rules cannot reveal real data. An admin role in a demo can still read everything the application's role permits.

This package stops requests that reach its Laravel route middleware. Commands, scheduled jobs, queued work, earlier middleware, login listeners, custom servers, and direct access to storage or the database require their own safeguards. Sessions, cache entries, and logging can still change. Check listeners for Laravel login and logout events, and keep writes such as last-login tracking within the isolated demo environment. Use a shared rate limiter on multi-instance demos.

Demo sessions expire after `login.session_minutes` (120 by default). A marked session is invalidated on its next covered web request when expired, when the configured account is removed or changes guard/ID, or when demo mode is turned off. If you repurpose a demo environment, revoke its sessions and credentials before enabling normal write traffic. Keep the package installed and the middleware attached during the transition.

## Configuration reference

These keys live in `config/demo-mode.php`.

| Configuration | Default | Purpose |
| --- | --- | --- |
| `enabled` / `DEMO_MODE_ENABLED` | `false` | Enable demo sign-in and write protection. |
| `mode` / `DEMO_MODE_MODE` | `both` | Select how blocked actions are shown. |
| `locale` / `DEMO_MODE_LOCALE` | `auto` | Follow the current site language or set a fixed locale for demo UI and responses. |
| `middleware_groups` | `['web', 'api']` | Attach protection to these route middleware groups. |
| `allow` | `[]` | Allow specific HTTP methods on named routes. |
| `block` | `[]` | Block all methods on matching route names. |
| `accounts` | `[]` | Map public demo slugs to accounts and session guards. |
| `html_response` | `redirect` | Redirect eligible browser submissions with flash feedback, or use `page` for a 403 response. |
| `return_to` | `/` | Root-relative fallback for blocked requests and the denial page's return link. |
| `toast_duration_ms` | `6500` | Toast timeout in milliseconds. Use `0` for manual dismissal. |
| `login.attempts_per_minute` | `10` per IP | Rate limit on the one-click sign-in endpoint. |
| `login.session_minutes` | `120` | Maximum lifetime of a demo sign-in. |

## Troubleshooting

| Symptom | Check |
| --- | --- |
| No demo account buttons | Enable demo mode, rebuild cached config, verify `accounts` and the guard passed to `demo_mode_accounts()`. |
| Login returns 404 | Check the public key, guard type, seeded identifier, policy, and demo flag. |
| Login returns 419 | Verify the form has `@csrf`, is outside other forms, and uses the same session and origin. |
| Login returns 409 | Sign out of the current session before choosing a different account. |
| Login returns 429 | Check rate limit settings and trusted proxy IP handling. |
| Logout is blocked | Add its exact route name and HTTP method to `allow`. |
| Toast is missing | Use `toast` or `both` with redirect responses, and include `demo_mode_notice()` on the return page. For a JSON request, display the API message in the client. |
| A mutation succeeds | Confirm the route actually has the middleware, inspect method overrides and exceptions, then check earlier middleware or writes hidden inside a GET endpoint. |

Related Laravel docs: [package development](https://laravel.com/docs/13.x/packages), [authentication](https://laravel.com/docs/13.x/authentication), [middleware](https://laravel.com/docs/13.x/middleware), and [request forgery protection](https://laravel.com/docs/13.x/csrf).

Licensed under [MIT](LICENSE).
