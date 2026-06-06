<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTitleCaseAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    use Auditable, HasFactory, HasTitleCaseAttributes;

    protected $fillable = [
        'legacy_id',
        'legacy_source',
        'legacy_metadata',
        'name',
        'legal_name',
        'cnpj',
        'inep',
        'founded_at',
        'phone',
        'email',
        'website',
        'letterhead_text',
        'logo_path',
        'address',
        'district',
        'number',
        'city',
        'state',
        'postal_code',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'founded_at' => 'date',
            'legacy_metadata' => 'array',
        ];
    }

    protected function titleCaseAttributes(): array
    {
        return [
            'name',
            'legal_name',
            'address',
            'district',
            'city',
        ];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(PersonSchoolRole::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function calendarEvents(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }
}
