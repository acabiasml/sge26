<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            if (! Schema::hasColumn('school_classes', 'starts_period_id')) {
                $table->foreignId('starts_period_id')
                    ->nullable()
                    ->after('academic_year_id')
                    ->constrained('academic_periods')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('school_classes', 'ends_period_id')) {
                $table->foreignId('ends_period_id')
                    ->nullable()
                    ->after('starts_period_id')
                    ->constrained('academic_periods')
                    ->nullOnDelete();
            }
        });

        DB::table('school_classes')
            ->orderBy('id')
            ->get()
            ->each(function (object $class): void {
                $coursePeriods = DB::table('academic_course_school_class')
                    ->join('academic_courses', 'academic_courses.id', '=', 'academic_course_school_class.academic_course_id')
                    ->where('academic_course_school_class.school_class_id', $class->id)
                    ->select('academic_courses.starts_period_id', 'academic_courses.ends_period_id')
                    ->get();

                $starts = $coursePeriods->pluck('starts_period_id')->filter()->unique()->values();
                $ends = $coursePeriods->pluck('ends_period_id')->filter()->unique()->values();

                $classUpdates = array_filter([
                    'starts_period_id' => $class->starts_period_id ?? ($starts->count() === 1 ? $starts->first() : null),
                    'ends_period_id' => $class->ends_period_id ?? ($ends->count() === 1 ? $ends->first() : null),
                ], fn ($value): bool => $value !== null);

                if ($classUpdates !== []) {
                    DB::table('school_classes')
                        ->where('id', $class->id)
                        ->update($classUpdates);
                }
            });

        DB::table('academic_courses')
            ->orderBy('id')
            ->get()
            ->each(function (object $course): void {
                DB::table('academic_courses')
                    ->where('id', $course->id)
                    ->update([
                        'stage' => $this->normalizeStage($course),
                        'modality' => $this->normalizeModality($course),
                        'status' => 'curricular',
                        'active' => true,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            if (Schema::hasColumn('school_classes', 'ends_period_id')) {
                $table->dropConstrainedForeignId('ends_period_id');
            }

            if (Schema::hasColumn('school_classes', 'starts_period_id')) {
                $table->dropConstrainedForeignId('starts_period_id');
            }
        });
    }

    private function normalizeStage(object $course): string
    {
        $stage = $this->normalize($course->stage ?? '');
        $text = $this->normalize(implode(' ', [
            $course->stage ?? '',
            $course->modality ?? '',
            $course->name ?? '',
        ]));

        return match (true) {
            in_array($stage, ['fundamental', 'ensino fundamental'], true),
            str_contains($text, 'ensino fundamental') => 'fundamental',

            in_array($stage, ['medio', 'ensino medio'], true),
            str_contains($text, 'ensino medio') => 'medio',

            in_array($stage, ['tecnico', 'ensino tecnico', 'educacao profissional tecnica de nivel medio'], true),
            str_contains($text, 'tecnico'),
            str_contains($text, 'profissional') => 'tecnico',

            default => 'outro',
        };
    }

    private function normalizeModality(object $course): string
    {
        $text = $this->normalize(implode(' ', [
            $course->modality ?? '',
            $course->name ?? '',
            $course->stage ?? '',
        ]));

        return match (true) {
            str_contains($text, 'eja'),
            str_contains($text, 'jovens e adultos') => 'eja',

            str_contains($text, 'especial') => 'educacao_especial',
            str_contains($text, 'indigena') => 'educacao_indigena',
            str_contains($text, 'quilombola') => 'educacao_quilombola',
            str_contains($text, 'campo') => 'educacao_do_campo',
            str_contains($text, 'distancia'),
            str_contains($text, 'ead') => 'educacao_a_distancia',

            str_contains($text, 'tecnico'),
            str_contains($text, 'profissional'),
            str_contains($text, 'moveis') => 'educacao_profissional_tecnologica',

            $text === '',
            str_contains($text, 'regular'),
            str_contains($text, 'medio'),
            str_contains($text, 'fundamental') => 'regular',

            default => 'outra',
        };
    }

    private function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }
};
