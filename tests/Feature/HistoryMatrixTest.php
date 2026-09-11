<?php

namespace Tests\Feature;

use App\Models\StudentAcademicHistory;
use App\Models\StudentAcademicHistoryComponent;
use App\Models\StudentAcademicHistoryRecord;
use App\Models\StudentAcademicHistoryYear;
use Tests\TestCase;

class HistoryMatrixTest extends TestCase
{
    public function test_global_year_spans_all_components_without_discarding_detailed_results(): void
    {
        $history = new StudentAcademicHistory;
        $global = new StudentAcademicHistoryYear(['label' => '1º Ano', 'transcript_mode' => 'summary', 'final_result' => 'Aprovado', 'workload_hours' => 800]);
        $global->id = 1;
        $detailed = new StudentAcademicHistoryYear(['label' => '2º Ano', 'transcript_mode' => 'detailed', 'workload_hours' => 1000]);
        $detailed->id = 2;
        $history->setRelation('years', collect([$global, $detailed]));
        $history->setRelation('components', collect(['Língua Portuguesa', 'Matemática'])->map(function ($name) {
            $component = new StudentAcademicHistoryComponent(['name' => $name, 'formation' => 'Formação Geral Básica', 'knowledge_area' => 'Área']);
            $component->setRelation('records', collect([new StudentAcademicHistoryRecord([
                'student_academic_history_year_id' => 2, 'score_label' => '8,5', 'workload_hours' => 200,
            ])]));

            return $component;
        }));
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//td[@data-global-year="1" and @rowspan="2"]')->length);
        $this->assertSame(2, substr_count($html, '8,5'));
        $this->assertStringContainsString('Língua Portuguesa', $html);
        $this->assertStringContainsString('Matemática', $html);
        $this->assertSame(1, substr_count($html, 'Aprovado'));

        $global->transcript_mode = 'detailed';
        $html = view('reports.partials.basic-history-matrix', compact('history'))->render();
        $this->assertStringNotContainsString('data-global-year="1"', $html);
        $this->assertSame(2, substr_count($html, '8,5'));
    }
}
