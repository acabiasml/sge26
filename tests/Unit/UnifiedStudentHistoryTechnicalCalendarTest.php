<?php

namespace Tests\Unit;

use App\Models\AcademicCourse;
use App\Models\AcademicPeriod;
use App\Models\AcademicYear;
use App\Models\CurriculumComponent;
use App\Models\StudentEnrollment;
use App\Support\UnifiedStudentHistorySynchronizer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

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

        $moduleOne = $this->component(542, 48, 'Desenho Técnico I', '2025-02-03', '2025-09-15');
        $moduleTwo = $this->component(548, 48, 'Desenho Técnico II', '2025-09-16', '2026-05-14');
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
                    ['component' => $this->component($courseId, $courseId, $componentName, $startsAt, '2025-12-31')],
                ]),
            ],
        ];
    }

    private function component(int $id, int $courseId, string $name, string $startsAt, string $endsAt): CurriculumComponent
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
