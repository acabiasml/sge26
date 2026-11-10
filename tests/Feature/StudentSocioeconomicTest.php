<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSocioeconomicTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_can_register_edit_and_clear_socioeconomic_fields_without_turning_unknown_into_no(): void
    {
        [$manager, $enrollment] = $this->fixture();
        $student = $enrollment->student;
        $this->actingAs($manager)->get(route('people.edit', $student))->assertOk()->assertSee('Ficha socioeconômica')->assertSee('socioeconomic_data[rg_number]', false);
        $data = $student->only(['full_name', 'cpf', 'birth_date', 'birth_city', 'birth_state', 'nationality', 'mother_name', 'address', 'city', 'state', 'postal_code']);
        $data['birth_date'] = $student->birth_date->toDateString();
        $profile = ['rg_number' => 'RG-001', 'sus_card' => '000000000000015', 'has_disability' => '0', 'image_use_authorized' => null,
            'lives_with' => ['mae', 'pai'], 'receives_bolsa_familia' => '1', 'benefits' => ['alimentacao'], 'benefit_year' => 2026];
        $this->put(route('people.update', $student), $data + ['socioeconomic_profile_present' => 1, 'socioeconomic_data' => $profile])->assertSessionHasNoErrors();
        $saved = $student->fresh()->socioeconomic_data;
        $this->assertFalse($saved['has_disability']);
        $this->assertNull($saved['image_use_authorized']);
        $this->assertSame('000000000000015', $saved['sus_card']);
        $this->assertTrue($saved['receives_bolsa_familia']);
        $this->put(route('people.update', $student), $data)->assertSessionHasNoErrors();
        $this->assertSame($saved, $student->fresh()->socioeconomic_data);
        $this->put(route('people.update', $student), $data + ['socioeconomic_data' => ['rg_number' => 'RG-002']])->assertSessionHasNoErrors();
        $this->assertSame(['alimentacao'], $student->fresh()->socioeconomic_data['benefits']);
        $this->put(route('people.update', $student), $data + ['socioeconomic_profile_present' => 1, 'socioeconomic_data' => ['rg_number' => null]])->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->socioeconomic_data['rg_number']);
        $this->assertSame([], $student->fresh()->socioeconomic_data['benefits']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Person::class, 'auditable_id' => $student->id, 'actor_user_id' => $manager->id, 'action' => 'updated']);
    }

    public function test_creation_accepts_the_new_fields_and_rejects_invalid_or_forged_data_without_partial_saves(): void
    {
        $this->freezeTime();
        [$manager] = $this->fixture();
        $admin = User::factory()->create(['person_id' => $this->person('Admin', PersonSchoolRole::ROLE_ADMINISTRATOR)->id]);
        $this->actingAs($admin)->post(route('people.store'), ['full_name' => 'Novo estudante', 'socioeconomic_data' => ['race' => 'parda', 'rg_number' => '0123']])->assertSessionHasNoErrors();
        $student = Person::where('full_name', 'Novo Estudante')->sole();
        $this->assertSame('0123', $student->socioeconomic_data['rg_number']);
        $this->assertSame(now()->utc()->toIso8601String(), $student->socioeconomic_data['assessed_at']);
        $before = $student->getAttributes();
        foreach ([['race' => 'inventada'], ['blood_type' => 'C+'], ['has_disability' => 'talvez'], ['sus_card' => '123'], ['benefits' => ['alimentacao']],
            ['benefits' => ['inventado'], 'benefit_year' => 2026], ['rg_issued_at' => '2099-01-01'], ['benefit_year' => 3000], ['role' => 'administrador']] as $invalid) {
            $this->put(route('people.update', $student), ['full_name' => $student->full_name, 'socioeconomic_data' => $invalid])->assertSessionHasErrors();
            $this->assertSame($before, $student->fresh()->getAttributes());
        }
    }

    public function test_management_can_issue_from_the_panel_and_pdf_has_two_pages_and_only_this_schools_enrollments(): void
    {
        [$manager, $enrollment] = $this->fixture();
        $student = $enrollment->student;
        $student->update(['socioeconomic_data' => ['sex' => 'feminino', 'rg_number' => 'RG-SECRETO', 'has_disability' => false,
            'residential_zone' => 'rural', 'race' => 'parda', 'family_income_range' => '2_a_3', 'image_use_authorized' => null,
            'benefit_year' => 2026, 'benefits' => ['alimentacao'], 'notes' => 'Observação restrita']]);
        $student->contacts()->create(['name' => 'Responsável de teste', 'relationship_type' => 'mae', 'legal_guardian' => true, 'phone' => '(66) 99999-1111', 'nis' => '00000000001']);
        $other = $this->enrollment($student, $this->school('Outra escola'), 2025, 'TURMA FORA DO ESCOPO');
        $previous = $this->enrollment($student, $enrollment->schoolClass->academicYear->school, 2025, 'Ano anterior');
        $previous->update(['status' => StudentEnrollment::STATUS_TRANSFERRED, 'transferred_at' => '2025-10-01']);
        $this->actingAs($manager)->get(route('document-issuance.index'))->assertOk()->assertSee('value="socioeconomic-form"', false);
        $this->getJson(route('document-issuance.targets', ['type' => 'socioeconomic-form']))->assertOk()->assertJsonCount(2, 'targets');
        $this->get(route('document-issuance.issue', ['type' => 'socioeconomic-form', 'target_id' => $enrollment->id]))->assertRedirect(route('enrollments.socioeconomic.pdf', $enrollment));
        $this->get(route('enrollments.socioeconomic.pdf', $other))->assertForbidden();
        $renderData = null;
        $renderer = null;
        $declarationBottom = $signatureBottom = null;
        $footerTop = [];
        $headerBottom = $bodyTop = [];
        view()->composer('reports.records.socioeconomic', function ($view) use (&$renderData): void { $renderData = $view->getData(); });
        app()->afterResolving('dompdf.wrapper', function ($pdf) use (&$renderer, &$declarationBottom, &$signatureBottom, &$footerTop, &$headerBottom, &$bodyTop): void {
            $renderer = $pdf;
            $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$declarationBottom, &$signatureBottom, &$footerTop, &$headerBottom, &$bodyTop): void {
                $node = $frame->get_node();
                if (! $node instanceof \DOMElement) return;
                $box = $frame->get_border_box();
                $page = $canvas->get_page_number();
                if ($node->getAttribute('class') === 'declaration') $declarationBottom = $box['y'] + $box['h'];
                if ($node->getAttribute('class') === 'signatures') $signatureBottom = $box['y'] + $box['h'];
                if ($node->getAttribute('class') === 'document-footer') $footerTop[$page] = $box['y'];
                if ($node->tagName === 'header') $headerBottom[$page] = $box['y'] + $box['h'];
                if ($node->getAttribute('class') === 'subtitle' || ($node->tagName === 'h2' && trim($node->textContent) === 'Matrículas')) $bodyTop[$page] = $box['y'];
            }]]);
        });
        $response = $this->get(route('enrollments.socioeconomic.pdf', $enrollment))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame(2, $renderer->getDomPDF()->getCanvas()->get_page_count());
        $this->assertNotNull($declarationBottom);
        $this->assertNotNull($signatureBottom);
        $this->assertLessThan($footerTop[1] - 5, $declarationBottom);
        $this->assertLessThan($footerTop[2] - 5, $signatureBottom);
        foreach ($bodyTop as $page => $top) $this->assertGreaterThan($headerBottom[$page] + 3, $top);
        $this->assertSame([$previous->id, $enrollment->id], $renderData['enrollments']->modelKeys());
        $html = view('reports.records.socioeconomic', $renderData)->render();
        foreach (['RG-SECRETO', 'Rural', 'Responsável de Teste', '(X) Alimentação', 'per capita', 'Nº de registro', '2026', $previous->schoolClass->name] as $text) $this->assertStringContainsString($text, $html);
        $this->assertStringNotContainsString('TURMA FORA DO ESCOPO', $html);
        $this->assertStringContainsString('Autorização de uso de imagem:</strong> Não informado', $html);
        $this->assertStringContainsString('NIS do beneficiário:</strong> Não informado', $html);
        $issued = IssuedDocument::where('type', 'student-socioeconomic')->sole();
        $public = $this->get(route('documents.verify', $issued->verification_code))->assertOk()->assertSee('Ficha socioeconômica');
        foreach (['RG-SECRETO', 'Observação restrita', $student->cpf, 'family_income_range', 'has_disability'] as $secret) {
            $public->assertDontSee($secret);
            $this->assertStringNotContainsString($secret, json_encode($issued->payload));
        }
    }

    public function test_teachers_students_and_other_schools_cannot_read_change_or_issue_the_profile(): void
    {
        [$manager, $enrollment] = $this->fixture();
        $school = $enrollment->schoolClass->academicYear->school;
        foreach ([PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_STUDENT] as $role) {
            $user = User::factory()->create(['person_id' => $this->person('Outro perfil', $role, $school->id)->id]);
            $this->actingAs($user)->get(route('enrollments.socioeconomic.pdf', $enrollment))->assertForbidden();
            $this->getJson(route('document-issuance.targets', ['type' => 'socioeconomic-form']))->assertUnprocessable();
            $this->put(route('people.update', $enrollment->student), ['full_name' => 'Tentativa', 'socioeconomic_data' => ['rg_number' => 'Inválido']])->assertForbidden();
        }
        $outsider = User::factory()->create(['person_id' => $this->person('Gestão externa', PersonSchoolRole::ROLE_MANAGER, $this->school('Escola externa')->id)->id]);
        $this->actingAs($outsider)->get(route('people.edit', $enrollment->student))->assertForbidden();
        $this->get(route('enrollments.socioeconomic.pdf', $enrollment))->assertForbidden();
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_blank_profiles_remain_unknown_and_benefits_from_another_year_are_not_assumed(): void
    {
        [$manager, $enrollment] = $this->fixture();
        $this->actingAs($manager);
        $student = $enrollment->student;
        $student->update(['receives_federal_aid' => true]);
        $data = null;
        view()->composer('reports.records.socioeconomic', function ($view) use (&$data): void { $data = $view->getData(); });
        $this->get(route('enrollments.socioeconomic.pdf', $enrollment))->assertOk();
        $html = view('reports.records.socioeconomic', $data)->render();
        $this->assertStringContainsString('Recebe Bolsa Família:</strong> Não informado', $html);
        $this->assertStringContainsString('Possui deficiência (PCD):</strong> Não informado', $html);
        $this->assertStringContainsString('Autorização de uso de imagem:</strong> Não informado', $html);
        $this->assertStringNotContainsString('(X)', $html);
        $student->update(['socioeconomic_data' => ['benefit_year' => 2025, 'benefits' => ['alimentacao'], 'notes' => '<script>alert(1)</script>']]);
        $this->get(route('enrollments.socioeconomic.pdf', $enrollment))->assertOk();
        $html = view('reports.records.socioeconomic', $data)->render();
        $this->assertStringContainsString('Concessão de benefícios não informada para este ano.', $html);
        $this->assertStringNotContainsString('(X) Alimentação', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_last_save_time_is_automatic_for_management_and_self_service_and_cannot_be_forged(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-10T02:30:00+00:00'));
        [$manager, $enrollment] = $this->fixture();
        $student = $enrollment->student;
        $data = $student->only(['full_name', 'cpf', 'birth_city', 'birth_state', 'nationality', 'mother_name', 'address', 'city', 'state', 'postal_code']);
        $data['birth_date'] = $student->birth_date->toDateString();
        $payload = $data + ['socioeconomic_data' => ['rg_number' => 'RG-001', 'assessed_at' => '1900-01-01']];
        $this->actingAs($manager)->put(route('people.update', $student), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10T02:30:00+00:00', $student->fresh()->socioeconomic_data['assessed_at']);
        $this->assertSame('09/11/2026 22:30', \App\Support\SocioeconomicProfile::lastSavedLabel($student->fresh()->socioeconomic_data));

        $this->travel(15)->minutes();
        $this->put(route('people.update', $student), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10T02:45:00+00:00', $student->fresh()->socioeconomic_data['assessed_at']);
        $this->assertSame('RG-001', $student->fresh()->socioeconomic_data['rg_number']);
        $saved = $student->fresh()->socioeconomic_data;
        $this->travel(5)->minutes();
        $this->put(route('people.update', $student), $data)->assertSessionHasNoErrors();
        $this->assertSame($saved, $student->fresh()->socioeconomic_data);

        $self = User::factory()->create(['person_id' => $student->id]);
        $this->actingAs($self)->put(route('profile.update'), $data + ['socioeconomic_data' => ['has_disability' => '0', 'assessed_at' => '2099-01-01']])->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10T02:50:00+00:00', $student->fresh()->socioeconomic_data['assessed_at']);
        $this->travel(5)->minutes();
        $this->put(route('profile.update'), $data + ['socioeconomic_data' => ['sus_card' => '123']])->assertSessionHasErrors();
        $this->assertSame('2026-11-10T02:50:00+00:00', $student->fresh()->socioeconomic_data['assessed_at']);

        foreach ([route('profile.edit'), route('people.edit', $student)] as $url) {
            $this->actingAs($manager)->get($url)->assertOk()->assertDontSee('name="socioeconomic_data[assessed_at]"', false)
                ->assertDontSee('Data de atualização da ficha socioeconômica');
        }
    }

    public function fixture(): array
    {
        $school = $this->school('Escola de teste');
        $manager = User::factory()->create(['person_id' => $this->person('Gestão de teste', PersonSchoolRole::ROLE_MANAGER, $school->id)->id]);
        $student = $this->person('Estudante de teste', PersonSchoolRole::ROLE_STUDENT, $school->id);

        return [$manager, $this->enrollment($student, $school, 2026, '9º Ano')];
    }

    private function school(string $name): School
    {
        return School::create(['name' => $name, 'legal_name' => $name, 'cnpj' => fake()->unique()->numerify('##############'), 'inep' => fake()->unique()->numerify('########'), 'founded_at' => '2000-01-01',
            'phone' => '(66) 3000-0000', 'email' => 'escola@example.org', 'letterhead_text' => 'Autorização da escola', 'address' => 'Rua da escola', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800-000', 'active' => true]);
    }

    private function person(string $name, string $role, ?int $schoolId = null): Person
    {
        $person = Person::create(['full_name' => $name, 'cpf' => fake()->unique()->numerify('###########'), 'birth_date' => '2007-01-01', 'birth_city' => 'Poxoréu',
            'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Mãe de teste', 'address' => 'Rua da escola', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800-000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'school_id' => $schoolId, 'active' => true]);

        return $person;
    }

    private function enrollment(Person $student, School $school, int $reference, string $className): StudentEnrollment
    {
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => 'Ano '.$reference, 'reference_year' => $reference, 'starts_at' => $reference.'-01-01', 'ends_at' => $reference.'-12-31']);
        $course = $year->courses()->create(['name' => 'Ensino Fundamental', 'stage' => AcademicCourse::STAGE_ELEMENTARY, 'modality' => 'regular']);
        $class = $year->classes()->create(['name' => $className, 'shift' => 'Matutino', 'active' => true]);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => $reference.'-02-01', 'status' => 'matriculado', 'type' => 'regular']);
        $enrollment->courses()->attach($course);

        return $enrollment;
    }
}
