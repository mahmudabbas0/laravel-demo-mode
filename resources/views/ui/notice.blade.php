@php
    $direction = \PortfolioLabs\DemoMode\DemoMode::message('direction');
    $language = str_replace('_', '-', \PortfolioLabs\DemoMode\DemoMode::locale());
    $blocked = request()->hasSession() && session(\PortfolioLabs\DemoMode\DemoMode::FLASH_KEY) === 'blocked';
@endphp

@if ($mode->showsBanner())
    <aside class="demo-mode-banner {{ $class }}" lang="{{ $language }}" dir="{{ $direction }}">
        <span class="demo-mode-banner__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">
                <rect x="5" y="10" width="14" height="11" rx="2"/>
                <path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>
            </svg>
        </span>
        <strong class="demo-mode-banner__title">{{ \PortfolioLabs\DemoMode\DemoMode::message('title') }}</strong>
        <span class="demo-mode-banner__status">{{ \PortfolioLabs\DemoMode\DemoMode::message('read_only') }}</span>
        <p class="demo-mode-banner__description">{{ \PortfolioLabs\DemoMode\DemoMode::message('banner') }}</p>
        @if ($blocked && ! $mode->showsToast())
            <strong class="demo-mode-feedback" role="status">{{ \PortfolioLabs\DemoMode\DemoMode::message('blocked') }}</strong>
        @endif
    </aside>
@elseif ($mode === \PortfolioLabs\DemoMode\DisplayMode::Badge)
    <aside class="demo-mode-badge {{ $class }}" lang="{{ $language }}" dir="{{ $direction }}">
        <span class="demo-mode-badge__label">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                <rect x="5" y="10" width="14" height="11" rx="2"/>
                <path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>
            </svg>
            {{ \PortfolioLabs\DemoMode\DemoMode::message('badge') }}
        </span>
        @if ($blocked)
            <strong class="demo-mode-feedback" role="status">{{ \PortfolioLabs\DemoMode\DemoMode::message('blocked') }}</strong>
        @endif
    </aside>
@elseif ($mode === \PortfolioLabs\DemoMode\DisplayMode::Silent && $blocked)
    <p class="{{ $class }}" lang="{{ $language }}" dir="{{ $direction }}" role="status" data-demo-mode-inline>
        {{ \PortfolioLabs\DemoMode\DemoMode::message('blocked') }}
    </p>
@endif

@if ($mode->showsToast() && $blocked)
    @php($duration = (int) config('demo-mode.toast_duration_ms', 6500))
    <div class="demo-mode-toast {{ $mode->showsBanner() ? '' : $class }}" lang="{{ $language }}" dir="{{ $direction }}" data-demo-mode-toast
         data-duration="{{ $duration > 0 ? max(5000, $duration) : 0 }}">
        <span role="status">{{ \PortfolioLabs\DemoMode\DemoMode::message('blocked') }}</span>
        <button type="button" class="demo-mode-toast__dismiss" hidden data-demo-mode-dismiss
                aria-label="{{ \PortfolioLabs\DemoMode\DemoMode::message('dismiss') }}">&times;</button>
    </div>
@endif
