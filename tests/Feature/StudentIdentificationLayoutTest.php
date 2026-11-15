<?php

namespace Tests\Feature;

use App\Models\AcademicCourse;
use App\Models\AcademicYear;
use App\Models\Person;
use App\Models\SchoolClass;
use Barryvdh\DomPDF\Facade\Pdf;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StudentIdentificationLayoutTest extends TestCase
{
    public static function modes(): array
    {
        return [['full'], ['individual'], ['bulletin']];
    }

    #[DataProvider('modes')]
    public function test_long_identification_values_wrap_inside_the_page_in_every_document_mode(string $mode): void
    {
        $student = new Person([
            'full_name' => 'Estudante de Teste com Nome Completo Bastante Extenso para Conferência de Impressão',
            'social_name' => 'Nome Social de Teste Também Extenso',
            'birth_date' => '2004-12-31', 'cpf' => '12345678909', 'student_inep' => '123456789012',
            'legacy_code' => '123456789012345', 'nis' => '12345678901', 'receives_federal_aid' => true,
            'birth_city' => 'Município de Nascimento de Nome Muito Comprido', 'birth_state' => 'MT',
            'nationality' => 'Brasileira naturalizada', 'phone' => '(66) 99999-9999',
            'personal_email' => str_repeat('estudante', 8).'@example.test',
            'institutional_email' => str_repeat('institucional', 6).'@example.test',
            'mother_name' => 'Nome Completo da Mãe de Teste Bastante Extenso para Conferência',
            'father_name' => 'Nome Completo do Pai de Teste Bastante Extenso para Conferência',
            'address' => 'Avenida de Nome Muito Comprido para Conferência do Endereço Completo',
            'number' => '12345', 'address_complement' => 'Apartamento 123, Bloco de Teste',
            'district' => 'Bairro de Nome Extenso', 'city' => 'Município de Teste', 'state' => 'MT', 'postal_code' => '78800-000',
        ]);
        $html = '<style>@page { size: A4 portrait; margin: 24px; } body { font-size: 11px; line-height: 1.06; }'
            .view('reports.partials.letterhead-styles')->render()
            .'.meta-table { width: 100%; border-collapse: collapse; } .meta-table td { border: .45px solid #d8ccc4; padding: 1.5px 3px; }</style>'
            .view('reports.partials.student-identification', [
                'student' => $student, 'mode' => $mode,
                'academicYear' => new AcademicYear(['reference_year' => 2026, 'starts_at' => '2026-02-01', 'ends_at' => '2026-12-20']),
                'schoolClass' => new SchoolClass(['name' => 'Turma com nome extenso para conferência da etapa']),
                'courses' => collect([new AcademicCourse(['stage' => 'medio'])]),
            ])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $cells = 0;
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$cells): void {
            $node = $frame->get_node();
            if (! $node instanceof \DOMElement || $node->nodeName !== 'td') {
                return;
            }
            $cells++;
            $box = $frame->get_content_box();
            foreach ($frame->get_line_boxes() as $line) {
                $this->assertLessThanOrEqual($box[2] + 1, $line->w, 'O texto deve quebrar dentro da célula.');
            }
            $this->assertGreaterThanOrEqual(17, $box[0]);
            $this->assertLessThanOrEqual(579, $box[0] + $box[2]);
        }]]);
        $pdf->render();
        $this->assertGreaterThan(0, $cells);
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringContainsString('123456789012', $html);
        if ($mode !== 'bulletin') {
            $this->assertStringContainsString('123.456.789-09', $html);
            $this->assertStringContainsString($student->personal_email, $html);
            $this->assertStringContainsString($student->institutional_email, $html);
            $this->assertStringContainsString($student->mother_name, $html);
            $this->assertStringContainsString($student->father_name, $html);
        }
    }
}
