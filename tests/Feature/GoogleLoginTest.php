<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_requires_credentials(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $response = $this->get(route('auth.google.redirect'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_first_workspace_user_becomes_administrator(): void
    {
        $this->mockGoogleUser('primeiro@ctjj.org', 'Primeiro Admin', 'google-1');

        $response = $this->get(route('auth.google.callback'));

        $person = Person::query()->where('institutional_email', 'primeiro@ctjj.org')->firstOrFail();

        $response->assertRedirect(route('profile.edit'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('person_school_roles', [
            'person_id' => $person->id,
            'school_id' => null,
            'role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            'active' => true,
        ]);
    }

    public function test_unknown_workspace_user_cannot_self_register_after_administrator_exists(): void
    {
        $adminPerson = Person::query()->create([
            'full_name' => 'Admin',
            'institutional_email' => 'admin@ctjj.org',
        ]);

        $adminPerson->schoolRoles()->create([
            'role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            'active' => true,
        ]);

        $this->mockGoogleUser('desconhecido@ctjj.org', 'Pessoa Desconhecida', 'google-2');

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();
        $this->assertDatabaseMissing('people', [
            'institutional_email' => 'desconhecido@ctjj.org',
        ]);
    }

    public function test_first_workspace_user_becomes_administrator_when_orphan_user_exists_but_no_admin_exists(): void
    {
        User::factory()->create([
            'person_id' => null,
            'email' => 'antigo@ctjj.org',
        ]);

        $this->mockGoogleUser('primeiro@ctjj.org', 'Primeiro Admin', 'google-admin-orphan');

        $response = $this->get(route('auth.google.callback'));

        $person = Person::query()->where('institutional_email', 'primeiro@ctjj.org')->firstOrFail();

        $response->assertRedirect(route('profile.edit'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('person_school_roles', [
            'person_id' => $person->id,
            'school_id' => null,
            'role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            'active' => true,
        ]);
    }

    public function test_email_outside_workspace_domain_cannot_login(): void
    {
        $this->mockGoogleUser('pessoa@gmail.com', 'Pessoa Externa', 'google-outside');

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Use sua conta institucional @ctjj.org para acessar o Beabá.');
        $this->assertGuest();
        $this->assertDatabaseMissing('people', [
            'institutional_email' => 'pessoa@gmail.com',
        ]);
    }

    public function test_registered_person_with_active_role_can_login(): void
    {
        User::factory()->create();

        $person = Person::query()->create([
            'full_name' => 'Docente Cadastrada',
            'institutional_email' => 'docente@ctjj.org',
            'cpf' => '12345678900',
            'birth_date' => '1990-01-01',
            'birth_city' => 'Poxoreu',
            'birth_state' => 'MT',
            'nationality' => 'Brasileira',
            'mother_name' => 'Maria da Silva',
            'father_name' => 'José da Silva',
            'phone' => '(65) 99999-0000',
            'address' => 'Rua de Teste',
            'city' => 'Poxoreu',
            'state' => 'MT',
            'postal_code' => '78700-000',
            'profile_completed_at' => now(),
        ]);

        $person->schoolRoles()->create([
            'role' => PersonSchoolRole::ROLE_TEACHER,
            'active' => true,
        ]);

        $this->mockGoogleUser('docente@ctjj.org', 'Docente Cadastrada', 'google-3');

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'person_id' => $person->id,
            'email' => 'docente@ctjj.org',
        ]);
    }

    private function mockGoogleUser(string $email, string $name, string $id): void
    {
        config([
            'services.google.allowed_domain' => 'ctjj.org',
        ]);

        $googleUser = new class($email, $name, $id) {
            public function __construct(
                private readonly string $email,
                private readonly string $name,
                private readonly string $id,
            ) {}

            public function getEmail(): string
            {
                return $this->email;
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getNickname(): ?string
            {
                return null;
            }

            public function getId(): string
            {
                return $this->id;
            }

            public function getAvatar(): ?string
            {
                return null;
            }
        };

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        $socialite = Mockery::mock(SocialiteFactory::class);
        $socialite->shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $this->app->instance(SocialiteFactory::class, $socialite);
    }
}

