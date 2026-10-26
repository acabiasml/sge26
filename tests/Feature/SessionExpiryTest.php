<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/teste-duracao-sessao', fn () => response('Sessão ativa'));
    }

    public function test_session_expires_after_two_hours_of_inactivity_and_closes_with_browser(): void
    {
        $this->assertSame(120, config('session.lifetime'));
        $this->assertTrue(config('session.expire_on_close'));
    }

    public function test_database_session_remains_valid_with_activity_and_expires_after_two_idle_hours(): void
    {
        $handler = new DatabaseSessionHandler(DB::connection(), 'sessions', config('session.lifetime'), app());
        $sessionId = Str::random(40);
        $handler->write($sessionId, 'sessão ativa');

        $this->travel(119)->minutes();
        $this->assertSame('sessão ativa', $handler->read($sessionId));
        $handler->write($sessionId, 'sessão renovada');

        $this->travel(119)->minutes();
        $this->assertSame('sessão renovada', $handler->read($sessionId));

        $this->travel(121)->minutes();
        $this->assertSame('', $handler->read($sessionId));
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

}
