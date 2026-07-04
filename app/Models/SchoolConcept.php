<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolConcept extends Model
{
    use Auditable, HasTitleCaseAttributes;

    protected $fillable = [
        'school_id',
        'effective_from',
        'name',
        'abbreviation',
        'minimum_score',
        'maximum_score',
        'minimum_inclusive',
        'maximum_inclusive',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'minimum_score' => 'decimal:2',
            'maximum_score' => 'decimal:2',
            'effective_from' => 'date',
            'minimum_inclusive' => 'boolean',
            'maximum_inclusive' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function matches(float $score): bool
    {
        $aboveMinimum = $this->minimum_score === null
            || ($this->minimum_inclusive ? $score >= (float) $this->minimum_score : $score > (float) $this->minimum_score);

        $belowMaximum = $this->maximum_score === null
            || ($this->maximum_inclusive ? $score <= (float) $this->maximum_score : $score < (float) $this->maximum_score);

        return $aboveMinimum && $belowMaximum;
    }

    public function rangeLabel(): string
    {
        if ($this->minimum_score === null && $this->maximum_score === null) {
            return 'Qualquer nota';
        }

        if ($this->minimum_score === null) {
            return ($this->maximum_inclusive ? 'x <= ' : 'x < ').$this->formatScore($this->maximum_score);
        }

        if ($this->maximum_score === null) {
            return ($this->minimum_inclusive ? 'x >= ' : 'x > ').$this->formatScore($this->minimum_score);
        }

        $left = $this->formatScore($this->minimum_score).($this->minimum_inclusive ? ' <= x' : ' < x');
        $right = ($this->maximum_inclusive ? ' <= ' : ' < ').$this->formatScore($this->maximum_score);

        return $left.$right;
    }

    public function shortLabel(): string
    {
        return $this->abbreviation ?: $this->name;
    }

    private function formatScore(string|float|null $score): string
    {
        return number_format((float) $score, 1, ',', '.');
    }
}
