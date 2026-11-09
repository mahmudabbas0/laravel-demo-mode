<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode;

final class DemoAccount
{
    public function __construct(
        public readonly string $key,
        public readonly string $guard,
        public readonly int|string $id,
        public readonly string $label,
        public readonly ?string $labelKey,
        public readonly ?string $descriptionKey,
        public readonly string $redirect,
    ) {}
}
