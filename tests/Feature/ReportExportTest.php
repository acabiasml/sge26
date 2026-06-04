<?php

namespace Tests\Feature;

use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_report_creates_verifiable_document_code(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('reports.pdf', 'schools'))
            ->assertOk();

        $document = IssuedDocument::query()->firstOrFail();

        $this->assertStringStartsWith('BEABA-', $document->verification_code);

        $this->get(route('documents.verify', $document->verification_code))
            ->assertOk()
            ->assertSee($document->verification_code);
    }

    public function test_excel_report_can_be_downloaded(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('reports.excel', 'schools'))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_school_record_pdf_creates_verifiable_document_code(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $school = School::query()->create(['name' => 'Escola A', 'active' => true]);

        $this->actingAs($admin)
            ->get(route('schools.pdf', $school))
            ->assertOk();

        $document = IssuedDocument::query()
            ->where('type', 'school-record')
            ->firstOrFail();

        $this->assertSame($school->id, $document->school_id);
        $this->assertStringStartsWith('BEABA-', $document->verification_code);
    }

    public function test_person_record_pdf_creates_verifiable_document_code(): void
    {
        $admin = $this->userWithRole(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $person = Person::query()->create([
            'full_name' => 'Maria Silva',
            'institutional_email' => 'maria@ctjj.org',
            'cpf' => '11122233344',
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('people.pdf', $person))
            ->assertOk();

        $document = IssuedDocument::query()
            ->where('type', 'person-record')
            ->firstOrFail();

        $this->assertSame($person->id, $document->person_id);
        $this->assertStringStartsWith('BEABA-', $document->verification_code);
    }

    private function userWithRole(string $role): User
    {
        $person = Person::query()->create([
            'full_name' => 'Pessoa '.$role,
            'institutional_email' => str($role)->ascii()->slug()->value().'@ctjj.org',
            'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01',
            'phone' => '(65) 99999-0000',
            'profile_completed_at' => now(),
        ]);

        $person->schoolRoles()->create([
            'role' => $role,
            'active' => true,
            'started_at' => now()->subMonth()->toDateString(),
        ]);

        return User::factory()->create([
            'person_id' => $person->id,
            'email' => $person->institutional_email,
        ]);
    }
}
