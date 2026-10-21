<?php

namespace Tests\Feature;

use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use App\Support\BrazilianMoney;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VolunteerReceiptTest extends TestCase
{
    use RefreshDatabase;

    private int $schoolSequence = 0;

    private int $personSequence = 0;

    public function test_management_can_generate_a_verified_receipt_and_non_management_cannot(): void
    {
        $school = $this->school('Escola A');
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school);
        $teacher = $this->userWithRole(PersonSchoolRole::ROLE_TEACHER, $school);
        $volunteer = $this->personWithRole('Voluntária', PersonSchoolRole::ROLE_EMPLOYEE, $school);
        $params = $this->receiptParameters($school);

        $this->actingAs($manager)->get(route('document-issuance.index'))->assertOk()->assertSee('value="volunteer-receipt"', false);
        $this->getJson(route('document-issuance.targets', ['type' => 'volunteer-receipt', 'q' => 'Voluntária']))
            ->assertOk()->assertJsonPath('targets.0.id', $volunteer->id);
        $this->get(route('document-issuance.issue', array_merge($params, ['type' => 'volunteer-receipt', 'target_id' => $volunteer->id])))
            ->assertRedirect(route('people.volunteer-receipt', array_merge(['person' => $volunteer], $params)));
        $response = $this->get(route('people.volunteer-receipt', array_merge(['person' => $volunteer], $params)));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $issued = IssuedDocument::query()->where('type', 'volunteer-receipt')->sole();
        $this->assertSame('450.00', $issued->payload['amount']);
        $this->assertSame('quatrocentos e cinquenta reais', $issued->payload['amount_words']);
        $this->assertSame($volunteer->full_name, $issued->payload['person_name']);
        $this->assertSame($school->id, $issued->school_id);
        $this->get(route('documents.verify', $issued->verification_code))->assertOk()->assertSee('Recibo de atividade voluntária');

        $this->actingAs($teacher)->get(route('document-issuance.index'))->assertDontSee('value="volunteer-receipt"', false);
        $this->get(route('people.volunteer-receipt', array_merge(['person' => $volunteer], $params)))->assertForbidden();
    }

    public function test_receipt_checks_school_person_and_input_on_direct_url(): void
    {
        $school = $this->school('Escola A');
        $other = $this->school('Escola B');
        $manager = $this->userWithRole(PersonSchoolRole::ROLE_MANAGER, $school);
        $volunteer = $this->personWithRole('Voluntária', PersonSchoolRole::ROLE_EMPLOYEE, $school);
        $outsider = $this->personWithRole('Outra pessoa', PersonSchoolRole::ROLE_EMPLOYEE, $other);
        $params = $this->receiptParameters($school);
        $this->actingAs($manager);

        $this->get(route('people.volunteer-receipt', array_merge(['person' => $outsider], $params)))->assertNotFound();
        $this->get(route('people.volunteer-receipt', array_merge(['person' => $volunteer], $this->receiptParameters($other))))->assertForbidden();
        foreach ([['amount' => '-1'], ['period_end' => '2026-06-01'], ['issued_date' => 'x']] as $invalid) {
            $this->getJson(route('people.volunteer-receipt', array_merge(['person' => $volunteer], $params, $invalid)))
                ->assertUnprocessable();
        }
        $this->assertSame(0, IssuedDocument::query()->count());
    }

    public function test_amount_in_words_handles_cents_and_thousands(): void
    {
        $this->assertSame('um real e um centavo', BrazilianMoney::words('1.01'));
        $this->assertSame('mil e quinhentos reais', BrazilianMoney::words('1500.00'));
        $this->assertSame('quatrocentos e cinquenta reais', BrazilianMoney::words('450.00'));
    }

    private function receiptParameters(School $school): array
    {
        return ['receipt_school_id' => $school->id, 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'amount' => '450.00', 'issued_date' => '2026-08-04'];
    }

    private function school(string $name): School
    {
        $this->schoolSequence++;

        return School::query()->create([
            'name' => $name, 'legal_name' => $name, 'cnpj' => sprintf('00.176.974/%04d-20', $this->schoolSequence), 'inep' => str_pad((string) $this->schoolSequence, 8, '0', STR_PAD_LEFT),
            'founded_at' => '1990-10-04', 'phone' => '66999999999', 'email' => 'escola'.$this->schoolSequence.'@example.org',
            'letterhead_text' => 'Instituição de ensino', 'address' => 'Rua Principal', 'city' => 'Poxoréu',
            'state' => 'MT', 'postal_code' => '78800-000', 'active' => true,
        ]);
    }

    private function userWithRole(string $role, School $school): User
    {
        $person = $this->personWithRole('Usuário '.uniqid(), $role, $school);

        return User::factory()->create(['person_id' => $person->id, 'name' => $person->full_name, 'email' => $person->institutional_email]);
    }

    private function personWithRole(string $name, string $role, School $school): Person
    {
        $this->personSequence++;
        $person = Person::query()->create([
            'full_name' => $name, 'institutional_email' => uniqid().'@ctjj.org', 'cpf' => str_pad((string) $this->personSequence, 11, '0', STR_PAD_LEFT),
            'address' => 'Rua das Flores', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800-000',
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT',
            'nationality' => 'Brasileira', 'mother_name' => 'Maria', 'profile_completed_at' => now(), 'active' => true,
        ]);
        $person->schoolRoles()->create(['school_id' => $school->id, 'role' => $role, 'active' => true, 'started_at' => '2026-01-01']);

        return $person;
    }
}
