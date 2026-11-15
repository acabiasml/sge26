<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryComponent;
use App\Models\StudentAcademicHistoryRecord;
use App\Models\StudentAcademicHistoryYear;
use App\Models\StudentEnrollment;
use App\Support\AttendanceSummaryCalculator;
use App\Support\StudentAcademicHistoryPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSignaturePaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_and_class_report_cards_keep_each_signature_with_its_own_results_at_page_boundaries(): void
    {
        $school = $this->school();
        $year = new AcademicYear(['reference_year' => 2026, 'starts_at' => '2026-02-01', 'ends_at' => '2026-12-20']);
        $year->setRelation('school', $school);
        $class = new SchoolClass(['name' => 'Turma de teste']);
        $class->setRelation('academicYear', $year);
        $course = new AcademicCourse(['name' => 'Matriz de teste', 'stage' => 'medio']);
        $course->id = 1;
        $course->setRelation('academicYear', $year);
        $periods = collect(range(1, 4))->map(function ($number) {
            $period = new AcademicPeriod(['name' => $number.'º Bimestre', 'position' => $number, 'ends_at' => '2026-12-20']);
            $period->id = $number;

            return $period;
        });
        $attendance = app(AttendanceSummaryCalculator::class)->fromTotals(80, 78, 0);
        foreach ([14, 18, 22, 25, 29, 35, 45] as $count) {
            $report = [
                'enrollment' => (new StudentEnrollment(['status' => 'matriculado', 'type' => 'regular']))->setRelation('schoolClass', $class),
                'student' => new Person(['full_name' => 'Estudante com Nome de Teste Bastante Extenso', 'student_inep' => '123456789012', 'birth_date' => '2007-12-31', 'legacy_code' => 'SES-0001']),
                'schoolClass' => $class, 'academicYear' => $year, 'courses' => collect([$course]), 'periods' => $periods,
                'periodReports' => collect(), 'annualAttendance' => $attendance, 'finalResult' => ['label' => 'Aprovado'],
                'annualComponents' => collect(range(1, $count))->map(function ($number) use ($course, $periods, $attendance) {
                    $component = new CurriculumComponent(['name' => 'Componente curricular '.$number, 'workload_hours' => 80]);
                    $component->id = $number;
                    $component->setRelation('course', $course)->setRelation('area', null);

                    return ['component' => $component, 'attendance' => $attendance,
                        'periods' => $periods->map(fn ($period) => ['period' => $period, 'component' => $component, 'average' => ['value' => 8.5], 'attendance' => $attendance])];
                }),
            ];
            $second = $report;
            $second['student'] = new Person(['full_name' => 'Outro estudante do mesmo lote', 'student_inep' => '987654321012']);
            foreach (['reports.student-report-card' => ['report' => $report], 'reports.class-report-cards' => ['reports' => collect([$report, $second])]] as $view => $data) {
                $pdf = Pdf::loadView($view, $data + $this->documentData())->setPaper('a4', 'portrait');
                $this->assertClosingPages($pdf, $view.' com '.$count.' componentes', $view === 'reports.class-report-cards' ? 2 : 1);
                if ($count === 45) $this->assertGreaterThan(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
            }
        }
    }

    public function test_basic_and_technical_histories_keep_the_last_study_and_signatures_together_when_content_spans_pages(): void
    {
        $school = $this->school();
        foreach (['fundamental', 'medio', 'tecnico'] as $stage) {
            foreach ([9, 17, 26, 48] as $count) {
                $history = new StudentAcademicHistory(['title' => 'Histórico escolar', 'education_stage' => $stage,
                    'notes' => str_repeat('Observação extensa para conferir a continuidade do histórico. ', 12)]);
                $history->setRelation('school', $school);
                $years = collect(range(1, $stage === 'fundamental' ? 9 : 3))->map(function ($number) {
                    $year = new StudentAcademicHistoryYear(['year' => 2016 + $number, 'label' => $number.'º Ano', 'transcript_mode' => 'detailed',
                        'school_name' => 'Escola de Educação com Nome Extenso para Teste de Impressão', 'city' => 'Poxoréu', 'state' => 'MT', 'country' => 'Brasil', 'final_result' => 'Aprovado']);
                    $year->id = $number;

                    return $year;
                });
                $history->setRelation('years', $years);
                $history->setRelation('components', collect(range(1, $count))->map(function ($number) use ($years) {
                    $component = new StudentAcademicHistoryComponent(['name' => 'Componente curricular '.$number, 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Linguagens']);
                    $component->setRelation('records', $years->map(fn ($year) => new StudentAcademicHistoryRecord(['student_academic_history_year_id' => $year->id, 'score_label' => '8,50', 'workload_hours' => 80])));

                    return $component;
                }));
                $pdf = app(StudentAcademicHistoryPdf::class)->make($this->documentData() + [
                    'history' => $history, 'person' => new Person(['full_name' => 'Estudante do histórico de teste', 'birth_city' => 'Santo Antônio do Leste', 'birth_state' => 'MT']),
                ]);
                $this->assertClosingPages($pdf, 'Histórico '.$stage.' com '.$count.' componentes', 1);
            }
        }
    }

    private function assertClosingPages($pdf, string $scenario, int $expectedDocuments): void
    {
        $contents = $signatures = $bottoms = $footers = [];
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$contents, &$signatures, &$bottoms, &$footers): void {
            $node = $frame->get_node();
            if (! $node instanceof \DOMElement) return;
            $page = $canvas->get_page_number();
            if ($node->hasAttribute('data-pdf-content')) $contents[$node->getAttribute('data-pdf-content')][$page] = true;
            if ($node->hasAttribute('data-pdf-signatures')) $signatures[$node->getAttribute('data-pdf-signatures')][$page] = true;
            $box = $frame->get_border_box();
            if ($node->getAttribute('class') === 'signature-role') $bottoms[$page] = max($bottoms[$page] ?? 0, $box[1] + $box[3]);
            if ($node->getAttribute('class') === 'document-footer') $footers[$page] = $box[1];
        }]]);
        $pdf->render();
        $this->assertCount($expectedDocuments, $signatures, $scenario);
        foreach ($signatures as $key => $pages) {
            $this->assertCount(1, $pages, 'As assinaturas devem permanecer juntas: '.$scenario);
            foreach ($pages as $page => $_) {
                $this->assertTrue($contents[$key][$page] ?? false, 'A página das assinaturas deve conter resultados do mesmo estudante: '.$scenario.', página '.$page);
                $this->assertGreaterThanOrEqual($bottoms[$page] + 5, $footers[$page], 'As assinaturas precisam respeitar o rodapé: '.$scenario);
            }
        }
    }

    private function school(): School
    {
        $school = School::create(['name' => 'Escola de teste']);
        foreach (['diretor', 'secretário'] as $position) {
            $person = Person::create(['full_name' => 'Responsável com Nome Completo para Impressão', 'active' => true]);
            $person->schoolRoles()->create(['school_id' => $school->id, 'role' => PersonSchoolRole::ROLE_MANAGER, 'position' => $position, 'active' => true]);
        }

        return $school;
    }

    private function documentData(): array
    {
        $document = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'PREVIA']);
        $document->setRelation('issuedBy', null);

        return ['issuedDocument' => $document, 'verificationUrl' => '', 'scoreView' => 'numeros', 'canChooseScoreView' => false,
            'letterhead' => ['lines' => ['Mantenedora de teste', 'Certificado de educação', 'Escola de teste', 'Cadastro da escola', 'Autorização de funcionamento'],
                'footer_lines' => ['Rua de teste, 100, Poxoréu-MT', 'Telefone e endereço eletrônico da escola de teste']]];
    }
}
