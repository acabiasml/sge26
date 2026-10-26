<?php

namespace App\Support;

final class StudentProgressSummary
{
    /** @param array<string, mixed> $report
     *  @return array<string, mixed>
     */
    public function fromReport(array $report): array
    {
        $passingPoints = (float) $report['passingPoints'];
        $minimumAttendance = (int) $report['minimumAttendance'];
        $components = $report['annualComponents']->map(function (array $summary) use ($passingPoints): array {
            $component = $summary['component'];
            $course = $component->course;
            $attendance = $summary['attendance'];
            $lessonMinutes = (int) ($course?->class_hour_minutes ?: 0);
            $rawAbsences = max(0, (int) $attendance['absent']);
            $justifiedAbsences = min($rawAbsences, max(0, (int) $attendance['justified']));
            $effectiveAbsences = $rawAbsences - $justifiedAbsences;
            $hasScore = $summary['periods']->contains(fn (array $period): bool => ($period['average']['value'] ?? null) !== null);

            return [
                'component' => $component,
                'course' => $course,
                'points' => $hasScore ? (float) $summary['points'] : null,
                'missing_points' => max(0, round($passingPoints - (float) $summary['points'], 2)),
                'complete_periods' => (int) $summary['complete_periods'],
                'total_periods' => (int) $summary['total_periods'],
                'attendance' => $attendance,
                'raw_absences' => $rawAbsences,
                'justified_absences' => $justifiedAbsences,
                'effective_absences' => $effectiveAbsences,
                'raw_absence_minutes' => $lessonMinutes > 0 ? $rawAbsences * $lessonMinutes : null,
                'justified_minutes' => $lessonMinutes > 0 ? $justifiedAbsences * $lessonMinutes : null,
                'absence_minutes' => $lessonMinutes > 0 ? $effectiveAbsences * $lessonMinutes : null,
                'planned_minutes' => $lessonMinutes > 0
                    ? (int) round($component->calculatedWorkloadHours($course) * 60)
                    : null,
            ];
        });

        $knownHours = $components->isNotEmpty()
            && $components->every(fn (array $component): bool => $component['planned_minutes'] !== null && $component['planned_minutes'] > 0);
        $plannedMinutes = $knownHours ? $components->sum('planned_minutes') : null;
        $rawAbsenceMinutes = $knownHours ? $components->sum('raw_absence_minutes') : null;
        $justifiedMinutes = $knownHours ? $components->sum('justified_minutes') : null;
        $absenceMinutes = $knownHours ? $components->sum('absence_minutes') : null;
        $limitMinutes = $plannedMinutes !== null
            ? (int) floor($plannedMinutes * (100 - $minimumAttendance) / 100)
            : null;

        return [
            'components' => $components,
            'passing_points' => $passingPoints,
            'minimum_attendance' => $minimumAttendance,
            'attendance' => $report['annualAttendance'],
            'raw_absences' => $components->sum('raw_absences'),
            'justified_absences' => $components->sum('justified_absences'),
            'effective_absences' => $components->sum('effective_absences'),
            'planned_minutes' => $plannedMinutes,
            'raw_absence_minutes' => $rawAbsenceMinutes,
            'justified_minutes' => $justifiedMinutes,
            'absence_minutes' => $absenceMinutes,
            'absence_limit_minutes' => $limitMinutes,
            'remaining_absence_minutes' => $limitMinutes !== null ? max(0, $limitMinutes - $absenceMinutes) : null,
            'over_absence_limit' => $limitMinutes !== null && $absenceMinutes > $limitMinutes,
        ];
    }
}
