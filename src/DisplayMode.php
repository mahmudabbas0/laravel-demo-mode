<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode;

enum DisplayMode: string
{
    case Banner = 'banner';
    case Toast = 'toast';
    case Both = 'both';
    case Badge = 'badge';
    case Page = 'page';
    case Silent = 'silent';

    public function showsBanner(): bool
    {
        return $this === self::Banner || $this === self::Both;
    }

    public function showsToast(): bool
    {
        return $this === self::Toast || $this === self::Both;
    }

    public function supportsFlash(): bool
    {
        return $this !== self::Page;
    }
}
