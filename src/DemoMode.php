<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Str;

final class DemoMode
{
    public const FLASH_KEY = 'demo_mode.blocked';
    public const SESSION_KEY = 'demo_mode.login';
    public const LOGIN_ROUTE = 'demo-mode.login';

    public function __construct(private readonly Repository $config) {}

    public static function locale(): string
    {
        $locale = config('demo-mode.locale', 'auto');

        return is_string($locale) && $locale !== '' && $locale !== 'auto'
            ? $locale
            : app()->getLocale();
    }

    public static function message(string $key): string
    {
        $translator = app('translator');
        $keys = ['demo-mode.'.$key, 'demo-mode::messages.'.$key];
        $locales = array_unique([self::locale(), $translator->getFallback(), 'en']);

        foreach ($locales as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }

            foreach ($keys as $translationKey) {
                $message = $translator->get($translationKey, [], $locale, false);

                if (is_string($message) && $message !== $translationKey) {
                    return $message;
                }
            }
        }

        return $keys[1];
    }

    public function enabled(): bool
    {
        return filter_var($this->config->get('demo-mode.enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function mode(): DisplayMode
    {
        $mode = $this->config->get('demo-mode.mode');

        return (is_string($mode) ? DisplayMode::tryFrom($mode) : null) ?? DisplayMode::Both;
    }

    public static function isSafeMethod(string $method): bool
    {
        return in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
    }

    public function blocksRoute(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        foreach ((array) $this->config->get('demo-mode.block', []) as $pattern) {
            if (is_string($pattern) && Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    public function allowsMethod(?string $name, string $method): bool
    {
        $rules = (array) $this->config->get('demo-mode.allow', []);
        $methods = $name === null ? null : ($rules[$name] ?? null);

        return is_array($methods) && in_array($method, $methods, true);
    }

    /** @return list<DemoAccount> */
    public function accounts(?string $guard = null): array
    {
        $accounts = [];

        foreach ((array) $this->config->get('demo-mode.accounts', []) as $key => $settings) {
            $account = is_string($key) ? $this->makeAccount($key, $settings) : null;

            if ($account !== null && ($guard === null || $guard === $account->guard)) {
                $accounts[] = $account;
            }
        }

        return $accounts;
    }

    public function account(string $key): ?DemoAccount
    {
        $accounts = (array) $this->config->get('demo-mode.accounts', []);

        return $this->makeAccount($key, $accounts[$key] ?? null);
    }

    private function makeAccount(string $key, mixed $settings): ?DemoAccount
    {
        if (! preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $key) || ! is_array($settings)) {
            return null;
        }

        $guard = $settings['guard'] ?? 'web';
        $id = $settings['id'] ?? null;

        if (! is_string($guard) || $guard === '' || (! is_int($id) && (! is_string($id) || trim($id) === ''))) {
            return null;
        }

        return new DemoAccount(
            $key,
            $guard,
            $id,
            is_string($settings['label'] ?? null) ? $settings['label'] : Str::headline($key),
            is_string($settings['label_key'] ?? null) ? $settings['label_key'] : null,
            is_string($settings['description_key'] ?? null) ? $settings['description_key'] : null,
            LocalUrl::path($settings['redirect'] ?? null) ?? '/',
        );
    }
}
