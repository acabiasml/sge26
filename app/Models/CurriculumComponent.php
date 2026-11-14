<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\HistoryCurriculumCatalog;
use App\Support\MatrixHistoryCatalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class CurriculumComponent extends Model
{
    use Auditable, HasFactory;

    private ?HistoryCurriculumComponent $selectedCatalogDefinition = null;

    protected static function booted(): void
    {
        static::saved(fn (self $component) => app(MatrixHistoryCatalog::class)->syncComponent($component, $component->wasChanged(['name', 'knowledge_area_id']), $component->selectedCatalogDefinition));
    }

    protected $fillable = [
        'academic_course_id',
        'knowledge_area_id',
        'starts_period_id',
        'ends_period_id',
        'name',
        'weekly_lessons',
        'workload_hours',
        'period_loads',
        'notes',
        'legacy_source',
        'legacy_id',
        'legacy_metadata',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'weekly_lessons' => 'integer',
            'legacy_metadata' => 'array',
            'period_loads' => 'array',
            'active' => 'boolean',
        ];
    }

    public function historyCatalogEntries(): BelongsToMany
    {
        return $this->belongsToMany(HistoryCurriculumComponent::class, 'matrix_catalog_components');
    }

    public function adoptCatalogDefinition(HistoryCurriculumComponent $entry): void
    {
        HistoryCurriculumCatalog::validateDefinition($entry, $this->course->stage);
        $this->setRawAttributes(array_merge($this->getAttributes(), [
            'name' => $entry->name, 'knowledge_area_id' => $entry->knowledge_area_id,
        ]));
        $this->selectedCatalogDefinition = $entry;
        try {
            $this->save();
        } finally {
            $this->selectedCatalogDefinition = null;
            $this->unsetRelation('historyCatalogEntries');
        }
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademicCourse::class, 'academic_course_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArea::class, 'knowledge_area_id');
    }

    public function startsPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'starts_period_id');
    }

    public function endsPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'ends_period_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(DiaryAssessment::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(DiaryAttendanceRecord::class);
    }

    public function calculatedWorkloadHours(?AcademicCourse $course = null): float
    {
        if ($this->hasPeriodLoads()) {
            $course ??= $this->course;

            return $this->calculatedWorkloadHoursForPeriods($course->academicYear->periods, $course);
        }
        if ($this->workload_hours !== null) {
            return (float) $this->workload_hours;
        }

        $course ??= $this->relationLoaded('course') ? $this->course : $this->course()->first();

        if (! $course || $this->weekly_lessons === null) {
            return 0.0;
        }

        $course->loadMissing('academicYear.periods', 'startsPeriod', 'endsPeriod');
        $year = $course->academicYear;
        $weeks = ($year?->minimum_school_days ?: 200) / 5;
        $periods = $year?->periods;

        if ($periods?->isNotEmpty()) {
            $activePeriods = $periods->filter(fn (AcademicPeriod $period): bool => $this->isActiveInPeriod($period)
                && (! $course->startsPeriod || $period->position >= $course->startsPeriod->position)
                && (! $course->endsPeriod || $period->position <= $course->endsPeriod->position)
            )->count();
            $weeks *= $activePeriods / $periods->count();
        }

        return round(((int) $this->weekly_lessons * (int) $course->class_hour_minutes * $weeks) / 60, 2);
    }

    public function formattedCalculatedWorkloadHours(?AcademicCourse $course = null): string
    {
        return number_format($this->calculatedWorkloadHours($course), 2, ',', '.');
    }

    public function isActiveInPeriod(AcademicPeriod $period): bool
    {
        $this->loadMissing('startsPeriod', 'endsPeriod');

        $starts = $this->startsPeriod;
        $ends = $this->endsPeriod;

        if ($starts && $period->position < $starts->position) {
            return false;
        }

        if ($ends && $period->position > $ends->position) {
            return false;
        }

        if ($this->hasPeriodLoads()) {
            $load = $this->period_loads[$period->id] ?? null;

            return $load !== null && ((int) ($load['weekly_lessons'] ?? 0) > 0 || (float) ($load['workload_hours'] ?? 0) > 0);
        }

        return true;
    }

    public function hasPeriodLoads(): bool
    {
        return is_array($this->period_loads) && $this->period_loads !== [];
    }

    public function weeklyLessonsForPeriod(AcademicPeriod $period): ?int
    {
        if (! $this->isActiveInPeriod($period)) {
            return 0;
        }

        return $this->hasPeriodLoads() ? (int) ($this->period_loads[$period->id]['weekly_lessons'] ?? 0) : $this->weekly_lessons;
    }

    public function weeklyLessonsForDate(CarbonInterface $date): ?int
    {
        if (! $this->hasPeriodLoads()) {
            return $this->weekly_lessons;
        }
        $period = $this->course->academicYear->periods->first(fn ($period) => $date->betweenIncluded($period->starts_at, $period->ends_at));

        return $period ? $this->weeklyLessonsForPeriod($period) : 0;
    }

    public function weeklyLessonsForSchedule(SchoolClassSchedule $schedule): ?int
    {
        if (! $this->hasPeriodLoads()) {
            return $this->weekly_lessons;
        }
        $end = $schedule->ends_at ?? $schedule->schoolClass->ends_at ?? $this->course->academicYear->ends_at;
        $rates = $this->course->academicYear->periods->filter(fn ($period) => $period->ends_at->gte($schedule->starts_at)
            && $period->starts_at->lte($end))->map(fn ($period) => $this->weeklyLessonsForPeriod($period))->unique()->values();

        return $rates->count() === 1 ? $rates->first() : null;
    }

    public function calculatedWorkloadHoursForPeriods(Collection $periods, ?AcademicCourse $course = null): float
    {
        $course ??= $this->course;
        $course->loadMissing('academicYear.periods', 'startsPeriod', 'endsPeriod');
        $active = $periods->filter(fn ($period) => $this->isActiveInPeriod($period)
            && (! $course->startsPeriod || $period->position >= $course->startsPeriod->position)
            && (! $course->endsPeriod || $period->position <= $course->endsPeriod->position));
        if (! $this->hasPeriodLoads()) {
            $all = $course->academicYear->periods->filter(fn ($period) => $this->isActiveInPeriod($period)
                && (! $course->startsPeriod || $period->position >= $course->startsPeriod->position)
                && (! $course->endsPeriod || $period->position <= $course->endsPeriod->position));

            return $all->isEmpty() ? 0 : round($this->calculatedWorkloadHours($course) * $active->count() / $all->count(), 2);
        }
        $weeks = ($course->academicYear->minimum_school_days ?: 200) / 5 / max(1, $course->academicYear->periods->count());

        return round($active->sum(function ($period) use ($weeks, $course) {
            $load = $this->period_loads[$period->id];

            return isset($load['workload_hours']) && $load['workload_hours'] !== ''
                ? (float) $load['workload_hours'] : (int) $load['weekly_lessons'] * $weeks * $course->class_hour_minutes / 60;
        }), 2);
    }

    public function weeklyLessonsLabel(): string
    {
        if (! $this->hasPeriodLoads()) {
            return (string) $this->weekly_lessons;
        }

        return $this->course->academicYear->periods->sortBy('position')
            ->map(fn ($period) => (string) ($this->weeklyLessonsForPeriod($period) ?? '—'))->implode(' / ');
    }
}
