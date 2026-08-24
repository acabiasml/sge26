<?php

namespace Tests\Unit;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\StudentEnrollment;
use App\Support\UnifiedStudentHistorySynchronizer;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class UnifiedStudentHistoryTechnicalCalendarTest extends TestCase
{
    public function test_components_from_a_multiyear_technical_calendar_are_assigned_when_their_module_ends(): void
    {
        $technicalYear = new AcademicYear([
            'reference_year' => 2025,
            'starts_at' => '2025-02-03',
            'ends_at' => '2026-12-22',
        ]);
        $course = new AcademicCourse(['name' => 'Técnico em Móveis', 'stage' => AcademicCourse::STAGE_TECHNICAL]);
        $course->id = 48;
        $enrollment = new StudentEnrollment;
        $enrollment->id = 628;

        $moduleOne = $this->makeCurriculumComponent(542, 48, 'Desenho Técnico I', '2025-02-03', '2025-09-15');
        $moduleTwo = $this->makeCurriculumComponent(548, 48, 'Desenho Técnico II', '2025-09-16', '2026-05-14');
        $sources = collect([[
            'enrollment' => $enrollment,
            'course' => $course,
            'report' => [
                'academicYear' => $technicalYear,
                'annualComponents' => collect([
                    ['component' => $moduleOne],
                    ['component' => $moduleTwo],
                ]),
            ],
        ]]);

        $this->assertSame(
            ['Desenho Técnico I'],
            $this->componentsForYear($sources, 2025)->pluck('component.name')->all(),
        );
        $this->assertSame(
            ['Desenho Técnico II'],
            $this->componentsForYear($sources, 2026)->pluck('component.name')->all(),
        );
    }

    public function test_longer_current_calendar_wins_over_duplicate_legacy_technical_enrollment(): void
    {
        $legacy = $this->source(105, 13, 'Curso Técnico em Móveis', '2025-01-01', '2025-12-31', 'Componente legado');
        $current = $this->source(628, 48, 'Técnico em Móveis', '2025-02-03', '2026-12-22', 'Componente atual');

        $this->assertSame(
            ['Componente atual'],
            $this->componentsForYear(collect([$legacy, $current]), 2025)->pluck('component.name')->all(),
        );
    }

    public function test_technical_module_label_uses_period_and_intermediate_certification(): void
    {
        $course = new AcademicCourse([
            'name' => 'Técnico em Móveis',
            'stage' => AcademicCourse::STAGE_TECHNICAL,
            'module_certifications' => "Auxiliar de fabricação\nDesenhista de móveis\nTécnico em Móveis",
        ]);
        $period = new AcademicPeriod(['name' => 'II Módulo', 'position' => 2]);
        $component = new CurriculumComponent(['name' => 'Desenho Técnico']);
        $component->setRelation('startsPeriod', $period);
        $component->setRelation('endsPeriod', $period);
        $synchronizer = $this->getMockBuilder(UnifiedStudentHistorySynchronizer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $method = new ReflectionMethod($synchronizer, 'technicalModuleLabel');

        $this->assertSame(
            'Módulo II — Desenhista de móveis (II Módulo)',
            $method->invoke($synchronizer, $component, $course),
        );
    }

    public function test_technical_regulatory_reference_is_specific_to_the_course_offer(): void
    {
        $course = new AcademicCourse([
            'stage' => AcademicCourse::STAGE_TECHNICAL,
            'authorization_act' => 'Ato 056/2025-CEE/MT',
            'regulatory_process' => '7/2023/SIPE/CEE-MT',
            'regulatory_opinion' => 'CEPS 8/2025',
            'official_gazette_reference' => 'DOE-MT nº 28.918, p. 27, 28/01/2025',
        ]);

        $reference = $course->regulatoryReference();

        $this->assertStringContainsString('Resolução CNE/CP nº 1/2021', $reference);
        $this->assertStringContainsString('Ato 056/2025-CEE/MT', $reference);
        $this->assertStringContainsString('DOE-MT nº 28.918', $reference);
    }

    public function test_concise_technical_reference_keeps_only_essential_rules_and_authorization(): void
    {
        $course = new AcademicCourse([
            'stage' => AcademicCourse::STAGE_TECHNICAL,
            'authorization_act' => 'Ato 056/2025-CEE/MT',
            'regulatory_process' => '7/2023/SIPE/CEE-MT',
            'official_gazette_reference' => 'DOE-MT nº 28.918, p. 27, 28/01/2025',
        ]);

        $reference = $course->conciseRegulatoryReference();

        $this->assertSame(
            'Lei Federal nº 9.394/1996, arts. 36-B a 42; Resolução CNE/CP nº 1/2021; Ato 056/2025-CEE/MT.',
            $reference,
        );
    }

    public function test_technical_module_without_named_exit_is_still_identified_as_certifiable(): void
    {
        $course = new AcademicCourse(['stage' => AcademicCourse::STAGE_TECHNICAL]);
        $period = new AcademicPeriod(['name' => 'Módulo I', 'position' => 1]);
        $component = new CurriculumComponent(['name' => 'Desenho Técnico']);
        $component->setRelation('startsPeriod', $period);
        $component->setRelation('endsPeriod', $period);
        $synchronizer = $this->getMockBuilder(UnifiedStudentHistorySynchronizer::class)->disableOriginalConstructor()->getMock();
        $method = new ReflectionMethod($synchronizer, 'technicalModuleLabel');

        $this->assertSame('Módulo I — Certificação intermediária (Módulo I)', $method->invoke($synchronizer, $component, $course));
    }

    private function source(int $enrollmentId, int $courseId, string $courseName, string $startsAt, string $endsAt, string $componentName): array
    {
        $year = new AcademicYear(['reference_year' => 2025, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
        $course = new AcademicCourse(['name' => $courseName, 'stage' => AcademicCourse::STAGE_TECHNICAL]);
        $course->id = $courseId;
        $enrollment = new StudentEnrollment;
        $enrollment->id = $enrollmentId;

        return [
            'enrollment' => $enrollment,
            'course' => $course,
            'report' => [
                'academicYear' => $year,
                'annualComponents' => collect([
                    ['component' => $this->makeCurriculumComponent($courseId, $courseId, $componentName, $startsAt, '2025-12-31')],
                ]),
            ],
        ];
    }

    private function makeCurriculumComponent(int $id, int $courseId, string $name, string $startsAt, string $endsAt): CurriculumComponent
    {
        $component = new CurriculumComponent(['academic_course_id' => $courseId, 'name' => $name]);
        $component->id = $id;
        $component->setRelation('startsPeriod', new AcademicPeriod(['starts_at' => $startsAt, 'ends_at' => $endsAt]));
        $component->setRelation('endsPeriod', new AcademicPeriod(['starts_at' => $startsAt, 'ends_at' => $endsAt]));

        return $component;
    }

    /** @param Collection<int, array<string, mixed>> $sources */
    private function componentsForYear(Collection $sources, int $year): Collection
    {
        $synchronizer = $this->getMockBuilder(UnifiedStudentHistorySynchronizer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $method = new ReflectionMethod($synchronizer, 'technicalComponentsForYear');

        return $method->invoke($synchronizer, $sources, $year);
    }
}
