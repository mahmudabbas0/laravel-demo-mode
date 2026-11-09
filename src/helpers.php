<?php

declare(strict_types=1);

use Illuminate\Support\HtmlString;
use PortfolioLabs\DemoMode\DemoUi;

if (! function_exists('demo_mode_assets')) {
    function demo_mode_assets(): HtmlString
    {
        return DemoUi::assets();
    }
}

if (! function_exists('demo_mode_notice')) {
    function demo_mode_notice(?string $class = null): HtmlString
    {
        return DemoUi::notice($class);
    }
}

if (! function_exists('demo_mode_accounts')) {
    function demo_mode_accounts(?string $guard = null, ?string $class = null): HtmlString
    {
        return DemoUi::accounts($guard, $class);
    }
}
