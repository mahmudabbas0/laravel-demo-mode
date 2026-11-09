<?php

return [
    'enabled' => env('DEMO_MODE_ENABLED', false),
    'mode' => env('DEMO_MODE_MODE', 'both'),
    'locale' => env('DEMO_MODE_LOCALE', 'auto'),

    // Register protection on these route middleware groups. Use [] for manual registration.
    'middleware_groups' => ['web', 'api'],

    // Block unsafe methods by default. Use exact route names and explicit methods for exceptions.
    'allow' => [
        // 'logout' => ['POST'],
        // 'admin.logout' => ['POST'],
    ],

    // Block all methods on these named routes, including GET and HEAD. Wildcards are supported.
    'block' => [
        // 'admin.exports.*',
    ],

    // Use "page" for an HTTP 403 page or "redirect" for a 303 and session flash on web forms.
    'html_response' => 'redirect',
    'return_to' => '/',

    // Set 0 to require manual dismissal. Positive values below 5000 are raised to 5000.
    'toast_duration_ms' => 6500,

    'login' => [
        'attempts_per_minute' => 10,
        'session_minutes' => 120,
    ],

    // Configure seeded demo users only. IDs must be stable across demo deployments.
    'accounts' => [
        // 'store-admin' => [
        //     'guard' => 'admin',
        //     'id' => 'demo-admin-uuid',
        //     'label' => 'Store administrator',
        //     'label_key' => 'demo-accounts.store_admin', // Optional app translation.
        //     'description_key' => 'demo-accounts.store_admin_description',
        //     'redirect' => '/admin', // Fixed, root-relative URL.
        // ],
    ],
];
