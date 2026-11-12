<?php

namespace Tests\Feature;

use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Models\School;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryComponent;
use App\Models\StudentAcademicHistoryRecord;
use App\Models\StudentAcademicHistoryYear;
use App\Models\User;
use App\Support\StudentAcademicHistoryPdf;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class HistoryMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_screen_merges_formations_areas_and_global_results_across_all_rows(): void
    {
        $this->actingAs(User::factory()->create());
        view()->share('errors', new ViewErrorBag);
        $history = new StudentAcademicHistory(['education_stage' => 'fundamental', 'title' => 'Histórico escolar']);
        $history->id = 1;
        $history->setRelation('school', null);
        $year = new StudentAcademicHistoryYear(['label' => '9º Ano', 'transcript_mode' => 'summary', 'final_result' => 'Aprovado']);
        $year->id = 1;
        $detailed = new StudentAcademicHistoryYear(['label' => '8º Ano', 'transcript_mode' => 'detailed']);
        $detailed->id = 2;
        $untranscribed = new StudentAcademicHistoryYear(['label' => '7º Ano', 'transcript_mode' => 'no_transcription', 'final_result' => 'Classificação']);
        $untranscribed->id = 3;
        $history->setRelation('years', collect([$year, $detailed, $untranscribed]));
        $history->setRelation('components', collect(range(1, 43))->map(function (int $number): StudentAcademicHistoryComponent {
            $component = new StudentAcademicHistoryComponent([
                'name' => 'Componente '.$number,
                'formation' => $number <= 35 ? 'Formação Geral Básica' : 'Parte Diversificada',
                'knowledge_area' => $number <= 25 ? 'Linguagens' : 'Matemática',
            ]);
            $component->setRelation('records', collect([new StudentAcademicHistoryRecord([
                'student_academic_history_year_id' => 2, 'score_label' => '8,5', 'workload_hours' => 80,
            ])]));

            return $component;
        }));
        $person = new Person(['full_name' => 'Estudante de teste']);
        $person->id = 1;

        $html = view('student-histories.show', [
            'history' => $history,
            'person' => $person,
            'historyCompleteness' => ['complete' => true, 'message' => ''],
            'technicalPeriodDurations' => collect(),
        ])->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//table[contains(@class,"sge-history-read-table")]/tbody/tr');
        $this->assertSame(43, $rows->length);
        $table = $xpath->query('//table[contains(@class,"sge-history-read-table")]')->item(0);
        $this->assertFalse($table->hasAttribute('data-sge-client-pagination'));
        $this->assertSame(1, $xpath->query('.//td[@rowspan="35"]/strong[text()="Formação Geral Básica"]', $table)->length);
        $this->assertSame(1, $xpath->query('.//td[@rowspan="8"]/strong[text()="Parte Diversificada"]', $table)->length);
        $this->assertSame(1, $xpath->query('.//td[@rowspan="25" and text()="Linguagens"]', $table)->length);
        $this->assertSame(1, $xpath->query('.//td[@rowspan="10" and text()="Matemática"]', $table)->length);
        $this->assertSame(1, $xpath->query('.//td[@rowspan="8" and text()="Matemática"]', $table)->length);
        $this->assertSame(1, $xpath->query('.//td[@data-global-year="1" and @rowspan="43"]/strong[text()="Aprovado"]', $table)->length);
        $this->assertSame(1, $xpath->query('.//td[@data-global-year="3" and @rowspan="43"]/strong[text()="Classificação"]', $table)->length);
        $this->assertSame(2, $xpath->query('.//td[@data-global-year]', $table)->length);
        $this->assertSame(43, $xpath->query('.//td/strong[text()="8,5"]', $table)->length);
    }

    public function test_technical_history_repeats_area_on_the_next_table_page(): void
    {
        $history = new StudentAcademicHistory;
        $history->setRelation('years', collect());
        $history->setRelation('components', collect(range(1, 21))->map(function (int $number): StudentAcademicHistoryComponent {
            $component = new StudentAcademicHistoryComponent(['name' => 'Componente '.$number, 'knowledge_area' => 'Curso técnico']);
            $component->setRelation('records', collect());

            return $component;
        }));

        $html = view('student-histories._technical-components-table', ['history' => $history])->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(21, $xpath->query('//tbody/tr')->length);
        $this->assertSame(1, $xpath->query('//tbody/tr[1]/td[@rowspan="10"]')->length);
        $this->assertSame(1, $xpath->query('//tbody/tr[11]/td[@rowspan="10"]')->length);
        $this->assertSame(1, $xpath->query('//tbody/tr[21]/td[@rowspan="1"]')->length);
    }

    public function test_studies_table_uses_remaining_page_space_and_repeats_its_header(): void
    {
        $history = new StudentAcademicHistory(['education_stage' => 'fundamental', 'title' => 'Histórico Escolar']);
        $history->setRelation('school', null);
        $years = collect(range(1, 9))->map(function ($number) {
            $year = new StudentAcademicHistoryYear(['label' => $number.'º Ano', 'grade_phase' => 'ESTUDO '.$number, 'transcript_mode' => 'summary', 'final_result' => 'Aprovado', 'school_name' => str_repeat('Escola Municipal de Educação ', 9)]);
            $year->id = $number;

            return $year;
        });
        $history->setRelation('years', $years);
        $component = new StudentAcademicHistoryComponent(['name' => 'COMPONENTE FINAL', 'formation' => 'Parte Diversificada', 'knowledge_area' => 'Linguagens']);
        $component->setRelation('records', collect());
        $history->setRelation('components', collect([$component]));
        $issued = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'PREVIA']);
        $issued->setRelation('issuedBy', null);
        $pdf = Pdf::loadView('reports.student-academic-history', [
            'history' => $history, 'person' => new Person(['full_name' => 'Estudante de teste']),
            'issuedDocument' => $issued, 'verificationUrl' => 'https://ctjj.org/verificar/PREVIA', 'letterhead' => ['lines' => ['Escola de teste']],
        ]);
        $matrixPage = null;
        $studiesPage = null;
        $headers = [];
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$matrixPage, &$studiesPage, &$headers): void {
            $node = $frame->get_node();
            $page = $canvas->get_page_number();
            if ($node->nodeType === XML_TEXT_NODE && str_contains($node->nodeValue, 'COMPONENTE FINAL')) {
                $matrixPage = $page;
            }
            if ($node->nodeType === XML_TEXT_NODE && str_contains($node->nodeValue, 'ESTUDO 1')) {
                $studiesPage = $page;
            }
            if ($node instanceof \DOMElement && $node->tagName === 'thead' && str_contains($node->parentNode->getAttribute('class'), 'studies-table')) {
                $headers[$page] = true;
            }
        }]]);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertNotNull($matrixPage);
        $this->assertSame($matrixPage, $studiesPage, 'Estudos realizados deve aproveitar o espaço após a matriz.');
        $this->assertGreaterThanOrEqual(2, count($headers));
    }

    public function test_history_with_nine_years_keeps_signatures_on_the_first_page(): void
    {
        $school = School::query()->create(['name' => 'Escola de Jarudore']);
        foreach ([PersonSchoolRole::POSITION_DIRECTOR => 'Maria Aparecida dos Santos de Oliveira', PersonSchoolRole::POSITION_SECRETARY => 'Maria da Conceição Ferreira de Souza'] as $position => $name) {
            $staff = Person::query()->create(['full_name' => $name]);
            PersonSchoolRole::query()->create([
                'person_id' => $staff->id, 'school_id' => $school->id,
                'role' => PersonSchoolRole::ROLE_MANAGER, 'position' => $position, 'active' => true,
            ]);
        }
        $history = new StudentAcademicHistory(['education_stage' => 'fundamental', 'title' => 'Histórico escolar']);
        $history->setRelation('school', $school);
        $history->setRelation('years', collect(range(1, 9))->map(function (int $number): StudentAcademicHistoryYear {
            $year = new StudentAcademicHistoryYear([
                'label' => $number.'º Ano', 'grade_phase' => $number.'º Ano', 'year' => (string) (2016 + $number),
                'transcript_mode' => 'detailed', 'final_result' => 'Aprovado',
                'school_name' => 'Escola Laura Vicuña',
                'city' => 'Poxoréu', 'state' => 'MT', 'country' => 'Brasil',
            ]);
            $year->id = $number;

            return $year;
        }));
        $history->setRelation('components', collect(range(1, 9))->map(function (int $number): StudentAcademicHistoryComponent {
            $component = new StudentAcademicHistoryComponent([
                'name' => 'Componente curricular '.$number,
                'formation' => $number <= 7 ? 'Formação Geral Básica' : 'Parte Diversificada',
                'knowledge_area' => $number <= 7 ? 'Linguagens' : 'Área complementar',
            ]);
            $component->setRelation('records', collect(range(1, 9))->map(fn (int $year) => new StudentAcademicHistoryRecord([
                'student_academic_history_year_id' => $year, 'score_label' => '8,00', 'workload_hours' => 80,
            ])));

            return $component;
        }));
        $issued = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'PREVIA']);
        $issued->setRelation('issuedBy', null);
        $person = new Person([
            'full_name' => 'Estudante com histórico de nove anos',
            'birth_city' => 'Poxoréu', 'birth_state' => 'MT',
            'mother_name' => 'Nome da mãe', 'father_name' => 'Nome do pai',
            'address' => 'Rua da Escola', 'number' => '100', 'district' => 'Centro',
            'city' => 'Poxoréu', 'state' => 'MT',
        ]);
        $pdf = Pdf::loadView('reports.student-academic-history', [
            'history' => $history, 'person' => $person, 'issuedDocument' => $issued,
            'verificationUrl' => 'https://ctjj.org/verificar/PREVIA',
            'letterhead' => [
                'lines' => ['Centro Técnico Juvenil de Jarudore', 'Certificado CEBAS Educação', 'Escola de Jarudore', 'CNPJ e INEP', 'Autorização da escola'],
                'footer_lines' => ['Tel.: (66) 3000-0000 | E-mail: escola@example.org | Site: ctjj.org', 'Endereço: Rua da Escola, 100, Poxoréu-MT'],
            ],
        ])->setPaper('a4', 'portrait');

        $signatureBottom = null;
        $footerTop = null;
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$signatureBottom, &$footerTop): void {
            $node = $frame->get_node();
            if (! $node instanceof \DOMElement) {
                return;
            }
            $box = $frame->get_border_box();
            if ($node->getAttribute('class') === 'signatures') {
                $signatureBottom = $box['y'] + $box['h'];
            }
            if ($node->getAttribute('class') === 'document-footer') {
                $footerTop = $box['y'];
            }
        }]]);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertNotNull($signatureBottom);
        $this->assertNotNull($footerTop);
        $this->assertLessThan($footerTop - 5, $signatureBottom, 'As assinaturas devem terminar antes do rodapé, com espaço de separação.');
    }

    public function test_cycle_heading_breaks_between_phase_and_cycle(): void
    {
        $history = new StudentAcademicHistory;
        $year = new StudentAcademicHistoryYear(['label' => '1ª Fase do 1º Ciclo', 'transcript_mode' => 'no_transcription']);
        $year->id = 1;
        $history->setRelation('years', collect([$year]));
        $history->setRelation('components', collect());
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertStringContainsString('vertical-heading-multiline', $html);
        $this->assertStringContainsString('1ª Fase do<br>1º Ciclo', $html);
        $this->assertSame('1ª Fase do 1º Ciclo', $year->label);
    }

    public function test_untranscribed_year_has_one_header_and_a_vertical_result_next_to_mixed_years(): void
    {
        $history = new StudentAcademicHistory;
        $years = collect(['no_transcription', 'summary', 'detailed'])->map(function ($mode, $index) {
            $year = new StudentAcademicHistoryYear(['label' => ($index + 1).'º Ano', 'transcript_mode' => $mode, 'final_result' => $index === 0 ? 'Inturmado por Idade' : 'Aprovado']);
            $year->id = $index + 1;

            return $year;
        });
        $component = new StudentAcademicHistoryComponent(['name' => 'Português', 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Linguagens']);
        $component->setRelation('records', collect());
        $history->setRelation('years', $years)->setRelation('components', collect([$component]));
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//thead/tr[1]/th[@rowspan="2"][contains(., "1º Ano")]')->length);
        $this->assertSame(2, $xpath->query('//thead/tr[2]/th')->length);
        $this->assertSame(1, $xpath->query('//td[@data-global-year="1"]/div[@class="vertical-result"]/strong[contains(., "Inturmado por Idade")]')->length);
        $this->assertStringNotContainsString('Sem transcrição', $html);
        $years[0]->final_result = null;
        $history->setRelation('years', collect([$years[0]]));
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertStringContainsString('<strong>Sem transcrição</strong>', $html);
        $this->assertStringNotContainsString('rowspan="2"', $html);
    }

    public function test_global_fundamental_history_keeps_basic_curriculum_rows_without_creating_records(): void
    {
        $history = new StudentAcademicHistory(['education_stage' => 'fundamental']);
        $year = new StudentAcademicHistoryYear(['label' => '1º Ano', 'transcript_mode' => 'summary', 'final_result' => 'Aprovado', 'workload_hours' => 800]);
        $year->id = 1;
        $history->setRelation('years', collect([$year]));
        $history->setRelation('components', collect());
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        foreach (config('curriculum.stages.fundamental.formations.formacao_geral_basica.areas') as $area) {
            foreach ($area['components'] as $name) {
                if ($name !== 'Ensino Religioso') {
                    $this->assertStringContainsString($name, $html);
                }
            }
        }
        $this->assertStringContainsString('rowspan="8" class="center global-year"', $html);
        $this->assertSame(1, substr_count($html, 'Síntese Global - Aprovado'));
        $this->assertStringContainsString('800', $html);
        $this->assertCount(0, $history->components);
        $this->assertStringNotContainsString('Ensino Religioso', $html);
        $this->assertStringNotContainsString('>Global<', $html);

        $component = new StudentAcademicHistoryComponent(['name' => 'Língua Portuguesa', 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Linguagens']);
        $component->setRelation('records', collect());
        $history->components->push($component);
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertSame(1, substr_count($html, 'Língua Portuguesa'));
        $this->assertStringContainsString('rowspan="8" class="center global-year"', $html);
        $this->assertCount(1, $history->components);

        $religion = new StudentAcademicHistoryComponent(['name' => 'Ensino Religioso', 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Ensino Religioso']);
        $religion->setRelation('records', collect());
        $history->components->push($religion);
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertStringContainsString('Ensino Religioso', $html);
        $this->assertStringContainsString('rowspan="9" class="center global-year"', $html);

        $history->education_stage = 'medio';
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertStringNotContainsString('Educação Física', $html);
    }

    public function test_planned_and_completed_hours_are_separate_and_current_year_hours_are_hidden(): void
    {
        $history = new StudentAcademicHistory(['education_stage' => 'medio']);
        $finished = new StudentAcademicHistoryYear(['label' => '1º Ano', 'transcript_mode' => 'detailed', 'final_result' => 'Aprovado']);
        $finished->id = 1;
        $current = new StudentAcademicHistoryYear(['label' => '2º Ano', 'transcript_mode' => 'detailed', 'final_result' => 'Cursando']);
        $current->id = 2;
        $history->setRelation('years', collect([$finished, $current]));
        $components = collect();
        foreach (['Formação Geral Básica' => [800, 850], 'Itinerário Formativo' => [200, 250]] as $formation => $hours) {
            $component = new StudentAcademicHistoryComponent(['name' => $formation, 'formation' => $formation, 'knowledge_area' => 'Área']);
            $component->setRelation('records', collect($hours)->map(fn ($value, $index) => new StudentAcademicHistoryRecord([
                'student_academic_history_year_id' => $index + 1, 'workload_hours' => $value,
                'frequency_percentage' => 97, 'score_label' => $index === 0 ? '8,0' : '-',
            ])));
            $components->push($component);
        }
        $history->setRelation('components', $components);
        $this->assertSame([
            'Formação Geral Básica' => ['planned' => 1650.0, 'completed' => 800.0],
            'Itinerário Formativo' => ['planned' => 450.0, 'completed' => 200.0],
        ], $history->formationWorkloadTotals());
        $this->assertSame(['planned' => 800.0, 'completed' => 800.0], $history->formationWorkloadTotals($finished)['Formação Geral Básica']);
        $this->assertSame(['planned' => 250.0, 'completed' => null], $history->formationWorkloadTotals($current)['Itinerário Formativo']);
        $annual = view('reports.partials.history-annual-formation-workloads', compact('history'))->render();
        $this->assertStringContainsString('data-workload-year="1"', $annual);
        $this->assertStringContainsString('data-workload-year="2"', $annual);
        $this->assertStringContainsString('800,00', $annual);
        $this->assertStringContainsString('200,00', $annual);
        $this->assertSame(1, substr_count($annual, '850,00'));
        $this->assertSame(1, substr_count($annual, '250,00'));
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertStringContainsString('Carga horária cursada', $html);
        $this->assertStringContainsString('formation-cell', $html);
        $this->assertStringNotContainsString('850', $html);
        $this->assertStringNotContainsString('250', $html);
        $this->assertStringNotContainsString('97', $html);
        $this->assertStringNotContainsString('F%', $html);
        $totals = view('reports.partials.history-formation-workloads', compact('history'))->render();
        $this->assertStringContainsString('1.650,00', $totals);
        $this->assertStringContainsString('800,00', $totals);
        $finished->final_result = 'Cursando';
        $this->assertNull($history->formationWorkloadTotals()['Formação Geral Básica']['completed']);
    }

    public function test_long_history_can_render_pdf_without_losing_merged_columns_between_sections(): void
    {
        $history = new StudentAcademicHistory;
        $year = new StudentAcademicHistoryYear(['label' => '1º Ano', 'transcript_mode' => 'summary', 'final_result' => 'Aprovado']);
        $year->id = 1;
        $history->setRelation('years', collect([$year]));
        $history->setRelation('components', collect(range(1, 48))->map(function ($number) {
            $component = new StudentAcademicHistoryComponent([
                'name' => 'Componente '.$number, 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Área',
            ]);
            $component->setRelation('records', collect());

            return $component;
        }));
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//thead')->length);
        foreach ($xpath->query('//table[contains(@class,"matrix-section")]') as $table) {
            $this->assertSame(1, $xpath->query('.//td[contains(@class,"formation-cell")]', $table)->length);
            $this->assertSame(1, $xpath->query('.//td[@data-global-year="1"]', $table)->length);
        }
        $headersPerPage = [];
        $pdf = Pdf::loadHTML('<style>table { width:100%; border-collapse:collapse; } td, th { font-size:11px; padding:4px; }</style>'.$html)->setPaper('a5');
        $pdf->getDomPDF()->setCallbacks([[
            'event' => 'end_frame',
            'f' => function ($frame, $canvas) use (&$headersPerPage): void {
                if ($frame->get_node()->nodeName === 'thead') {
                    $page = $canvas->get_page_number();
                    $headersPerPage[$page] = ($headersPerPage[$page] ?? 0) + 1;
                }
            },
        ]]);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertGreaterThanOrEqual(2, count($headersPerPage));
        foreach ($headersPerPage as $count) {
            $this->assertSame(1, $count, 'O cabeçalho deve aparecer uma única vez em cada página da tabela.');
        }
        $this->assertStringContainsString('Componente 48', $html);
    }

    public function test_pdf_keeps_thirteen_basic_formation_components_and_their_areas_in_one_block(): void
    {
        $data = $this->paginatedHistoryData(13);
        $data['history']->components->push(...collect(range(1, 8))->map(function ($number) {
            $component = new StudentAcademicHistoryComponent(['name' => 'Itinerário '.$number, 'formation' => 'Itinerário Formativo', 'knowledge_area' => 'Aprofundamento de Estudos']);
            $component->setRelation('records', collect());

            return $component;
        }));
        $before = $data['history']->components->map->getAttributes()->all();
        $renderedData = null;
        view()->composer('reports.student-academic-history', function ($view) use (&$renderedData): void {
            $renderedData = $view->getData();
        });
        $pdf = app(StudentAcademicHistoryPdf::class)->make($data);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.view('reports.partials.basic-history-matrix', $renderedData)->render());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//td[contains(@class,"formation-cell") and @rowspan="13"]//span[text()="Formação Geral Básica"]')->length);
        $this->assertSame(1, $xpath->query('//td[contains(@class,"area-label") and @rowspan="3" and text()="Ciências da Natureza e Suas Tecnologias"]')->length);
        $this->assertSame(2, $xpath->query('//td[contains(@class,"formation-cell")]')->length);
        $this->assertSame(1, $xpath->query('//td[contains(@class,"formation-cell")]//span[text()="Itinerário Formativo"]')->length);
        $this->assertSame($before, $data['history']->components->map->getAttributes()->all());
    }

    public function test_pdf_keeps_page_breaks_and_all_results_when_a_formation_spans_multiple_pages(): void
    {
        $data = $this->paginatedHistoryData(60);
        $renderedData = null;
        view()->composer('reports.student-academic-history', function ($view) use (&$renderedData): void {
            $renderedData = $view->getData();
        });
        $pdf = app(StudentAcademicHistoryPdf::class)->make($data);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertGreaterThanOrEqual(2, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.view('reports.partials.basic-history-matrix', $renderedData)->render());
        $xpath = new \DOMXPath($document);
        $cells = $xpath->query('//td[contains(@class,"formation-cell")]');
        $this->assertGreaterThan(1, $cells->length);
        $this->assertSame(60, array_sum(array_map(fn ($cell) => (int) $cell->getAttribute('rowspan'), iterator_to_array($cells))));
        foreach (range(1, 60) as $number) {
            $this->assertSame(1, $xpath->query('//td[text()="Componente '.$number.'"]')->length);
        }
        $this->assertSame(180, $xpath->query('//td[contains(@class,"score-cell") and text()="8,5"]')->length);
    }

    public function test_pdf_fills_the_remaining_page_with_at_least_twenty_six_components_above_the_footer(): void
    {
        $data = $this->paginatedHistoryData(13);
        foreach (range(1, 20) as $number) {
            $component = new StudentAcademicHistoryComponent(['name' => 'Itinerário '.$number, 'formation' => 'Itinerário Formativo',
                'knowledge_area' => $number <= 3 ? 'Aprofundamento de Estudos' : 'Técnico em Móveis']);
            $component->setRelation('records', collect());
            $data['history']->components->push($component);
        }
        $data['person']->fill(['legacy_code' => 'PREVIA', 'birth_date' => '2007-09-19', 'birth_city' => 'Palmas', 'birth_state' => 'TO',
            'personal_email' => 'teste@example.org', 'institutional_email' => 'teste@escola.example.org', 'address' => 'Rua da Escola', 'city' => 'Poxoréu', 'state' => 'MT']);
        $data['letterhead']['lines'] = ['Centro Técnico de Teste', 'Certificado de educação', 'Escola de teste', 'Cadastro da escola', 'Autorização de funcionamento'];
        $rows = $bottoms = $footers = $formations = [];
        $pdf = app(StudentAcademicHistoryPdf::class)->make($data);
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$rows, &$bottoms, &$footers, &$formations): void {
            $node = $frame->get_node();
            if (! $node instanceof \DOMElement) return;
            $page = $canvas->get_page_number();
            $box = $frame->get_border_box();
            if ($node->tagName === 'td' && preg_match('/^(Componente|Itinerário) [0-9]+$/u', trim($node->textContent))) {
                $rows[$page][] = trim($node->textContent);
                $bottoms[$page] = $box['y'] + $box['h'];
            }
            if (str_contains($node->getAttribute('class'), 'formation-cell')) {
                $formations[$page][] = trim($node->textContent);
            }
            if ($node->getAttribute('class') === 'document-footer') $footers[$page] = $box['y'];
        }]]);
        $this->assertStringStartsWith('%PDF-', $pdf->output());
        $this->assertGreaterThanOrEqual(26, count($rows[1]));
        $this->assertSame($data['history']->components->pluck('name')->all(), array_merge(...array_values($rows)));
        foreach ($formations as $labels) {
            $this->assertSame(count($labels), count(array_unique($labels)), 'A formação deve aparecer uma única vez por página da matriz.');
        }
        foreach ($bottoms as $page => $bottom) {
            $this->assertLessThan($footers[$page] - 3, $bottom, 'Os componentes devem terminar antes do rodapé.');
        }
        $this->assertLessThan(35, $footers[1] - $bottoms[1], 'O espaço livre deve ser aproveitado por linhas vindas da página seguinte.');
    }

    public function test_all_history_content_stays_above_the_footer_including_studies_and_formation_totals(): void
    {
        foreach ([13, 17, 21, 26, 33] as $componentCount) {
            $data = $this->paginatedHistoryData($componentCount);
            $data['history']->setRelation('years', $data['history']->years->take(1));
            $data['history']->years->first()->fill([
                'year' => '2026', 'grade_phase' => '1º Ano', 'school_name' => 'Escola de Ensino Médio de Teste',
                'city' => 'Poxoréu', 'state' => 'MT', 'country' => 'Brasil', 'modality' => 'Regular',
            ]);
            foreach (range(1, 4) as $number) {
                $component = new StudentAcademicHistoryComponent([
                    'name' => 'Componente técnico '.$number, 'formation' => 'Itinerário Formativo',
                    'knowledge_area' => 'Técnico em Móveis',
                    'regulatory_reference' => 'Lei Federal nº 9.394/1996, arts. 36-B a 42; Resolução CNE/CP nº 1/2021.',
                ]);
                $component->setRelation('records', collect());
                $data['history']->components->push($component);
            }
            $data['person']->fill([
                'legacy_code' => 'PREVIA', 'birth_date' => '2007-09-19', 'birth_city' => 'Palmas', 'birth_state' => 'TO',
                'personal_email' => 'teste@example.org', 'institutional_email' => 'teste@escola.example.org',
                'address' => 'Rua da Escola', 'city' => 'Poxoréu', 'state' => 'MT',
            ]);
            $data['letterhead']['lines'] = ['Centro Técnico de Teste', 'Certificado de educação', 'Escola de teste', 'Cadastro da escola', 'Autorização de funcionamento'];
            $data['letterhead']['footer_lines'] = [
                'E-mail: escola@example.org | Endereço: Rua da Escola, 100, Poxoréu-MT, CEP 78800-000',
                'Tel.: (66) 3000-0000 | Site: https://ctjj.org',
            ];
            $bottoms = $footers = [];
            $pdf = app(StudentAcademicHistoryPdf::class)->make($data);
            $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame, $canvas) use (&$bottoms, &$footers): void {
                $node = $frame->get_node();
                if (! $node instanceof \DOMElement) {
                    return;
                }
                $page = $canvas->get_page_number();
                $box = $frame->get_border_box();
                if ($node->getAttribute('class') === 'document-footer') {
                    $footers[$page] = $box['y'];
                }
                for ($ancestor = $node; $ancestor instanceof \DOMElement; $ancestor = $ancestor->parentNode) {
                    if ($ancestor->tagName === 'footer' || $ancestor->tagName === 'header') {
                        return;
                    }
                }
                if (in_array($node->tagName, ['td', 'th', 'p'], true)) {
                    $bottoms[$page] = max($bottoms[$page] ?? 0, $box['y'] + $box['h']);
                }
            }]]);
            $this->assertStringStartsWith('%PDF-', $pdf->output());
            $this->assertNotEmpty($bottoms);
            foreach ($bottoms as $page => $bottom) {
                $this->assertArrayHasKey($page, $footers);
                $this->assertLessThan($footers[$page] - 5, $bottom,
                    'Todo o conteúdo deve respeitar o rodapé: '.$componentCount.' componentes básicos, página '.$page.'.');
            }
        }
    }

    private function paginatedHistoryData(int $componentCount): array
    {
        $history = new StudentAcademicHistory(['education_stage' => 'medio', 'title' => 'Histórico Escolar — Ensino Médio']);
        $history->setRelation('school', null);
        $history->setRelation('years', collect(range(1, 3))->map(function ($number) {
            $year = new StudentAcademicHistoryYear(['label' => $number.'º Ano', 'transcript_mode' => 'detailed', 'final_result' => 'Aprovado']);
            $year->id = $number;

            return $year;
        }));
        $history->setRelation('components', collect(range(1, $componentCount))->map(function ($number) {
            $area = match (true) {
                $number <= 5 => 'Linguagens e Suas Tecnologias',
                $number <= 8 => 'Ciências da Natureza e Suas Tecnologias',
                $number <= 12 => 'Ciências Humanas e Sociais Aplicadas',
                default => 'Matemática e Suas Tecnologias',
            };
            $component = new StudentAcademicHistoryComponent(['name' => 'Componente '.$number, 'formation' => 'Formação Geral Básica', 'knowledge_area' => $area]);
            $component->setRelation('records', collect(range(1, 3))->map(fn ($year) => new StudentAcademicHistoryRecord([
                'student_academic_history_year_id' => $year, 'score_label' => '8,5', 'workload_hours' => 80,
            ])));

            return $component;
        }));
        $issued = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'PREVIA']);
        $issued->setRelation('issuedBy', null);

        return ['history' => $history, 'person' => new Person(['full_name' => 'Estudante de teste']),
            'issuedDocument' => $issued, 'verificationUrl' => 'https://ctjj.org/verificar/PREVIA', 'letterhead' => ['lines' => ['Escola de teste']]];
    }

    public function test_global_year_spans_all_components_without_discarding_detailed_results(): void
    {
        $history = new StudentAcademicHistory;
        $global = new StudentAcademicHistoryYear(['label' => '1º Ano', 'transcript_mode' => 'summary', 'final_result' => 'Aprovado', 'workload_hours' => 800]);
        $global->id = 1;
        $detailed = new StudentAcademicHistoryYear(['label' => '2º Ano', 'transcript_mode' => 'detailed', 'workload_hours' => 1000]);
        $detailed->id = 2;
        $history->setRelation('years', collect([$global, $detailed]));
        $history->setRelation('components', collect(['Língua Portuguesa', 'Matemática'])->map(function ($name) {
            $component = new StudentAcademicHistoryComponent(['name' => $name, 'formation' => $name === 'Matemática' ? 'Parte Diversificada' : 'Formação Geral Básica', 'knowledge_area' => 'Área']);
            $component->setRelation('records', collect([new StudentAcademicHistoryRecord([
                'student_academic_history_year_id' => 2, 'score_label' => '8,5', 'workload_hours' => 200,
            ])]));

            return $component;
        }));
        $summary = new StudentAcademicHistoryComponent(['name' => 'Síntese Global do Documento de Origem', 'knowledge_area' => 'Síntese Curricular', 'formation' => 'Formação Geral Básica']);
        $summary->setRelation('records', collect());
        $history->components->push($summary);
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//td[@data-global-year="1" and @rowspan="2"]')->length);
        $this->assertSame(2, substr_count($html, '8,5'));
        $this->assertStringContainsString('Língua Portuguesa', $html);
        $this->assertStringContainsString('Matemática', $html);
        $this->assertSame(1, substr_count($html, 'Síntese Global - Aprovado'));
        $this->assertStringNotContainsString('Síntese Curricular', $html);
        $this->assertStringNotContainsString('Síntese Global do Documento de Origem', $html);
        $this->assertStringContainsString('1.000', $html);
        $this->assertCount(3, $history->components);

        $global->transcript_mode = 'detailed';
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertStringNotContainsString('data-global-year="1"', $html);
        $this->assertStringContainsString('Síntese Global do Documento de Origem', $html);
        $this->assertSame(2, substr_count($html, '8,5'));
    }
}
