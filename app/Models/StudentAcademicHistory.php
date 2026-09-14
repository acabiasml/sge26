<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAcademicHistory extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'person_id',
        'school_id',
        'created_by_person_id',
        'updated_by_person_id',
        'title',
        'stage',
        'legal_basis',
        'notes',
        'issued_place',
        'issued_date',
        'active',
        'is_unified',
        'education_stage',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'active' => 'boolean',
            'is_unified' => 'boolean',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'title',
            'stage',
            'issued_place',
        ];
    }

    /** @return array<string, array{planned: ?float, completed: ?float}> */
    public function formationWorkloadTotals(?StudentAcademicHistoryYear $selectedYear = null): array
    {
        $this->loadMissing('years', 'components.records');
        $years = $this->years->keyBy('id');
        $totals = [];

        foreach (['Formação Geral Básica', 'Itinerário Formativo'] as $formation) {
            $planned = null;
            $completed = null;
            foreach ($this->components->where('formation', $formation) as $component) {
                foreach ($component->records as $record) {
                    $year = $years->get($record->student_academic_history_year_id);
                    if ($selectedYear && $record->student_academic_history_year_id != $selectedYear->id) {
                        continue;
                    }
                    if (! $year || $year->transcript_mode === 'no_transcription' || $record->workload_hours === null) {
                        continue;
                    }
                    $planned = ($planned ?? 0) + (float) $record->workload_hours;
                    if ($year->displaysCompletedWorkload()) {
                        $completed = ($completed ?? 0) + (float) $record->workload_hours;
                    }
                }
            }
            $totals[$formation] = [
                'planned' => $planned !== null ? round($planned, 2) : null,
                'completed' => $completed !== null ? round($completed, 2) : null,
            ];
        }

        return $totals;
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'person_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by_person_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'updated_by_person_id');
    }

    public function years(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryYear::class)->orderBy('position');
    }

    public function components(): HasMany
    {
        return $this->hasMany(StudentAcademicHistoryComponent::class)->orderBy('position');
    }
}
