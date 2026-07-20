<?php

namespace App\Support;

use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use Illuminate\Support\Collection;

final class AttendanceSummaryCalculator
{
    /**
     * @param  Collection<int, DiaryAttendanceRecord>  $records
     * @param  Collection<int, DiaryAttendanceJustification>  $justifications
     * @return array{lessons: int, attended: int, absent: int, justified: int, effective_attended: int, percentage: float|null}
     */
    public function summarize(Collection $records, Collection $justifications): array
    {
        $lessons = 0;
        $attended = 0;
        $justified = 0;

        foreach ($records as $record) {
            $entry = $record->entries->first();
            $lessonCount = (int) $record->lesson_count;
            $attendedLessons = (int) ($entry?->attended_lessons ?? 0);
            $lessons += $lessonCount;
            $attended += $attendedLessons;

            $isJustified = $entry?->status === DiaryAttendanceRecord::STATUS_EXCUSED
                || $justifications->contains(fn (DiaryAttendanceJustification $justification): bool => $justification->appliesTo($record->class_date->toDateString()));

            if ($isJustified) {
                $justified += max(0, $lessonCount - $attendedLessons);
            }
        }

        return $this->fromTotals($lessons, $attended, $justified);
    }

    /**
     * @param  Collection<int, array{lessons?: int, attended?: int, justified?: int}>  $summaries
     * @return array{lessons: int, attended: int, absent: int, justified: int, effective_attended: int, percentage: float|null}
     */
    public function aggregate(Collection $summaries): array
    {
        return $this->fromTotals(
            $summaries->sum(fn (array $summary): int => (int) ($summary['lessons'] ?? 0)),
            $summaries->sum(fn (array $summary): int => (int) ($summary['attended'] ?? 0)),
            $summaries->sum(fn (array $summary): int => (int) ($summary['justified'] ?? 0)),
        );
    }

    /**
     * @return array{lessons: int, attended: int, absent: int, justified: int, effective_attended: int, percentage: float|null}
     */
    private function fromTotals(int $lessons, int $attended, int $justified): array
    {
        $effectiveAttended = min($lessons, $attended + $justified);

        return [
            'lessons' => $lessons,
            'attended' => $attended,
            'absent' => max(0, $lessons - $attended),
            'justified' => $justified,
            'effective_attended' => $effectiveAttended,
            'percentage' => $lessons > 0 ? round(($effectiveAttended / $lessons) * 100, 1) : null,
        ];
    }
}
