<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode\Console;

use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use PortfolioLabs\DemoMode\DemoMode;
use PortfolioLabs\DemoMode\Http\DemoLoginController;
use PortfolioLabs\DemoMode\Http\ProtectDemo;

final class AuditRoutes extends Command
{
    protected $signature = 'demo-mode:audit {--all : Also show routes using only safe HTTP methods}';

    protected $description = 'Check which Laravel routes are covered by demo mode';

    public function handle(Router $router, DemoMode $demo): int
    {
        $this->info('Demo mode is '.($demo->enabled() ? 'enabled' : 'disabled').'; display: '.$demo->mode()->value.'.');

        $rows = [];
        $uncovered = 0;

        foreach ($router->getRoutes() as $route) {
            $name = $route->getName();
            $covered = in_array(ProtectDemo::class, $router->gatherRouteMiddleware($route), true);
            $login = $name === DemoMode::LOGIN_ROUTE && $route->getControllerClass() === DemoLoginController::class;
            $blocked = $demo->blocksRoute($name);

            foreach ($route->methods() as $method) {
                $safe = DemoMode::isSafeMethod($method);

                if ($safe && ! $blocked && ! $this->option('all')) {
                    continue;
                }

                if (! $covered && (! $safe || $blocked)) {
                    $uncovered++;
                }

                $status = match (true) {
                    ! $covered => 'UNCOVERED',
                    $login && $method === 'POST' => 'DEMO LOGIN',
                    $blocked => 'BLOCKED',
                    $safe => 'BROWSING',
                    $demo->allowsMethod($name, $method) => 'ALLOWLISTED',
                    default => 'BLOCKED',
                };

                $rows[] = [$method, $route->getDomain() ?? 'Any', '/'.ltrim($route->uri(), '/'), $name ?? '—', $status];
            }
        }

        $this->table(['Method', 'Domain', 'Route', 'Name', 'When enabled'], $rows);

        if ($uncovered > 0) {
            $this->error("{$uncovered} route/method combinations need review and explicit middleware coverage.");

            return self::FAILURE;
        }

        $this->line('Review allowlisted routes and verify that browsing routes have no application side effects.');

        return self::SUCCESS;
    }
}
