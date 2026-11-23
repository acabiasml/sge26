<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ManagementSubstitution extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'cancelled_at' => 'datetime'];
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function role()
    {
        return $this->belongsTo(PersonSchoolRole::class, 'role_id');
    }
}
