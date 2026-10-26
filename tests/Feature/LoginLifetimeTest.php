<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceLoginLifetime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginLifetimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/teste-duracao-sessao', fn () => response('Sessão ativa'));
    }

    public function test_session_is_limited_to_four_hours_and_closes_with_browser(): void
    {
        $this->assertSame(240, config('session.lifetime'));
        $this->assertTrue(config('session.expire_on_close'));
    }

    public function test_active_requests_do_not_extend_login_beyond_four_hours(): void
    {
        $this->travelTo('2026-10-26 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user)
            ->withSession([EnforceLoginLifetime::SESSION_KEY => now()->timestamp - 4 * 60 * 60 + 1])
            ->get('/teste-duracao-sessao')
            ->assertOk();

        $this->get('/teste-duracao-sessao')->assertOk();
        $this->withSession([EnforceLoginLifetime::SESSION_KEY => now()->timestamp - 4 * 60 * 60])
            ->get('/teste-duracao-sessao')
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_old_remembered_login_is_rejected(): void
    {
        $user = User::factory()->create();
        $guard = Auth::guard('web');
        $this->actingAs($user);
        (new \ReflectionProperty($guard, 'viaRemember'))->setValue($guard, true);

        $this->get('/teste-duracao-sessao')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_existing_session_without_start_time_receives_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/teste-duracao-sessao')->assertOk();

        $this->assertSame(now()->timestamp, session(EnforceLoginLifetime::SESSION_KEY));
    }
}
