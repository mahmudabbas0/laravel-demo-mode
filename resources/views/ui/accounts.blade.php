    @php($locale = \PortfolioLabs\DemoMode\DemoMode::locale())
    <section class="demo-mode-accounts {{ $class }}" lang="{{ str_replace('_', '-', $locale) }}" dir="{{ \PortfolioLabs\DemoMode\DemoMode::message('direction') }}"
             aria-label="{{ \PortfolioLabs\DemoMode\DemoMode::message('accounts_title') }}">
        <p class="demo-mode-accounts__title">{{ \PortfolioLabs\DemoMode\DemoMode::message('accounts_title') }}</p>
        <div class="demo-mode-accounts__actions">
            @foreach ($accounts as $account)
                <form method="POST" action="{{ route(\PortfolioLabs\DemoMode\DemoMode::LOGIN_ROUTE, ['account' => $account->key], false) }}">
                    @csrf
                    <button class="demo-mode-button" type="submit">
                        {{ $account->labelKey === null ? $account->label : __($account->labelKey, [], $locale) }}
                    </button>
                    @if ($account->descriptionKey !== null)
                        <small>{{ __($account->descriptionKey, [], $locale) }}</small>
                    @endif
                </form>
            @endforeach
        </div>
    </section>
