<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode\Http;

use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use PortfolioLabs\DemoMode\DemoMode;
use PortfolioLabs\DemoMode\DisplayMode;
use PortfolioLabs\DemoMode\LocalUrl;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

final class ProtectDemo
{
    public function __construct(private readonly DemoMode $demo, private readonly AuthManager $auth) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $name = $route?->getName();
        $login = $name === DemoMode::LOGIN_ROUTE && $route->getControllerClass() === DemoLoginController::class;
        $enabled = $this->demo->enabled();
        $session = $request->hasSession() ? $request->session() : null;
        $marker = $session?->get(DemoMode::SESSION_KEY);

        if ($marker !== null && ! $this->validSession($marker, $enabled)) {
            $this->forgetGuard($marker);
            $session->invalidate();
            $session->regenerateToken();

            if (! $login || $enabled) {
                return $this->denied($request, 'DEMO_SESSION_EXPIRED', 'session_expired', false);
            }
        }

        if (! $enabled || ($login && $request->method() === 'POST' && $request->getRealMethod() === 'POST')) {
            return $next($request);
        }

        $method = $request->method();
        $original = $request->getRealMethod();
        $safe = DemoMode::isSafeMethod($method) && DemoMode::isSafeMethod($original);
        $blockedRoute = $this->demo->blocksRoute($name);

        if (! $blockedRoute && ($safe || ($method === $original && $this->demo->allowsMethod($name, $method)))) {
            return $next($request);
        }

        return $this->denied($request, 'DEMO_MODE_READ_ONLY', 'blocked', ! $safe && ! $blockedRoute);
    }

    private function validSession(mixed $marker, bool $enabled): bool
    {
        if (! $enabled || ! is_array($marker) || ! is_string($marker['account'] ?? null)
            || ! is_int($marker['expires_at'] ?? null) || $marker['expires_at'] <= now()->timestamp) {
            return false;
        }

        $account = $this->demo->account($marker['account']);

        return $account !== null && $account->guard === ($marker['guard'] ?? null)
            && (string) $account->id === ($marker['id'] ?? null);
    }

    private function forgetGuard(mixed $marker): void
    {
        $name = is_array($marker) ? ($marker['guard'] ?? null) : null;
        $guards = (array) config('auth.guards', []);

        if (is_string($name) && isset($guards[$name])) {
            $guard = $this->auth->guard($name);

            if ($guard instanceof SessionGuard) {
                $guard->logoutCurrentDevice();
            }
        }
    }

    private function denied(Request $request, string $code, string $messageKey, bool $mayRedirect): Response
    {
        $headers = ['Cache-Control' => 'no-store', 'Vary' => 'Accept, X-Requested-With'];
        $message = DemoMode::message($messageKey);

        if ($request->expectsJson() || ! $request->hasSession()) {
            return response()->json(['code' => $code, 'message' => $message], 403, $headers);
        }

        $mode = $this->demo->mode();
        $fallback = LocalUrl::path(config('demo-mode.return_to')) ?? '/';

        if ($mayRedirect && $mode->supportsFlash() && config('demo-mode.html_response') === 'redirect') {
            $request->session()->flash(DemoMode::FLASH_KEY, $messageKey);

            return new RedirectResponse(LocalUrl::previous($request) ?? $fallback, 303, $headers);
        }

        return response()->view('demo-mode::blocked', [
            'message' => $message,
            'returnTo' => $fallback,
        ], 403, $headers);
    }
}
