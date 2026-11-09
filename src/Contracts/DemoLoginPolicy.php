<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use PortfolioLabs\DemoMode\DemoAccount;

interface DemoLoginPolicy
{
    public function allows(Request $request, DemoAccount $account, Authenticatable $user): bool;
}
