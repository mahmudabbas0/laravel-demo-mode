<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode;

use Illuminate\Support\HtmlString;

final class DemoUi
{
    public static function accounts(?string $guard = null, ?string $class = null): HtmlString
    {
        $demo = app(DemoMode::class);
        $accounts = $demo->enabled() ? $demo->accounts($guard) : [];

        return $accounts === [] ? new HtmlString('') : self::render('accounts', compact('accounts', 'class'));
    }

    public static function notice(?string $class = null): HtmlString
    {
        $demo = app(DemoMode::class);

        return $demo->enabled() ? self::render('notice', ['mode' => $demo->mode(), 'class' => $class]) : new HtmlString('');
    }

    public static function assets(): HtmlString
    {
        return app(DemoMode::class)->enabled() ? self::render('assets') : new HtmlString('');
    }

    private static function render(string $view, array $data = []): HtmlString
    {
        return new HtmlString(view('demo-mode::ui.'.$view, $data)->render());
    }
}
