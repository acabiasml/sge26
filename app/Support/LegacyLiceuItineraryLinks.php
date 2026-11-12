<?php

namespace App\Support;

use App\Models\AcademicCourse;
use App\Models\School;
use App\Models\StudentEnrollment;
use Illuminate\Support\Str;

/** Recorte da regra antiga, usado somente para conferir a regularização autorizada do Liceu. */
final class LegacyLiceuItineraryLinks
{
    public const SCHOOL_NAME = 'Liceu Pedagógico São Francisco de Assis';

    public function snapshot(): array
    {
        $school = School::where('name', self::SCHOOL_NAME)->sole();
        $technical = StudentEnrollment::whereHas('schoolClass.academicYear', fn ($query) => $query->where('school_id', $school->id))
            ->whereHas('courses', fn ($query) => $query->where('stage', AcademicCourse::STAGE_TECHNICAL))
            ->with(['courses.components.startsPeriod', 'courses.components.endsPeriod', 'schoolClass.academicYear', 'student:id,full_name'])
            ->get()->flatMap(function ($enrollment) {
                return $enrollment->courses->filter(fn ($course) => $course->stage === AcademicCourse::STAGE_TECHNICAL
                    && preg_match('/(?:tecnico\s+em\s+)?moveis/', Str::lower(Str::ascii($course->name))))
                    ->map(fn ($course) => ['enrollment' => $enrollment, 'course' => $course, 'year' => $enrollment->schoolClass->academicYear]);
            });
        $regular = StudentEnrollment::whereIn('person_id', $technical->pluck('enrollment.person_id')->unique())
            ->where('status', '!=', StudentEnrollment::STATUS_CANCELLED)
            ->whereHas('schoolClass.academicYear', fn ($query) => $query->where('school_id', $school->id))
            ->whereHas('courses', fn ($query) => $query->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL))
            ->with(['courses', 'schoolClass.academicYear', 'student:id,full_name'])->orderBy('id')->get();
        $links = $ambiguous = [];
        foreach ($regular as $enrollment) {
            $referenceYear = (int) ($enrollment->schoolClass->academicYear->reference_year ?: $enrollment->schoolClass->academicYear->starts_at?->year);
            $sources = $technical->where('enrollment.person_id', $enrollment->person_id)
                ->filter(fn ($source) => $referenceYear >= (int) $source['year']->starts_at?->year
                    && $referenceYear <= (int) $source['year']->ends_at?->year)
                ->sortByDesc(fn ($source) => sprintf('%010d-%010d',
                    $source['year']->starts_at?->diffInDays($source['year']->ends_at) ?? 0, $source['enrollment']->id))
                ->groupBy(fn ($source) => preg_replace('/^curso\s+/', '', Str::lower(Str::ascii(trim($source['course']->name)))))
                ->map->first()
                ->filter(fn ($source) => $source['enrollment']->id === $enrollment->id || $source['course']->components->contains(
                    fn ($component) => (int) ($component->endsPeriod?->ends_at?->year ?? $component->startsPeriod?->ends_at?->year
                        ?? $source['year']->ends_at?->year ?? $source['year']->reference_year) === $referenceYear
                ))->values();
            if ($sources->count() !== 1) {
                if ($sources->isNotEmpty()) {
                    $ambiguous[] = ['enrollment_id' => $enrollment->id, 'person_id' => $enrollment->person_id,
                        'technical_course_ids' => $sources->pluck('course.id')->all()];
                }
                continue;
            }
            $source = $sources->first();
            foreach ($enrollment->courses->where('stage', AcademicCourse::STAGE_HIGH_SCHOOL) as $course) {
                $links[] = ['enrollment_id' => $enrollment->id, 'person_id' => $enrollment->person_id,
                    'student_name' => $enrollment->student->full_name, 'high_school_course_id' => $course->id,
                    'high_school_course' => $course->name, 'reference_year' => $referenceYear,
                    'technical_course_id' => $source['course']->id, 'technical_course' => $source['course']->name,
                    'technical_enrollment_id' => $source['enrollment']->id, 'status' => $enrollment->status];
            }
        }
        $identity = collect($links)->map(fn ($row) => [$row['enrollment_id'], $row['high_school_course_id'], $row['technical_course_id']])->all();

        return ['school_id' => $school->id, 'scope' => 'Liceu / Técnico em Móveis / vínculos que a regra anterior já incorporava ao Médio',
            'links' => $links, 'count' => count($links), 'sha256' => hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR)),
            'ambiguous' => $ambiguous,
            'technical_offers' => $technical->pluck('course')->unique('id')->map(fn ($course) => [
                'course' => $course->only(['id', 'name', 'academic_year_id', 'active', 'legacy_source', 'legacy_metadata', 'notes']),
                'components' => $course->components->map(fn ($component) => $component->only(['id', 'name', 'workload_hours', 'knowledge_area_id', 'legacy_metadata']))->values()->all(),
            ])->values()->all()];
    }
}
