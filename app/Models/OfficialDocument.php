<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialDocument extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    public const TYPE_NOTICE = 'comunicado';
    public const TYPE_LETTER = 'oficio';
    public const TYPE_HANDOUT = 'apostila';
    public const TYPE_OTHER = 'outro';

    public const TYPE_LABELS = [
        self::TYPE_NOTICE => 'Comunicado',
        self::TYPE_LETTER => 'Ofício',
        self::TYPE_HANDOUT => 'Apostila',
        self::TYPE_OTHER => 'Documento livre',
    ];

    protected $fillable = [
        'school_id',
        'created_by_user_id',
        'issued_document_id',
        'type',
        'title',
        'content_html',
        'paper_size',
        'orientation',
        'line_spacing',
    ];

    protected function casts(): array
    {
        return [
            'line_spacing' => 'float',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'title',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function issuedDocument(): BelongsTo
    {
        return $this->belongsTo(IssuedDocument::class);
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }
}
