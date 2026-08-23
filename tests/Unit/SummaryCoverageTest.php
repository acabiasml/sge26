<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SummaryCoverageTest extends TestCase
{
    public function test_summary_views_keep_their_essential_information(): void
    {
        $expectedLabels = [
            'academic-courses/show.blade.php' => ['Situação', 'Formação', 'componentes ativos', 'turmas vinculadas', 'carga prevista', 'Observações'],
            'academic-years/show.blade.php' => ['dias letivos previstos', 'matrizes prontas', 'impedimentos ao fechamento', 'Duração da hora-aula', 'Estrutura acadêmica', 'Observações'],
            'curriculum-components/show.blade.php' => ['Componente', 'Escola', 'Ano letivo', 'Formação', 'Situação', 'Observações'],
            'school-classes/show.blade.php' => ['matrículas ativas', 'carga prevista', 'Etapa', 'Matrizes', 'Horários', 'Observações'],
            'teacher-diaries/show.blade.php' => ['Turno', 'Vigência do componente', 'Carga horária prevista', 'Período atual', 'Situação do período', 'estudantes ativos'],
            'student-histories/show.blade.php' => ['Emissão', 'Origem dos dados'],
            'student-map/show.blade.php' => ['Documentos emitidos', 'Contatos'],
            'student-report-cards/show.blade.php' => ['Matrícula', 'Resultado final'],
            'academic-years/periods/index.blade.php' => ['regras de avaliação', 'períodos consolidados'],
            'document-issuance/index.blade.php' => ['ano(s) letivo(s)', 'turma(s) acessíveis'],
            'teacher-diaries/management-index.blade.php' => ['reabertos'],
            'data-quality/index.blade.php' => ['Total em análise'],
            'schools/concepts/index.blade.php' => ['conceitos vigentes', 'dependência permitida', 'registros históricos'],
        ];

        foreach ($expectedLabels as $relativePath => $labels) {
            $contents = file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$relativePath);

            foreach ($labels as $label) {
                $this->assertStringContainsString($label, $contents, $relativePath.' deve informar: '.$label);
            }
        }
    }
}
