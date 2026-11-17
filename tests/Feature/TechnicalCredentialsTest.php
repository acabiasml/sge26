<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\DiaryAssessment;
use App\Models\DiaryAssessmentResult;
use App\Models\DiaryAttendanceRecord;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\TechnicalProgram;
use App\Models\User;
use App\Support\DocumentVerificationPresenter;
use App\Support\TechnicalEnrollmentOffer;
use App\Support\TechnicalFurnitureProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TechnicalCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_has_exactly_the_two_new_credential_types_and_opens_preparation_before_issuing(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user'])->get(route('document-issuance.index'))->assertOk()
            ->assertSee('Emissão de Certificado Técnico Intermediário')->assertSee('Emissão de Diploma de Curso Técnico');
        $this->getJson(route('document-issuance.targets', ['type' => 'technical-course-diploma', 'q' => 'Estudante']))
            ->assertOk()->assertJsonCount(1, 'targets');
        $this->get(route('document-issuance.issue', ['type' => 'technical-course-diploma', 'target_id' => $s['enrollment']->id]))
            ->assertRedirect(route('technical-credentials.prepare', ['enrollment' => $s['enrollment']->id, 'kind' => 'diploma']));
        $this->get($this->prepareUrl($s))->assertOk()->assertSee('Livro do registro')->assertSee('AINDA NÃO FINALIZADO');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_intermediate_certificate_cannot_be_issued_before_the_actual_module_end_even_with_all_grades(): void
    {
        $s = $this->scenario();
        $this->travelTo(now()->setDate(2025, 3, 30));
        $data = $this->fields($s, 'certificate');
        $data['completed_at'] = '2025-03-01';
        $this->actingAs($s['user'])->post($this->storeUrl($s), $data)->assertSessionHasErrors('module_key');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_certificate_after_module_end_is_saved_as_final_with_the_matrix_qualification_and_exact_hours(): void
    {
        $s = $this->scenario();
        $response = $this->actingAs($s['user'])->post($this->storeUrl($s), $this->fields($s, 'certificate'));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $document = IssuedDocument::sole();
        $this->assertSame('technical-intermediate-certificate', $document->type);
        $this->assertTrue($document->payload['is_final']);
        $this->assertSame('Auxiliar de Projetista de Móveis', $document->payload['module']['qualification']);
        $this->assertEquals(400, $document->payload['total_hours']);
        $this->assertSame([8, 8, 8, 8], array_column($document->payload['components'], 'final_average'));
        $this->assertNotNull($document->registration_key);
        Storage::disk('local')->assertExists($document->file_path);
        $this->get(route('technical-credentials.pdf', $document))->assertOk();
        $this->assertDatabaseCount('issued_documents', 1);
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        if (is_dir('/setup')) {
            file_put_contents('/setup/technical-certificate-sample.pdf', $response->getContent());
        }
    }

    public function test_a_grade_below_six_prevents_certification_of_that_module(): void
    {
        $s = $this->scenario();
        $first = $s['assessments'][0];
        $first->results()->update(['score' => 5]);
        $this->actingAs($s['user'])->post($this->storeUrl($s), $this->fields($s, 'certificate'))->assertSessionHasErrors('module_key');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_missing_registry_or_program_data_prevents_pdf_and_protocol_creation(): void
    {
        $s = $this->scenario();
        $data = $this->fields($s, 'certificate');
        unset($data['registry_book']);
        $this->actingAs($s['user'])->post($this->storeUrl($s), $data)->assertSessionHasErrors('registry_book');
        $s['program']->update(['authorization_act' => null]);
        $this->post($this->storeUrl($s), $this->fields($s, 'certificate'))->assertSessionHasErrors('document');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_diploma_preview_is_available_while_unfinished_without_reserving_an_official_registration(): void
    {
        $s = $this->scenario();
        $response = $this->actingAs($s['user'])->post($this->storeUrl($s), $this->fields($s));
        $response->assertOk();
        $document = IssuedDocument::sole();
        $this->assertSame('technical-course-diploma-preview', $document->type);
        $this->assertFalse($document->payload['is_final']);
        $this->assertNull($document->registration_key);
        $this->assertNull($document->payload['completed_at']);
        $presenter = DocumentVerificationPresenter::make($document);
        $this->assertStringContainsString('AINDA NÃO FINALIZADO', $presenter['type_label']);
        $this->assertStringContainsString('Não comprova', $presenter['description']);
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        if (is_dir('/setup')) {
            file_put_contents('/setup/technical-diploma-preview-sample.pdf', $response->getContent());
        }
    }

    public function test_final_diploma_requires_all_modules_and_high_school_completion_and_keeps_a_snapshot(): void
    {
        $s = $this->scenario();
        $this->completeHighSchool($s);
        $response = $this->actingAs($s['user'])->post($this->storeUrl($s), $this->fields($s));
        $response->assertOk();
        $document = IssuedDocument::sole();
        $this->assertSame('technical-course-diploma', $document->type);
        $this->assertTrue($document->payload['is_final']);
        $this->assertCount(13, $document->payload['components']);
        $this->assertCount(13, array_filter($document->payload['components'], fn ($row) => $row['final_average'] === 8));
        $this->assertEquals(1200, $document->payload['total_hours']);
        $s['program']->update(['authorization_act' => 'Nova autorização']);
        $this->assertSame('Autorização de teste 056/2025', $document->fresh()->payload['program']['authorization_act']);
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        if (is_dir('/setup')) {
            file_put_contents('/setup/technical-diploma-final-sample.pdf', $response->getContent());
        }
    }

    public function test_duplicate_official_registration_is_rejected_but_original_is_available_for_reprinting(): void
    {
        $s = $this->scenario();
        $this->actingAs($s['user'])->post($this->storeUrl($s), $this->fields($s, 'certificate'))->assertOk();
        $this->post($this->storeUrl($s), $this->fields($s, 'certificate'))->assertSessionHasErrors('registry_number');
        $this->assertDatabaseCount('issued_documents', 1);
        $this->get(route('technical-credentials.pdf', IssuedDocument::sole()))->assertOk();
    }

    public function test_managers_from_another_school_and_teachers_cannot_prepare_or_emit(): void
    {
        $s = $this->scenario();
        $other = School::create(['name' => 'Outra escola']);
        $foreign = $this->manager($other);
        $this->actingAs($foreign)->get($this->prepareUrl($s))->assertForbidden();
        $this->post($this->storeUrl($s), $this->fields($s))->assertForbidden();
        $this->get(route('technical-programs.edit', $s['program']))->assertForbidden();
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_integrated_enrollment_is_automatically_concurrent_and_standalone_inference_respects_high_school(): void
    {
        $s = $this->scenario();
        $this->completeHighSchool($s);
        $service = app(TechnicalEnrollmentOffer::class);
        $s['enrollment']->courses()->updateExistingPivot($s['course']->id, ['technical_offer_form' => null]);
        $this->assertSame('subsequente', $service->suggest($s['student'], '2025-02-01'));
        $service->record($s['enrollment']->fresh(), []);
        $this->assertSame('subsequente', $s['enrollment']->fresh()->courses->first()->pivot->technical_offer_form);
        $regular = $s['year']->courses()->create(['name' => '2º Ano', 'stage' => 'medio']);
        $s['enrollment']->courses()->attach($regular);
        $this->assertSame('concomitante', $service->resolve($s['enrollment']->fresh(), $s['course']));
        $service->record($s['enrollment']->fresh(), [$s['course']->id => 'subsequente']);
        $this->assertSame('concomitante', $s['enrollment']->fresh()->courses->firstWhere('id', $s['course']->id)->pivot->technical_offer_form);
    }

    public function test_program_can_store_a_different_technical_course_with_its_own_modules(): void
    {
        $s = $this->scenario();
        $data = $s['program']->only(['school_id', 'graduate_title', 'education_mode', 'offer_forms']);
        $data['name'] = 'Curso Técnico em Informática';
        $data['graduate_title'] = 'Técnico em Informática';
        $data['active'] = 1;
        $data['modules'] = [['name' => 'Fundamentos', 'qualification' => 'Auxiliar de Informática', 'components' => [['name' => 'Redes', 'hours' => 80]]]];
        $this->actingAs($s['user'])->post(route('technical-programs.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $new = TechnicalProgram::where('name', 'Curso Técnico em Informática')->sole();
        $this->assertEquals(80, $new->totalHours());
        $this->assertSame('Auxiliar de Informática', $new->modules[0]['qualification']);
        $this->assertSame(1200.0, $s['program']->fresh()->totalHours());
    }

    public function test_central_program_edits_update_linked_matrices_and_prevent_removing_components_in_use(): void
    {
        $s = $this->scenario();
        $grades = DiaryAssessmentResult::orderBy('id')->get()->toArray();
        $data = $s['program']->toArray();
        $data['modules'][0]['components'][0]['hours'] = 130;
        $this->actingAs($s['user'])->put(route('technical-programs.update', $s['program']), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(130, $s['course']->components()->where('technical_program_component_key', $data['modules'][0]['components'][0]['key'])->sole()->workload_hours);
        $this->assertEquals(1210, $s['course']->fresh()->workload_hours);
        $this->assertSame($grades, DiaryAssessmentResult::orderBy('id')->get()->toArray());
        array_shift($data['modules'][0]['components']);
        $this->put(route('technical-programs.update', $s['program']), $data)->assertSessionHasErrors('modules');
        $this->assertEquals(1210, $s['program']->fresh()->totalHours());
    }

    public function test_abbreviated_signatory_name_is_rejected_before_any_issuance(): void
    {
        $s = $this->scenario();
        $data = $this->fields($s);
        $data['director_name'] = 'D. Fictício';
        $this->actingAs($s['user'])->post($this->storeUrl($s), $data)->assertSessionHasErrors('director_name');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_missing_attendance_prevents_an_intermediate_certificate_even_after_the_module_ends(): void
    {
        $s = $this->scenario();
        DiaryAttendanceRecord::where('curriculum_component_id', $s['assessments'][0]->curriculum_component_id)->delete();
        $this->actingAs($s['user'])->post($this->storeUrl($s), $this->fields($s, 'certificate'))->assertSessionHasErrors('module_key');
        $this->assertDatabaseCount('issued_documents', 0);
    }

    public function test_diploma_is_only_a_preview_when_the_course_end_date_is_still_in_the_future(): void
    {
        $s = $this->scenario();
        $this->completeHighSchool($s);
        $this->travelTo(now()->setDate(2025, 3, 30));
        $this->actingAs($s['user'])->post($this->storeUrl($s), $this->fields($s))->assertOk();
        $this->assertSame('technical-course-diploma-preview', IssuedDocument::sole()->type);
        $this->assertFalse(IssuedDocument::sole()->payload['is_final']);
    }

    public function test_existing_liceu_offers_are_linked_idempotently_without_changing_grades_or_hours(): void
    {
        $s = $this->scenario();
        $this->completeHighSchool($s);
        $s['course']->update(['technical_program_id' => null, 'authorization_act' => 'Autorização preservada', 'authorization_starts_at' => '2025-01-28']);
        $s['course']->components()->update(['technical_program_component_key' => null]);
        $s['program']->delete();
        $s['enrollment']->courses()->updateExistingPivot($s['course']->id, ['technical_offer_form' => null]);
        $grades = DiaryAssessmentResult::orderBy('id')->get()->toArray();
        $hours = $s['course']->components()->pluck('workload_hours', 'id')->all();
        TechnicalFurnitureProject::registerExisting();
        TechnicalFurnitureProject::registerExisting();
        $program = TechnicalProgram::sole();
        $this->assertSame('Autorização preservada', $program->authorization_act);
        $this->assertSame($program->id, $s['course']->fresh()->technical_program_id);
        $this->assertEquals(1200, $s['course']->fresh()->workload_hours);
        $this->assertSame($hours, $s['course']->components()->pluck('workload_hours', 'id')->all());
        $this->assertEquals(13, $s['course']->components()->whereNotNull('technical_program_component_key')->count());
        $this->assertSame($grades, DiaryAssessmentResult::orderBy('id')->get()->toArray());
        $this->assertSame('subsequente', $s['enrollment']->fresh()->courses->first()->pivot->technical_offer_form);
        $program->update(['authorization_starts_at' => '2026-01-01']);
        $this->assertSame('2026-01-01', $s['course']->fresh()->authorization_starts_at->toDateString());
    }

    public function test_long_full_signatory_names_are_preserved_and_the_document_stays_two_pages(): void
    {
        $s = $this->scenario();
        $fields = $this->fields($s, 'certificate');
        $fields['director_name'] = 'Eleonora Maria Francisca Aparecida de Albuquerque Vasconcelos de Oliveira e Silva Fictícia';
        $fields['secretary_name'] = 'Maria Aparecida Francisca de Albuquerque Vasconcelos de Oliveira e Silva Fictícia';
        $response = $this->actingAs($s['user'])->post($this->storeUrl($s), $fields)->assertOk();
        $payload = IssuedDocument::sole()->payload;
        $this->assertSame($fields['director_name'], $payload['registration']['director_name']);
        $this->assertSame($fields['secretary_name'], $payload['registration']['secretary_name']);
        $this->assertSame(2, preg_match_all('/\/Type\s*\/Page\b/', $response->getContent()));
        if (is_dir('/setup')) {
            file_put_contents('/setup/technical-long-names-sample.pdf', $response->getContent());
        }
    }

    public function test_matrix_import_uses_the_central_module_and_does_not_duplicate_components(): void
    {
        $s = $this->scenario();
        $matrix = $s['year']->courses()->create(['name' => 'Nova oferta técnica', 'stage' => 'tecnico', 'class_hour_minutes' => 60, 'technical_program_id' => $s['program']->id]);
        $data = ['modules' => [['key' => $s['program']->modules[0]['key'], 'start' => $s['periods'][0]->id, 'end' => $s['periods'][0]->id]]];
        $url = route('academic-years.courses.technical-components', [$s['year'], $matrix]);
        $this->actingAs($s['user'])->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(4, $matrix->components()->count());
        $this->assertEquals(400, $matrix->fresh()->workload_hours);
        $this->assertEquals(4, $matrix->components()->whereNotNull('technical_program_component_key')->count());
    }

    public function test_final_component_average_uses_all_its_periods_instead_of_the_sum_of_points(): void
    {
        $s = $this->scenario();
        $component = CurriculumComponent::findOrFail($s['assessments'][0]->curriculum_component_id);
        $component->update(['ends_period_id' => $s['periods'][1]->id]);
        $second = DiaryAssessment::create(['school_class_id' => $s['class']->id, 'curriculum_component_id' => $component->id,
            'academic_period_id' => $s['periods'][1]->id, 'title' => 'Média do segundo período', 'assessment_date' => '2025-06-30']);
        $second->results()->create(['student_enrollment_id' => $s['enrollment']->id, 'score' => 6]);
        $this->actingAs($s['user'])->post($this->storeUrl($s), $this->fields($s, 'certificate'))->assertOk();
        $this->assertEquals(7, IssuedDocument::sole()->payload['components'][0]['final_average']);
    }

    private function completeHighSchool(array $s): void
    {
        $history = $s['student']->academicHistories()->create(['school_id' => $s['school']->id, 'title' => 'Ensino Médio', 'education_stage' => 'medio', 'stage' => 'Ensino Médio']);
        $history->years()->create(['position' => 1, 'source' => 'external', 'label' => '3º Ano', 'grade_number' => 3, 'year' => '2024', 'final_result' => 'Aprovado']);
    }

    private function fields(array $s, string $kind = 'diploma'): array
    {
        return ['kind' => $kind, 'course_id' => $s['course']->id, 'module_key' => $s['program']->modules[0]['key'],
            'registry_number' => '123', 'registry_book' => '01', 'registry_folio' => '12', 'registered_by' => 'Responsável Fictício',
            'secretary_name' => 'Secretária Fictícia', 'director_name' => 'Diretor Fictício', 'issued_place' => 'Jarudore, Poxoréu-MT',
            'registration_date' => now()->toDateString(), 'issued_date' => now()->toDateString(), 'observations' => ''];
    }

    private function prepareUrl(array $s): string
    {
        return route('technical-credentials.prepare', ['enrollment' => $s['enrollment'], 'kind' => 'diploma']);
    }

    private function storeUrl(array $s): string
    {
        return route('technical-credentials.store', $s['enrollment']);
    }

    private function manager(School $school): User
    {
        $person = Person::create(['full_name' => 'Gestor de Teste '.$school->id, 'cpf' => str_pad((string) $school->id, 11, '0', STR_PAD_LEFT),
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'mother_name' => 'Maria de Teste',
            'phone' => '(66) 99999-0000', 'address' => 'Rua de Teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800-000', 'profile_completed_at' => now(), 'active' => true]);
        $person->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_MANAGER, 'active' => true, 'started_at' => '2020-01-01']);

        return User::factory()->create(['person_id' => $person->id]);
    }

    private function scenario(): array
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 17));
        $school = School::create(['name' => 'Liceu Pedagógico São Francisco de Assis', 'cnpj' => '00.176.974/0001-20', 'city' => 'Poxoréu', 'state' => 'MT', 'active' => true]);
        $program = TechnicalProgram::create(['school_id' => $school->id, 'name' => 'Curso Técnico em Móveis', 'graduate_title' => 'Técnico em Móveis',
            'technological_axis' => 'Produção Industrial', 'education_mode' => 'Presencial', 'offer_forms' => 'Concomitante e Subsequente',
            'maintainer' => 'Centro Técnico Juvenil de Jarudore – CTJJ', 'creation_act' => 'C. 4668 D.O.M.T. 07/11/1990.', 'authorization_act' => 'Autorização de teste 056/2025',
            'legal_basis' => 'Lei nº 9.394, de 20/12/1996; Lei nº 11.741 de 16/07/2008; e respectivos atos de Autorização de Curso',
            'registry_resolution' => 'Resolução nº 249/07 – CEE/MT', 'front_address' => 'Av. São João, s/n. Jarudore, Poxoréu-MT. CEP 78800-000.',
            'back_address' => 'Rua São João, s/n. Jarudore, Poxoréu-MT. CEP 78800-000. Tel.: (66) 99613-6796.',
            'state_heading' => 'ESTADO DE MATO GROSSO', 'education_system' => 'SISTEMA ESTADUAL DE ENSINO', 'modules' => TechnicalFurnitureProject::modules(), 'active' => true]);
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => 'Técnico 2025', 'reference_year' => 2025, 'starts_at' => '2025-02-01', 'ends_at' => '2025-12-19', 'class_hour_minutes' => 60, 'minimum_attendance_percentage' => 75, 'passing_points' => 24, 'active' => true]);
        $dates = [['2025-02-01', '2025-03-31'], ['2025-04-01', '2025-06-30'], ['2025-08-01', '2025-12-19']];
        $periods = [];
        foreach ($dates as $i => [$start, $end]) {
            $periods[] = $year->periods()->create(['position' => $i + 1, 'name' => 'Módulo '.($i + 1), 'starts_at' => $start, 'ends_at' => $end]);
        }
        $course = $year->courses()->create(['name' => 'Técnico em Móveis', 'stage' => 'tecnico', 'class_hour_minutes' => 60, 'workload_hours' => 1200, 'technical_program_id' => $program->id]);
        $class = $year->classes()->create(['name' => 'Técnico em Móveis', 'starts_period_id' => $periods[0]->id, 'ends_period_id' => $periods[2]->id, 'active' => true]);
        $class->courses()->attach($course);
        $student = Person::create(['full_name' => 'Estudante Fictício do Modelo', 'birth_date' => '2007-05-15', 'nationality' => 'Brasileira', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'mother_name' => 'Mãe Fictícia', 'father_name' => 'Pai Fictício', 'active' => true]);
        $enrollment = $class->enrollments()->create(['person_id' => $student->id, 'enrolled_at' => '2025-02-01', 'status' => 'matriculado', 'type' => 'regular']);
        $enrollment->courses()->attach($course, ['technical_offer_form' => 'concomitante']);
        $assessments = [];
        foreach ($program->modules as $i => $module) {
            foreach ($module['components'] as $definition) {
                $period = $periods[$i];
                $component = $course->components()->create(['name' => $definition['name'], 'workload_hours' => $definition['hours'], 'starts_period_id' => $period->id, 'ends_period_id' => $period->id, 'active' => true, 'technical_program_component_key' => $definition['key']]);
                $assessment = DiaryAssessment::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'title' => 'Média do módulo', 'assessment_date' => $period->ends_at]);
                $assessment->results()->create(['student_enrollment_id' => $enrollment->id, 'score' => 8]);
                $assessments[] = $assessment;
                $record = DiaryAttendanceRecord::create(['school_class_id' => $class->id, 'curriculum_component_id' => $component->id, 'academic_period_id' => $period->id, 'class_date' => $period->ends_at, 'lesson_count' => 2]);
                $record->entries()->create(['student_enrollment_id' => $enrollment->id, 'status' => 'presente', 'attended_lessons' => 2, 'lesson_presence' => [true, true]]);
            }
        }
        $user = $this->manager($school);

        return compact('school', 'program', 'year', 'course', 'class', 'student', 'enrollment', 'user', 'assessments', 'periods');
    }
}
