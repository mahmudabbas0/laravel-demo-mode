<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode\Http;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use PortfolioLabs\DemoMode\Contracts\DemoLoginPolicy;
use PortfolioLabs\DemoMode\DemoMode;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class DemoLoginController
{
    public function __construct(
        private readonly DemoMode $demo,
        private readonly AuthManager $auth,
        private readonly Container $container,
    ) {}

    public function __invoke(Request $request, string $account): RedirectResponse
    {
        abort_unless($this->demo->enabled() && $request->hasSession(), 404);

        $demoAccount = $this->demo->account($account);
        abort_unless($demoAccount !== null, 404);

        $guards = (array) config('auth.guards', []);
        abort_unless(isset($guards[$demoAccount->guard]), 404);

        $guard = $this->auth->guard($demoAccount->guard);
        abort_unless($guard instanceof SessionGuard, 404);

        foreach ($guards as $name => $settings) {
            if (($name === $demoAccount->guard || ($settings['driver'] ?? null) === 'session')
                && $this->auth->guard($name)->check()) {
                abort(409, DemoMode::message('already_signed_in'));
            }
        }

        $user = $guard->getProvider()->retrieveById($demoAccount->id);
        abort_unless($user !== null && (string) $user->getAuthIdentifier() === (string) $demoAccount->id, 404);

        if ($this->container->bound(DemoLoginPolicy::class)) {
            abort_unless($this->container->make(DemoLoginPolicy::class)->allows($request, $demoAccount, $user), 404);
        }

        $request->session()->put(DemoMode::SESSION_KEY, [
            'account' => $demoAccount->key,
            'guard' => $demoAccount->guard,
            'id' => (string) $demoAccount->id,
            'expires_at' => now()->addMinutes(max(1, (int) config('demo-mode.login.session_minutes', 120)))->timestamp,
        ]);
        $request->session()->forget('url.intended');
        $guard->login($user, false);
        $request->session()->regenerateToken();

        return new RedirectResponse($demoAccount->redirect, 303, ['Cache-Control' => 'no-store']);
    }
}
