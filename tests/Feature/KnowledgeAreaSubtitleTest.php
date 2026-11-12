<?php

namespace Tests\Feature;

use App\Livewire\HistoryCurriculumComponentsTable;
use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\User;
use App\Support\CurriculumCatalog;
use App\Support\HistoryCurriculumCatalog;
use App\Support\StudentAcademicHistoryPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KnowledgeAreaSubtitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_edit_and_clear_an_optional_subtitle(): void
    {
        $this->actingAs($this->user(PersonSchoolRole::ROLE_ADMINISTRATOR));
        $definition = ['name' => 'Técnico em Móveis', 'formation' => 'Formação Técnica Profissional'];
        $this->post(route('knowledge-areas.store'), $definition)->assertSessionHasNoErrors();
        $area = KnowledgeArea::where('name', $definition['name'])->sole();
        $this->assertNull($area->subtitle);
        $this->assertSame($area->name, $area->selectionLabel());

        $subtitle = 'Credenciamento: Portaria nº 123/2026. Autorização: Resolução CEE/MT nº 456/2026.';
        $this->put(route('knowledge-areas.update', $area), $definition + ['subtitle' => $subtitle])->assertSessionHasNoErrors();
        $this->assertSame($subtitle, $area->fresh()->subtitle);
        $this->get(route('knowledge-areas.index', ['q' => 'Técnico em Móveis']))
            ->assertOk()->assertSee('name="subtitle"', false)->assertSee($subtitle);
        $this->get(route('knowledge-areas.usages', $area))->assertOk()->assertSee($subtitle);

        $this->put(route('knowledge-areas.update', $area), $definition + ['subtitle' => str_repeat('x', 1001)])
            ->assertSessionHasErrors('subtitle');
        $this->assertSame($subtitle, $area->fresh()->subtitle);
        $this->put(route('knowledge-areas.update', $area), $definition + ['subtitle' => ''])->assertSessionHasNoErrors();
        $this->assertNull($area->fresh()->subtitle);
        $this->get(route('knowledge-areas.usages', $area))->assertOk()->assertDontSee($subtitle);
        $this->assertStringNotContainsString('sge-area-subtitle', view('partials.knowledge-area-label', ['labelArea' => $area->fresh()])->render());
    }

    public function test_non_administrators_cannot_register_or_change_area_subtitles(): void
    {
        $area = KnowledgeArea::create(['name' => 'Área protegida', 'formation' => 'Parte Complementar']);
        foreach ([PersonSchoolRole::ROLE_MANAGER, PersonSchoolRole::ROLE_TEACHER, PersonSchoolRole::ROLE_STUDENT] as $role) {
            $this->actingAs($this->user($role));
            $data = ['name' => $area->name, 'formation' => $area->formation, 'subtitle' => 'Alteração indevida'];
            $this->post(route('knowledge-areas.store'), $data)->assertForbidden();
            $this->put(route('knowledge-areas.update', $area), $data)->assertForbidden();
        }
        $this->assertNull($area->fresh()->subtitle);
    }

    public function test_existing_histories_and_matrices_use_the_current_subtitle_without_changing_results(): void
    {
        $admin = $this->user(PersonSchoolRole::ROLE_ADMINISTRATOR);
        $this->actingAs($admin);
        $area = KnowledgeArea::create(['name' => 'Técnico em Móveis', 'formation' => 'Formação Técnica Profissional']);
        $school = School::create(['name' => 'Escola de teste']);
        $academicYear = AcademicYear::create(['school_id' => $school->id, 'name' => '2026', 'reference_year' => 2026,
            'starts_at' => '2026-01-01', 'ends_at' => '2026-12-31']);
        $course = $academicYear->courses()->create(['name' => 'Curso de Móveis', 'stage' => 'tecnico']);
        $matrixComponent = $course->components()->create(['name' => 'Desenho Técnico', 'knowledge_area_id' => $area->id]);
        $history = StudentAcademicHistory::create(['person_id' => $admin->person_id, 'school_id' => $school->id,
            'title' => 'Histórico de teste', 'education_stage' => 'tecnico', 'is_unified' => true]);
        $year = $history->years()->create(['label' => 'Módulo I', 'year' => '2026', 'source' => 'external', 'transcript_mode' => 'detailed']);
        $component = $history->components()->create(['name' => 'Desenho Técnico', 'formation' => $area->formation,
            'knowledge_area' => $area->name, 'module_label' => 'Módulo I']);
        $component->catalogEntries()->attach($matrixComponent->historyCatalogEntries()->sole());
        $record = $component->records()->create(['student_academic_history_year_id' => $year->id,
            'score_label' => '8,5', 'workload_hours' => 80, 'completed_workload_hours' => 75]);
        $snapshot = [$matrixComponent->fresh()->getAttributes(), $component->fresh()->getAttributes(), $record->fresh()->getAttributes()];

        $subtitle = 'Credenciamento nº 123/2026 · Autorização CEE/MT nº 456/2026';
        $area->update(['subtitle' => $subtitle]);
        $history->load(['years.records', 'components.records', 'school']);
        foreach (['student-histories._technical-components-table', 'reports.partials.technical-history-matrix', 'reports.partials.basic-history-matrix'] as $view) {
            $html = view($view, ['history' => $history])->render();
            $this->assertStringContainsString('<small class="sge-area-subtitle">'.$subtitle.'</small>', $html);
        }
        Livewire::test(HistoryCurriculumComponentsTable::class)->set('search', 'Desenho Técnico')->assertSee($subtitle);
        $this->get(route('academic-years.courses.show', [$academicYear, $course]))->assertOk()->assertSee($subtitle);
        $this->get(route('academic-years.courses.components.show', [$academicYear, $course, $matrixComponent]))->assertOk()->assertSee($subtitle);
        $this->assertStringContainsString($subtitle, CurriculumCatalog::suggestionsForCourse($course)[0]['area_label']);
        $this->assertSame($subtitle, collect(HistoryCurriculumCatalog::all()['tecnico'])->firstWhere('name', 'Desenho Técnico')['area_subtitle']);

        $issued = new IssuedDocument(['verification_code' => 'PREVIA', 'issued_at' => now()]);
        $issued->setRelation('issuedBy', null);
        $pdf = app(StudentAcademicHistoryPdf::class)->make(['history' => $history, 'person' => $admin->person,
            'issuedDocument' => $issued, 'verificationUrl' => 'https://example.org/verificar/PREVIA',
            'letterhead' => ['lines' => ['Escola de teste']]]);
        $this->assertStringStartsWith('%PDF-', $pdf->output());

        $area->update(['subtitle' => 'Autorização retificada nº 789/2026']);
        $updatedHtml = view('reports.partials.technical-history-matrix', ['history' => $history])->render();
        $this->assertStringContainsString('Autorização retificada nº 789/2026', $updatedHtml);
        $this->assertStringNotContainsString($subtitle, $updatedHtml);
        $this->assertSame($snapshot, [$matrixComponent->fresh()->getAttributes(), $component->fresh()->getAttributes(), $record->fresh()->getAttributes()]);
    }

    public function test_area_subtitles_are_plain_text_and_html_is_escaped(): void
    {
        $area = new KnowledgeArea(['name' => 'Área de teste', 'subtitle' => '<script>alert(1)</script>']);
        $html = view('partials.knowledge-area-label', ['labelArea' => $area])->render();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    private function user(string $role): User
    {
        $person = Person::create(['full_name' => 'Pessoa '.$role, 'cpf' => fake()->unique()->numerify('###########'),
            'birth_date' => '1990-01-01', 'birth_city' => 'Poxoréu', 'birth_state' => 'MT', 'nationality' => 'Brasileira',
            'mother_name' => 'Maria', 'address' => 'Rua de teste', 'city' => 'Poxoréu', 'state' => 'MT',
            'postal_code' => '78700000', 'profile_completed_at' => now()]);
        $person->schoolRoles()->create(['role' => $role, 'active' => true]);

        return User::factory()->create(['person_id' => $person->id]);
    }
}
