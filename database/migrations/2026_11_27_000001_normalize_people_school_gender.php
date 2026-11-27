<?php

use App\Models\Person;
use App\Models\PersonSchoolRole;
use App\Support\SchoolGender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            Person::query()->select(['id', 'school_gender', 'legacy_metadata'])
                ->withExists(['studentEnrollments', 'academicHistories', 'schoolRoles as has_student_role' => fn ($roles) => $roles->where('role', PersonSchoolRole::ROLE_STUDENT)])
                ->where(fn ($people) => $people->whereNotNull('school_gender')->orWhereNotNull('legacy_metadata'))
                ->chunkById(500, function ($people): void {
                    foreach ($people as $person) {
                        $previous = $person->school_gender;
                        $metadata = $person->legacy_metadata ?? [];
                        $gender = SchoolGender::fromRecordedValue($previous);
                        if (blank($previous) && ($person->student_enrollments_exists || $person->academic_histories_exists
                            || $person->has_student_role || ($metadata['tipo'] ?? null) === 'estud')) {
                            $gender = SchoolGender::fromRecordedValue($metadata['sexo'] ?? null);
                        }
                        if ($previous === $gender?->value) {
                            continue;
                        }

                        DB::table('people')->where('id', $person->id)->update(['school_gender' => $gender?->value]);
                    }
                });
        });

        Schema::table('people', fn (Blueprint $table) => $table->enum('school_gender', ['Masculino', 'Feminino'])->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('people', fn (Blueprint $table) => $table->string('school_gender', 60)->nullable()->change());
    }
};
