<?php

namespace Tests\Feature;

use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalEmailGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_an_institutional_email_ignoring_name_particles(): void
    {
        $person = Person::query()->create(['full_name' => 'Augusto dos Anjos Faustino']);

        $this->assertSame('augusto.anjos@ctjj.org', $person->institutional_email);
    }

    public function test_it_adds_a_numeric_suffix_when_the_address_already_exists(): void
    {
        Person::query()->create(['full_name' => 'Augusto dos Anjos Faustino']);
        $secondPerson = Person::query()->create(['full_name' => 'Augusto Anjos da Silva']);

        $this->assertSame('augusto.anjos2@ctjj.org', $secondPerson->institutional_email);
    }

    public function test_it_preserves_an_explicit_institutional_email(): void
    {
        $person = Person::query()->create([
            'full_name' => 'Maria Clara de Souza',
            'institutional_email' => 'secretaria@ctjj.org',
        ]);

        $this->assertSame('secretaria@ctjj.org', $person->institutional_email);
    }
}
