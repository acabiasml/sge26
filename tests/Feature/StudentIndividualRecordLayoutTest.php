<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\KnowledgeArea;
use App\Models\Person;
use App\Models\School;
use App\Support\PdfLetterhead;
use App\Support\StudentReportCardBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class StudentIndividualRecordLayoutTest extends TestCase
{
    use RefreshDatabase;

    public static function curricula(): array
    {
        return [['tecnico'], ['fundamental']];
    }

    #[DataProvider('curricula')]
    public function test_record_keeps_signatures_on_the_same_a4_page(string $stage): void
    {
        $school = School::create(['name' => 'Instituto de Formação Técnica de Teste', 'legal_name' => 'Centro Técnico Juvenil de Teste',
            'cnpj' => '00176974000120', 'inep' => '51061716', 'founded_at' => '1990-01-01', 'city' => 'Poxoréu', 'state' => 'MT',
            'address' => 'Rua de Teste', 'postal_code' => '78800000', 'email' => 'instituto@example.test', 'phone' => '66999999999',
            'letterhead_text' => "Certificado de educação profissional\nCredenciamento e autorização de funcionamento da escola"]);
        foreach ([['Ótimo', 'OTI', 9, 10], ['Bom', 'BOM', 8, 8.9], ['Suficiente', 'SUF', 6, 7.9], ['Insuficiente', 'INS', 4.1, 5.9], ['Insuficiente Grave', 'INS GR', 0, 4]] as $i => [$name, $abbreviation, $min, $max]) {
            if ($stage === 'fundamental' && $i === 4) continue;
            $school->concepts()->create(['name' => $name, 'abbreviation' => $abbreviation, 'minimum_score' => $min, 'maximum_score' => $max, 'sort_order' => $i, 'effective_from' => '2025-01-01']);
        }
        foreach ([['Diretora Fictícia de Teste', 'diretor'], ['Secretária Fictícia de Teste', 'secretário']] as [$name, $position]) {
            $staff = Person::create(['full_name' => $name]);
            $staff->schoolRoles()->create(['role' => 'gestor', 'position' => $position, 'school_id' => $school->id, 'active' => true, 'started_at' => '2025-01-01']);
        }
        $year = AcademicYear::create(['school_id' => $school->id, 'name' => '2025-2026', 'reference_year' => 2025, 'starts_at' => '2025-02-03', 'ends_at' => '2026-12-22']);
        $periods = [];
        foreach ([['I', '2025-02-03', '2025-09-15'], ['II', '2025-09-16', '2026-05-14'], ['III', '2026-05-15', '2026-12-22']] as $i => [$number, $start, $end]) {
            $periods[] = $year->periods()->create(['name' => 'Módulo '.$number, 'position' => $i + 1, 'starts_at' => $start, 'ends_at' => $end]);
        }
        $course = $year->courses()->create(['name' => $stage === 'tecnico' ? 'Técnico em Móveis' : '8º Ano', 'stage' => $stage, 'class_hour_minutes' => 60,
            'technical_legal_basis' => 'Ato de autorização do curso técnico e publicação no Diário Oficial.']);
        foreach ([['Desenho Técnico I', 120, 0], ['Equipamentos e Tecnologia da Madeira', 120, 0], ['História da Arte e do Mobiliário', 60, 0], ['Informática Básica', 100, 0],
            ['Desenho Assistido por Computador I', 80, 1], ['Desenho Técnico II', 80, 1], ['Contabilidade Aplicada ao Setor Moveleiro', 60, 1], ['Processo de Fabricação I', 120, 1], ['Projeto e Design de Móveis', 60, 1],
            ['Desenho Assistido por Computador II', 80, 2], ['Administração Aplicada ao Setor Moveleiro', 60, 2], ['Processo de Fabricação II', 200, 2], ['Segurança do Trabalho, Meio Ambiente e Saúde', 60, 2]] as [$name, $hours, $module]) {
            $course->components()->create(['name' => $name, 'workload_hours' => $hours, 'starts_period_id' => $periods[$module]->id, 'ends_period_id' => $periods[$module]->id, 'active' => true]);
        }
        if ($stage === 'fundamental') {
            $course->components()->delete();
            foreach ([
                ['Ciências da Natureza', ['Ciências']], ['Ciências Humanas', ['Geografia', 'História']],
                ['Ensino Religioso', ['Ensino Religioso']], ['Linguagens', ['Arte', 'Educação Física', 'Língua Inglesa', 'Língua Portuguesa']],
                ['Matemática', ['Matemática']], ['Parte Diversificada', ['Cultivo de Hortaliças', 'Estudo Orientado', 'Informática Básica', 'Marcenaria Manual', 'Marchetaria']],
            ] as [$areaName, $names]) {
                $area = KnowledgeArea::firstOrCreate(['name' => $areaName]);
                foreach ($names as $name) {
                    $course->components()->create(['name' => $name, 'knowledge_area_id' => $area->id, 'workload_hours' => 120, 'active' => true]);
                }
            }
            $year->periods()->delete();
            foreach ([['I', '2025-02-03', '2025-04-15'], ['II', '2025-04-16', '2025-06-30'], ['III', '2025-07-01', '2025-09-30'], ['IV', '2025-10-01', '2026-12-22']] as $i => [$number, $start, $end]) {
                $periods[$i] = $year->periods()->create(['name' => $number.' Bimestre', 'position' => $i + 1, 'starts_at' => $start, 'ends_at' => $end]);
            }
        }
        $class = $year->classes()->create(['name' => $stage === 'tecnico' ? '2025-2026' : '8º Ano', 'starts_period_id' => $periods[0]->id, 'ends_period_id' => $periods[2]->id]);
        $class->courses()->attach($course);
        $person = Person::create(['full_name' => 'Estudante Fictício de Teste', 'cpf' => '12345678909', 'birth_date' => '2003-12-30',
            'birth_city' => 'Cidade de Teste', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'phone' => '66999999999',
            'institutional_email' => 'estudante@example.test', 'mother_name' => 'Mãe Fictícia de Teste', 'father_name' => 'Pai Fictício de Teste',
            'address' => 'Rua de Teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000']);
        $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2025-02-03', 'status' => 'matriculado', 'type' => 'regular']);
        $enrollment->courses()->attach($course);
        if ($stage === 'fundamental') {
            $person->update(['nis' => '12345678901', 'legacy_code' => 'SES-0001', 'student_inep' => '123456789012']);
            $enrollment->update(['final_result_status' => 'aprovado', 'final_result_calculated_at' => now(), 'final_result_details' => ['reason' => 'Estudante alcançou a soma mínima de pontos e a frequência mínima.']]);
        }
        $document = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'TESTE-LAYOUT']);
        $document->setRelation('issuedBy', null);
        $html = view('reports.student-individual-record', [
            'report' => app(StudentReportCardBuilder::class)->build($enrollment), 'scoreView' => 'numeros', 'canChooseScoreView' => false,
            'issuedDocument' => $document, 'verificationUrl' => '', 'letterhead' => PdfLetterhead::make($school, [$stage]),
        ])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $signatureTop = $footerTop = INF;
        $signatureBottom = $dateBottom = 0;
        $safetyLines = $classLines = null;
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$signatureTop, &$footerTop, &$signatureBottom, &$dateBottom, &$safetyLines, &$classLines): void {
            $node = $frame->get_node();
            if (! $node instanceof \DOMElement) {
                return;
            }
            if ($node->tagName === 'td' && trim($node->textContent) === 'Segurança do Trabalho, Meio Ambiente e Saúde') $safetyLines = count($frame->get_line_boxes());
            if ($node->tagName === 'td' && str_starts_with(trim($node->textContent), 'Turma e curso:')) $classLines = count($frame->get_line_boxes());
            $box = $frame->get_border_box();
            match ($node->getAttribute('class')) {
                'signature-line' => $signatureTop = min($signatureTop, $box[1]),
                'signature-role' => $signatureBottom = max($signatureBottom, $box[1] + $box[3]),
                'issue-place-date' => $dateBottom = max($dateBottom, $box[1] + $box[3]),
                'document-footer' => $footerTop = min($footerTop, $box[1]),
                default => null,
            };
        }]]);
        $pdf->render();
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertGreaterThan(0, $signatureBottom);
        $this->assertGreaterThanOrEqual($dateBottom + 30, $signatureTop);
        $this->assertGreaterThanOrEqual($signatureBottom + 6, $footerTop);
        if ($stage === 'tecnico') {
            $this->assertSame(1, $safetyLines, 'O nome completo da disciplina de segurança deve caber em uma linha.');
            $this->assertSame(1, $classLines, 'Turma e curso devem caber na célula ampliada.');
        }
        $this->assertStringContainsString($stage === 'tecnico' ? 'Turma e curso' : 'Turma e etapa', $html);
        $this->assertStringContainsString('Diretora Fictícia de Teste', $html);
        $this->assertStringContainsString('Secretária Fictícia de Teste', $html);
    }
}
