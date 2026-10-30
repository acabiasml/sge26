<?php

namespace App\Support;

use App\Models\DiaryAttendanceJustification;
use App\Models\DiaryAttendanceRecord;
use Illuminate\Support\Collection;

final class AttendanceSummaryCalculator
{
    /** Converte somente os minutos de presença efetivamente registrados nos diários. */
    public function completedWorkloadHours(array $attendance): ?float
    {
        $minutes = $attendance['diary_attended_minutes'] ?? null;

        return $minutes === null ? null : round(max(0, (int) $minutes) / 60, 2);
    }

    /**
     * @param  Collection<int, DiaryAttendanceRecord>  $records
     * @param  Collection<int, DiaryAttendanceJustification>  $justifications
     * @return array{lessons: int, attended: int, absent: int, justified: int, effective_attended: int, percentage: float|null, diary_attended_minutes: int|null}
     */
    public function summarize(Collection $records, Collection $justifications, ?int $lessonMinutes = null): array
    {
        $lessons = 0;
        $attended = 0;
        $justified = 0;
        $diaryAttended = 0;

        foreach ($records as $record) {
            $entry = $record->entries->first();
            $lessonCount = (int) $record->lesson_count;
            $attendedLessons = (int) ($entry?->attended_lessons ?? 0);
            $lessons += $lessonCount;
            $attended += $attendedLessons;
            $diaryAttended += max(0, min($lessonCount, $attendedLessons));

            $isJustified = $entry?->status === DiaryAttendanceRecord::STATUS_EXCUSED
                || $justifications->contains(fn (DiaryAttendanceJustification $justification): bool => $justification->appliesTo($record->class_date->toDateString()));

            if ($isJustified) {
                $justified += max(0, $lessonCount - $attendedLessons);
            }
        }

        return $this->fromTotals($lessons, $attended, $justified) + [
            'diary_attended_minutes' => $lessonMinutes > 0 ? $diaryAttended * $lessonMinutes : null,
        ];
    }

    /**
     * @param  Collection<int, array{lessons?: int, attended?: int, justified?: int, diary_attended_minutes?: int|null}>  $summaries
     * @return array{lessons: int, attended: int, absent: int, justified: int, effective_attended: int, percentage: float|null, diary_attended_minutes?: int|null}
     */
    public function aggregate(Collection $summaries): array
    {
        $summary = $this->fromTotals(
            $summaries->sum(fn (array $summary): int => (int) ($summary['lessons'] ?? 0)),
            $summaries->sum(fn (array $summary): int => (int) ($summary['attended'] ?? 0)),
            $summaries->sum(fn (array $summary): int => (int) ($summary['justified'] ?? 0)),
        );
        $diaries = $summaries->filter(fn (array $item): bool => array_key_exists('diary_attended_minutes', $item));
        if ($diaries->isNotEmpty()) {
            $summary['diary_attended_minutes'] = $diaries->contains(fn (array $item): bool => $item['diary_attended_minutes'] === null)
                ? null
                : $diaries->sum('diary_attended_minutes');
        }

        return $summary;
    }

    /**
     * @return array{lessons: int, attended: int, absent: int, justified: int, effective_attended: int, percentage: float|null}
     */
    public function fromTotals(int $lessons, int $attended, int $justified): array
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
