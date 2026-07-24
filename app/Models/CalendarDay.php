<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarDay extends Model
{
    use Auditable;

    public const TYPE_SCHOOL_DAY = 'letivo';
    public const TYPE_HOLIDAY = 'feriado';
    public const TYPE_FINAL_VACATION = 'ferias_finais';
    public const TYPE_RECESS = 'recesso';
    public const TYPE_WEEKEND = 'fim_de_semana';
    public const TYPE_TRAINING = 'formacao';
    public const TYPE_CLASS_COUNCIL = 'conselho_classe';
    public const TYPE_OTHER = 'outro';

    public const TYPE_LABELS = [
        self::TYPE_SCHOOL_DAY => 'Letivo',
        self::TYPE_HOLIDAY => 'Feriado',
        self::TYPE_FINAL_VACATION => 'Férias',
        self::TYPE_RECESS => 'Recesso',
        self::TYPE_WEEKEND => 'Fim de semana',
        self::TYPE_TRAINING => 'Estudos pedagógicos',
        self::TYPE_CLASS_COUNCIL => 'Conselho de classe',
        self::TYPE_OTHER => 'Outro',
    ];

    public const PRINT_CODES = [
        self::TYPE_SCHOOL_DAY => '*',
        self::TYPE_HOLIDAY => 'F',
        self::TYPE_FINAL_VACATION => 'FF',
        self::TYPE_RECESS => 'RE',
        self::TYPE_WEEKEND => '',
        self::TYPE_TRAINING => 'EP',
        self::TYPE_CLASS_COUNCIL => 'CC',
        self::TYPE_OTHER => 'O',
    ];

    protected $fillable = [
        'academic_year_id',
        'date',
        'type',
        'counts_as_school_day',
        'title',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'counts_as_school_day' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function label(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function printCode(): string
    {
        return self::PRINT_CODES[$this->type] ?? 'O';
    }

    /**
     * @return array<string, string>
     */
    public static function printLegend(): array
    {
        return [
            '*' => 'Dia letivo',
            'S' => 'Sábado',
            'D' => 'Domingo',
            'F' => 'Feriado',
            'FF' => 'Férias',
            'RE' => 'Recesso escolar',
            'EP' => 'Estudos pedagógicos',
            'CC' => 'Conselho de classe',
            'IP' => 'Início de período avaliativo',
            'TP' => 'Término de período avaliativo',
            'O' => 'Outro',
        ];
    }

    public static function labelWithPrintCode(string $type): string
    {
        $label = self::TYPE_LABELS[$type] ?? $type;
        $code = self::PRINT_CODES[$type] ?? null;

        return filled($code) ? "{$label} ({$code})" : $label.' (S/D)';
    }
}
