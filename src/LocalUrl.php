<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode;

use Illuminate\Http\Request;

final class LocalUrl
{
    public static function path(mixed $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, '/') || str_starts_with($value, '//')
            || str_contains($value, '\\') || preg_match('/[\x00-\x20\x7f]/', $value)) {
            return null;
        }

        // Browsers normalize dot segments, including their percent-encoded forms.
        $path = explode('?', explode('#', $value, 2)[0], 2)[0];

        return preg_match('~(?:^|/)(?:\.|%2e){1,2}(?:/|$)~i', $path) ? null : $value;
    }

    public static function previous(Request $request): ?string
    {
        $origin = $request->getSchemeAndHttpHost();
        $referer = $request->headers->get('referer', '');

        return str_starts_with($referer, $origin.'/')
            ? self::path(substr($referer, strlen($origin)))
            : null;
    }
}
