<?php

use Illuminate\Support\Facades\Route;
use PortfolioLabs\DemoMode\DemoMode;
use PortfolioLabs\DemoMode\Http\DemoLoginController;
use PortfolioLabs\DemoMode\Http\ProtectDemo;

Route::post('/_demo/login/{account}', DemoLoginController::class)
    ->where('account', '[a-z][a-z0-9_-]{0,63}')
    ->middleware(['web', ProtectDemo::class, 'throttle:demo-mode.login'])
    ->name(DemoMode::LOGIN_ROUTE);
