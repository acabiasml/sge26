<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvalidateAllSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_removes_all_sessions_and_old_remember_tokens(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'token-antigo']);

        foreach ([$user->id, null] as $userId) {
            DB::table('sessions')->insert([
                'id' => Str::random(40),
                'user_id' => $userId,
                'payload' => base64_encode('sessão'),
                'last_activity' => now()->timestamp,
            ]);
        }

        $this->artisan('sessions:invalidate-all', ['--force' => true])
            ->expectsOutputToContain('Sessões removidas: 2. Tokens antigos invalidados: 1.')
            ->assertExitCode(0);

        $this->assertDatabaseCount('sessions', 0);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'remember_token' => null]);
    }

    public function test_it_removes_file_sessions_without_deleting_other_files(): void
    {
        $path = sys_get_temp_dir().'/sge-sessions-'.Str::random(16);
        File::ensureDirectoryExists($path);

        try {
            config(['session.driver' => 'file', 'session.files' => $path]);
            $user = User::factory()->create(['remember_token' => 'token-antigo']);
            $sessionFile = $path.'/'.Str::random(40);
            File::put($sessionFile, 'sessão');
            File::put($path.'/.gitignore', '*');

            $this->artisan('sessions:invalidate-all', ['--force' => true])
                ->expectsOutputToContain('Sessões removidas: 1. Tokens antigos invalidados: 1.')
                ->assertExitCode(0);

            $this->assertFalse(File::exists($sessionFile));
            $this->assertTrue(File::exists($path.'/.gitignore'));
            $this->assertDatabaseHas('users', ['id' => $user->id, 'remember_token' => null]);
        } finally {
            File::deleteDirectory($path);
        }
    }

    public function test_it_requires_explicit_force_and_database_session_driver(): void
    {
        $user = User::factory()->create(['remember_token' => 'token-antigo']);
        DB::table('sessions')->insert([
            'id' => Str::random(40), 'payload' => base64_encode('sessão'),
            'last_activity' => now()->timestamp,
        ]);

        $this->artisan('sessions:invalidate-all', ['--force' => true])->assertExitCode(1);
        config(['session.driver' => 'database']);
        $this->artisan('sessions:invalidate-all')->assertExitCode(1);

        $this->assertDatabaseCount('sessions', 1);
        $this->assertSame('token-antigo', $user->fresh()->getRememberToken());
    }
}
