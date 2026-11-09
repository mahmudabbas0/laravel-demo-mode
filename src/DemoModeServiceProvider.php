<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Kernel as FoundationKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PortfolioLabs\DemoMode\Console\AuditRoutes;
use PortfolioLabs\DemoMode\Http\ProtectDemo;

final class DemoModeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/demo-mode.php', 'demo-mode');
        $this->app->singleton(DemoMode::class);
    }

    public function boot(Router $router): void
    {
        $kernel = $this->app->make(Kernel::class);
        if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(SubstituteBindings::class, ProtectDemo::class);
        }

        $router->aliasMiddleware('demo.read-only', ProtectDemo::class);

        foreach ((array) config('demo-mode.middleware_groups', []) as $group) {
            if (is_string($group) && $group !== '') {
                if ($kernel instanceof FoundationKernel) {
                    try {
                        $kernel->appendMiddlewareToGroup($group, ProtectDemo::class);

                        continue;
                    } catch (InvalidArgumentException) {
                        // A provider may have registered this group directly on the router.
                    }
                }

                $router->pushMiddlewareToGroup($group, ProtectDemo::class);
            }
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'demo-mode');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'demo-mode');

        RateLimiter::for('demo-mode.login', static function (Request $request): Limit {
            if (! app(DemoMode::class)->enabled()) {
                return Limit::none();
            }

            return Limit::perMinute(max(1, (int) config('demo-mode.login.attempts_per_minute', 10)))
                ->by((string) $request->ip());
        });

        $this->publishes([__DIR__.'/../config/demo-mode.php' => config_path('demo-mode.php')], 'demo-mode-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/demo-mode')], 'demo-mode-views');
        $this->publishes([__DIR__.'/../lang/en/messages.php' => lang_path('en/demo-mode.php')], 'demo-mode-lang');
        $this->publishes([__DIR__.'/../resources/public' => public_path('vendor/demo-mode')], 'demo-mode-assets');

        if ($this->app->runningInConsole()) {
            $this->commands([AuditRoutes::class]);
        }
    }
}
