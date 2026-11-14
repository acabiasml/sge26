<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\IssuedDocument;
use App\Models\Person;
use App\Models\School;
use App\Support\PdfLetterhead;
use App\Support\StudentReportCardBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentIndividualRecordLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_thirteen_component_technical_record_keeps_signatures_on_the_same_a4_page(): void
    {
        $school = School::create(['name' => 'Instituto de Formação Técnica de Teste', 'legal_name' => 'Centro Técnico Juvenil de Teste',
            'cnpj' => '00176974000120', 'inep' => '51061716', 'founded_at' => '1990-01-01', 'city' => 'Poxoréu', 'state' => 'MT',
            'address' => 'Rua de Teste', 'postal_code' => '78800000', 'email' => 'instituto@example.test', 'phone' => '66999999999',
            'letterhead_text' => "Certificado de educação profissional\nCredenciamento e autorização de funcionamento da escola"]);
        foreach ([['Ótimo', 'OTI', 9, 10], ['Bom', 'BOM', 8, 8.9], ['Suficiente', 'SUF', 6, 7.9], ['Insuficiente', 'INS', 4.1, 5.9], ['Insuficiente Grave', 'INS GR', 0, 4]] as $i => [$name, $abbreviation, $min, $max]) {
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
        $course = $year->courses()->create(['name' => 'Técnico em Móveis', 'stage' => 'tecnico', 'class_hour_minutes' => 60,
            'technical_legal_basis' => 'Ato de autorização do curso técnico e publicação no Diário Oficial.']);
        foreach ([['Desenho Técnico I', 120, 0], ['Equipamentos e Tecnologia da Madeira', 120, 0], ['História da Arte e do Mobiliário', 60, 0], ['Informática Básica', 100, 0],
            ['Desenho Assistido por Computador I', 80, 1], ['Desenho Técnico II', 80, 1], ['Contabilidade Aplicada ao Setor Moveleiro', 60, 1], ['Processo de Fabricação I', 120, 1], ['Projeto e Design de Móveis', 60, 1],
            ['Desenho Assistido por Computador II', 80, 2], ['Administração Aplicada ao Setor Moveleiro', 60, 2], ['Processo de Fabricação II', 200, 2], ['Segurança do Trabalho, Meio Ambiente e Saúde', 60, 2]] as [$name, $hours, $module]) {
            $course->components()->create(['name' => $name, 'workload_hours' => $hours, 'starts_period_id' => $periods[$module]->id, 'ends_period_id' => $periods[$module]->id, 'active' => true]);
        }
        $class = $year->classes()->create(['name' => '2025-2026', 'starts_period_id' => $periods[0]->id, 'ends_period_id' => $periods[2]->id]);
        $class->courses()->attach($course);
        $person = Person::create(['full_name' => 'Estudante Fictício de Teste', 'cpf' => '12345678909', 'birth_date' => '2003-12-30',
            'birth_city' => 'Cidade de Teste', 'birth_state' => 'MT', 'nationality' => 'Brasileira', 'phone' => '66999999999',
            'institutional_email' => 'estudante@example.test', 'mother_name' => 'Mãe Fictícia de Teste', 'father_name' => 'Pai Fictício de Teste',
            'address' => 'Rua de Teste', 'city' => 'Poxoréu', 'state' => 'MT', 'postal_code' => '78800000']);
        $enrollment = $class->enrollments()->create(['person_id' => $person->id, 'enrolled_at' => '2025-02-03', 'status' => 'matriculado', 'type' => 'regular']);
        $enrollment->courses()->attach($course);
        $document = new IssuedDocument(['issued_at' => now(), 'verification_code' => 'TESTE-LAYOUT']);
        $document->setRelation('issuedBy', null);
        $html = view('reports.student-individual-record', [
            'report' => app(StudentReportCardBuilder::class)->build($enrollment), 'scoreView' => 'numeros', 'canChooseScoreView' => false,
            'issuedDocument' => $document, 'verificationUrl' => '', 'letterhead' => PdfLetterhead::make($school, ['tecnico']),
        ])->render();
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
        $pdf->render();
        $this->assertSame(1, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertStringContainsString('Turma e curso', $html);
        $this->assertStringContainsString('Diretora Fictícia de Teste', $html);
        $this->assertStringContainsString('Secretária Fictícia de Teste', $html);
    }
}
