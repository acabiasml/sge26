<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\School;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItalianInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_pages_translate_controls_and_preserve_record_names_and_form_values(): void
    {
        [$user, $year, $course, $class, $component, $period, $enrollment] = $this->scenario();

        $pages = [
            [route('schools.academic-years.index', $year->school), 'Anni scolastici'],
            [route('academic-years.show', $year), 'Gestisci periodi'],
            [route('academic-years.periods.index', $year), 'Nuovo periodo di valutazione'],
            [route('academic-years.closure', $year), 'Verifica di chiusura'],
            [route('academic-years.courses.show', [$year, $course]), 'Nuovo componente curricolare'],
            [route('academic-years.classes.show', [$year, $class]), 'Riepilogo della classe'],
            [route('academic-years.classes.edit', [$year, $class]), 'Salva classe'],
            [route('academic-years.classes.schedules.index', [$year, $class]), 'Nuova versione dell’orario'],
            [route('classes.enrollments.index', $class), 'Nuova iscrizione'],
            [route('enrollments.documents', $enrollment), 'Documenti disponibili'],
            [route('enrollments.report-card.show', $enrollment), 'Riepilogo della pagella'],
            [route('teacher-diaries.show', [$class, $component, 'period' => $period->id]), 'Salva voti'],
            [route('teacher-diaries.attendance', [$class, $component, 'period' => $period->id]), 'Foglio presenze'],
            [route('teacher-diaries.contents', [$class, $component, 'period' => $period->id]), 'Contenuto per giorno'],
            [route('people.histories.create', $user->person), 'Registra carriera scolastica'],
            [route('official-documents.create'), 'Formattazione del contenuto'],
            [route('schools.concepts.index', $year->school), 'Tabella dei giudizi'],
            [route('people.show', $user->person), 'Dati personali'],
            [route('documents.verify.form'), 'Codice di verifica'],
        ];

        foreach ($pages as [$url, $text]) {
            $response = $this->actingAs($user)->get($url);
            file_put_contents('/tmp/italian-last.html', $response->getContent());
            $response->assertOk()->assertSee($text);
        }

        $this->get(route('academic-years.classes.edit', [$year, $class]))
            ->assertSee('value="Matutino"', false)
            ->assertSeeText('Mattutino')
            ->assertSee('value="Turma de Teste"', false);

        $this->get(route('people.histories.create', $user->person))
            ->assertSee('value="Formação Geral Básica"', false)
            ->assertSee("formation.value === 'Formação Geral Básica'", false)
            ->assertSee('value="Regular"', false)
            ->assertSeeText('Regolare');

        $this->get(route('enrollments.report-card.show', $enrollment))
            ->assertSee('notas=numeros', false)
            ->assertDontSee('voti=numeros', false);

        $this->assertSame('Turma de Teste', $class->fresh()->name);
        $this->assertSame('Matemática', $component->fresh()->name);
    }

    public function test_document_search_translates_generated_labels_without_translating_student_names(): void
    {
        [$user, $year, $course, $class, $component, $period, $enrollment] = $this->scenario();

        $this->actingAs($user)->get(route('document-issuance.index'))
            ->assertOk()
            ->assertSeeText('Centro emissioni')
            ->assertSee('Attesta l’iscrizione attiva dello studente.')
            ->assertSee('value="enrollment-declaration"', false);

        $response = $this->getJson(route('document-issuance.targets', [
            'type' => 'report-card',
            'q' => 'Pessoa de teste',
        ]))->assertOk();
        $target = collect($response->json('targets'))->firstWhere('id', $enrollment->id);
        $this->assertNotNull($target);
        $this->assertSame($user->person->full_name, $target['title']);
        $this->assertStringContainsString('Iscritto', $target['subtitle']);

        $this->get(route('document-issuance.issue', [
            'type' => 'report-card',
            'target_id' => $enrollment->id,
            'score_view' => 'numeros',
        ]))->assertRedirect(route('enrollments.report-card.pdf', [
            'enrollment' => $enrollment, 'notas' => 'numeros',
        ]));
    }

    public function test_validation_and_success_messages_follow_the_language_and_portuguese_can_be_restored(): void
    {
        [$user] = $this->scenario();

        $this->actingAs($user)->post(route('announcements.store'), [])
            ->assertSessionHasErrors(['title' => 'Il campo titolo è obbligatorio.']);

        $this->post(route('announcements.store'), [
            'title' => 'Mensagem da escola',
            'body' => 'Conteúdo preservado',
            'active' => true, 'starts_at' => '2026-03-01',
        ])->assertSessionHas('status', 'Avviso registrato con successo.');

        $this->patch(route('locale.update'), ['locale' => 'pt_BR'])->assertRedirect();
        $this->actingAs($user->fresh())->get(route('document-issuance.index'))
            ->assertOk()->assertSeeText('Central de emissão')
            ->assertSee('Comprova a matrícula ativa do estudante.');
    }

    private function scenario(): array
    {
        $this->travelTo(now()->setDate(2026, 3, 11));
        $person = Person::create([
            'full_name' => 'Pessoa de teste', 'cpf' => '12345678901',
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT',
            'nationality' => 'Brasileira', 'mother_name' => 'Nome da mãe',
            'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT',
            'postal_code' => '78800000', 'active' => true, 'profile_completed_at' => now(),
        ]);
        $person->schoolRoles()->create(['role' => 'administrador', 'active' => true, 'started_at' => '2026-01-01']);
        $user = User::factory()->create(['person_id' => $person->id, 'locale' => 'it']);
        $school = School::create(['name' => 'Escola de teste', 'active' => true]);
        $year = AcademicYear::create([
            'school_id' => $school->id, 'name' => 'Ano de teste', 'reference_year' => 2026,
            'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31', 'active' => true,
            'approved_at' => '2025-12-01', 'class_hour_minutes' => 50,
        ]);
        $period = $year->periods()->create([
            'name' => 'I Bimestre', 'starts_at' => '2026-02-01', 'ends_at' => '2026-04-10', 'position' => 1,
        ]);
        $year->days()->create(['date' => '2026-03-11', 'type' => 'letivo', 'counts_as_school_day' => true]);
        $course = $year->courses()->create([
            'name' => 'Matriz de teste', 'stage' => AcademicCourse::STAGE_HIGH_SCHOOL,
            'status' => 'iniciado', 'active' => true, 'class_hour_minutes' => 50,
        ]);
        $component = $course->components()->create([
            'name' => 'Matemática', 'weekly_lessons' => 5, 'workload_hours' => 160, 'active' => true,
        ]);
        $class = $year->classes()->create(['name' => 'Turma de teste', 'shift' => 'matutino', 'active' => true]);
        $class->courses()->attach($course);
        SchoolClassComponent::create([
            'school_class_id' => $class->id, 'curriculum_component_id' => $component->id,
            'teacher_person_id' => $person->id, 'active' => true,
        ]);
        $enrollment = $class->enrollments()->create([
            'person_id' => $person->id, 'enrolled_at' => '2026-02-01',
            'status' => StudentEnrollment::STATUS_ENROLLED, 'type' => StudentEnrollment::TYPE_REGULAR,
        ]);
        $enrollment->courses()->attach($course);

        return [$user, $year, $course, $class, $component, $period, $enrollment];
    }
}
