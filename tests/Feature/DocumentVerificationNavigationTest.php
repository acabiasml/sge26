<?php

namespace Tests\Feature;

use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentVerificationNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_lookup_and_all_results_keep_application_navigation(): void
    {
        $user = User::factory()->create(['theme' => 'govbr']);
        $document = $this->document();

        $this->actingAs($user)->get(route('documents.verify.form'))
            ->assertOk()->assertSee('id="accordionSidebar"', false)
            ->assertSee('data-theme="govbr"', false)->assertSee('Voltar ao início');

        $this->post(route('documents.verify.lookup'), ['code' => strtolower($document->verification_code)])
            ->assertRedirect(route('documents.verify', $document->verification_code));

        foreach ([false, true] as $revoked) {
            $document->update(['revoked_at' => $revoked ? now() : null]);
            $this->get(route('documents.verify', $document->verification_code))
                ->assertOk()->assertSee($revoked ? 'Documento revogado' : 'Documento válido')
                ->assertSee('id="accordionSidebar"', false)->assertSee('Voltar ao início')
                ->assertSee('href="'.route('documents.verify.form').'"', false)
                ->assertDontSee('Voltar ao site do CTJJ');
        }

        $this->get(route('documents.verify', 'BEABA-NOT-FOUND'))
            ->assertNotFound()->assertSee('id="accordionSidebar"', false)
            ->assertSee('Verificar outro documento')->assertSee('Voltar ao início');

        $this->from(route('documents.verify.form'))->post(route('documents.verify.lookup'), ['code' => ''])
            ->assertRedirect(route('documents.verify.form'))->assertSessionHasErrors('code');
    }

    public function test_public_verification_remains_available_without_login(): void
    {
        $document = $this->document();
        $this->get(route('documents.verify.form'))->assertOk()
            ->assertDontSee('id="accordionSidebar"', false)->assertSee('Código de verificação');
        $this->get(route('documents.verify', $document->verification_code))->assertOk()
            ->assertSee('Documento válido')->assertSee('Voltar ao site do CTJJ')
            ->assertSee('href="'.route('documents.verify.form').'"', false)
            ->assertDontSee('id="accordionSidebar"', false);
        $this->get(route('documents.verify', 'BEABA-NOT-FOUND'))->assertNotFound()
            ->assertSee('Verificar outro documento')->assertDontSee('id="accordionSidebar"', false);
    }

    public function test_public_verification_shows_institution_contact_and_active_managers(): void
    {
        $school = School::query()->create([
            'name' => 'Escola de Teste',
            'phone' => '(66) 99999-1111',
            'email' => 'contato@escola.test',
            'website' => 'https://escola.test',
            'address' => 'Rua das Flores',
            'number' => '123',
            'district' => 'Centro',
            'city' => 'Poxoréu',
            'state' => 'MT',
            'postal_code' => '78800-000',
            'active' => true,
        ]);
        $manager = Person::query()->create(['full_name' => 'Maria Gestora', 'active' => true]);
        PersonSchoolRole::query()->create([
            'person_id' => $manager->id,
            'school_id' => $school->id,
            'role' => PersonSchoolRole::ROLE_MANAGER,
            'position' => PersonSchoolRole::POSITION_DIRECTOR,
            'active' => true,
        ]);

        $document = $this->document();
        $document->update(['school_id' => $school->id]);

        $this->get(route('documents.verify', $document->verification_code))
            ->assertOk()
            ->assertSee('Escola de Teste')
            ->assertSee('(66) 99999-1111')
            ->assertSee('contato@escola.test')
            ->assertSee('https://escola.test')
            ->assertSee('Rua das Flores, nº 123, Centro, Poxoréu - MT, CEP 78800-000')
            ->assertSee('Maria Gestora')
            ->assertSee('Direção');
    }

    private function document(): IssuedDocument
    {
        $person = Person::query()->create(['full_name' => 'Pessoa de teste', 'active' => true]);

        return IssuedDocument::query()->create([
            'uuid' => (string) Str::uuid(), 'verification_code' => 'BEABA-ABCD-EFGH-IJKL',
            'type' => 'official-document', 'person_id' => $person->id,
            'payload' => ['title' => 'Documento de teste'], 'issued_at' => now(),
        ]);
    }
}
