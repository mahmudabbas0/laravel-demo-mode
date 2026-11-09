<?php

declare(strict_types=1);

namespace PortfolioLabs\DemoMode\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PortfolioLabs\DemoMode\Contracts\DemoLoginPolicy;
use PortfolioLabs\DemoMode\DemoAccount;
use PortfolioLabs\DemoMode\DemoMode;
use PortfolioLabs\DemoMode\DemoModeServiceProvider;

final class DemoModeTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [DemoModeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app->useLangPath(__DIR__.'/Fixtures/lang');

        $app['config']->set([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'session.driver' => 'array',
            'cache.default' => 'array',
            'database.default' => 'testing',
            'database.connections.testing' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'auth.defaults.guard' => 'web',
            'auth.guards.web' => ['driver' => 'session', 'provider' => 'demo-fixtures'],
            'auth.guards.admin' => ['driver' => 'session', 'provider' => 'demo-fixtures'],
            'auth.providers.demo-fixtures' => ['driver' => 'database', 'table' => 'demo_users'],
            'demo-mode.enabled' => true,
            'demo-mode.accounts' => [
                'customer' => ['id' => 1, 'label' => 'Customer demo', 'redirect' => '/catalog'],
                'manager' => ['guard' => 'admin', 'id' => 2, 'label' => 'Manager demo', 'redirect' => '/dashboard'],
                'accountant' => ['guard' => 'admin', 'id' => 3, 'label' => '<Accountant>', 'redirect' => '/dashboard'],
            ],
        ]);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('web')->group(function (): void {
            Route::get('/sign-in', fn () => Blade::render(
                '{{ demo_mode_assets() }} {{ demo_mode_notice() }} {{ demo_mode_accounts() }}'
            ));
            Route::get('/admin-sign-in', fn () => Blade::render(
                '{{ demo_mode_accounts(\'admin\') }}'
            ));
            Route::get('/catalog', fn () => Blade::render(<<<'BLADE'
                {{ demo_mode_notice() }}
                <form action="/products" method="POST">
                    @csrf
                    <button type="submit">Save product</button>
                </form>
                <form action="/products" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit">Delete product</button>
                </form>
                BLADE));
            Route::get('/dashboard', fn () => response()->json(['id' => Auth::guard('admin')->id()]))
                ->middleware('auth:admin');
            Route::match(['POST', 'PUT', 'PATCH', 'DELETE'], '/products', function () {
                DB::table('products')->insert(['name' => 'Write reached the application']);

                return response()->noContent();
            })->name('products.write');
            Route::get('/products', fn () => response()->json(['count' => DB::table('products')->count()]));
            Route::get('/export', fn () => response()->noContent())->name('admin.exports.download');
            Route::post('/sign-out', function (Request $request) {
                Auth::guard('admin')->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->noContent();
            })->name('admin.logout');
        });

        Route::middleware('api')->post('/api/orders', fn () => response()->noContent());
        Route::middleware('demo.read-only')->post('/custom/orders', fn () => response()->noContent());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('demo_users', function (Blueprint $table): void {
            $table->id();
            $table->string('password')->nullable();
            $table->rememberToken();
        });
        DB::table('demo_users')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
        Schema::create('products', fn (Blueprint $table) => $table->string('name'));
    }

    public function test_writes_are_rejected_before_the_controller_even_for_guests(): void
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            $this->json($method, '/products')->assertForbidden()
                ->assertJsonPath('code', 'DEMO_MODE_READ_ONLY')->assertHeader('Cache-Control', 'no-store, private');
        }

        $this->postJson('/products', ['_method' => 'DELETE'])->assertForbidden();
        $this->getJson('/products')->assertOk()->assertExactJson(['count' => 0]);
        $this->post('/api/orders')->assertForbidden()->assertJsonPath('code', 'DEMO_MODE_READ_ONLY');
        $this->postJson('/custom/orders')->assertForbidden();
    }

    public function test_an_explicit_block_beats_safe_methods_and_allow_rules(): void
    {
        config(['demo-mode.block' => ['admin.exports.*', 'products.write'], 'demo-mode.allow' => [
            'products.write' => ['POST'],
            'admin.exports.download' => ['GET'],
        ]]);

        $this->get('/export')->assertForbidden();
        $this->call('HEAD', '/export')->assertForbidden();
        $this->postJson('/products')->assertForbidden();
    }

    public function test_only_exact_allowlisted_route_and_method_pass(): void
    {
        config(['demo-mode.allow' => ['products.write' => ['POST'], 'admin.*' => ['POST']]]);

        $this->postJson('/products')->assertNoContent();
        $this->patchJson('/products')->assertForbidden();
        $this->postJson('/products', ['_method' => 'PATCH'])->assertForbidden();
        $this->postJson('/sign-out')->assertForbidden();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_disabled_mode_hides_ui_and_login_and_lets_writes_reach_the_app(): void
    {
        config(['demo-mode.enabled' => false]);

        $this->get('/sign-in')->assertOk()->assertDontSee('Customer demo')->assertDontSee('demo-mode-banner');
        $this->post('/_demo/login/manager')->assertNotFound();
        $this->postJson('/products')->assertNoContent();
        $this->assertDatabaseCount('products', 1);
    }

    public function test_buttons_only_expose_public_keys_and_filter_by_guard(): void
    {
        $this->get('/sign-in')->assertOk()->assertSee('Customer demo')->assertSee('Manager demo')
            ->assertSee('name="_token"', false)->assertSee('/_demo/login/manager');
        $this->get('/admin-sign-in')->assertOk()->assertDontSee('Customer demo')
            ->assertSee('Manager demo')->assertSee('<Accountant>');
    }

    public function test_demo_login_uses_the_configured_guard_and_rejects_account_switching(): void
    {
        $this->withSession(['url.intended' => '/other']);
        $oldSession = session()->getId();

        $this->post('/_demo/login/manager', ['id' => 1, 'guard' => 'web', 'redirect' => '//example.org'])
            ->assertStatus(303)->assertLocation('/dashboard')->assertSessionMissing('url.intended')
            ->assertSessionHas(DemoMode::SESSION_KEY.'.id', '2');
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertGuest('web');
        $this->getJson('/dashboard')->assertOk()->assertJsonPath('id', 2);
        $this->postJson('/_demo/login/customer')->assertConflict();
        $this->postJson('/products')->assertForbidden();

        config(['demo-mode.allow' => ['admin.logout' => ['POST']]]);
        $this->post('/sign-out')->assertNoContent()->assertSessionMissing(DemoMode::SESSION_KEY);
        $this->assertGuest('admin');
    }

    public function test_unknown_accounts_and_a_policy_denial_do_not_create_sessions(): void
    {
        $this->app->instance(DemoLoginPolicy::class, new class implements DemoLoginPolicy
        {
            public function allows(Request $request, DemoAccount $account, Authenticatable $user): bool
            {
                return false;
            }
        });

        $this->get('/_demo/login/manager')->assertMethodNotAllowed();
        $this->post('/_demo/login/unknown')->assertNotFound();
        $this->post('/_demo/login/manager')->assertNotFound()->assertSessionMissing(DemoMode::SESSION_KEY);
        $this->assertGuest('admin');
    }

    public function test_login_requires_request_forgery_protection(): void
    {
        $this->app->instance('env', 'local');

        $this->post('/_demo/login/manager')->assertStatus(419);
        $this->assertGuest('admin');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        config(['demo-mode.login.attempts_per_minute' => 1]);

        $this->post('/_demo/login/missing')->assertNotFound();
        $this->post('/_demo/login/manager')->assertTooManyRequests();
        $this->assertGuest('admin');
    }

    public function test_disabling_demo_revokes_the_session_before_a_write_can_run(): void
    {
        $this->post('/_demo/login/manager')->assertStatus(303);
        config(['demo-mode.enabled' => false]);

        $this->postJson('/products')->assertForbidden()->assertJsonPath('code', 'DEMO_SESSION_EXPIRED')
            ->assertSessionMissing(DemoMode::SESSION_KEY);
        $this->assertGuest('admin');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_sessions_expire_at_the_configured_limit(): void
    {
        config(['demo-mode.login.session_minutes' => 1]);
        $this->post('/_demo/login/manager')->assertStatus(303);
        $this->travel(61)->seconds();

        $this->getJson('/dashboard')->assertForbidden()->assertJsonPath('code', 'DEMO_SESSION_EXPIRED');
        $this->assertGuest('admin');
    }

    public function test_toast_flashes_once_and_untrusted_referrers_use_the_local_fallback(): void
    {
        config(['demo-mode.return_to' => '/sign-in']);

        $this->withHeader('Referer', 'https://outside.example/path')->post('/products')
            ->assertStatus(303)->assertLocation('/sign-in');
        $this->get('/sign-in')->assertSee('data-demo-mode-toast', false);
        $this->get('/sign-in')->assertDontSee('data-demo-mode-toast', false);
        $this->withHeader('Referer', 'http://localhost//outside.example/path')->post('/products')
            ->assertStatus(303)->assertLocation('/sign-in');
        $this->withHeader('Referer', 'http://localhost/sign-in?tab=1')->post('/products')
            ->assertStatus(303)->assertLocation('/sign-in?tab=1');
    }

    #[DataProvider('displayModes')]
    public function test_display_mode_reports_block_without_hiding_buttons(
        string $mode,
        ?string $element,
        int $status,
        ?string $feedback,
    ): void {
        config(['demo-mode.mode' => $mode, 'demo-mode.return_to' => '/catalog']);
        $page = $this->get('/catalog')->assertOk()->assertSee('Save product')->assertSee('Delete product');
        foreach (['demo-mode-banner', 'demo-mode-badge'] as $class) {
            $element === $class ? $page->assertSee($class) : $page->assertDontSee($class);
        }

        $response = $this->post('/products')->assertStatus($status);
        if ($status === 303) {
            $response->assertLocation('/catalog');
            $response = $this->get('/catalog')->assertSee('Save product')->assertSee('Delete product');
            $response->assertSee($feedback);
        }
        $response->assertSee('This action is disabled in demo mode.');
        $this->postJson('/products')->assertForbidden()->assertJsonPath('code', 'DEMO_MODE_READ_ONLY');
    }

    public static function displayModes(): array
    {
        return [
            ['banner', 'demo-mode-banner', 303, 'demo-mode-feedback'],
            ['toast', null, 303, 'data-demo-mode-toast'],
            ['both', 'demo-mode-banner', 303, 'data-demo-mode-toast'],
            ['badge', 'demo-mode-badge', 303, 'demo-mode-feedback'],
            ['page', null, 403, null],
            ['silent', null, 303, 'data-demo-mode-inline'],
        ];
    }

    public function test_an_application_can_translate_messages_with_an_english_fallback(): void
    {
        $this->app->setLocale('es');
        $this->app['translator']->setFallback('fr');

        $this->get('/sign-in')->assertSee('Try a demo account')->assertSee('dir="ltr"', false);
        $this->app['translator']->addLines([
            'messages.accounts_title' => 'Elige una cuenta de demostración',
            'messages.blocked' => 'Acción bloqueada en el modo de demostración.',
        ], 'es', 'demo-mode');

        $this->get('/sign-in')->assertSee('Elige una cuenta de demostración');
        $this->postJson('/products')->assertForbidden()
            ->assertJsonPath('message', 'Acción bloqueada en el modo de demostración.');
    }

    public function test_demo_locale_can_follow_the_site_or_be_fixed_without_changing_it(): void
    {
        $this->app->setLocale('ar');
        config(['demo-mode.accounts.customer.label_key' => 'demo-mode.staff']);

        $this->get('/sign-in')->assertSee('جرّب كموظف')->assertSee('lang="ar" dir="rtl"', false);
        $this->app->setLocale('en');
        $this->get('/sign-in')->assertSee('Try a demo account')->assertSee('lang="en" dir="ltr"', false);

        config(['demo-mode.locale' => 'ar', 'demo-mode.mode' => 'page']);
        $this->get('/sign-in')->assertSee('جرّب حسابًا تجريبيًا')->assertSee('جرّب كموظف');
        $this->post('/products')->assertForbidden()->assertSee('lang="ar" dir="rtl"', false);
        $this->postJson('/products')->assertForbidden()
            ->assertJsonPath('message', 'هذا الإجراء معطّل في الوضع التجريبي.');
        $this->assertSame('en', $this->app->getLocale());
    }

    public function test_route_audit_reports_missing_middleware(): void
    {
        Route::post('/uncovered', fn () => response()->noContent());

        $this->assertSame(1, Artisan::call('demo-mode:audit'));
        $this->assertMatchesRegularExpression('~/uncovered[^\r\n]*UNCOVERED~', Artisan::output());
    }
}
