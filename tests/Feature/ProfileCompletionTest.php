<?php

namespace Tests\Feature;

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_profile_cannot_access_internal_screens(): void
    {
        $person = Person::query()->create([
            'full_name' => 'Admin Sem CPF',
            'institutional_email' => 'admin@ctjj.org',
        ]);

        $person->schoolRoles()->create([
            'role' => PersonSchoolRole::ROLE_ADMINISTRATOR,
            'active' => true,
        ]);

        $user = User::factory()->create([
            'person_id' => $person->id,
            'email' => $person->institutional_email,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.edit'));

        $this->actingAs($user)
            ->get(route('schools.index'))
            ->assertRedirect(route('profile.edit'));
    }
}
