<?php

namespace Tests\Feature;

use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryComponent;
use App\Models\StudentAcademicHistoryRecord;
use App\Models\StudentAcademicHistoryYear;
use Tests\TestCase;

class HistoryMatrixTest extends TestCase
{
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
                if ($name !== 'Ensino Religioso') $this->assertStringContainsString($name, $html);
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
        $this->assertStringContainsString('800,00h', $annual);
        $this->assertStringContainsString('200,00h', $annual);
        $this->assertSame(1, substr_count($annual, '850,00h'));
        $this->assertSame(1, substr_count($annual, '250,00h'));
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertStringContainsString('Carga horária cursada', $html);
        $this->assertStringContainsString('formation-cell', $html);
        $this->assertStringNotContainsString('850', $html);
        $this->assertStringNotContainsString('250', $html);
        $this->assertStringNotContainsString('97', $html);
        $this->assertStringNotContainsString('F%', $html);
        $totals = view('reports.partials.history-formation-workloads', compact('history'))->render();
        $this->assertStringContainsString('1.650,00h', $totals);
        $this->assertStringContainsString('800,00h', $totals);
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
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<style>table { width:100%; border-collapse:collapse; } td, th { font-size:11px; padding:4px; }</style>'.$html)->setPaper('a5');
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
