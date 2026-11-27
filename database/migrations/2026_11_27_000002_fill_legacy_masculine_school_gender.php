<?php

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Support\SchoolGender;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Person::query()
            ->whereNull('school_gender')
            ->where('legacy_metadata->sexo', 'mas')
            ->where(fn ($students) => $students->where('legacy_metadata->tipo', 'estud')
                ->orWhereHas('studentEnrollments')->orWhereHas('academicHistories')
                ->orWhereHas('schoolRoles', fn ($roles) => $roles->where('role', PersonSchoolRole::ROLE_STUDENT)))
            ->toBase()->update(['school_gender' => SchoolGender::Masculine->value]);
    }

    public function down(): void
    {
        // Um valor confirmado no cadastro não deve voltar a ficar vazio.
    }
};
