<?php

namespace App\Support;

use App\Models\DiaryAssessment;
use App\Models\DiaryAttendanceRecord;
use App\Models\DiaryContent;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolClassComponent;
use App\Models\StudentEnrollment;
use App\Models\StudentPeriodConvalidation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Relatório agregado, somente leitura: não exporta notas nem nomes de estudantes. */
final class SchoolDiaryCompletionAudit
{
    public function forSchool(School $school): array
    {
        $classes = SchoolClass::whereHas('academicYear', fn ($q) => $q->where('school_id', $school->id))
            ->whereHas('enrollments')->with([
                'academicYear.periods', 'courses.components.area', 'courses.components.startsPeriod',
                'courses.components.endsPeriod', 'courses.startsPeriod', 'courses.endsPeriod',
                'enrollments.courses',
            ])->orderBy('academic_year_id')->orderBy('name')->get();
        $storedAssignments = SchoolClassComponent::whereIn('school_class_id', $classes->modelKeys())
            ->with('teacher:id,full_name')->get()->keyBy(fn ($a) => $a->school_class_id.':'.$a->curriculum_component_id);
        $assignments = collect();
        $excluded = [];
        $virtualId = -1;
        foreach ($classes as $class) {
            foreach ($class->courses as $course) {
                foreach ($course->components as $component) {
                    if (! $component->active || Str::lower(Str::ascii(trim($component->name))) === 'comportamento') {
                        $excluded[] = ['class_id' => $class->id, 'class' => $class->name,
                            'year' => $class->academicYear->reference_year, 'component_id' => $component->id,
                            'component' => $component->name, 'reason' => ! $component->active ? 'inactive_component' : 'behavior'];
                        continue;
                    }
                    $component->setRelation('course', $course);
                    $course->setRelation('academicYear', $class->academicYear);
                    $assignment = $storedAssignments->get($class->id.':'.$component->id);
                    if (! $assignment) {
                        $assignment = new SchoolClassComponent(['school_class_id' => $class->id,
                            'curriculum_component_id' => $component->id]);
                        $assignment->id = $virtualId--;
                        $assignment->setRelation('teacher', null);
                    }
                    $assignment->setRelation('schoolClass', $class)->setRelation('component', $component);
                    $assignments->push($assignment);
                }
            }
        }
        if ($assignments->count() > 600) {
            throw new \RuntimeException('Recorte de diários excede o limite da consulta agregada.');
        }
        $summaries = app(DiaryListSummary::class)->forAssignments($assignments);
        $classIds = $classes->modelKeys();
        $componentIds = $assignments->pluck('curriculum_component_id')->unique();
        $contents = DiaryContent::whereIn('school_class_id', $classIds)->whereIn('curriculum_component_id', $componentIds)
            ->get()->groupBy(fn ($row) => $row->school_class_id.':'.$row->curriculum_component_id);
        $attendance = DiaryAttendanceRecord::whereIn('school_class_id', $classIds)->whereIn('curriculum_component_id', $componentIds)
            ->where('lesson_count', '>', 0)->get()->groupBy(fn ($row) => $row->school_class_id.':'.$row->curriculum_component_id);
        $assessments = DiaryAssessment::whereIn('school_class_id', $classIds)->whereIn('curriculum_component_id', $componentIds)
            ->with('results')->get()->groupBy(fn ($row) => $row->school_class_id.':'.$row->curriculum_component_id);
        $originGrades = StudentPeriodConvalidation::whereIn('student_enrollment_id', $classes->flatMap->enrollments->pluck('id'))
            ->whereIn('curriculum_component_id', $componentIds)->whereNotNull('score')->get()
            ->keyBy(fn ($row) => $row->student_enrollment_id.':'.$row->curriculum_component_id.':'.$row->academic_period_id);
        $today = now()->toDateString();
        $rows = $assignments->map(function ($assignment) use ($summaries, $contents, $attendance, $assessments, $originGrades, $today): array {
            $class = $assignment->schoolClass;
            $component = $assignment->component;
            $course = $component->course;
            $summary = $summaries->get($assignment->id);
            $periods = collect($summary['periods'])->pluck('period');
            $periodIds = $periods->pluck('id');
            $key = $class->id.':'.$component->id;
            $diaryContents = $contents->get($key, collect())->whereIn('academic_period_id', $periodIds)
                ->filter(fn ($row) => $row->class_date->toDateString() <= $today && $this->hasContent($row->content));
            $diaryAssessments = $assessments->get($key, collect())->whereIn('academic_period_id', $periodIds);
            $scoreCount = $diaryAssessments->sum(fn ($a) => $a->results->whereNotNull('score')->count());
            $grades = $this->gradeCoverage($class, $course->id, $component->id, $periods, $diaryAssessments, $originGrades, $today);
            $originCount = $originGrades->where('curriculum_component_id', $component->id)
                ->whereIn('student_enrollment_id', $class->enrollments->pluck('id'))->whereIn('academic_period_id', $periodIds)->count();
            $recordsByDate = $attendance->get($key, collect())->whereIn('academic_period_id', $periodIds)
                ->keyBy(fn ($row) => $row->class_date->toDateString());
            $registered = $unweighted = 0;
            foreach ($diaryContents as $content) {
                $date = $content->class_date->toDateString();
                $lessons = $recordsByDate->get($date)?->lesson_count;
                if (! $lessons) {
                    $lessons = $class->schedules->filter(fn ($s) => $s->starts_at->toDateString() <= $date
                        && (! $s->ends_at || $s->ends_at->toDateString() >= $date))
                        ->flatMap->slots->where('type', 'aula')->where('school_class_component_id', $assignment->id)
                        ->where('weekday', $content->class_date->dayOfWeekIso)->count();
                }
                $registered += $lessons ?: 0;
                $unweighted += ! $lessons ? 1 : 0;
            }
            $expected = $summary['timeliness']['expected_today'];
            $contentPercentage = $expected > 0 ? round($registered * 100 / $expected, 2) : null;
            // Há conteúdo sem quantidade de aulas identificável: não concluir que é ausência.
            $contentUncertain = $unweighted > 0;
            $noContent = $diaryContents->isEmpty();
            $noScores = $scoreCount === 0;

            return [
                'assignment_id' => $assignment->exists ? $assignment->id : null,
                'assignment_active' => $assignment->exists ? $assignment->active : null,
                'class_id' => $class->id, 'class' => $class->name, 'class_active' => $class->active,
                'academic_year_id' => $class->academic_year_id, 'year' => $class->academicYear->reference_year,
                'academic_year' => $class->academicYear->name, 'closed' => $class->academicYear->isClosed(),
                'course_id' => $course->id, 'course' => $course->name, 'stage' => $course->stage,
                'component_id' => $component->id, 'component' => $component->name,
                'teacher' => $assignment->teacher?->full_name, 'class_hour_minutes' => $course->class_hour_minutes,
                'content_dates' => $diaryContents->pluck('class_date')->unique()->count(),
                'score_entries' => $scoreCount, 'origin_grade_entries' => $originCount,
                'no_content' => $noContent, 'no_scores' => $noScores, 'neither_content_nor_scores' => $noContent && $noScores,
                'planned_lessons' => $summary['planned'], 'forecast_source' => $summary['source'],
                'expected_lessons_to_date' => $expected, 'content_lessons' => $registered,
                'content_percentage_to_date' => $contentPercentage,
                'content_percentage_annual' => $summary['planned'] > 0 ? round($registered * 100 / $summary['planned'], 2) : null,
                'unweighted_content_dates' => $unweighted, 'content_percentage_uncertain' => $contentUncertain,
                'attendance_lessons' => $summary['attendance']['registered'],
                'future_entries' => $summary['future'], 'grade_coverage' => $grades,
                'below_90_content' => ! $contentUncertain && $expected > 0 && $registered < $expected * .9,
                'below_90_grades' => $grades['expected'] > 0 && $grades['complete'] < $grades['expected'] * .9,
                'starts_at' => $periods->min('starts_at')?->toDateString(),
                'ends_at' => $periods->max('ends_at')?->toDateString(),
                'periods' => $periods->map(fn ($p) => $p->only(['id', 'name', 'starts_at', 'ends_at']))->values(),
            ];
        })->values()->all();

        return ['school_id' => $school->id, 'school' => $school->name, 'as_of' => now()->toIso8601String(),
            'years' => $classes->pluck('academicYear.reference_year')->unique()->sort()->values()->all(),
            'diaries' => $rows, 'excluded_components' => $excluded];
    }

    private function hasContent(?string $content): bool
    {
        $text = html_entity_decode(strip_tags((string) $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/[\s\x{00a0}\x{200b}]+/u', '', $text) !== '';
    }

    private function gradeCoverage(SchoolClass $class, int $courseId, int $componentId, Collection $periods, Collection $assessments, Collection $originGrades, string $today): array
    {
        $expected = $complete = $partial = 0;
        $details = [];
        foreach ($periods->filter(fn ($p) => $p->ends_at && $p->ends_at->toDateString() < $today) as $period) {
            $eligible = $class->enrollments->filter(function ($enrollment) use ($courseId, $period): bool {
                $end = $enrollment->attendanceEndsAt();

                return $enrollment->courses->contains('id', $courseId)
                    && ! in_array($enrollment->status, [StudentEnrollment::STATUS_CANCELLED, StudentEnrollment::STATUS_RECLASSIFIED], true)
                    && (! $enrollment->enrolled_at || $enrollment->enrolled_at->toDateString() <= $period->ends_at->toDateString())
                    && (! $end || $end >= $period->ends_at->toDateString());
            });
            // Rematrículas não criam duas obrigações para a mesma pessoa e período.
            $eligible = $eligible->sortByDesc('enrolled_at')->unique('person_id');
            $averages = app(DiaryGradeCalculator::class)->averages($eligible, $assessments->where('academic_period_id', $period->id));
            $filled = $hasValue = 0;
            foreach ($eligible as $enrollment) {
                $origin = $originGrades->has($enrollment->id.':'.$componentId.':'.$period->id);
                $average = $averages[$enrollment->id];
                $filled += $origin || $average['complete'] ? 1 : 0;
                $hasValue += ! $origin && ! $average['complete'] && $average['value'] !== null ? 1 : 0;
            }
            $expected += $eligible->count();
            $complete += $filled;
            $partial += $hasValue;
            $details[] = ['period' => $period->name, 'ends_at' => $period->ends_at->toDateString(),
                'expected' => $eligible->count(), 'complete' => $filled, 'partial' => $hasValue];
        }

        return ['expected' => $expected, 'complete' => $complete, 'partial' => $partial,
            'percentage' => $expected > 0 ? round($complete * 100 / $expected, 2) : null, 'periods' => $details];
    }
}
